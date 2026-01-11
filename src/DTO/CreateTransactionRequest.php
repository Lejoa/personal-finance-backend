<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateTransactionRequest
{
    #[Assert\NotBlank(
        message: 'El nombre es obligatorio.'
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: 'El nombre no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $name = null;

    #[Assert\NotBlank(
        message: 'El tipo es obligatorio.'
    )]
    #[Assert\Choice(
        choices: ['ingreso', 'gasto'],
        message: 'El tipo debe ser "ingreso" o "gasto".'
    )]
    public ?string $type = null;

    #[Assert\NotBlank(
        message: 'El monto es obligatorio.'
    )]
    #[Assert\Positive(
        message: 'El monto debe ser mayor a cero'
    )]
    #[Assert\GreaterThan(
        value: 0,
        message: 'El monto debe ser mayor a cero.'
    )]
    public ?float $amount = null;

    #[Assert\NotBlank(
        message: 'La fecha es obligatoria.'
    )]
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

}