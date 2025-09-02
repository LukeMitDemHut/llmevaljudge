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

                                default:
                                    throwException(new \InvalidArgumentException('Unknown metric parameter'));
                                    break;
                            }

                            if (!$satisfied) {
                                if (!$message->onlyMissing) {
                                    $this->logger->warning('Benchmark parameter not satisfied', [
                                        'benchmarkId' => $benchmark->getId(),
                                        'testCaseId' => $testCase->getId(),
                                        'promptId' => $prompt->getId(),
                                        'metricId' => $metric->getId(),
                                        'missingParam' => $param->value
                                    ]);
                                    $benchmark->addError('Test skipped due to benchmark parameter not satisfied: ' . $param->value . ' for prompt ' . $prompt->getId());
                                }

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
        // Dispatch all collected requests
        foreach ($requests as $index => $request) {
            $this->evaluatePrompt($request['prompt'], $request['metric'], $request['model']);

            // update benchmark progress
            $benchmark->setProgress(($index + 1) / $totalRequests * 100);
            $this->entityManager->flush();
        }

        // Only mark benchmark as finished if this was a full run, not missing-only
        if (!$message->onlyMissing) {
            $benchmark->setFinishedAt(new \DateTimeImmutable());
        }

        $benchmark->setProgress(100); // Ensure progress is 100% when finished
        $this->entityManager->flush();

        $this->logger->info('Benchmark execution completed', [
            'benchmarkId' => $benchmark->getId(),
            'onlyMissing' => $message->onlyMissing,
            'requestsProcessed' => $totalRequests
        ]);
    }

    private function evaluatePrompt($prompt, $metric, $model): void
    {
        $maxRetries = 2;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                // Check if a result already exists for this prompt/metric/model/benchmark combination
                // This prevents duplicate results and ensures data consistency when benchmarks are re-run
                $existingResult = $this->entityManager->getRepository(Result::class)
                    ->findByPromptMetricModelBenchmark($prompt, $metric, $model, $this->currentBenchmark);

                // Prepare data for Python evaluation service
                $evaluationData = [
                    'prompt' => [
                        'input' => $prompt->getInput(),
                        'output' => '', // Will be filled by the model
                        'expected_output' => $prompt->getExpectedOutput() ?? '',
                        'context' => $prompt->getContext() ?? ''
                    ],
                    'model' => [
                        'name' => $model->getName(),
                        'url' => $model->getProvider()->getApiUrl(),
                        'key' => $model->getProvider()->getApiKey()
                    ],
                    'metric' => [
                        'name' => $metric->getName(),
                        'type' => $metric->getType()->value,
                        'definition' => json_encode($metric->getDefinition()),
                        'param' => $metric->getParam(),
                        'model' => [
                            'name' => $metric->getRatingModel()->getName(),
                            'url' => $metric->getRatingModel()->getProvider()->getApiUrl(),
                            'key' => $metric->getRatingModel()->getProvider()->getApiKey()
                        ]
                    ],
                    'system_prompt' => $this->getSystemPrompt($prompt)
                ];

                // Call Python evaluation service
                $response = $this->httpClient->request('POST', 'http://judge_eval:5000/', [
                    'json' => $evaluationData,
                    'timeout' => 120 // 2 minutes timeout for evaluation
                ]);

                $result = $response->toArray();

                // Use existing result if found, otherwise create new one
                // This ensures we update existing results instead of creating duplicates
                $resultEntity = $existingResult ?? new Result();
                $resultEntity->setPrompt($prompt);
                $resultEntity->setMetric($metric);
                $resultEntity->setModel($model);
                $resultEntity->setBenchmark($this->currentBenchmark);
                $resultEntity->setActualOutput($result['actual_output'] ?? '');
                $resultEntity->setScore($result['score'] ?? 0.0);
                $resultEntity->setReason($result['reason'] ?? '');
                $resultEntity->setLogs($result['logs'] ?? '');

                // Only persist if it's a new entity (existing entities are automatically tracked)
                if (!$existingResult) {
                    $this->entityManager->persist($resultEntity);
                }
                $this->entityManager->flush();

                $action = $existingResult ? 'updated' : 'created';
                $this->logger->info("Evaluation $action", [
                    'promptId' => $prompt->getId(),
                    'metricId' => $metric->getId(),
                    'modelId' => $model->getId(),
                    'score' => $result['score'] ?? 0.0,
                    'action' => $action,
                    'attempt' => $attempt
                ]);

                // Success - exit retry loop
                return;
            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->warning("Evaluation attempt failed", [
                    'promptId' => $prompt->getId(),
                    'metricId' => $metric->getId(),
                    'modelId' => $model->getId(),
                    'attempt' => $attempt,
                    'maxRetries' => $maxRetries,
                    'error' => $e->getMessage()
                ]);

                // If this was the last attempt, handle failure
                if ($attempt >= $maxRetries) {
                    break;
                }

                // Brief delay before retry to avoid overwhelming the service
                usleep(500000); // 0.5 seconds
            }
        }

        // All retries failed - handle failure case
        $errorMessage = sprintf(
            'Evaluation failed for prompt %d, metric %d, model %d after %d attempts: %s',
            $prompt->getId(),
            $metric->getId(),
            $model->getId(),
            $maxRetries,
            $lastException ? $lastException->getMessage() : 'Unknown error'
        );

        $this->logger->error('Evaluation failed after all retries', [
            'promptId' => $prompt->getId(),
            'metricId' => $metric->getId(),
            'modelId' => $model->getId(),
            'totalAttempts' => $maxRetries,
            'error' => $lastException ? $lastException->getMessage() : 'Unknown error'
        ]);

        // Add error to benchmark
        if ($this->currentBenchmark) {
            $this->currentBenchmark->addError($errorMessage);
            $this->entityManager->flush();
        }

        // Check for existing result and remove it if it exists for this benchmark
        $existingResult = $this->entityManager->getRepository(Result::class)
            ->findByPromptMetricModelBenchmark($prompt, $metric, $model, $this->currentBenchmark);

        if ($existingResult) {
            $this->entityManager->remove($existingResult);
            $this->entityManager->flush();
            $this->logger->info('Removed existing result due to evaluation failure', [
                'promptId' => $prompt->getId(),
                'metricId' => $metric->getId(),
                'modelId' => $model->getId()
            ]);
        }
    }

    /**
     * Get the system prompt from settings and replace {context} placeholder
     */
    private function getSystemPrompt($prompt): string
    {
        $systemPromptTemplate = $this->settingRepository->getSettingValue('system_prompt', '');

        if (empty($systemPromptTemplate)) {
            return '';
        }

        // Replace {context} placeholder with the actual prompt context
        $context = $prompt->getContext() ?? '';
        return str_replace('{context}', $context, $systemPromptTemplate);
    }
}
