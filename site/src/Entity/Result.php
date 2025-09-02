<?php

namespace App\Entity;

use App\Repository\ResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ResultRepository::class)]
class Result
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api', 'results'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api', 'results'])]
    private ?Prompt $prompt = null;

    #[ORM\ManyToOne(inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api', 'results'])]
    private ?Metric $metric = null;

    #[ORM\ManyToOne(inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api', 'results'])]
    private ?Model $model = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api', 'results'])]
    private ?string $actualOutput = null;

    #[ORM\Column]
    #[Groups(['api', 'results'])]
    private ?float $score = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api', 'results'])]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api', 'results'])]
    private ?string $logs = null;

    #[ORM\ManyToOne(inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api', 'results'])]
    private ?Benchmark $benchmark = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrompt(): ?Prompt
    {
        return $this->prompt;
    }

    public function setPrompt(?Prompt $prompt): static
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function getMetric(): ?Metric
    {
        return $this->metric;
    }

    public function setMetric(?Metric $metric): static
    {
        $this->metric = $metric;

        return $this;
    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function setModel(?Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getActualOutput(): ?string
    {
        return $this->actualOutput;
    }

    public function setActualOutput(string $actualOutput): static
    {
        $this->actualOutput = $actualOutput;

        return $this;
    }

    public function getScore(): ?float
    {
        return $this->score;
    }

    public function setScore(float $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getLogs(): ?string
    {
        return $this->logs;
    }

    public function setLogs(string $logs): static
    {
        $this->logs = $logs;

        return $this;
    }

    public function getBenchmark(): ?Benchmark
    {
        return $this->benchmark;
    }

    public function setBenchmark(?Benchmark $benchmark): static
    {
        $this->benchmark = $benchmark;

        return $this;
    }
}
