<?php

namespace App\MessageHandler;

use App\Entity\Benchmark;
use App\Entity\Result;
use App\Entity\TestCase;
use App\Message\RunBenchmark;
use App\Repository\SettingRepository;
use App\Service\MetricTestCaseResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\EvaluatePrompt;

#[AsMessageHandler]
final class RunBenchmarkHandler
{
    private ?Benchmark $currentBenchmark = null;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private SettingRepository $settingRepository,
        private MessageBusInterface $messageBus,
        private MetricTestCaseResolver $metricTestCaseResolver,
    ) {}

    public function __invoke(RunBenchmark $message): void
    {
        // Load benchmark entity
        $benchmark = $this->entityManager->getRepository(Benchmark::class)
            ->find($message->benchmarkId);

        if (!$benchmark) {
            throw new \RuntimeException('Benchmark not found');
        }

        // Store reference to current benchmark for error tracking
        $this->currentBenchmark = $benchmark;

        $this->logger->info('Starting benchmark execution', [
            'benchmarkId' => $benchmark->getId(),
            'onlyMissing' => $message->onlyMissing
        ]);

        if (!$message->onlyMissing) {
            // Clear any previous errors when starting a new full run
            $benchmark->clearErrors();
            // Reset progress to 0 when starting full run
            $benchmark->setProgress(0);
            $benchmark->setFinishedAt(null);
            $this->entityManager->persist($benchmark);
            $this->entityManager->flush();
        }

        // collect requests that can be run
        $requests = [];
        $repeatCount = $benchmark->getRepeatCount();

        try {
            // Execute benchmark: foreach metric, foreach of its test cases, foreach prompt, foreach model, foreach run.
            // Pairing metric<->test case: explicit assignment wins, empty assignment means "all test cases"
            // (MetricTestCaseResolver::getEffectiveTestCasesForMetric). EvaluatePromptHandler
            // ::getTotalExpectedResults derives its progress total from the same resolver.
            $allTestCases = $this->entityManager->getRepository(TestCase::class)->findAll();

            foreach ($benchmark->getMetrics() as $metric) {
                foreach ($this->metricTestCaseResolver->getEffectiveTestCasesForMetric($metric, $allTestCases) as $testCase) {
                    foreach ($testCase->getPrompts() as $prompt) {
                        // Skip only this (prompt, metric) pair when the metric params are not satisfied;
                        // other metrics of the same prompt still run.
                        if (!$this->metricTestCaseResolver->pairSatisfiesParams($metric, $prompt)) {
                            $missing = $this->getMissingParam($metric, $prompt);
                            $this->logger->warning('Benchmark parameter not satisfied', [
                                'benchmarkId' => $benchmark->getId(),
                                'testCaseId' => $testCase->getId(),
                                'promptId' => $prompt->getId(),
                                'metricId' => $metric->getId(),
                                'missingParam' => $missing
                            ]);
                            $benchmark->addError('Test skipped due to benchmark parameter not satisfied: ' . $missing . ' for prompt ' . $prompt->getId());

                            continue; // Skip to next prompt (for this metric) if any param is not satisfied
                        }

                        foreach ($benchmark->getModels() as $model) {
                            for ($runIndex = 1; $runIndex <= $repeatCount; $runIndex++) {
                                // If only missing mode, check if result already exists for this benchmark + runIndex
                                if ($message->onlyMissing) {
                                    $existingResult = $this->entityManager->getRepository(Result::class)
                                        ->findByPromptMetricModelBenchmarkAndRun($prompt, $metric, $model, $benchmark, $runIndex);

                                    if ($existingResult) {
                                        continue; // Skip this combination as it already has a result
                                    }
                                }

                                // Collect requests for this model and run
                                $requests[] = [
                                    'prompt' => $prompt,
                                    'metric' => $metric,
                                    'model' => $model,
                                    'runIndex' => $runIndex
                                ];
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Benchmark execution failed', [
                'benchmarkId' => $benchmark->getId(),
                'error' => $e->getMessage()
            ]);

            // Add the error to the benchmark
            $benchmark->addError('Benchmark execution failed: ' . $e->getMessage());
            $this->entityManager->flush();

            throw $e;
        }

        if (empty($requests)) {
            $this->logger->info('No missing results found for benchmark', [
                'benchmarkId' => $benchmark->getId()
            ]);

            if ($message->onlyMissing) {
                $benchmark->addError('No missing results to run');
                $this->entityManager->flush();
                return;
            }
        }

        $totalRequests = count($requests);

        // Dispatch all collected requests for parallel processing
        foreach ($requests as $request) {
            $evaluateMessage = new EvaluatePrompt(
                $request['prompt']->getId(),
                $request['metric']->getId(),
                $request['model']->getId(),
                $benchmark->getId(),
                $request['runIndex']
            );
            $this->messageBus->dispatch($evaluateMessage);
        }

        $this->entityManager->flush();

        $this->logger->info('Benchmark execution initiated', [
            'benchmarkId' => $benchmark->getId(),
            'onlyMissing' => $message->onlyMissing,
            'requestsDispatched' => $totalRequests
        ]);
    }

    /**
     * Returns the first metric param that the prompt does not satisfy, for error messages.
     */
    private function getMissingParam(\App\Entity\Metric $metric, \App\Entity\Prompt $prompt): string
    {
        $params = $metric->getParam();
        foreach ($params as $param) {
            if (!$this->paramSatisfied($param, $prompt)) {
                return $param->value;
            }
        }

        return '';
    }

    private function paramSatisfied(\App\Enum\MetricParam $param, \App\Entity\Prompt $prompt): bool
    {
        return match ($param) {
            \App\Enum\MetricParam::INPUT => !empty($prompt->getInput()),
            \App\Enum\MetricParam::EXPECTED_OUTPUT => !empty($prompt->getExpectedOutput()),
            \App\Enum\MetricParam::CONTEXT => !empty($prompt->getContext()),
            \App\Enum\MetricParam::ACTUAL_OUTPUT => true,
        };
    }
}
