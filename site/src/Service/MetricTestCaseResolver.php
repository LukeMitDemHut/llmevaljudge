<?php

namespace App\Service;

use App\Entity\Metric;
use App\Entity\TestCase;
use App\Enum\MetricParam;
use App\Repository\TestCaseRepository;
use Doctrine\Common\Collections\Collection;

/**
 * Single source of truth for the metric<->test-case pairing used by both the
 * dispatch (RunBenchmarkHandler) and the progress calculation
 * (EvaluatePromptHandler::getTotalExpectedResults).
 *
 * Semantics: a metric with no explicitly assigned test cases applies to ALL
 * test cases (empty = all).
 */
class MetricTestCaseResolver
{
    public function __construct(
        private TestCaseRepository $testCaseRepository,
    ) {}

    /**
     * Effective test cases for a metric: the explicit assignment, or all test
     * cases when the assignment is empty ("empty = all" fallback).
     *
     * @param array<int, TestCase>|null $allTestCases Optional pre-fetched list of all
     *                                                test cases (avoids repeated
     *                                                findAll() calls in hot loops).
     *
     * @return TestCase[]
     */
    public function getEffectiveTestCasesForMetric(Metric $metric, ?array $allTestCases = null): array
    {
        $assigned = $metric->getTestCases();

        if ($assigned instanceof Collection && !$assigned->isEmpty()) {
            return $assigned->toArray();
        }

        if (is_array($assigned) && $assigned !== []) {
            return $assigned;
        }

        return $allTestCases ?? $this->testCaseRepository->findAll();
    }

    /**
     * Whether the (metric, prompt) pair satisfies all metric params. A pair that
     * does not satisfy them is skipped (only the pair, not the whole prompt).
     */
    public function pairSatisfiesParams(Metric $metric, \App\Entity\Prompt $prompt): bool
    {
        foreach ($metric->getParam() as $param) {
            $satisfied = match ($param) {
                MetricParam::INPUT => !empty($prompt->getInput()),
                MetricParam::EXPECTED_OUTPUT => !empty($prompt->getExpectedOutput()),
                MetricParam::CONTEXT => !empty($prompt->getContext()),
                MetricParam::ACTUAL_OUTPUT => true, // generated during execution
            };

            if (!$satisfied) {
                return false;
            }
        }

        return true;
    }
}