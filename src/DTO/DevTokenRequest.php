<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class DevTokenRequest
{
    #[Assert\NotBlank(
        message: 'El email es obligatorio.'
    )]
    #[Assert\Email(
        message: 'El email "{{ value }}" no es válido.'
    )]
    public ?string $email = null;

    #[Assert\Length(
        max: 255,
        maxMessage: 'El nombre no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $name = null;
}
