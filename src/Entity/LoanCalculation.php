<?php

namespace App\Entity;

use App\Repository\LoanCalculationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoanCalculationRepository::class)]
#[ORM\Table(name: 'loan_calculations')]
class LoanCalculation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(type: Types::FLOAT)]
    private float $amount = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $annualRate = 0.0;

    #[ORM\Column]
    private int $termMonths = 0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $extraPayment = 0.0;

    #[ORM\Column(length: 20)]
    private string $frequency = 'monthly';

    #[ORM\Column(type: Types::FLOAT)]
    private float $periodicPayment = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $totalInterest = 0.0;

    #[ORM\Column(type: Types::FLOAT)]
    private float $totalPaid = 0.0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getAnnualRate(): float
    {
        return $this->annualRate;
    }

    public function setAnnualRate(float $annualRate): static
    {
        $this->annualRate = $annualRate;

        return $this;
    }

    public function getTermMonths(): int
    {
        return $this->termMonths;
    }

    public function setTermMonths(int $termMonths): static
    {
        $this->termMonths = $termMonths;

        return $this;
    }

    public function getExtraPayment(): float
    {
        return $this->extraPayment;
    }

    public function setExtraPayment(float $extraPayment): static
    {
        $this->extraPayment = $extraPayment;

        return $this;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getPeriodicPayment(): float
    {
        return $this->periodicPayment;
    }

    public function setPeriodicPayment(float $periodicPayment): static
    {
        $this->periodicPayment = $periodicPayment;

        return $this;
    }

    public function getTotalInterest(): float
    {
        return $this->totalInterest;
    }

    public function setTotalInterest(float $totalInterest): static
    {
        $this->totalInterest = $totalInterest;

        return $this;
    }

    public function getTotalPaid(): float
    {
        return $this->totalPaid;
    }

    public function setTotalPaid(float $totalPaid): static
    {
        $this->totalPaid = $totalPaid;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
