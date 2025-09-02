<?php

namespace App\Controller;

use App\Repository\ModelRepository;
use App\Repository\MetricRepository;
use App\Repository\TestCaseRepository;
use App\Repository\BenchmarkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

final class EvaluationController extends AbstractController
{
    #[Route('/evaluation', name: 'app_evaluation')]
    public function index(
        ModelRepository $modelRepository,
        MetricRepository $metricRepository,
        TestCaseRepository $testCaseRepository,
        BenchmarkRepository $benchmarkRepository,
        NormalizerInterface $normalizer
    ): Response {
        // Get initial data for dropdowns
        $models = $modelRepository->findAll();
        $metrics = $metricRepository->findAll();
        $testCases = $testCaseRepository->findAll();
        $benchmarks = $benchmarkRepository->findAll();

        // Serialize entities to arrays
        $modelsData = $normalizer->normalize($models, null, ['groups' => 'api']);
        $metricsData = $normalizer->normalize($metrics, null, ['groups' => 'metrics']);
        $testCasesData = $normalizer->normalize($testCases, null, ['groups' => 'test_cases_summary']);
        $benchmarksData = $normalizer->normalize($benchmarks, null, ['groups' => 'benchmarks']);

        return $this->render('vue/index.html.twig', [
            'title' => 'Evaluation',
            'component' => 'evaluation/index',
            'models' => $modelsData,
            'metrics' => $metricsData,
            'testCases' => $testCasesData,
            'benchmarks' => $benchmarksData,
        ]);
    }
}
