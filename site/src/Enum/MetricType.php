<?php

namespace App\Enum;

enum MetricType: string
{
    case GEVAL = 'g-eval';
    case DAG = 'dag';
    case TALE = 'tale';

    public function getLabel(): string
    {
        return match ($this) {
            self::GEVAL => 'G-Eval',
            self::DAG => 'DAG (Directed Acyclic Graph)',
            self::TALE => 'TALE (Tool-Augmented LLM Evaluation)',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::GEVAL => 'Evaluation using GPT-based scoring with customizable criteria and steps',
            self::DAG => 'Future feature: Directed Acyclic Graph based evaluation (coming soon)',
            self::TALE => 'Tool-Augmented LLM Evaluation leveraging external tools and APIs',
        };
    }
}
