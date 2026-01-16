<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'El nombre es obligatorio')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'El nombre no puede exceder {{ limit }} caracteres'
    )]
    private ?string $name = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: 'El tipo es obligatorio')]
    #[Assert\Choice(
        choices: ['ingreso', 'gasto'],
        message: 'El tipo debe ser "ingreso" o "gasto"'
    )]
    private ?string $type = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotBlank(message: 'El monto es obligatorio')]
    #[Assert\Positive(message: 'El monto debe ser mayor a cero')]
    #[Assert\GreaterThan(value: 0, message: 'El monto debe ser mayor a cero')]
    private ?float $amount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La fecha es obligatoria')]
    #[Assert\LessThanOrEqual(
        'today',
        message: 'La fecha no puede ser futura'
    )]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'El usuario es obligatorio')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(length: 10)]
    private string $synchronized = 'pending';

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->synchronized = 'pending';
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;
        return $this;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
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

    public function getSynchronized(): string
    {
        return $this->synchronized;
    }

    public function setSynchronized(string $synchronized): static
    {
        $this->synchronized = $synchronized;
        return $this;
    }
}
