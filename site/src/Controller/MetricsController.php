<?php

namespace App\Controller;

use App\Repository\MetricRepository;
use App\Repository\ModelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use App\Enum\MetricParam;

final class MetricsController extends AbstractController
{
    #[Route('/metrics', name: 'app_metrics')]
    public function index(
        MetricRepository $metricRepository,
        ModelRepository $modelRepository,
        NormalizerInterface $normalizer
    ): Response {
        // Get initial data
        $metrics = $metricRepository->findAll();
        $models = $modelRepository->findAll();
        $availableParams = MetricParam::cases();

        // Serialize entities to arrays
        $metricsData = $normalizer->normalize($metrics, null, ['groups' => 'metrics']);
        $modelsData = $normalizer->normalize($models, null, ['groups' => 'api']);

        return $this->render('vue/index.html.twig', [
            'title' => 'Metrics',
            'component' => 'metrics/index',
            'metrics' => $metricsData,
            'models' => $modelsData,
            'availableParams' => $availableParams,
        ]);
    }
}
