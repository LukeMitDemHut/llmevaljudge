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
        // Each tool carries two JSON Schemas:
        //   parameters      -> handed to the target model as OpenAI function-calling schema
        //   response_schema -> enforced via response_format on the judge simulation call
        // Both are normalized to OpenAI strict mode: every property required,
        // additionalProperties false everywhere.
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

            // Migrate legacy format (parameters was a plain list of simple fields)
            $parameters = $tool['parameters'] ?? null;
            if (is_array($parameters) && isset($parameters[0]) && is_array($parameters[0]) && isset($parameters[0]['name']) && !isset($parameters[0]['type']) === false && isset($parameters[0]['required'])) {
                // legacy list-to-schema conversion
                $parameters = self::legacyParameterListToSchema($parameters);
            }

            $parametersSchema = self::normalizeStrictJsonSchema(
                $parameters,
                sprintf('tools[%d].parameters', $i),
                true
            );

            $responseSchema = self::normalizeStrictJsonSchema(
                $tool['response_schema'] ?? null,
                sprintf('tools[%d].response_schema', $i),
                false
            );

            $tools[] = [
                'name' => $name,
                'description' => $description,
                'parameters' => $parametersSchema,
                'response_schema' => $responseSchema,
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

        return [
            'tools' => $tools,
            'simulation_prompt' => $simulationPrompt,
            'evaluation_prompt' => $evaluationPrompt,
            'max_tool_rounds' => $maxRounds,
        ];
    }

    /**
     * Normalizes a JSON Schema for OpenAI strict mode:
     * - must be an object schema with "type": "object" set at the root,
     * - every property recursively marked required,
     * - "additionalProperties": false set on every object,
     * - "description" values must be strings.
     */
    private function normalizeStrictJsonSchema(mixed $schema, string $path, bool $required): array
    {
        if ($schema === null) {
            if ($required) {
                throw new \InvalidArgumentException($path . ' is required');
            }
            // default minimal response schema
            $schema = [
                'type' => 'object',
                'properties' => [
                    'tool_result' => [
                        'type' => 'string',
                        'description' => 'The simulated tool response as plain text',
                    ],
                ],
                'required' => ['tool_result'],
                'additionalProperties' => false,
            ];
        }

        if (!is_array($schema)) {
            throw new \InvalidArgumentException($path . ' must be a JSON Schema object');
        }

        $type = $schema['type'] ?? 'object';
        if (!is_string($type) || $type === '') {
            throw new \InvalidArgumentException($path . '.type must be a non-empty string');
        }

        if ($type === 'object') {
            $properties = $schema['properties'] ?? [];
            if (!is_array($properties)) {
                throw new \InvalidArgumentException($path . '.properties must be an object');
            }

            $normalizedProperties = [];
            $names = [];
            foreach ($properties as $propName => $propSchema) {
                if (!is_string($propName) || $propName === '') {
                    throw new \InvalidArgumentException($path . '.properties keys must be non-empty strings');
                }
                if (isset($names[$propName])) {
                    throw new \InvalidArgumentException($path . '.properties "' . $propName . '" is defined twice');
                }
                $names[$propName] = true;
                $normalizedProperties[$propName] = $this->normalizeStrictProperty(
                    $propSchema,
                    $path . '.properties.' . $propName
                );
            }

            $normalized = [
                'type' => 'object',
                'properties' => $normalizedProperties,
                'required' => array_keys($normalizedProperties),
                'additionalProperties' => false,
            ];

            return $normalized;
        }

        if ($type === 'array') {
            $normalized = ['type' => 'array'];
            if (isset($schema['items'])) {
                $normalized['items'] = $this->normalizeStrictProperty($schema['items'], $path . '.items');
            } else {
                throw new \InvalidArgumentException($path . '.items is required for array types');
            }
            if (isset($schema['description']) && is_string($schema['description'])) {
                $normalized['description'] = $schema['description'];
            }
            return $normalized;
        }

        // scalar types (string/number/boolean/integer/null)
        $allowed = ['string', 'number', 'boolean', 'integer', 'null'];
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf(
                '%s.type "%s" is not allowed (allowed: %s)',
                $path, $type, implode(', ', array_merge($allowed, ['object', 'array']))
            ));
        }
        $normalized = ['type' => $type];
        if (isset($schema['description']) && is_string($schema['description'])) {
            $normalized['description'] = $schema['description'];
        }
        if (isset($schema['enum']) && is_array($schema['enum']) && $type === 'string') {
            $normalized['enum'] = array_values(array_filter($schema['enum'], 'is_string'));
        }
        return $normalized;
    }

    private function normalizeStrictProperty(mixed $schema, string $path): array
    {
        if (!is_array($schema)) {
            throw new \InvalidArgumentException($path . ' must be a JSON Schema object');
        }
        return $this->normalizeStrictJsonSchema($schema, $path, true);
    }

    /**
     * Converts the legacy parameter format (a plain list of field objects with
     * name/type/description/required) into a proper JSON Schema object.
     */
    private static function legacyParameterListToSchema(array $list): array
    {
        $properties = [];
        $required = [];
        foreach ($list as $param) {
            if (!is_array($param) || !isset($param['name']) || !is_string($param['name']) || $param['name'] === '') {
                continue;
            }
            $entry = ['type' => is_string($param['type'] ?? null) ? $param['type'] : 'string'];
            if (isset($param['description']) && is_string($param['description'])) {
                $entry['description'] = $param['description'];
            }
            $properties[$param['name']] = $entry;
            if (!empty($param['required'])) {
                $required[] = $param['name'];
            }
        }
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }
}
