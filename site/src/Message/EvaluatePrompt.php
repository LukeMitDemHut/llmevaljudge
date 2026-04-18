<?php

namespace App\Message;

final class EvaluatePrompt
{
    public function __construct(
        public int $promptId,
        public int $metricId,
        public int $modelId,
        public int $benchmarkId,
        public int $runIndex = 1,
        public int $attempt = 1
    ) {}
}
