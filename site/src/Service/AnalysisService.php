<?php

namespace App\Service;

use App\Entity\Benchmark;
use App\Entity\Result;
use App\Entity\Model;
use App\Entity\Metric;
use App\Repository\BenchmarkRepository;

class AnalysisService
{
    private BenchmarkRepository $benchmarkRepository;

    public function __construct(BenchmarkRepository $benchmarkRepository)
    {
        $this->benchmarkRepository = $benchmarkRepository;
    }

    /**
     * Get comprehensive analysis data for a benchmark
     */
    public function getAnalysis(Benchmark $benchmark): array
    {
        $models = $benchmark->getModels();

        // get overall analysis, not model or metric specific
        $overallStats = $this->benchmarkRepository->getAnalysisData($benchmark);

        // get model specific analysis
        $modelStats = [];
        foreach ($models as $model) {
            $modelStats[] = [
                'id' => $model->getId(),
                'name' => $model->getName(),
                'data' => $this->benchmarkRepository->getAnalysisData($benchmark, $model),
                'perMetric' => $this->getPerMetricAnalysis($benchmark, $model),
            ];
        }

        return [
            'overall' => $overallStats,
            'byModel' => $modelStats,
        ];
    }

    private function getPerMetricAnalysis(Benchmark $benchmark, Model $model): array
    {
        $metrics = $benchmark->getMetrics();
        $perMetric = [];
        foreach ($metrics as $metric) {
            $perMetric[] = [
                'id' => $metric->getId(),
                'name' => $metric->getName(),
                'data' => $this->benchmarkRepository->getAnalysisData($benchmark, $model, $metric),
            ];
        }
        return $perMetric;
    }
}
