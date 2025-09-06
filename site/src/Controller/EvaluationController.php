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
    public function index(): Response
    {
        return $this->render('vue/index.html.twig', [
            'title' => 'Evaluation',
            'component' => 'evaluation/index',
        ]);
    }
}
