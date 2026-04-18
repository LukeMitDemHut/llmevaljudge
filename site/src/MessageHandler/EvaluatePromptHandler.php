<?php

namespace App\MessageHandler;

use App\Entity\Benchmark;
use App\Entity\Metric;
use App\Entity\Model;
use App\Entity\Prompt;
use App\Entity\Result;
use App\Message\EvaluatePrompt;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class EvaluatePromptHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private SettingRepository $settingRepository,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(EvaluatePrompt $message): void
    {
        // Load entities
        $prompt = $this->entityManager->getRepository(Prompt::class)->find($message->promptId);
        $metric = $this->entityManager->getRepository(Metric::class)->find($message->metricId);
        $model = $this->entityManager->getRepository(Model::class)->find($message->modelId);
        $benchmark = $this->entityManager->getRepository(Benchmark::class)->find($message->benchmarkId);

        if (!$prompt || !$metric || !$model || !$benchmark) {
            $this->logger->error('Entity not found for evaluation', [
                'promptId' => $message->promptId,
                'metricId' => $message->metricId,
                'modelId' => $message->modelId,
                'benchmarkId' => $message->benchmarkId
            ]);
            return;
        }

        $maxRetries = 2;

        try {
            // Check if a result already exists for this prompt/metric/model/benchmark/runIndex combination
            $existingResult = $this->entityManager->getRepository(Result::class)
                ->findByPromptMetricModelBenchmarkAndRun($prompt, $metric, $model, $benchmark, $message->runIndex);

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
                'system_prompt' => $this->getSystemPrompt($prompt),
                'run_index' => $message->runIndex
            ];

            // Call Python evaluation service
            $response = $this->httpClient->request('POST', 'http://judge_eval:5000/', [
                'json' => $evaluationData,
                'timeout' => 1200 // 20 minutes timeout for evaluation (allow deep reasoning)
            ]);

            $result = $response->toArray();

            // Use existing result if found, otherwise create new one
            $resultEntity = $existingResult ?? new Result();
            $resultEntity->setPrompt($prompt);
            $resultEntity->setMetric($metric);
            $resultEntity->setModel($model);
            $resultEntity->setBenchmark($benchmark);
            $resultEntity->setRunIndex($message->runIndex);
            $resultEntity->setActualOutput($result['actual_output'] ?? '');
            $resultEntity->setScore($result['score'] ?? 0.0);
            $resultEntity->setReason($result['reason'] ?? '');
            $resultEntity->setLogs($result['logs'] ?? '');

            // Only persist if it's a new entity
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
                'attempt' => $message->attempt
            ]);

            // Update benchmark progress (atomic operation)
            $this->updateBenchmarkProgress($benchmark);
        } catch (\Exception $e) {
            $this->logger->warning("Evaluation attempt failed", [
                'promptId' => $message->promptId,
                'metricId' => $message->metricId,
                'modelId' => $message->modelId,
                'attempt' => $message->attempt,
                'maxRetries' => $maxRetries,
                'error' => $e->getMessage()
            ]);

            // Retry if we haven't reached max attempts
            if ($message->attempt < $maxRetries) {
                // Dispatch retry message with incremented attempt
                $retryMessage = new EvaluatePrompt(
                    $message->promptId,
                    $message->metricId,
                    $message->modelId,
                    $message->benchmarkId,
                    $message->runIndex,
                    $message->attempt + 1
                );

                // Add a small delay before retry
                sleep(1);
                $this->messageBus->dispatch($retryMessage);
                return;
            }

            // All retries failed - handle failure case
            $errorMessage = sprintf(
                'Evaluation failed for prompt %d, metric %d, model %d after %d attempts: %s',
                $prompt->getId(),
                $metric->getId(),
                $model->getId(),
                $maxRetries,
                $e->getMessage()
            );

            $this->logger->error('Evaluation failed after all retries', [
                'promptId' => $prompt->getId(),
                'metricId' => $metric->getId(),
                'modelId' => $model->getId(),
                'totalAttempts' => $maxRetries,
                'error' => $e->getMessage()
            ]);

            // Add error to benchmark atomically (avoid race condition with concurrent workers)
            $this->addBenchmarkErrorAtomic($benchmark, $errorMessage);

            // Check for existing result and remove it if it exists for this benchmark
            $existingResult = $this->entityManager->getRepository(Result::class)
                ->findByPromptMetricModelBenchmarkAndRun($prompt, $metric, $model, $benchmark, $message->runIndex);

            if ($existingResult) {
                $this->entityManager->remove($existingResult);
                $this->logger->info('Removed existing result due to evaluation failure', [
                    'promptId' => $prompt->getId(),
                    'metricId' => $metric->getId(),
                    'modelId' => $model->getId()
                ]);
                $this->entityManager->flush();
            }

            // Update progress even on failure so benchmark can finish
            $this->updateBenchmarkProgress($benchmark);
        }
    }

    private function updateBenchmarkProgress(Benchmark $benchmark): void
    {
        // Refresh the entity to get the latest state from DB
        $this->entityManager->refresh($benchmark);

        // Get total expected results for this benchmark
        $totalExpected = $this->getTotalExpectedResults($benchmark);

        // Get current completed results count
        $completed = $this->entityManager->getRepository(Result::class)
            ->count(['benchmark' => $benchmark]);

        // Count errors atomically from DB to avoid race conditions
        $errorCount = $this->getBenchmarkErrorCount($benchmark);
        $totalProcessed = $completed + $errorCount;

        if ($totalExpected > 0) {
            $progress = (int) round(($totalProcessed / $totalExpected) * 100);
            $benchmark->setProgress(min(100, $progress));

            // Check if benchmark is complete (all dispatched work is done)
            if ($totalProcessed >= $totalExpected) {
                $benchmark->setProgress(100);
                $benchmark->setFinishedAt(new \DateTimeImmutable());
                $this->logger->info('Benchmark completed', [
                    'benchmarkId' => $benchmark->getId(),
                    'totalResults' => $completed,
                    'totalErrors' => $errorCount
                ]);
            }

            $this->entityManager->flush();
        }
    }

    /**
     * Atomically append an error to the benchmark's errors JSON array using raw SQL.
     * This avoids the read-modify-write race condition when multiple workers add errors concurrently.
     */
    private function addBenchmarkErrorAtomic(Benchmark $benchmark, string $errorMessage): void
    {
        $conn = $this->entityManager->getConnection();
        $errorEntry = json_encode([
            'message' => $errorMessage,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);

        // Use JSON_MERGE_PRESERVE which works in MariaDB (CAST AS JSON is MySQL-only)
        $conn->executeStatement(
            'UPDATE benchmark SET errors = JSON_MERGE_PRESERVE(COALESCE(errors, \'[]\'), JSON_ARRAY(JSON_EXTRACT(:error, \'$\'))) WHERE id = :id',
            ['error' => $errorEntry, 'id' => $benchmark->getId()]
        );

        // Refresh entity so subsequent reads see the updated errors
        $this->entityManager->refresh($benchmark);
    }

    /**
     * Count errors directly from DB to avoid stale in-memory data.
     */
    private function getBenchmarkErrorCount(Benchmark $benchmark): int
    {
        $conn = $this->entityManager->getConnection();
        $result = $conn->executeQuery(
            'SELECT CASE WHEN errors IS NULL THEN 0 ELSE JSON_LENGTH(errors) END as cnt FROM benchmark WHERE id = :id',
            ['id' => $benchmark->getId()]
        );
        return (int) $result->fetchOne();
    }

    private function getTotalExpectedResults(Benchmark $benchmark): int
    {
        $count = 0;
        $repeatCount = $benchmark->getRepeatCount();
        foreach ($benchmark->getTestCases() as $testCase) {
            foreach ($testCase->getPrompts() as $prompt) {
                foreach ($benchmark->getMetrics() as $metric) {
                    // Check if prompt satisfies metric params (same logic as main handler)
                    $satisfied = true;
                    foreach ($metric->getParam() as $param) {
                        switch ($param) {
                            case \App\Enum\MetricParam::INPUT:
                                $satisfied = !empty($prompt->getInput());
                                break;
                            case \App\Enum\MetricParam::EXPECTED_OUTPUT:
                                $satisfied = !empty($prompt->getExpectedOutput());
                                break;
                            case \App\Enum\MetricParam::CONTEXT:
                                $satisfied = !empty($prompt->getContext());
                                break;
                            case \App\Enum\MetricParam::ACTUAL_OUTPUT:
                                $satisfied = true;
                                break;
                            default:
                                $satisfied = false;
                                break;
                        }
                        if (!$satisfied) break;
                    }

                    if ($satisfied) {
                        $count += count($benchmark->getModels()) * $repeatCount;
                    }
                }
            }
        }
        return $count;
    }

    private function getSystemPrompt(Prompt $prompt): string
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
