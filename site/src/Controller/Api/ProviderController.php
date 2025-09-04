<?php

namespace App\Controller\Api;

use App\Entity\Provider;
use App\Repository\ProviderRepository;
use App\Utils\PaginationHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/providers', name: 'api_provider_')]
class ProviderController extends BaseApiController
{
    private ProviderRepository $providerRepository;

    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        ProviderRepository $providerRepository
    ) {
        parent::__construct($entityManager, $normalizer, $validator);
        $this->providerRepository = $providerRepository;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $queryBuilder = $this->providerRepository->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC');

        return $this->paginatedResponse($queryBuilder, $request);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);

        $provider = $this->createEntity(Provider::class, $data);

        $validationError = $this->handleValidationErrors($provider);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($provider);

        return $this->jsonResponse($provider, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Provider $provider): JsonResponse
    {
        return $this->jsonResponse($provider);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, Provider $provider): JsonResponse
    {
        $data = $this->getRequestData($request);

        $this->updateEntity($provider, $data);

        $validationError = $this->handleValidationErrors($provider);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($provider);

        return $this->jsonResponse($provider);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Provider $provider): JsonResponse
    {
        $this->deleteEntity($provider);

        return $this->jsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
