<?php

namespace App\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/evaluation', name: 'api_evaluation_')]
class EvaluationController extends BaseApiController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        NormalizerInterface $normalizer,
        ValidatorInterface $validator
    ) {
        parent::__construct($entityManager, $normalizer, $validator);
    }

    private array $allowedGroups = ['model', 'metric', 'test_case', 'benchmark', 'prompt'];
    private array $allowedDedupeStrategies = ['all', 'avg', 'latest'];

    /**
     * Validates deduplication strategy parameter
     *
     * @param string $strategy The deduplication strategy
     * @return string Valid deduplication strategy
     * @throws \InvalidArgumentException If strategy is invalid
     */
    private function validateDedupeStrategy(string $strategy): string
    {
        if (empty($strategy)) {
            return 'latest'; // default strategy
        }

        if (!in_array($strategy, $this->allowedDedupeStrategies)) {
            throw new \InvalidArgumentException("Invalid dedupe strategy: $strategy. Allowed values are: " . implode(', ', $this->allowedDedupeStrategies));
        }

        return $strategy;
    }

    /**
     * Validates and parses a dash-separated string of IDs into an array of positive integers
     *
     * @param string $idString Dash-separated string of IDs
     * @return array Array of validated positive integers
     * @throws \InvalidArgumentException If any ID is not a positive integer
     */
    private function validateAndParseIds(string $idString): array
    {
        if (empty($idString)) {
            return [];
        }

        $ids = explode('-', $idString);
        $validatedIds = [];

        foreach ($ids as $id) {
            $id = trim($id);

            // Skip empty strings (can happen with multiple dashes)
            if ($id === '') {
                continue;
            }

            // Validate that it's a positive integer
            if (!ctype_digit($id) || (int)$id <= 0) {
                throw new \InvalidArgumentException("Invalid ID: $id. All IDs must be positive integers.");
            }

            $validatedIds[] = (int)$id;
        }

        return $validatedIds;
    }

    #[Route('', name: 'api_evaluation_get', methods: ['GET'])]
    public function getEvaluation(Request $request): JsonResponse
    {
        try {
            $params = [];

            $modelIds = $this->validateAndParseIds($request->query->get('model', ''));
            if (!empty($modelIds)) {
                $params['result.model_id'] = $modelIds;
            }

            $metricIds = $this->validateAndParseIds($request->query->get('metric', ''));
            if (!empty($metricIds)) {
                $params['result.metric_id'] = $metricIds;
            }

            $testCaseIds = $this->validateAndParseIds($request->query->get('test_case', ''));
            if (!empty($testCaseIds)) {
                $params['prompt.test_case_id'] = $testCaseIds;
            }

            $benchmarkIds = $this->validateAndParseIds($request->query->get('benchmark', ''));
            if (!empty($benchmarkIds)) {
                $params['result.benchmark_id'] = $benchmarkIds;
            }

            $promptIds = $this->validateAndParseIds($request->query->get('prompt', ''));
            if (!empty($promptIds)) {
                $params['result.prompt_id'] = $promptIds;
            }

            $groupBy = $request->query->get('group', 'model');


            // check if group is valid`
            if (!in_array($groupBy, $this->allowedGroups)) {
                throw new \InvalidArgumentException("Invalid group: $groupBy. Allowed values are: " . implode(', ', $this->allowedGroups));
            }

            // Validate deduplication strategy
            $dedupeStrategy = $this->validateDedupeStrategy($request->query->get('dedupe', 'latest'));

            $page = max(1, (int) $request->query->get('page', 1));
            $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 20)));

            // Build SQL query
            $queryData = $this->buildSQLForGroup($groupBy, $params, $dedupeStrategy);


            // combine with pagination
            $finalQuery = $queryData . "\nLIMIT :limit OFFSET :offset";

            $connection = $this->entityManager->getConnection();
            $stmt = $connection->prepare($finalQuery);

            $paginationParams = [
                'limit' => $pageSize,
                'offset' => ($page - 1) * $pageSize,
            ];

            /*
            input format
            [
            'result.model_id' => [7,2,6],
            'result.metric_id' => [3,5]
            ]

            output format:
            [
                ':r_model_id_1' => 7,
                ':r_model_id_2' => 2,
                ':r_model_id_3' => 6,
                ':result.metric_id_1' => 3,
                ':result.metric_id_2' => 5]

            */

            $paramsToBind = $this->filterEmptyParams($params);

            // Build request parameters with sanitized names (dots -> underscores)
            $requestParams = [];
            foreach ($paramsToBind as $key => $values) {
                // sanitize param key so the placeholder name is valid (no dots)
                $paramKey = str_replace('.', '_', $key);
                foreach ($values as $index => $value) {
                    // use parameter names without the leading ':' for executeQuery()
                    $requestParams["{$paramKey}_{$index}"] = $value;
                }
            }

            // Merge request params with pagination params into a single flat array
            $allParams = array_merge($requestParams, $paginationParams);

            // Bind parameters and execute
            $result = $stmt->executeQuery($allParams);

            // Fetch all results as associative array
            $data = $result->fetchAllAssociative();

            // Return properly formatted JSON response
            return $this->jsonResponse([
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'total_items' => count($data)
                ]
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse([
                'error' => 'Invalid parameters',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
            // return $this->jsonResponse([
            //     'error' => 'Internal server error',
            //     'message' => 'An error occurred while processing the request'
            // ], 500);
        }
    }

    // builds the SQL for one group
    private function buildSQLForGroup($group, $params, $dedupeStrategy): string
    {
        // replace actual value with sql placeholder based on key
        /* input format:
        [
            'result.model_id' => [7,2,6],
            'result.metric_id' => [3,5]
        ]

        output format:
        [
            'result.model_id' => [':r_model_id_1', ':r_model_id_2', ':r_model_id_3'],
            'result.metric_id' => [':r_metric_id_1', ':r_metric_id_2']
        ]
        */
        $paramsWithoutEmpty = $this->filterEmptyParams($params);
        $templateParams = [];
        foreach ($paramsWithoutEmpty as $key => $values) {
            $placeholders = [];
            foreach ($values as $index => $value) {
                // sanitize key so placeholder names do not contain dots
                $sanitizedKey = str_replace('.', '_', $key);
                $placeholders[] = ":{$sanitizedKey}_" . ($index);
            }
            $templateParams[$key] = $placeholders;
        }

        return $this->renderView('evaluation/queryBuilder.sql.twig', [
            'group' => $group,
            'params' => $templateParams,
            'dedupe_strategy' => $dedupeStrategy,
        ]);
    }

    private function filterEmptyParams(array $params): array
    {
        return array_filter($params, function ($value) {
            return !empty($value);
        });
    }
}
