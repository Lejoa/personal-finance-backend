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

    #[Assert\Choice(
        choices: ['pending', 'done', 'rejected'],
        message: 'El estado debe ser "pending", "done" o "rejected".'
    )]
    public ?string $synchronized = null;

    /**
     * Origen de la transacción. 'manual' por defecto; 'sms' cuando proviene
     * de la lectura automática del buzón de SMS del dispositivo Android;
     * 'chat' cuando es registrada por el asistente financiero conversacional.
     */
    #[Assert\Choice(
        choices: ['manual', 'sms', 'chat'],
        message: 'El origen debe ser "manual", "sms" o "chat".'
    )]
    public ?string $source = 'manual';
}
