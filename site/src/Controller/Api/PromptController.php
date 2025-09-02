<?php

namespace App\Controller\Api;

use App\Entity\Prompt;
use App\Repository\PromptRepository;
use App\Repository\TestCaseRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/prompts', name: 'api_prompt_')]
class PromptController extends BaseApiController
{
    private PromptRepository $promptRepository;
    private TestCaseRepository $testCaseRepository;

    public function __construct(
        \Doctrine\ORM\EntityManagerInterface $entityManager,
        \Symfony\Component\Serializer\Normalizer\NormalizerInterface $normalizer,
        \Symfony\Component\Validator\Validator\ValidatorInterface $validator,
        PromptRepository $promptRepository,
        TestCaseRepository $testCaseRepository
    ) {
        parent::__construct($entityManager, $normalizer, $validator);
        $this->promptRepository = $promptRepository;
        $this->testCaseRepository = $testCaseRepository;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $testCaseId = $request->query->get('testCase');

        if ($testCaseId) {
            $prompts = $this->promptRepository->findBy(['testCase' => $testCaseId]);
        } else {
            $prompts = $this->promptRepository->findAll();
        }

        return $this->jsonResponse($prompts, groups: ['prompts']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $prompt = $this->promptRepository->find($id);

        if (!$prompt) {
            return $this->jsonResponse(['error' => 'Prompt not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->jsonResponse($prompt, groups: ['prompts']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->getRequestData($request);

        $prompt = new Prompt();
        $this->populatePromptFromData($prompt, $data);

        $validationError = $this->handleValidationErrors($prompt);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($prompt);

        return $this->jsonResponse($prompt, Response::HTTP_CREATED, ['prompts']);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $prompt = $this->promptRepository->find($id);

        if (!$prompt) {
            return $this->jsonResponse(['error' => 'Prompt not found'], Response::HTTP_NOT_FOUND);
        }

        $data = $this->getRequestData($request);
        $this->populatePromptFromData($prompt, $data);

        $validationError = $this->handleValidationErrors($prompt);
        if ($validationError) {
            return $validationError;
        }

        $this->persistEntity($prompt);

        return $this->jsonResponse($prompt, groups: ['prompts']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $prompt = $this->promptRepository->find($id);

        if (!$prompt) {
            return $this->jsonResponse(['error' => 'Prompt not found'], Response::HTTP_NOT_FOUND);
        }

        $this->deleteEntity($prompt);

        return $this->jsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function populatePromptFromData(Prompt $prompt, array $data): void
    {
        if (isset($data['input'])) {
            $prompt->setInput($data['input']);
        }

        if (isset($data['expectedOutput'])) {
            $prompt->setExpectedOutput($data['expectedOutput']);
        }

        if (isset($data['context'])) {
            $prompt->setContext($data['context']);
        }

        if (isset($data['testCaseId'])) {
            $testCase = $this->testCaseRepository->find($data['testCaseId']);
            if (!$testCase) {
                throw new \InvalidArgumentException('Test case not found');
            }
            $prompt->setTestCase($testCase);
        }
    }
}
