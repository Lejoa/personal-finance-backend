<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCategoryRequest
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
        message: 'La descripción es obligatoria.'
    )]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La descripción no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $description = null;

    #[Assert\NotBlank(
        message: 'El tipo es obligatorio.'
    )]
    #[Assert\Choice(
        choices: ['gasto', 'ingreso'],
        message: 'El tipo debe ser "gasto" o "ingreso".'
    )]
    public ?string $type = null;
}