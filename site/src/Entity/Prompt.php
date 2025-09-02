<?php

namespace App\Entity;

use App\Repository\PromptRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PromptRepository::class)]
class Prompt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api', 'prompts', 'test_cases', 'results'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['api', 'prompts', 'test_cases', 'results'])]
    private ?string $input = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api', 'prompts', 'test_cases', 'results'])]
    private ?string $expectedOutput = null;

    #[ORM\ManyToOne(inversedBy: 'prompts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api', 'prompts', 'results'])]
    private ?TestCase $testCase = null;

    /**
     * @var Collection<int, Result>
     */
    #[ORM\OneToMany(targetEntity: Result::class, mappedBy: 'prompt', orphanRemoval: true)]
    private Collection $results;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['api', 'prompts', 'test_cases', 'results'])]
    private ?string $context = null;

    public function __construct()
    {
        $this->results = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInput(): ?string
    {
        return $this->input;
    }

    public function setInput(string $input): static
    {
        $this->input = $input;

        return $this;
    }

    public function getExpectedOutput(): ?string
    {
        return $this->expectedOutput;
    }

    public function setExpectedOutput(string $expectedOutput): static
    {
        $this->expectedOutput = $expectedOutput;

        return $this;
    }

    public function getTestCase(): ?TestCase
    {
        return $this->testCase;
    }

    public function setTestCase(?TestCase $testCase): static
    {
        $this->testCase = $testCase;

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
            $result->setPrompt($this);
        }

        return $this;
    }

    public function removeResult(Result $result): static
    {
        if ($this->results->removeElement($result)) {
            // set the owning side to null (unless already changed)
            if ($result->getPrompt() === $this) {
                $result->setPrompt(null);
            }
        }

        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): static
    {
        $this->context = $context;

        return $this;
    }
}
