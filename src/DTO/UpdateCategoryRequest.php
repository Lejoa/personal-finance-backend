<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateCategoryRequest
{
    #[Assert\Length(
        max: 255,
        maxMessage: 'El nombre no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $name = null;

    #[Assert\Length(
        max: 1000,
        maxMessage: 'La descripción no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $description = null;
}
