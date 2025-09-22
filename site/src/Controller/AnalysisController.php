<?php

namespace App\Controller;

use App\Repository\BenchmarkRepository;
use App\Service\AnalysisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AnalysisController extends AbstractController
{
    #[Route('/analysis/{benchmarkId}', name: 'app_analysis')]
    public function index(
        int $benchmarkId,
        BenchmarkRepository $benchmarkRepository,
        AnalysisService $analysisService
    ): Response {

        // Get the benchmark given in the URL
        $benchmark = $benchmarkRepository->find($benchmarkId);

        if (!$benchmark) {
            throw $this->createNotFoundException('Benchmark not found');
        }

        // Get basic stats
        $analysisData = $analysisService->getAnalysis($benchmark);


        return $this->render('vue/index.html.twig', [
            'title' => $benchmark->getName() . ' - Analysis',
            'component' => 'analysis/index',
            'analysisData' => $analysisData,
        ]);
    }
}
