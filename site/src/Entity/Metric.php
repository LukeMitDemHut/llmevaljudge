<?php

namespace App\Entity;

use App\Enum\MetricParam;
use App\Enum\MetricType;
use App\Repository\MetricRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MetricRepository::class)]
class Metric
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api', 'metrics', 'results', 'benchmarks'])]
    private ?int $id = null;

    #[ORM\Column(enumType: MetricType::class)]
    #[Groups(['api', 'metrics', 'results', 'benchmarks'])]
    private ?MetricType $type = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api', 'metrics', 'results', 'benchmarks'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api', 'metrics'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['api', 'metrics'])]
    private array $definition = [];

    #[ORM\Column]
    #[Groups(['api', 'metrics'])]
    private ?float $threshold = null;

    #[ORM\ManyToOne(inversedBy: 'metrics')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api', 'metrics'])]
    private ?Model $ratingModel = null;

    /**
     * @var Collection<int, Benchmark>
     */
    #[ORM\ManyToMany(targetEntity: Benchmark::class, mappedBy: 'metrics')]
    private Collection $benchmarks;

    /**
     * Test cases this metric applies to. Owning side of the metric<->test-case pairing.
     * An empty collection means "all test cases" (implicit fallback, resolved by
     * MetricTestCaseResolver).
     *
     * @var Collection<int, TestCase>
     */
    #[ORM\ManyToMany(targetEntity: TestCase::class, inversedBy: 'metrics')]
    #[ORM\JoinTable(name: 'metric_test_case')]
    #[ORM\JoinColumn(name: 'metric_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'test_case_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $testCases;

    /**
     * @var Collection<int, Result>
     */
    #[ORM\OneToMany(targetEntity: Result::class, mappedBy: 'metric', orphanRemoval: true)]
    private Collection $results;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, enumType: MetricParam::class)]
    #[Groups(['api', 'metrics'])]
    private array $param = [];

    public function __construct()
    {
        $this->benchmarks = new ArrayCollection();
        $this->results = new ArrayCollection();
        $this->testCases = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?MetricType
    {
        return $this->type;
    }

    public function setType(MetricType $type): static
    {
        $this->type = $type;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDefinition(): array
    {
        return $this->definition;
    }

    public function setDefinition(array $definition): static
    {
        $this->definition = $definition;

        return $this;
    }

    public function getThreshold(): ?float
    {
        return $this->threshold;
    }

    public function setThreshold(float $threshold): static
    {
        $this->threshold = $threshold;

        return $this;
    }

    public function getRatingModel(): ?Model
    {
        return $this->ratingModel;
    }

    public function setRatingModel(?Model $ratingModel): static
    {
        $this->ratingModel = $ratingModel;

        return $this;
    }

    /**
     * @return Collection<int, Benchmark>
     */
    public function getBenchmarks(): Collection
    {
        return $this->benchmarks;
    }

    public function addBenchmark(Benchmark $benchmark): static
    {
        if (!$this->benchmarks->contains($benchmark)) {
            $this->benchmarks->add($benchmark);
            $benchmark->addMetric($this);
        }

        return $this;
    }

    public function removeBenchmark(Benchmark $benchmark): static
    {
        if ($this->benchmarks->removeElement($benchmark)) {
            $benchmark->removeMetric($this);
        }

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
            $testCase->addMetric($this);
        }

        return $this;
    }

    public function removeTestCase(TestCase $testCase): static
    {
        if ($this->testCases->removeElement($testCase)) {
            $testCase->removeMetric($this);
        }

        return $this;
    }

    /**
     * IDs of the explicitly assigned test cases. Empty array means "all test cases".
     * Serialized so benchmarks can expose the effective pairing through their metrics.
     */
    #[Groups(['api', 'metrics', 'benchmarks', 'metric_details'])]
    public function getTestCaseIds(): array
    {
        $ids = [];
        foreach ($this->testCases as $testCase) {
            if ($testCase->getId() !== null) {
                $ids[] = $testCase->getId();
            }
        }
        sort($ids);

        return $ids;
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
            $result->setMetric($this);
        }

        return $this;
    }

    public function removeResult(Result $result): static
    {
        if ($this->results->removeElement($result)) {
            // set the owning side to null (unless already changed)
            if ($result->getMetric() === $this) {
                $result->setMetric(null);
            }
        }

        return $this;
    }

    /**
     * @return MetricParam[]
     */
    public function getParam(): array
    {
        return $this->param;
    }

    public function setParam(array $param): static
    {
        $this->param = $param;

        return $this;
    }
}
