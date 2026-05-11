<?php

namespace App\DTO;

class LlmChatRequestDTO
{
    public function __construct(
        public readonly string $message,
        public readonly array $userContext,
        public readonly array $financialSummary,
        public readonly array $categories,
        public readonly array $budgets,
        public readonly string $additionalContext = '',
        public readonly string $contextType = 'none'
    ) {
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'user_context' => $this->userContext,
            'financial_summary' => $this->financialSummary,
            'categories' => $this->categories,
            'budgets' => $this->budgets,
            'additional_context' => $this->additionalContext,
            'context_type' => $this->contextType,
        ];
    }
}
