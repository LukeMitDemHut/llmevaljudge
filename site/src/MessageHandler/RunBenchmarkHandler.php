<?php

namespace App\MessageHandler;

use App\Entity\Benchmark;
use App\Entity\Result;
use App\Message\RunBenchmark;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\EvaluatePrompt;
use App\Enum\MetricParam;

use function PHPUnit\Framework\throwException;

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
            $this->entityManager->flush();
        }

        // collect requests that can be run
        $requests = [];

        try {
            // Execute benchmark: foreach test case, foreach prompt, foreach metric, foreach model
            foreach ($benchmark->getTestCases() as $testCase) {
                foreach ($testCase->getPrompts() as $prompt) {
                    foreach ($benchmark->getMetrics() as $metric) {
                        // test if the prompt satisfies all metric params, if not skip
                        $satisfied = false;
                        foreach ($metric->getParam() as $param) {
                            switch ($param) {
                                case MetricParam::INPUT:
                                    $satisfied = !empty($prompt->getInput());
                                    break;
                                case MetricParam::EXPECTED_OUTPUT:
                                    $satisfied = !empty($prompt->getExpectedOutput());
                                    break;
                                case MetricParam::CONTEXT:
                                    $satisfied = !empty($prompt->getContext());
                                    break;
                                case MetricParam::ACTUAL_OUTPUT:
                                    $satisfied = true; // actual output can be generated during execution
                                    break;

                                default:
                                    throwException(new \InvalidArgumentException('Unknown metric parameter'));
                                    break;
                            }

                            if (!$satisfied) {
                                $this->logger->warning('Benchmark parameter not satisfied', [
                                    'benchmarkId' => $benchmark->getId(),
                                    'testCaseId' => $testCase->getId(),
                                    'promptId' => $prompt->getId(),
                                    'metricId' => $metric->getId(),
                                    'missingParam' => $param->value
                                ]);
                                $benchmark->addError('Test skipped due to benchmark parameter not satisfied: ' . $param->value . ' for prompt ' . $prompt->getId());

                                continue 3; // Skip to next prompt if any param is not satisfied
                            }
                        }

                        foreach ($benchmark->getModels() as $model) {
                            // If only missing mode, check if result already exists for this benchmark
                            if ($message->onlyMissing) {
                                $existingResult = $this->entityManager->getRepository(Result::class)
                                    ->findByPromptMetricModelBenchmark($prompt, $metric, $model, $benchmark);

                                if ($existingResult) {
                                    continue; // Skip this combination as it already has a result for this benchmark
                                }
                            }

                            // Collect requests for this model
                            $requests[] = [
                                'prompt' => $prompt,
                                'metric' => $metric,
                                'model' => $model
                            ];
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
                $benchmark->getId()
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
}
