<?php

namespace App\Controller\Api;

use App\Entity\Metric;
use App\Enum\MetricParam;
use App\Enum\MetricType;
use App\Repository\MetricRepository;
use App\Repository\ModelRepository;
use App\Utils\PaginationHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/metrics', name: 'api_metric_')]
class MetricController extends BaseApiController
{
    private MetricRepository $metricRepository;
    private ModelRepository $modelRepository;

    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        MetricRepository $metricRepository,
        ModelRepository $modelRepository
    ) {
        parent::__construct($entityManager, $normalizer, $validator);
        $this->metricRepository = $metricRepository;
        $this->modelRepository = $modelRepository;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $limit = min($request->query->getInt('limit', 50), 100);

        $queryBuilder = $this->metricRepository->createQueryBuilder('m');

        if (!empty($search)) {
            $queryBuilder->where('m.name LIKE :search OR m.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // If pagination is requested, ignore the limit parameter as pagination will handle it
        if (!PaginationHelper::shouldPaginate($request)) {
            $queryBuilder->setMaxResults($limit);
        }

        $queryBuilder->orderBy('m.name', 'ASC');

        return $this->paginatedResponse($queryBuilder, $request, ['metrics']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $metric = $this->metricRepository->find($id);

        if (!$metric) {
            return $this->jsonResponse(['error' => 'Metric not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->jsonResponse($metric, groups: ['metrics']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);

        try {
            $metric = new Metric();
            $this->populateMetricFromData($metric, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['error' => 'Validation failed', 'violations' => ['definition' => $e->getMessage()]], Response::HTTP_BAD_REQUEST);
        }

        $validationError = $this->handleValidationErrors($metric);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($metric);

        return $this->jsonResponse($metric, Response::HTTP_CREATED, ['metrics']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $metric = $this->metricRepository->find($id);

        if (!$metric) {
            return $this->jsonResponse(['error' => 'Metric not found'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->getRequestData($request);

        try {
            $this->populateMetricFromData($metric, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['error' => 'Validation failed', 'violations' => ['definition' => $e->getMessage()]], Response::HTTP_BAD_REQUEST);
        }

        $validationError = $this->handleValidationErrors($metric);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($metric);

        return $this->jsonResponse($metric, groups: ['metrics']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $metric = $this->metricRepository->find($id);

        if (!$metric) {
            return $this->jsonResponse(['error' => 'Metric not found'], Response::HTTP_NOT_FOUND);
        }

        // Remove metric from all benchmarks (many-to-many relationship)
        foreach ($metric->getBenchmarks() as $benchmark) {
            $benchmark->removeMetric($metric);
        }

        // orphanRemoval: true will automatically delete related results
        $this->deleteEntity($metric);

        return $this->jsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/types', name: 'types', methods: ['GET'])]
    public function getTypes(): JsonResponse
    {
        $types = [];
        foreach (MetricType::cases() as $type) {
            $types[] = [
                'value' => $type->value,
                'label' => $type->getLabel(),
                'description' => $type->getDescription(),
            ];
        }

        return $this->jsonResponse($types);
    }

    private function populateMetricFromData(Metric $metric, array $data): void
    {
        if (isset($data['name'])) {
            $metric->setName($data['name']);
        }

        if (isset($data['description'])) {
            $metric->setDescription($data['description']);
        }

        if (isset($data['type'])) {
            if (is_string($data['type'])) {
                $type = MetricType::tryFrom($data['type']);
                if ($type) {
                    $metric->setType($type);
                }
            } elseif ($data['type'] instanceof MetricType) {
                $metric->setType($data['type']);
            }
        }

        if (isset($data['threshold'])) {
            $metric->setThreshold((float) $data['threshold']);
        }

        if (isset($data['definition'])) {
            // For G-eval metrics, ensure data integrity:
            // Either steps OR criteria should be present, not both
            if (isset($data['type']) && $data['type'] === 'g-eval') {
                $definition = $data['definition'];
                $hasSteps = isset($definition['steps']) && !empty($definition['steps']);
                $hasCriteria = isset($definition['criteria']) && !empty(trim($definition['criteria']));

                if ($hasSteps && $hasCriteria) {
                    // If both are present, prefer steps and clear criteria
                    $definition = ['steps' => $definition['steps']];
                } elseif (!$hasSteps && !$hasCriteria) {
                    // If neither is present, set empty steps as default
                    $definition = ['steps' => []];
                }

                $metric->setDefinition($definition);
            } elseif (isset($data['type']) && $data['type'] === 'tool-use') {
                $metric->setDefinition($this->normalizeToolUseDefinition($data['definition']));
            } else {
                $metric->setDefinition($data['definition']);
            }
        }

        if (isset($data['param']) || (isset($data['type']) && $data['type'] === 'tool-use')) {
            if (isset($data['type']) && $data['type'] === 'tool-use') {
                // tool-use metrics have a fixed evaluation context (input + actual_output);
                // the Parameters checkboxes are hidden in the UI for this type, so 'param'
                // may be absent entirely — the fixed value applies in both cases
                $metric->setParam([MetricParam::INPUT, MetricParam::ACTUAL_OUTPUT]);
            } elseif (is_array($data['param'])) {
                $params = array_map(function ($p) {
                    return is_string($p) ? MetricParam::tryFrom($p) : $p;
                }, $data['param']);
                $params = array_filter($params); // Remove nulls
                $metric->setParam($params);
            } else {
                $metric->setParam($data['param']);
            }
        }

        if (isset($data['rating_model_id'])) {
            $ratingModel = $this->modelRepository->find($data['rating_model_id']);
            if ($ratingModel) {
                $metric->setRatingModel($ratingModel);
            }
        }
    }

    /**
     * Normalizes and validates a tool-use metric definition.
     *
     * Expected structure:
     * {
     *   "tools": [
     *     {
     *       "name": "search_documents",
     *       "description": "Searches the internal document store",
     *       "parameters": [
     *         {
     *           "name": "query",
     *           "type": "string",
     *           "description": "The search query",
     *           "required": true
     *         }
     *       ]
     *     }
     *   ],
     *   "simulation_prompt": "...",
     *   "evaluation_prompt": "...",
     *   "max_tool_rounds": 5
     * }
     *
     * @param mixed $definition Raw definition from the request
     * @return array Normalized definition
     * @throws \InvalidArgumentException On structural violations
     */
    private function normalizeToolUseDefinition(mixed $definition): array
    {
        if (!is_array($definition)) {
            throw new \InvalidArgumentException('tool-use definition must be an object/array');
        }

        $allowedTypes = ['string', 'number', 'boolean', 'object', 'array'];

        // --- tools ---
        if (!isset($definition['tools']) || !is_array($definition['tools']) || count($definition['tools']) === 0) {
            throw new \InvalidArgumentException('tool-use definition requires at least one tool');
        }

        $tools = [];
        $seenNames = [];
        foreach ($definition['tools'] as $i => $tool) {
            if (!is_array($tool)) {
                throw new \InvalidArgumentException(sprintf('tools[%d] must be an object', $i));
            }

            $name = $tool['name'] ?? null;
            if (!is_string($name) || trim($name) === '') {
                throw new \InvalidArgumentException(sprintf('tools[%d].name is required', $i));
            }
            if (isset($seenNames[$name])) {
                throw new \InvalidArgumentException(sprintf('tools[%d].name "%s" is not unique', $i, $name));
            }
            $seenNames[$name] = true;

            $description = $tool['description'] ?? null;
            if (!is_string($description) || trim($description) === '') {
                throw new \InvalidArgumentException(sprintf('tools[%d].description is required', $i));
            }

            $parameters = $tool['parameters'] ?? [];
            if (!is_array($parameters)) {
                throw new \InvalidArgumentException(sprintf('tools[%d].parameters must be an array', $i));
            }

            $normalizedParams = [];
            $seenParamNames = [];
            foreach ($parameters as $j => $param) {
                if (!is_array($param)) {
                    throw new \InvalidArgumentException(sprintf('tools[%d].parameters[%d] must be an object', $i, $j));
                }

                $pName = $param['name'] ?? null;
                if (!is_string($pName) || trim($pName) === '') {
                    throw new \InvalidArgumentException(sprintf('tools[%d].parameters[%d].name is required', $i, $j));
                }
                if (isset($seenParamNames[$pName])) {
                    throw new \InvalidArgumentException(sprintf('tools[%d].parameters[%d].name "%s" is not unique', $i, $j, $pName));
                }
                $seenParamNames[$pName] = true;

                $pType = $param['type'] ?? 'string';
                if (!in_array($pType, $allowedTypes, true)) {
                    throw new \InvalidArgumentException(sprintf(
                        'tools[%d].parameters[%d].type "%s" is invalid (allowed: %s)',
                        $i, $j, is_string($pType) ? $pType : gettype($pType), implode(', ', $allowedTypes)
                    ));
                }

                $normalizedParams[] = [
                    'name' => $pName,
                    'type' => $pType,
                    'description' => is_string($param['description'] ?? null) ? $param['description'] : '',
                    'required' => (bool) ($param['required'] ?? false),
                ];
            }

            $tools[] = [
                'name' => $name,
                'description' => $description,
                'parameters' => $normalizedParams,
            ];
        }

        // --- simulation_prompt ---
        $simulationPrompt = $definition['simulation_prompt'] ?? null;
        if (!is_string($simulationPrompt) || trim($simulationPrompt) === '') {
            throw new \InvalidArgumentException('tool-use definition requires a non-empty simulation_prompt');
        }

        // --- evaluation_prompt ---
        $evaluationPrompt = $definition['evaluation_prompt'] ?? null;
        if (!is_string($evaluationPrompt) || trim($evaluationPrompt) === '') {
            throw new \InvalidArgumentException('tool-use definition requires a non-empty evaluation_prompt');
        }

        // --- max_tool_rounds ---
        $maxRounds = $definition['max_tool_rounds'] ?? 5;
        if (!is_int($maxRounds) && !(is_string($maxRounds) && ctype_digit($maxRounds))) {
            throw new \InvalidArgumentException('tool-use max_tool_rounds must be an integer');
        }
        $maxRounds = (int) $maxRounds;
        if ($maxRounds < 1 || $maxRounds > 20) {
            throw new \InvalidArgumentException('tool-use max_tool_rounds must be between 1 and 20');
        }

        // --- expected_behavior (optional "Erwartungshorizont") ---
        $expectedBehavior = $definition['expected_behavior'] ?? '';
        if (!is_string($expectedBehavior)) {
            throw new \InvalidArgumentException('tool-use expected_behavior must be a string');
        }
        $expectedBehavior = trim($expectedBehavior);

        return [
            'tools' => $tools,
            'simulation_prompt' => $simulationPrompt,
            'evaluation_prompt' => $evaluationPrompt,
            'expected_behavior' => $expectedBehavior,
            'max_tool_rounds' => $maxRounds,
        ];
    }
}
