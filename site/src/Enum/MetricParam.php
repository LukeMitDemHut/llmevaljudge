<?php

namespace App\Enum;

enum MetricParam: string
{
    case INPUT = 'input';
    case ACTUAL_OUTPUT = 'actual_output';
    case EXPECTED_OUTPUT = 'expected_output';
    case CONTEXT = 'context';
}
