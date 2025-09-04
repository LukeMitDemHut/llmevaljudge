<?php

namespace App\Controller\Api;

use App\Entity\Model;
use App\Repository\ModelRepository;
use App\Repository\ProviderRepository;
use App\Utils\PaginationHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/models', name: 'api_model_')]
class ModelController extends BaseApiController
{
    private ModelRepository $modelRepository;
    private ProviderRepository $providerRepository;

    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        ModelRepository $modelRepository,
        ProviderRepository $providerRepository
    ) {
        parent::__construct($entityManager, $normalizer, $validator);
        $this->modelRepository = $modelRepository;
        $this->providerRepository = $providerRepository;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $queryBuilder = $this->modelRepository->createQueryBuilder('m')
            ->orderBy('m.name', 'ASC');

        return $this->paginatedResponse($queryBuilder, $request);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);

        $model = new Model();

        // Handle provider relationship
        if (isset($data['providerId'])) {
            $provider = $this->providerRepository->find($data['providerId']);
            if (!$provider) {
                return $this->jsonResponse(['error' => 'Provider not found'], Response::HTTP_BAD_REQUEST);
            }
            $model->setProvider($provider);
            unset($data['providerId']); // Remove from data to avoid setter call
        }

        $this->populateEntity($model, $data);
        $model->setUpdatedAt(new \DateTimeImmutable());

        $validationError = $this->handleValidationErrors($model);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($model);

        return $this->jsonResponse($model, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Model $model): JsonResponse
    {
        return $this->jsonResponse($model);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, Model $model): JsonResponse
    {
        $data = $this->getRequestData($request);

        // Handle provider relationship
        if (isset($data['providerId'])) {
            $provider = $this->providerRepository->find($data['providerId']);
            if (!$provider) {
                return $this->jsonResponse(['error' => 'Provider not found'], Response::HTTP_BAD_REQUEST);
            }
            $model->setProvider($provider);
            unset($data['providerId']);
        }

        $this->updateEntity($model, $data);
        $model->setUpdatedAt(new \DateTimeImmutable());

        $validationError = $this->handleValidationErrors($model);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($model);

        return $this->jsonResponse($model);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Model $model): JsonResponse
    {
        // Remove model from all benchmarks (many-to-many relationship)
        foreach ($model->getBenchmarks() as $benchmark) {
            $benchmark->removeModel($model);
        }

        // orphanRemoval: true will automatically handle:
        // - Deleting related metrics (Model->metrics)
        // - Deleting related results (Model->results and Metric->results)
        $this->deleteEntity($model);

        return $this->jsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
