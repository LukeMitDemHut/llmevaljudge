<?php

namespace App\Command;

use App\Message\RunBenchmark;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\MessageHandler\RunBenchmarkHandler;

#[AsCommand(
    name: 'benchmark:run',
    description: 'Run a specific benchmark by ID',
)]
class RunBenchmarkCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private SettingRepository $settingRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('benchmark-id', InputArgument::REQUIRED, 'The benchmark ID to run');
        $this->addOption('only-missing', null, InputOption::VALUE_NONE, 'Only run missing/failed results');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $benchmarkId = (int) $input->getArgument('benchmark-id');
        $onlyMissing = $input->getOption('only-missing');

        $output->writeln("Starting benchmark execution for ID: $benchmarkId" .
            ($onlyMissing ? " (only missing results)" : ""));

        try {
            $handler = new RunBenchmarkHandler(
                $this->entityManager,
                $this->httpClient,
                $this->logger,
                $this->settingRepository
            );

            $message = new RunBenchmark($benchmarkId, $onlyMissing);
            $handler->__invoke($message);

            $output->writeln("Benchmark execution completed successfully!");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("Error: " . $e->getMessage());
            $output->writeln("Trace: " . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
