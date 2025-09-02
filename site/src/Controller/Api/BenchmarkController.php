<?php

namespace App\Controller\Api;

use App\Entity\Benchmark;
use App\Repository\BenchmarkRepository;
use App\Repository\TestCaseRepository;
use App\Repository\MetricRepository;
use App\Repository\ModelRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/benchmarks', name: 'api_benchmark_')]
class BenchmarkController extends BaseApiController
{
    private BenchmarkRepository $benchmarkRepository;
    private TestCaseRepository $testCaseRepository;
    private MetricRepository $metricRepository;
    private ModelRepository $modelRepository;

    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        BenchmarkRepository $benchmarkRepository,
        TestCaseRepository $testCaseRepository,
        MetricRepository $metricRepository,
        ModelRepository $modelRepository
    ) {
        parent::__construct($entityManager, $normalizer, $validator);
        $this->benchmarkRepository = $benchmarkRepository;
        $this->testCaseRepository = $testCaseRepository;
        $this->metricRepository = $metricRepository;
        $this->modelRepository = $modelRepository;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $benchmarks = $this->benchmarkRepository->findAll();
        return $this->jsonResponse($benchmarks, groups: ['benchmarks']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $benchmark = $this->benchmarkRepository->find($id);

        if (!$benchmark) {
            return $this->jsonResponse(['error' => 'Benchmark not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->jsonResponse($benchmark, groups: ['benchmarks']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);

        $benchmark = new Benchmark();
        $this->populateBenchmarkFromData($benchmark, $data);

        $validationError = $this->handleValidationErrors($benchmark);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($benchmark);

        return $this->jsonResponse($benchmark, Response::HTTP_CREATED, ['benchmarks']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $benchmark = $this->benchmarkRepository->find($id);

        if (!$benchmark) {
            return $this->jsonResponse(['error' => 'Benchmark not found'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->getRequestData($request);
        $this->populateBenchmarkFromData($benchmark, $data);

        $validationError = $this->handleValidationErrors($benchmark);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($benchmark);

        return $this->jsonResponse($benchmark, groups: ['benchmarks']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $benchmark = $this->benchmarkRepository->find($id);

        if (!$benchmark) {
            return $this->jsonResponse(['error' => 'Benchmark not found'], Response::HTTP_NOT_FOUND);
        }

        $this->deleteEntity($benchmark);

        return $this->jsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/results', name: 'results', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getResults(int $id): JsonResponse
    {
        $benchmark = $this->benchmarkRepository->find($id);

        if (!$benchmark) {
            return $this->jsonResponse(['error' => 'Benchmark not found'], Response::HTTP_NOT_FOUND);
        }

        // Get all results directly from the benchmark entity
        $results = $benchmark->getResults()->toArray();

        return $this->jsonResponse($results, groups: ['results']);
    }

    #[Route('/{id}/start', name: 'start', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function startBenchmark(int $id, \Symfony\Component\Messenger\MessageBusInterface $messageBus): JsonResponse
    {
        $benchmark = $this->benchmarkRepository->find($id);

        if (!$benchmark) {
            return $this->jsonResponse(['error' => 'Benchmark not found'], Response::HTTP_NOT_FOUND);
        }

        // check if benchmark is able to be run


        // allow only benchmarks that are not started yet or already finished
        if ($benchmark->getStartedAt()) {
            if ($benchmark->getFinishedAt()) {
                // benchmark is finished so restart it
                $benchmark->setStartedAt(new \DateTimeImmutable());
                $benchmark->setFinishedAt(null);
                $benchmark->clearErrors(); // Clear errors when restarting
                $benchmark->resetProgress(); // Reset progress when restarting
                $this->persistEntity($benchmark);
            } else {
                return $this->jsonResponse(['error' => 'Benchmark already started'], Response::HTTP_BAD_REQUEST);
            }
        }

        // Set started timestamp and clear any errors
        $benchmark->setStartedAt(new \DateTimeImmutable());
        $benchmark->clearErrors(); // Clear errors when starting fresh
        $benchmark->resetProgress(); // Reset progress when starting fresh
        $this->persistEntity($benchmark);

        // Dispatch the RunBenchmark message to execute asynchronously
        $messageBus->dispatch(new \App\Message\RunBenchmark($benchmark->getId()));

        return $this->jsonResponse($benchmark, groups: ['benchmarks']);
    }

    #[Route('/{id}/start-missing', name: 'start_missing', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function startMissingBenchmark(int $id, \Symfony\Component\Messenger\MessageBusInterface $messageBus): JsonResponse
    {
        $benchmark = $this->benchmarkRepository->find($id);

        if (!$benchmark) {
            return $this->jsonResponse(['error' => 'Benchmark not found'], Response::HTTP_NOT_FOUND);
        }

        // Only allow finished benchmarks to run missing parts
        if (!$benchmark->getStartedAt() || !$benchmark->getFinishedAt()) {
            return $this->jsonResponse(['error' => 'Benchmark must be finished to run missing parts'], Response::HTTP_BAD_REQUEST);
        }

        // Don't change the original start/finish times, but clear errors and reset progress for missing parts
        $benchmark->clearErrors(); // Clear errors when restarting missing
        $benchmark->resetProgress(); // Reset progress when restarting missing
        $this->persistEntity($benchmark);

        // Dispatch the RunBenchmark message with a flag to only run missing parts
        $messageBus->dispatch(new \App\Message\RunBenchmark($benchmark->getId(), true));

        return $this->jsonResponse($benchmark, groups: ['benchmarks']);
    }

    private function populateBenchmarkFromData(Benchmark $benchmark, array $data): void
    {
        if (isset($data['name'])) {
            $benchmark->setName($data['name']);
        }

        if (isset($data['startedAt'])) {
            $startedAt = new \DateTimeImmutable($data['startedAt']);
            $benchmark->setStartedAt($startedAt);
        }

        if (isset($data['finishedAt'])) {
            $finishedAt = new \DateTimeImmutable($data['finishedAt']);
            $benchmark->setFinishedAt($finishedAt);
        }

        // Handle test cases (many-to-many)
        if (isset($data['testCaseIds']) && is_array($data['testCaseIds'])) {
            // Clear existing associations
            foreach ($benchmark->getTestCases() as $testCase) {
                $benchmark->removeTestCase($testCase);
            }

            // Add new associations
            foreach ($data['testCaseIds'] as $testCaseId) {
                $testCase = $this->testCaseRepository->find($testCaseId);
                if ($testCase) {
                    $benchmark->addTestCase($testCase);
                }
            }
        }

        // Handle metrics (many-to-many)
        if (isset($data['metricIds']) && is_array($data['metricIds'])) {
            // Clear existing associations
            foreach ($benchmark->getMetrics() as $metric) {
                $benchmark->removeMetric($metric);
            }

            // Add new associations
            foreach ($data['metricIds'] as $metricId) {
                $metric = $this->metricRepository->find($metricId);
                if ($metric) {
                    $benchmark->addMetric($metric);
                }
            }
        }

        // Handle models (many-to-many)
        if (isset($data['modelIds']) && is_array($data['modelIds'])) {
            // Clear existing associations
            foreach ($benchmark->getModels() as $model) {
                $benchmark->removeModel($model);
            }

            // Add new associations
            foreach ($data['modelIds'] as $modelId) {
                $model = $this->modelRepository->find($modelId);
                if ($model) {
                    $benchmark->addModel($model);
                }
            }
        }
    }
}
