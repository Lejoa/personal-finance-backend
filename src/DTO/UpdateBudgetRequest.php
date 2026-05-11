<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateBudgetRequest
{
    #[Assert\Date(message: 'La fecha de inicio debe tener formato YYYY-MM-DD.')]
    public ?string $startDate = null;

    #[Assert\Date(message: 'La fecha de fin debe tener formato YYYY-MM-DD.')]
    public ?string $endDate = null;

    /**
     * Array de objetos con estructura: [{"categoryId": 1, "amount": 100.50}, ...].
     *
     * @var array<int, array{categoryId: int, amount: float}>|null
     */
    #[Assert\Type(type: 'array', message: 'Las categorías deben ser un array.')]
    #[Assert\Count(
        min: 1,
        minMessage: 'Debe incluir al menos una categoría.'
    )]
    #[Assert\All([
        new Assert\Collection([
            'fields' => [
                'categoryId' => [
                    new Assert\NotBlank(message: 'El ID de categoría es obligatorio.'),
                    new Assert\Type(type: 'integer', message: 'El ID de categoría debe ser un número entero.'),
                ],
                'amount' => [
                    new Assert\NotBlank(message: 'El monto es obligatorio.'),
                    new Assert\Type(type: 'numeric', message: 'El monto debe ser un número.'),
                    new Assert\Positive(message: 'El monto debe ser mayor a 0.'),
                ],
            ],
            'allowExtraFields' => false,
            'allowMissingFields' => false,
        ]),
    ])]
    public ?array $categories = null;
}
