<?php

namespace App\Message;

final class RunBenchmark
{
    public function __construct(
        public int $benchmarkId,
        public bool $onlyMissing = false
    ) {}
}
