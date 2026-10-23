<?php

namespace App\Entity;

use App\Repository\TestCaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: TestCaseRepository::class)]
class TestCase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api', 'test_cases', 'test_cases_summary', 'benchmarks'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['api', 'test_cases', 'test_cases_summary', 'benchmarks', 'results'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api', 'test_cases', 'test_cases_summary', 'benchmarks'])]
    private ?string $description = null;

    /**
     * @var Collection<int, Prompt>
     */
    #[ORM\OneToMany(targetEntity: Prompt::class, mappedBy: 'testCase', orphanRemoval: true)]
    #[Groups(['api', 'test_cases'])]
    private Collection $prompts;

    /**
     * Metrics this test case is paired with (inverse side; owning side is Metric).
     *
     * @var Collection<int, Metric>
     */
    #[ORM\ManyToMany(targetEntity: Metric::class, mappedBy: 'testCases')]
    private Collection $metrics;

    public function __construct()
    {
        $this->prompts = new ArrayCollection();
        $this->metrics = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @return Collection<int, Prompt>
     */
    public function getPrompts(): Collection
    {
        return $this->prompts;
    }

    public function addPrompt(Prompt $prompt): static
    {
        if (!$this->prompts->contains($prompt)) {
            $this->prompts->add($prompt);
            $prompt->setTestCase($this);
        }

        return $this;
    }

    public function removePrompt(Prompt $prompt): static
    {
        if ($this->prompts->removeElement($prompt)) {
            // set the owning side to null (unless already changed)
            if ($prompt->getTestCase() === $this) {
                $prompt->setTestCase(null);
            }
        }

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
            $metric->addTestCase($this);
        }

        return $this;
    }

    public function removeMetric(Metric $metric): static
    {
        if ($this->metrics->removeElement($metric)) {
            $metric->removeTestCase($this);
        }

        return $this;
    }

    #[Groups(['test_cases_summary'])]
    public function getPromptCount(): int
    {
        return $this->prompts->count();
    }
}
