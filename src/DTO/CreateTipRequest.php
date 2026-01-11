<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateTipRequest
{
    #[Assert\NotBlank(
        message: 'El título es obligatorio.'
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: 'El título no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $title = null;

    #[Assert\NotBlank(
        message: 'El autor es obligatorio.'
    )]
    #[Assert\Length(
        max: 255,
        maxMessage: 'El autor no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $author = null;

    #[Assert\NotBlank(
        message: 'La descripción es obligatoria.'
    )]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'La descripción no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $description = null;
}