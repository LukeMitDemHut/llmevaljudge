<?php

namespace App\Controller\Api;

use App\Repository\ResultRepository;
use App\Repository\ModelRepository;
use App\Repository\MetricRepository;
use App\Repository\TestCaseRepository;
use App\Repository\BenchmarkRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/evaluation', name: 'api_evaluation_')]
class EvaluationController extends BaseApiController
{
    private ResultRepository $resultRepository;
    private ModelRepository $modelRepository;
    private MetricRepository $metricRepository;
    private TestCaseRepository $testCaseRepository;
    private BenchmarkRepository $benchmarkRepository;

    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        ResultRepository $resultRepository,
        ModelRepository $modelRepository,
        MetricRepository $metricRepository,
        TestCaseRepository $testCaseRepository,
        BenchmarkRepository $benchmarkRepository
    ) {
        parent::__construct($entityManager, $normalizer, $validator);
        $this->resultRepository = $resultRepository;
        $this->modelRepository = $modelRepository;
        $this->metricRepository = $metricRepository;
        $this->testCaseRepository = $testCaseRepository;
        $this->benchmarkRepository = $benchmarkRepository;
    }

    #[Route('/benchmark-analysis', name: 'benchmark_analysis', methods: ['GET'])]
    public function getBenchmarkAnalysis(Request $request): JsonResponse
    {
        $benchmarkId = $request->query->get('benchmarkId');
        $modelIds = $request->query->get('modelIds', '');

        if (!$benchmarkId) {
            return $this->jsonResponse(['error' => 'Benchmark ID is required'], 400);
        }

        // Parse comma-separated IDs
        $modelIds = $modelIds ? array_filter(explode(',', $modelIds)) : [];

        // Get the benchmark
        $benchmark = $this->benchmarkRepository->find($benchmarkId);
        if (!$benchmark) {
            return $this->jsonResponse(['error' => 'Benchmark not found'], 404);
        }

        // Get results for this benchmark
        $queryBuilder = $this->resultRepository->createQueryBuilder('r')
            ->leftJoin('r.model', 'm')
            ->leftJoin('r.metric', 'mt')
            ->leftJoin('r.prompt', 'p')
            ->leftJoin('p.testCase', 'tc')
            ->where('r.benchmark = :benchmarkId')
            ->setParameter('benchmarkId', $benchmarkId);

        if (!empty($modelIds)) {
            $queryBuilder->andWhere('r.model IN (:modelIds)')
                ->setParameter('modelIds', $modelIds);
        }

        $results = $queryBuilder->getQuery()->getResult();

        // Calculate statistics
        $analysisData = $this->calculateBenchmarkAnalysis($results, $benchmark);

        return $this->jsonResponse($analysisData);
    }

    #[Route('/model-analysis', name: 'model_analysis', methods: ['GET'])]
    public function getModelAnalysis(Request $request): JsonResponse
    {
        $modelId = $request->query->get('modelId');
        $metricIds = $request->query->get('metricIds', '');
        $testCaseIds = $request->query->get('testCaseIds', '');
        $benchmarkId = $request->query->get('benchmarkId'); // Optional benchmark scope
        $deduplication = $request->query->get('deduplication', 'latest'); // latest, all, average

        if (!$modelId) {
            return $this->jsonResponse(['error' => 'Model ID is required'], 400);
        }

        // Parse comma-separated IDs
        $metricIds = $metricIds ? array_filter(explode(',', $metricIds)) : [];
        $testCaseIds = $testCaseIds ? array_filter(explode(',', $testCaseIds)) : [];

        // Get the model
        $model = $this->modelRepository->find($modelId);
        if (!$model) {
            return $this->jsonResponse(['error' => 'Model not found'], 404);
        }

        // Build query criteria
        $queryBuilder = $this->resultRepository->createQueryBuilder('r')
            ->leftJoin('r.model', 'm')
            ->leftJoin('r.metric', 'mt')
            ->leftJoin('r.prompt', 'p')
            ->leftJoin('p.testCase', 'tc')
            ->leftJoin('r.benchmark', 'b')
            ->where('r.model = :modelId')
            ->setParameter('modelId', $modelId);

        if (!empty($metricIds)) {
            $queryBuilder->andWhere('r.metric IN (:metricIds)')
                ->setParameter('metricIds', $metricIds);
        }

        if (!empty($testCaseIds)) {
            $queryBuilder->andWhere('tc.id IN (:testCaseIds)')
                ->setParameter('testCaseIds', $testCaseIds);
        }

        // If specific benchmark is requested, scope to that benchmark
        if ($benchmarkId) {
            $queryBuilder->andWhere('r.benchmark = :benchmarkId')
                ->setParameter('benchmarkId', $benchmarkId);
            $deduplication = 'all'; // No need for deduplication when scoped to one benchmark
        }

        $results = $queryBuilder->getQuery()->getResult();

        // Apply deduplication if needed
        if ($deduplication !== 'all' && !$benchmarkId) {
            $results = $this->deduplicateResults($results, $deduplication);
        }

        // Calculate statistics
        $analysisData = $this->calculateModelAnalysis($results, $model);
        $analysisData['deduplication'] = $deduplication;
        $analysisData['benchmarkScope'] = $benchmarkId;

        return $this->jsonResponse($analysisData);
    }

    #[Route('/metric-analysis', name: 'metric_analysis', methods: ['GET'])]
    public function getMetricAnalysis(Request $request): JsonResponse
    {
        $metricId = $request->query->get('metricId');
        $modelIds = $request->query->get('modelIds', '');
        $testCaseIds = $request->query->get('testCaseIds', '');
        $benchmarkId = $request->query->get('benchmarkId');
        $deduplication = $request->query->get('deduplication', 'latest'); // latest, all, average

        if (!$metricId) {
            return $this->jsonResponse(['error' => 'Metric ID is required'], 400);
        }

        // Parse comma-separated IDs
        $modelIds = $modelIds ? array_filter(explode(',', $modelIds)) : [];
        $testCaseIds = $testCaseIds ? array_filter(explode(',', $testCaseIds)) : [];

        // Get the metric
        $metric = $this->metricRepository->find($metricId);
        if (!$metric) {
            return $this->jsonResponse(['error' => 'Metric not found'], 404);
        }

        // Build query
        $queryBuilder = $this->resultRepository->createQueryBuilder('r')
            ->leftJoin('r.model', 'm')
            ->leftJoin('r.metric', 'mt')
            ->leftJoin('r.prompt', 'p')
            ->leftJoin('p.testCase', 'tc')
            ->where('r.metric = :metricId')
            ->setParameter('metricId', $metricId);

        // Add benchmark filter if specified
        if ($benchmarkId) {
            $queryBuilder->andWhere('r.benchmark = :benchmarkId')
                ->setParameter('benchmarkId', $benchmarkId);
        }

        if (!empty($modelIds)) {
            $queryBuilder->andWhere('r.model IN (:modelIds)')
                ->setParameter('modelIds', $modelIds);
        }

        if (!empty($testCaseIds)) {
            $queryBuilder->andWhere('tc.id IN (:testCaseIds)')
                ->setParameter('testCaseIds', $testCaseIds);
        }

        $results = $queryBuilder->getQuery()->getResult();

        // Apply deduplication if needed
        if ($deduplication !== 'all' && !$benchmarkId) {
            $results = $this->deduplicateResults($results, $deduplication);
        }

        // Calculate statistics
        $analysisData = $this->calculateMetricAnalysis($results, $metric);
        $analysisData['deduplication'] = $deduplication;
        $analysisData['benchmarkScope'] = $benchmarkId;

        return $this->jsonResponse($analysisData);
    }

    #[Route('/results', name: 'results', methods: ['GET'])]
    public function getEvaluationResults(Request $request): JsonResponse
    {
        $modelIds = $request->query->get('modelIds', '');
        $metricIds = $request->query->get('metricIds', '');
        $testCaseIds = $request->query->get('testCaseIds', '');
        $benchmarkId = $request->query->get('benchmarkId');
        $deduplication = $request->query->get('deduplication', 'latest'); // latest, all, average

        // Parse comma-separated IDs
        $modelIds = $modelIds ? array_filter(explode(',', $modelIds)) : [];
        $metricIds = $metricIds ? array_filter(explode(',', $metricIds)) : [];
        $testCaseIds = $testCaseIds ? array_filter(explode(',', $testCaseIds)) : [];

        $queryBuilder = $this->resultRepository->createQueryBuilder('r')
            ->leftJoin('r.model', 'm')
            ->leftJoin('r.metric', 'mt')
            ->leftJoin('r.prompt', 'p')
            ->leftJoin('p.testCase', 'tc');

        $hasWhere = false;

        // Add benchmark filter if specified
        if ($benchmarkId) {
            $queryBuilder->where('r.benchmark = :benchmarkId')
                ->setParameter('benchmarkId', $benchmarkId);
            $hasWhere = true;
        }

        if (!empty($modelIds)) {
            if ($hasWhere) {
                $queryBuilder->andWhere('r.model IN (:modelIds)');
            } else {
                $queryBuilder->where('r.model IN (:modelIds)');
                $hasWhere = true;
            }
            $queryBuilder->setParameter('modelIds', $modelIds);
        }

        if (!empty($metricIds)) {
            if ($hasWhere) {
                $queryBuilder->andWhere('r.metric IN (:metricIds)');
            } else {
                $queryBuilder->where('r.metric IN (:metricIds)');
                $hasWhere = true;
            }
            $queryBuilder->setParameter('metricIds', $metricIds);
        }

        if (!empty($testCaseIds)) {
            if ($hasWhere) {
                $queryBuilder->andWhere('tc.id IN (:testCaseIds)');
            } else {
                $queryBuilder->where('tc.id IN (:testCaseIds)');
            }
            $queryBuilder->setParameter('testCaseIds', $testCaseIds);
        }

        $results = $queryBuilder->getQuery()->getResult();

        // Apply deduplication if needed
        if ($deduplication !== 'all' && !$benchmarkId) {
            $results = $this->deduplicateResults($results, $deduplication);
        }

        return $this->jsonResponse($results, groups: ['results']);
    }

    private function calculateModelAnalysis(array $results, $model): array
    {
        $metricScores = [];
        $totalTests = 0;
        $totalScore = 0;

        foreach ($results as $result) {
            $metricName = $result->getMetric()->getName();
            $score = $result->getScore();

            if (!isset($metricScores[$metricName])) {
                $metricScores[$metricName] = [
                    'scores' => [],
                    'total' => 0,
                    'count' => 0,
                    'min' => null,
                    'max' => null,
                ];
            }

            $metricScores[$metricName]['scores'][] = $score;
            $metricScores[$metricName]['total'] += $score;
            $metricScores[$metricName]['count']++;

            if ($metricScores[$metricName]['min'] === null || $score < $metricScores[$metricName]['min']) {
                $metricScores[$metricName]['min'] = $score;
            }
            if ($metricScores[$metricName]['max'] === null || $score > $metricScores[$metricName]['max']) {
                $metricScores[$metricName]['max'] = $score;
            }

            $totalScore += $score;
            $totalTests++;
        }

        // Calculate averages and additional statistics
        $processedMetrics = [];
        foreach ($metricScores as $metricName => $data) {
            $average = $data['count'] > 0 ? $data['total'] / $data['count'] : 0;

            $processedMetrics[] = [
                'name' => $metricName,
                'average' => round($average, 3),
                'min' => $data['min'],
                'max' => $data['max'],
                'count' => $data['count'],
                'scores' => $data['scores'],
            ];
        }

        return [
            'model' => $this->normalizer->normalize($model, null, ['groups' => 'api']),
            'overall' => [
                'averageScore' => $totalTests > 0 ? round($totalScore / $totalTests, 3) : 0,
                'totalTests' => $totalTests,
                'totalScore' => $totalScore,
            ],
            'metrics' => $processedMetrics,
            'results' => $this->normalizer->normalize($results, null, ['groups' => 'results']),
        ];
    }

    private function calculateMetricAnalysis(array $results, $metric): array
    {
        $modelScores = [];
        $totalTests = 0;
        $totalScore = 0;

        foreach ($results as $result) {
            $modelName = $result->getModel()->getName();
            $score = $result->getScore();

            if (!isset($modelScores[$modelName])) {
                $modelScores[$modelName] = [
                    'scores' => [],
                    'total' => 0,
                    'count' => 0,
                    'min' => null,
                    'max' => null,
                ];
            }

            $modelScores[$modelName]['scores'][] = $score;
            $modelScores[$modelName]['total'] += $score;
            $modelScores[$modelName]['count']++;

            if ($modelScores[$modelName]['min'] === null || $score < $modelScores[$modelName]['min']) {
                $modelScores[$modelName]['min'] = $score;
            }
            if ($modelScores[$modelName]['max'] === null || $score > $modelScores[$modelName]['max']) {
                $modelScores[$modelName]['max'] = $score;
            }

            $totalScore += $score;
            $totalTests++;
        }

        // Calculate averages and additional statistics
        $processedModels = [];
        foreach ($modelScores as $modelName => $data) {
            $average = $data['count'] > 0 ? $data['total'] / $data['count'] : 0;

            $processedModels[] = [
                'name' => $modelName,
                'average' => round($average, 3),
                'min' => $data['min'],
                'max' => $data['max'],
                'count' => $data['count'],
                'scores' => $data['scores'],
            ];
        }

        return [
            'metric' => $this->normalizer->normalize($metric, null, ['groups' => 'metrics']),
            'overall' => [
                'averageScore' => $totalTests > 0 ? round($totalScore / $totalTests, 3) : 0,
                'totalTests' => $totalTests,
                'totalScore' => $totalScore,
            ],
            'models' => $processedModels,
            'results' => $this->normalizer->normalize($results, null, ['groups' => 'results']),
        ];
    }

    #[Route('/test-case-analysis', name: 'test_case_analysis', methods: ['GET'])]
    public function getTestCaseAnalysis(Request $request): JsonResponse
    {
        $testCaseId = $request->query->get('testCaseId');
        $modelIds = $request->query->get('modelIds', '');
        $metricIds = $request->query->get('metricIds', '');
        $benchmarkId = $request->query->get('benchmarkId');
        $deduplication = $request->query->get('deduplication', 'latest'); // latest, all, average

        if (!$testCaseId) {
            return $this->jsonResponse(['error' => 'Test case ID is required'], 400);
        }

        $testCase = $this->testCaseRepository->find($testCaseId);
        if (!$testCase) {
            return $this->jsonResponse(['error' => 'Test case not found'], 404);
        }

        // Parse comma-separated IDs
        $modelIds = $modelIds ? array_filter(explode(',', $modelIds)) : [];
        $metricIds = $metricIds ? array_filter(explode(',', $metricIds)) : [];

        // Get all results for this test case
        $queryBuilder = $this->resultRepository->createQueryBuilder('r')
            ->leftJoin('r.model', 'm')
            ->leftJoin('r.metric', 'mt')
            ->leftJoin('r.prompt', 'p')
            ->leftJoin('p.testCase', 'tc')
            ->where('tc.id = :testCaseId')
            ->setParameter('testCaseId', $testCaseId);

        // Add benchmark filter if specified
        if ($benchmarkId) {
            $queryBuilder->andWhere('r.benchmark = :benchmarkId')
                ->setParameter('benchmarkId', $benchmarkId);
        }

        if (!empty($modelIds)) {
            $queryBuilder->andWhere('r.model IN (:modelIds)')
                ->setParameter('modelIds', $modelIds);
        }

        if (!empty($metricIds)) {
            $queryBuilder->andWhere('r.metric IN (:metricIds)')
                ->setParameter('metricIds', $metricIds);
        }

        $results = $queryBuilder->getQuery()->getResult();

        // Apply deduplication if needed
        if ($deduplication !== 'all' && !$benchmarkId) {
            $results = $this->deduplicateResults($results, $deduplication);
        }

        if (empty($results)) {
            return $this->jsonResponse([
                'testCase' => $this->normalizer->normalize($testCase, null, ['groups' => 'test_cases']),
                'overall' => [
                    'averageScore' => 0,
                    'totalTests' => 0,
                    'totalScore' => 0,
                ],
                'modelMetrics' => [],
                'results' => [],
                'deduplication' => $deduplication,
                'benchmarkScope' => $benchmarkId,
            ]);
        }

        // Group results by model-metric combinations
        $modelMetricData = [];
        $totalScore = 0;
        $totalTests = 0;

        foreach ($results as $result) {
            $modelId = $result->getModel()->getId();
            $metricId = $result->getMetric()->getId();
            $key = "{$modelId}-{$metricId}";

            if (!isset($modelMetricData[$key])) {
                $modelMetricData[$key] = [
                    'modelId' => $modelId,
                    'modelName' => $result->getModel()->getName(),
                    'metricId' => $metricId,
                    'metricName' => $result->getMetric()->getName(),
                    'scores' => [],
                    'totalScore' => 0,
                    'count' => 0,
                ];
            }

            $score = $result->getScore();
            $modelMetricData[$key]['scores'][] = $score;
            $modelMetricData[$key]['totalScore'] += $score;
            $modelMetricData[$key]['count']++;
            $totalScore += $score;
            $totalTests++;
        }

        // Process model-metric combinations
        $processedModelMetrics = [];
        foreach ($modelMetricData as $data) {
            $scores = $data['scores'];
            sort($scores);

            $processedModelMetrics[] = [
                'modelId' => $data['modelId'],
                'modelName' => $data['modelName'],
                'metricId' => $data['metricId'],
                'metricName' => $data['metricName'],
                'score' => round($data['totalScore'] / $data['count'], 3),
                'count' => $data['count'],
                'scores' => $scores,
                'min' => min($scores),
                'max' => max($scores),
            ];
        }

        // Sort by score descending
        usort($processedModelMetrics, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return $this->jsonResponse([
            'testCase' => $this->normalizer->normalize($testCase, null, ['groups' => 'test_cases']),
            'overall' => [
                'averageScore' => $totalTests > 0 ? round($totalScore / $totalTests, 3) : 0,
                'totalTests' => $totalTests,
                'totalScore' => $totalScore,
            ],
            'modelMetrics' => $processedModelMetrics,
            'results' => $this->normalizer->normalize($results, null, ['groups' => 'results']),
            'deduplication' => $deduplication,
            'benchmarkScope' => $benchmarkId,
        ]);
    }

    private function calculateBenchmarkAnalysis(array $results, $benchmark): array
    {
        $modelScores = [];
        $metricScores = [];
        $totalTests = 0;
        $totalScore = 0;

        foreach ($results as $result) {
            $modelName = $result->getModel()->getName();
            $metricName = $result->getMetric()->getName();
            $score = $result->getScore();

            // Track model performance
            if (!isset($modelScores[$modelName])) {
                $modelScores[$modelName] = [
                    'scores' => [],
                    'total' => 0,
                    'count' => 0,
                    'min' => null,
                    'max' => null,
                ];
            }

            $modelScores[$modelName]['scores'][] = $score;
            $modelScores[$modelName]['total'] += $score;
            $modelScores[$modelName]['count']++;

            if ($modelScores[$modelName]['min'] === null || $score < $modelScores[$modelName]['min']) {
                $modelScores[$modelName]['min'] = $score;
            }
            if ($modelScores[$modelName]['max'] === null || $score > $modelScores[$modelName]['max']) {
                $modelScores[$modelName]['max'] = $score;
            }

            // Track metric performance across all models
            if (!isset($metricScores[$metricName])) {
                $metricScores[$metricName] = [
                    'scores' => [],
                    'total' => 0,
                    'count' => 0,
                    'min' => null,
                    'max' => null,
                ];
            }

            $metricScores[$metricName]['scores'][] = $score;
            $metricScores[$metricName]['total'] += $score;
            $metricScores[$metricName]['count']++;

            if ($metricScores[$metricName]['min'] === null || $score < $metricScores[$metricName]['min']) {
                $metricScores[$metricName]['min'] = $score;
            }
            if ($metricScores[$metricName]['max'] === null || $score > $metricScores[$metricName]['max']) {
                $metricScores[$metricName]['max'] = $score;
            }

            $totalScore += $score;
            $totalTests++;
        }

        // Calculate model averages and additional statistics
        $processedModels = [];
        foreach ($modelScores as $modelName => $data) {
            $average = $data['count'] > 0 ? $data['total'] / $data['count'] : 0;

            $processedModels[] = [
                'name' => $modelName,
                'average' => round($average, 3),
                'min' => $data['min'],
                'max' => $data['max'],
                'count' => $data['count'],
                'scores' => $data['scores'],
            ];
        }

        // Calculate metric averages and additional statistics
        $processedMetrics = [];
        foreach ($metricScores as $metricName => $data) {
            $average = $data['count'] > 0 ? $data['total'] / $data['count'] : 0;

            $processedMetrics[] = [
                'name' => $metricName,
                'average' => round($average, 3),
                'min' => $data['min'],
                'max' => $data['max'],
                'count' => $data['count'],
                'scores' => $data['scores'],
            ];
        }

        return [
            'benchmark' => $this->normalizer->normalize($benchmark, null, ['groups' => 'benchmarks']),
            'overall' => [
                'averageScore' => $totalTests > 0 ? round($totalScore / $totalTests, 3) : 0,
                'totalTests' => $totalTests,
                'totalScore' => $totalScore,
            ],
            'models' => $processedModels,
            'metrics' => $processedMetrics,
            'results' => $this->normalizer->normalize($results, null, ['groups' => 'results']),
        ];
    }

    /**
     * Deduplicate results based on the specified strategy
     */
    private function deduplicateResults(array $results, string $strategy): array
    {
        $grouped = [];

        // Group results by prompt+metric+model combination
        foreach ($results as $result) {
            $key = sprintf(
                '%d-%d-%d',
                $result->getPrompt()->getId(),
                $result->getMetric()->getId(),
                $result->getModel()->getId()
            );

            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }
            $grouped[$key][] = $result;
        }

        $deduplicated = [];

        foreach ($grouped as $group) {
            // only one result for this combination, no deduplication needed
            if (count($group) === 1) {
                $deduplicated[] = $group[0];
                continue;
            }

            switch ($strategy) {
                case 'latest':
                    // Find the result from the most recent benchmark (highest benchmark ID)
                    $latest = null;
                    $latestBenchmarkId = 0;

                    foreach ($group as $result) {
                        $benchmarkId = $result->getBenchmark()->getId();
                        if ($benchmarkId > $latestBenchmarkId) {
                            $latestBenchmarkId = $benchmarkId;
                            $latest = $result;
                        }
                    }

                    if ($latest) {
                        $deduplicated[] = $latest;
                    }
                    break;

                case 'average':
                    // Create a virtual result with averaged scores
                    $first = $group[0];
                    $totalScore = 0;
                    foreach ($group as $result) {
                        $totalScore += $result->getScore();
                    }
                    $averageScore = $totalScore / count($group);

                    // Clone the first result and update its score
                    $averaged = clone $first;
                    $averaged->setScore($averageScore);
                    $deduplicated[] = $averaged;
                    break;
            }
        }

        return $deduplicated;
    }
}
