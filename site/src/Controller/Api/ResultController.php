<?php

namespace App\Controller\Api;

use App\Entity\Result;
use App\Repository\ResultRepository;
use App\Repository\PromptRepository;
use App\Repository\MetricRepository;
use App\Repository\ModelRepository;
use App\Repository\BenchmarkRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/results', name: 'api_result_')]
class ResultController extends BaseApiController
{
    private ResultRepository $resultRepository;
    private PromptRepository $promptRepository;
    private MetricRepository $metricRepository;
    private ModelRepository $modelRepository;
    private BenchmarkRepository $benchmarkRepository;

    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        ResultRepository $resultRepository,
        PromptRepository $promptRepository,
        MetricRepository $metricRepository,
        ModelRepository $modelRepository,
        BenchmarkRepository $benchmarkRepository
    ) {
        parent::__construct($entityManager, $normalizer, $validator);
        $this->resultRepository = $resultRepository;
        $this->promptRepository = $promptRepository;
        $this->metricRepository = $metricRepository;
        $this->modelRepository = $modelRepository;
        $this->benchmarkRepository = $benchmarkRepository;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $queryBuilder = $this->resultRepository->createQueryBuilder('r')
            ->leftJoin('r.prompt', 'p')
            ->leftJoin('r.metric', 'm')
            ->leftJoin('r.model', 'mo')
            ->leftJoin('r.benchmark', 'b');

        // Support filtering by prompt, metric, model, or benchmark
        if ($promptId = $request->query->get('prompt')) {
            $queryBuilder->andWhere('r.prompt = :promptId')
                ->setParameter('promptId', $promptId);
        }
        if ($metricId = $request->query->get('metric')) {
            $queryBuilder->andWhere('r.metric = :metricId')
                ->setParameter('metricId', $metricId);
        }
        if ($modelId = $request->query->get('model')) {
            $queryBuilder->andWhere('r.model = :modelId')
                ->setParameter('modelId', $modelId);
        }
        if ($benchmarkId = $request->query->get('benchmark')) {
            $queryBuilder->andWhere('r.benchmark = :benchmarkId')
                ->setParameter('benchmarkId', $benchmarkId);
        }

        $queryBuilder->orderBy('r.id', 'DESC');

        return $this->paginatedResponse($queryBuilder, $request, ['results']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $result = $this->resultRepository->find($id);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Result not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->jsonResponse($result, groups: ['results']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);

        $result = new Result();
        $this->populateResultFromData($result, $data);

        $validationError = $this->handleValidationErrors($result);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($result);

        return $this->jsonResponse($result, Response::HTTP_CREATED, ['results']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $result = $this->resultRepository->find($id);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Result not found'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->getRequestData($request);
        $this->populateResultFromData($result, $data);

        $validationError = $this->handleValidationErrors($result);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($result);

        return $this->jsonResponse($result, groups: ['results']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $result = $this->resultRepository->find($id);

        if (!$result) {
            return $this->jsonResponse(['error' => 'Result not found'], Response::HTTP_NOT_FOUND);
        }

        $this->deleteEntity($result);

        return $this->jsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function populateResultFromData(Result $result, array $data): void
    {
        if (isset($data['actualOutput'])) {
            $result->setActualOutput($data['actualOutput']);
        }

        if (isset($data['score'])) {
            $result->setScore((float) $data['score']);
        }

        if (isset($data['reason'])) {
            $result->setReason($data['reason']);
        }

        if (isset($data['logs'])) {
            $result->setLogs($data['logs']);
        }

        if (isset($data['promptId'])) {
            $prompt = $this->promptRepository->find($data['promptId']);
            if (!$prompt) {
                throw new \InvalidArgumentException('Prompt not found');
            }
            $result->setPrompt($prompt);
        }

        if (isset($data['metricId'])) {
            $metric = $this->metricRepository->find($data['metricId']);
            if (!$metric) {
                throw new \InvalidArgumentException('Metric not found');
            }
            $result->setMetric($metric);
        }

        if (isset($data['modelId'])) {
            $model = $this->modelRepository->find($data['modelId']);
            if (!$model) {
                throw new \InvalidArgumentException('Model not found');
            }
            $result->setModel($model);
        }

        if (isset($data['benchmarkId'])) {
            $benchmark = $this->benchmarkRepository->find($data['benchmarkId']);
            if (!$benchmark) {
                throw new \InvalidArgumentException('Benchmark not found');
            }
            $result->setBenchmark($benchmark);
        }
    }
}
