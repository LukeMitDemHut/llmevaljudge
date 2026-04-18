<?php

namespace App\Command;

use App\Entity\Benchmark;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'benchmark:analyze-cache',
    description: 'Analyze cache integrity: detect results where the model answer differs across repeats/metrics for the same prompt+model combination',
)]
class AnalyzeCacheIntegrityCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('benchmark-id', InputArgument::REQUIRED, 'The benchmark ID to analyze');
        $this->addOption('fix', null, InputOption::VALUE_NONE, 'Delete affected results (so they can be re-run with --only-missing)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $benchmarkId = (int) $input->getArgument('benchmark-id');

        $benchmark = $this->entityManager->getRepository(Benchmark::class)->find($benchmarkId);
        if (!$benchmark) {
            $io->error("Benchmark $benchmarkId not found.");
            return Command::FAILURE;
        }

        $io->title("Cache Integrity Analysis — Benchmark #{$benchmarkId}");

        // Use a lightweight query — only fetch IDs, hashes, and names (not full actual_output)
        $conn = $this->entityManager->getConnection();

        $rows = $conn->fetchAllAssociative('
            SELECT r.id, r.prompt_id, r.model_id, r.metric_id, r.run_index,
                   MD5(r.actual_output) AS answer_hash,
                   m.name AS model_name,
                   met.name AS metric_name
            FROM result r
            JOIN model m ON r.model_id = m.id
            JOIN metric met ON r.metric_id = met.id
            WHERE r.benchmark_id = :bid
        ', ['bid' => $benchmarkId]);

        $io->text(sprintf('Total results: %d', count($rows)));

        // Group by prompt+model (the cache key dimensions)
        $groups = [];
        foreach ($rows as $row) {
            $key = $row['prompt_id'] . '-' . $row['model_id'];
            $groups[$key][] = $row;
        }

        $io->text(sprintf('Unique prompt+model combinations: %d', count($groups)));
        $io->newLine();

        // Analyze each group
        $totalAffectedResults = 0;
        $totalOkResults = 0;
        $affectedByModel = [];
        $affectedByMetric = [];
        $affectedIds = [];
        $modelTotals = [];

        foreach ($groups as $key => $groupRows) {
            $modelName = $groupRows[0]['model_name'];
            $modelTotals[$modelName] = ($modelTotals[$modelName] ?? 0) + count($groupRows);

            // Count occurrences of each answer hash
            $hashCounts = [];
            foreach ($groupRows as $row) {
                $hash = $row['answer_hash'];
                $hashCounts[$hash] = ($hashCounts[$hash] ?? 0) + 1;
            }

            if (count($hashCounts) === 1) {
                $totalOkResults += count($groupRows);
                continue;
            }

            // Multiple different answers — canonical = most frequent (tie: first alphabetically)
            arsort($hashCounts);
            $canonicalHash = array_key_first($hashCounts);

            foreach ($groupRows as $row) {
                if ($row['answer_hash'] === $canonicalHash) {
                    $totalOkResults++;
                } else {
                    $totalAffectedResults++;
                    $affectedByModel[$modelName] = ($affectedByModel[$modelName] ?? 0) + 1;
                    $affectedByMetric[$row['metric_name']] = ($affectedByMetric[$row['metric_name']] ?? 0) + 1;
                    $affectedIds[] = (int) $row['id'];
                }
            }
        }

        // Summary
        $total = count($rows);
        $io->section('Overall');
        $io->table(
            ['', 'Count', '% of total'],
            [
                ['Total results', $total, '100.0%'],
                ['OK (canonical answer)', $totalOkResults, sprintf('%.1f%%', 100 * $totalOkResults / $total)],
                ['To re-run (wrong answer)', $totalAffectedResults, sprintf('%.1f%%', 100 * $totalAffectedResults / $total)],
            ]
        );

        // Per model
        arsort($affectedByModel);
        $io->section('Affected results per model');
        $modelRows = [];
        foreach ($affectedByModel as $model => $count) {
            $mt = $modelTotals[$model];
            $modelRows[] = [$model, $count, $mt, sprintf('%.1f%%', 100 * $count / $mt)];
        }
        $io->table(['Model', 'To re-run', 'Total', '% affected'], $modelRows);

        // Per metric
        arsort($affectedByMetric);
        $io->section('Affected results per metric');
        $metricRows = [];
        foreach ($affectedByMetric as $metric => $count) {
            $metricRows[] = [$metric, $count];
        }
        $io->table(['Metric', 'To re-run'], $metricRows);

        // Distinct answers distribution
        $io->section('Distinct answers per prompt+model combination');
        $distribution = [];
        foreach ($groups as $groupRows) {
            $hashes = [];
            foreach ($groupRows as $row) {
                $hashes[$row['answer_hash']] = true;
            }
            $n = count($hashes);
            $distribution[$n] = ($distribution[$n] ?? 0) + 1;
        }
        ksort($distribution);
        $distRows = [];
        foreach ($distribution as $distinctCount => $combos) {
            $distRows[] = [
                $distinctCount,
                $combos,
                $distinctCount === 1 ? 'OK — cache worked' : 'PROBLEM — race condition',
            ];
        }
        $io->table(['Distinct answers', 'Prompt+Model combos', 'Status'], $distRows);

        // Affected result IDs
        if ($totalAffectedResults > 0) {
            $io->section('Affected result IDs (for deletion/re-run)');
            sort($affectedIds);
            $io->text(sprintf('Result IDs to delete and re-run (%d total):', count($affectedIds)));
            $io->text(implode(', ', $affectedIds));
        }

        $io->newLine();
        if ($totalAffectedResults === 0) {
            $io->success('No cache integrity issues found.');
        } else {
            $io->warning(sprintf(
                '%d of %d results (%.1f%%) have a non-canonical model answer due to cache race conditions and need to be re-evaluated.',
                $totalAffectedResults,
                $total,
                100 * $totalAffectedResults / $total
            ));

            if ($input->getOption('fix')) {
                $io->section('Deleting affected results...');
                $deleted = $conn->executeStatement(
                    'DELETE FROM result WHERE id IN (' . implode(',', $affectedIds) . ')'
                );
                $io->success(sprintf('Deleted %d results. Run "benchmark:run %d --only-missing" to re-evaluate.', $deleted, $benchmarkId));
            } else {
                $io->note('Use --fix to delete affected results, then re-run with "benchmark:run <id> --only-missing".');
            }
        }

        return Command::SUCCESS;
    }
}
