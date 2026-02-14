<?php

namespace App\DTO;

class ChatResponseDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $message,
        public readonly string $role,
        public readonly string $timestamp,
        public readonly int $conversationId,
        public readonly ?array $metadata = null
    ) {}
}