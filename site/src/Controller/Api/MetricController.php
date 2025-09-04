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

        $metric = new Metric();
        $this->populateMetricFromData($metric, $data);

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
        $this->populateMetricFromData($metric, $data);

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
            } else {
                $metric->setDefinition($data['definition']);
            }
        }

        if (isset($data['param'])) {
            if (is_array($data['param'])) {
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
}
