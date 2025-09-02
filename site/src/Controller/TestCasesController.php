<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use App\Repository\TestCaseRepository;

final class TestCasesController extends AbstractController
{
    #[Route('/testcases', name: 'app_testcases')]
    public function index(
        TestCaseRepository $testCaseRepository,
        NormalizerInterface $normalizer
    ): Response {
        // Get only basic test case data without prompts for initial load
        $testCases = $testCaseRepository->findAll();

        // Serialize entities to arrays with summary groups only (no prompts)
        $testCasesData = $normalizer->normalize($testCases, null, ['groups' => 'test_cases_summary']);

        return $this->render('vue/index.html.twig', [
            'title' => 'Test Cases',
            'component' => 'testcases/index',
            'testcases' => $testCasesData,
        ]);
    }
}
