<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateLoanCalculationRequest
{
    #[Assert\NotBlank(message: 'El nombre es obligatorio.')]
    #[Assert\Length(max: 100, maxMessage: 'El nombre no puede superar 100 caracteres.')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'El monto es obligatorio.')]
    #[Assert\Positive(message: 'El monto debe ser mayor a cero.')]
    public ?float $amount = null;

    #[Assert\NotBlank(message: 'La tasa de interés es obligatoria.')]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'La tasa debe estar entre 0 y 100.')]
    public ?float $annualRate = null;

    #[Assert\NotBlank(message: 'El plazo es obligatorio.')]
    #[Assert\Range(min: 1, max: 360, notInRangeMessage: 'El plazo debe estar entre 1 y 360 meses.')]
    public ?int $termMonths = null;

    #[Assert\PositiveOrZero(message: 'El pago extra no puede ser negativo.')]
    public float $extraPayment = 0.0;

    #[Assert\Choice(choices: ['monthly', 'biweekly', 'weekly'], message: 'Frecuencia inválida.')]
    public string $frequency = 'monthly';

    #[Assert\NotBlank(message: 'El pago periódico es obligatorio.')]
    #[Assert\Positive(message: 'El pago periódico debe ser mayor a cero.')]
    public ?float $periodicPayment = null;

    #[Assert\NotBlank(message: 'El interés total es obligatorio.')]
    #[Assert\PositiveOrZero(message: 'El interés total no puede ser negativo.')]
    public ?float $totalInterest = null;

    #[Assert\NotBlank(message: 'El total pagado es obligatorio.')]
    #[Assert\Positive(message: 'El total pagado debe ser mayor a cero.')]
    public ?float $totalPaid = null;
}
