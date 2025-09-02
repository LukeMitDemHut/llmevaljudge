<?php

namespace App\Entity;

use App\Repository\ModelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ModelRepository::class)]
class Model
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['settings', 'api', 'metrics', 'results', 'benchmarks'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'models')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['settings', 'api', 'results'])]
    private ?Provider $provider = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['settings', 'api'])]
    private ?float $inputPrice = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['settings', 'api'])]
    private ?float $outputPrice = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['settings', 'api'])]
    private ?float $requestPrice = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['settings', 'api'])]
    private ?float $reasonPrice = null;

    #[ORM\Column(length: 255)]
    #[Groups(['settings', 'api', 'metrics', 'results', 'benchmarks'])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(['settings', 'api'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, Metric>
     */
    #[ORM\OneToMany(targetEntity: Metric::class, mappedBy: 'ratingModel', orphanRemoval: true)]
    private Collection $metrics;

    /**
     * @var Collection<int, Benchmark>
     */
    #[ORM\ManyToMany(targetEntity: Benchmark::class, mappedBy: 'models')]
    private Collection $benchmarks;

    /**
     * @var Collection<int, Result>
     */
    #[ORM\OneToMany(targetEntity: Result::class, mappedBy: 'model', orphanRemoval: true)]
    private Collection $results;

    public function __construct()
    {
        $this->metrics = new ArrayCollection();
        $this->benchmarks = new ArrayCollection();
        $this->results = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function setProvider(?Provider $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getInputPrice(): ?float
    {
        return $this->inputPrice;
    }

    public function setInputPrice(?float $inputPrice): static
    {
        $this->inputPrice = $inputPrice;

        return $this;
    }

    public function getOutputPrice(): ?float
    {
        return $this->outputPrice;
    }

    public function setOutputPrice(?float $outputPrice): static
    {
        $this->outputPrice = $outputPrice;

        return $this;
    }

    public function getRequestPrice(): ?float
    {
        return $this->requestPrice;
    }

    public function setRequestPrice(?float $requestPrice): static
    {
        $this->requestPrice = $requestPrice;

        return $this;
    }

    public function getReasonPrice(): ?float
    {
        return $this->reasonPrice;
    }

    public function setReasonPrice(?float $reasonPrice): static
    {
        $this->reasonPrice = $reasonPrice;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

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
            $metric->setRatingModel($this);
        }

        return $this;
    }

    public function removeMetric(Metric $metric): static
    {
        if ($this->metrics->removeElement($metric)) {
            // set the owning side to null (unless already changed)
            if ($metric->getRatingModel() === $this) {
                $metric->setRatingModel(null);
            }
        }

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
            $benchmark->addModel($this);
        }

        return $this;
    }

    public function removeBenchmark(Benchmark $benchmark): static
    {
        if ($this->benchmarks->removeElement($benchmark)) {
            $benchmark->removeModel($this);
        }

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
            $result->setModel($this);
        }

        return $this;
    }

    public function removeResult(Result $result): static
    {
        if ($this->results->removeElement($result)) {
            // set the owning side to null (unless already changed)
            if ($result->getModel() === $this) {
                $result->setModel(null);
            }
        }

        return $this;
    }
}
