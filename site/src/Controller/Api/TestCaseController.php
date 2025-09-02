<?php

namespace App\Controller\Api;

use App\Entity\TestCase;
use App\Repository\TestCaseRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/test-cases', name: 'api_test_case_')]
class TestCaseController extends BaseApiController
{
    private TestCaseRepository $testCaseRepository;

    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        TestCaseRepository $testCaseRepository
    ) {
        parent::__construct($entityManager, $normalizer, $validator);
        $this->testCaseRepository = $testCaseRepository;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $testCases = $this->testCaseRepository->findAll();
        return $this->jsonResponse($testCases, groups: ['test_cases']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $testCase = $this->testCaseRepository->find($id);

        if (!$testCase) {
            return $this->jsonResponse(['error' => 'Test case not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->jsonResponse($testCase, groups: ['test_cases']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);

        $testCase = $this->createEntity(TestCase::class, $data);

        $validationError = $this->handleValidationErrors($testCase);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($testCase);

        return $this->jsonResponse($testCase, Response::HTTP_CREATED, ['test_cases']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $testCase = $this->testCaseRepository->find($id);

        if (!$testCase) {
            return $this->jsonResponse(['error' => 'Test case not found'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->getRequestData($request);
        $this->updateEntity($testCase, $data);

        $validationError = $this->handleValidationErrors($testCase);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($testCase);

        return $this->jsonResponse($testCase, groups: ['test_cases']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $testCase = $this->testCaseRepository->find($id);

        if (!$testCase) {
            return $this->jsonResponse(['error' => 'Test case not found'], Response::HTTP_NOT_FOUND);
        }

        $this->deleteEntity($testCase);

        return $this->jsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
