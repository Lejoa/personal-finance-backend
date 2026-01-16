<?php

namespace App\DTO;

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
}
