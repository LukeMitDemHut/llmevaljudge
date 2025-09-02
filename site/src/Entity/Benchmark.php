<?php

namespace App\Entity;

use App\Repository\BenchmarkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: BenchmarkRepository::class)]
class Benchmark
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api', 'benchmarks'])]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api', 'benchmarks'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api', 'benchmarks'])]
    private ?\DateTimeImmutable $finishedAt = null;

    /**
     * @var Collection<int, TestCase>
     */
    #[ORM\ManyToMany(targetEntity: TestCase::class, inversedBy: 'benchmarks')]
    #[Groups(['api', 'benchmarks'])]
    private Collection $testCases;

    /**
     * @var Collection<int, Metric>
     */
    #[ORM\ManyToMany(targetEntity: Metric::class, inversedBy: 'benchmarks')]
    #[Groups(['api', 'benchmarks'])]
    private Collection $metrics;

    /**
     * @var Collection<int, Model>
     */
    #[ORM\ManyToMany(targetEntity: Model::class, inversedBy: 'benchmarks')]
    #[Groups(['api', 'benchmarks'])]
    private Collection $models;

    #[ORM\Column(length: 255)]
    #[Groups(['api', 'benchmarks'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['api', 'benchmarks'])]
    private ?array $errors = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Groups(['api', 'benchmarks'])]
    private ?int $progress = null;

    /**
     * @var Collection<int, Result>
     */
    #[ORM\OneToMany(targetEntity: Result::class, mappedBy: 'benchmark', orphanRemoval: true)]
    private Collection $results;

    public function __construct()
    {
        $this->testCases = new ArrayCollection();
        $this->metrics = new ArrayCollection();
        $this->models = new ArrayCollection();
        $this->results = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeImmutable $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    /**
     * @return Collection<int, TestCase>
     */
    public function getTestCases(): Collection
    {
        return $this->testCases;
    }

    public function addTestCase(TestCase $testCase): static
    {
        if (!$this->testCases->contains($testCase)) {
            $this->testCases->add($testCase);
        }

        return $this;
    }

    public function removeTestCase(TestCase $testCase): static
    {
        $this->testCases->removeElement($testCase);

        return $this;
    }

    /**
     * @return Collection<int, Metric>
     */
    public function getMetrics(): Collection
    {
        return $this->metrics;
    }

    public function addMetric(Metric $metric): static
    {
        if (!$this->metrics->contains($metric)) {
            $this->metrics->add($metric);
        }

        return $this;
    }

    public function removeMetric(Metric $metric): static
    {
        $this->metrics->removeElement($metric);

        return $this;
    }

    /**
     * @return Collection<int, Model>
     */
    public function getModels(): Collection
    {
        return $this->models;
    }

    public function addModel(Model $model): static
    {
        if (!$this->models->contains($model)) {
            $this->models->add($model);
        }

        return $this;
    }

    public function removeModel(Model $model): static
    {
        $this->models->removeElement($model);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getErrors(): ?array
    {
        return $this->errors ?? [];
    }

    public function setErrors(?array $errors): static
    {
        $this->errors = $errors;

        return $this;
    }

    public function addError(string $error): static
    {
        if ($this->errors === null) {
            $this->errors = [];
        }

        $this->errors[] = [
            'message' => $error,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        ];

        return $this;
    }

    public function clearErrors(): static
    {
        $this->errors = null;

        return $this;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getProgress(): ?int
    {
        return $this->progress;
    }

    public function setProgress(?int $progress): static
    {
        $this->progress = $progress;

        return $this;
    }

    public function resetProgress(): static
    {
        $this->progress = null;

        return $this;
    }

    /**
     * @return Collection<int, Result>
     */
    public function getResults(): Collection
    {
        return $this->results;
    }

    public function addResult(Result $result): static
    {
        if (!$this->results->contains($result)) {
            $this->results->add($result);
            $result->setBenchmark($this);
        }

        return $this;
    }

    public function removeResult(Result $result): static
    {
        if ($this->results->removeElement($result)) {
            // set the owning side to null (unless already changed)
            if ($result->getBenchmark() === $this) {
                $result->setBenchmark(null);
            }
        }

        return $this;
    }
}
