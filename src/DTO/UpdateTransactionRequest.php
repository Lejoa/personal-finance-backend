<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateTransactionRequest
{
    #[Assert\Length(
        max: 255,
        maxMessage: 'El nombre no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $nombre = null;

    #[Assert\Choice(
        choices: ['ingreso', 'gasto'],
        message: 'El tipo debe ser "ingreso" o "gasto".'
    )]
    public ?string $tipo = null;

    #[Assert\Positive(
        message: 'El monto debe ser mayor a cero'
    )]
    #[Assert\GreaterThan(
        value: 0,
        message: 'El monto debe ser mayor a cero.'
    )]
    public ?float $monto = null;

    #[Assert\LessThanOrEqual(
        'today',
        message: 'La fecha no puede ser futura.'
    )]
    public ?string $fecha = null;

    #[Assert\Length(
        max: 500,
        maxMessage: 'La descripción no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $nota = null;
}
