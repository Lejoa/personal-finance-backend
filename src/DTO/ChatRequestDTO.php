<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ChatRequestDTO
{
    #[Assert\NotBlank(
        message: 'El mensaje es obligatorio.'
    )]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'El mensaje no puede tener más de {{ limit }} caracteres.'
    )]
    public ?string $message = null;

    public ?int $conversationId = null;
}
