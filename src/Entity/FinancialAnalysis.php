<?php

namespace App\Entity;

use App\Repository\FinancialAnalysisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FinancialAnalysisRepository::class)]
#[ORM\Table(name: 'financial_analyses')]
#[ORM\UniqueConstraint(name: 'uq_analysis_user_period_checkpoint', columns: ['user_id', 'period', 'checkpoint'])]
class FinancialAnalysis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 7)]
    private ?string $period = null;

    #[ORM\Column(length: 10)]
    private ?string $checkpoint = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $generatedAt = null;

    public function __construct()
    {
        $this->generatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPeriod(): ?string
    {
        return $this->period;
    }

    public function setPeriod(string $period): static
    {
        $this->period = $period;
        return $this;
    }

    public function getCheckpoint(): ?string
    {
        return $this->checkpoint;
    }

    public function setCheckpoint(string $checkpoint): static
    {
        $this->checkpoint = $checkpoint;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }
}