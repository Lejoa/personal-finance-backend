<?php

namespace App\DTO;

use App\Entity\Transaction;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateTransactionRequest
{
    #[Assert\Length(
        max: 255,
        maxMessage: 'El nombre no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $name = null;

    #[Assert\Choice(
        choices: ['ingreso', 'gasto'],
        message: 'El tipo debe ser "ingreso" o "gasto".'
    )]
    public ?string $type = null;

    #[Assert\Positive(
        message: 'El monto debe ser mayor a cero'
    )]
    #[Assert\GreaterThan(
        value: 0,
        message: 'El monto debe ser mayor a cero.'
    )]
    public ?float $amount = null;

    #[Assert\LessThanOrEqual(
        'today',
        message: 'La fecha no puede ser futura.'
    )]
    public ?string $date = null;

    #[Assert\Length(
        max: 500,
        maxMessage: 'La descripción no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $note = null;

    #[Assert\Positive(message: 'El ID de categoría debe ser positivo')]
    public ?int $categoryId = null;

    #[Assert\Choice(
        choices: ['pending', 'done', 'rejected'],
        message: 'El estado debe ser "pending", "done" o "rejected".'
    )]
    public ?string $synchronized = null;

    /**
     * Applies all non-null scalar fields to the given Transaction entity.
     * categoryId is excluded because it requires a repository lookup — that
     * responsibility stays in the service layer.
     * Fields left as null are not modified on the entity.
     */
    public function applyTo(Transaction $transaction): void
    {
        if ($this->name !== null)         { $transaction->setName($this->name); }
        if ($this->type !== null)         { $transaction->setType($this->type); }
        if ($this->amount !== null)       { $transaction->setAmount($this->amount); }
        if ($this->date !== null)         { $transaction->setDate(new \DateTime($this->date)); }
        if ($this->note !== null)         { $transaction->setNote($this->note); }
        if ($this->synchronized !== null) { $transaction->setSynchronized($this->synchronized); }
    }
}
