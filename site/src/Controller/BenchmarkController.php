<?php

namespace App\Controller;

use App\Repository\BenchmarkRepository;
use App\Repository\ModelRepository;
use App\Repository\MetricRepository;
use App\Repository\TestCaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class BenchmarkController extends AbstractController
{
    #[Route('/benchmarks', name: 'app_benchmarks')]
    public function index(
        BenchmarkRepository $benchmarkRepository,
        ModelRepository $modelRepository,
        MetricRepository $metricRepository,
        TestCaseRepository $testCaseRepository,
        NormalizerInterface $normalizer
    ): Response {
        // Use createQueryBuilder to ensure consistent sorting with API endpoint
        $benchmarks = $benchmarkRepository->createQueryBuilder('b')
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();

        $models = $modelRepository->findAll();
        $metrics = $metricRepository->findAll();
        $testCases = $testCaseRepository->findAll();

        $normalizedBenchmarks = $normalizer->normalize($benchmarks, null, ['groups' => ['benchmarks']]);
        $normalizedModels = $normalizer->normalize($models, null, ['groups' => ['settings']]);
        $normalizedMetrics = $normalizer->normalize($metrics, null, ['groups' => ['metrics']]);
        $normalizedTestCases = $normalizer->normalize($testCases, null, ['groups' => ['test_cases']]);

        return $this->render('vue/index.html.twig', [
            'title' => 'Benchmarks',
            'component' => 'benchmarks/index',
            'benchmarks' => $normalizedBenchmarks,
            'models' => $normalizedModels,
            'metrics' => $normalizedMetrics,
            'testCases' => $normalizedTestCases,
        ]);
    }
}
