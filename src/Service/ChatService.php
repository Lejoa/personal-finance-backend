<?php

namespace App\Service;

use App\DTO\ChatRequestDTO;
use App\DTO\ChatResponseDTO;
use App\DTO\LlmChatRequestDTO;
use App\Entity\ChatConversation;
use App\Entity\ChatMessage;
use App\Entity\User;
use App\Repository\ChatConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly FinancialContextService $contextService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatConversationRepository $conversationRepository,
        private readonly string $llmServiceUrl
    ) {}

    /**
     * Processes a user message and returns the LLM response.
     */
    public function processMessage(ChatRequestDTO $dto, User $user): ChatResponseDTO
    {
        $conversation = $this->getOrCreateConversation($dto, $user);

        // Persist user message
        $userMessage = new ChatMessage();
        $userMessage->setContent($dto->message);
        $userMessage->setRole('user');
        $conversation->addMessage($userMessage);
        $conversation->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($userMessage);
        $this->entityManager->flush();

        // Build financial context and call LLM
        $context = $this->contextService->buildContext($user);
        $llmRequest = new LlmChatRequestDTO(
            message: $dto->message,
            userContext: $context['userContext'],
            financialSummary: $context['summary'],
            categories: $context['categories'],
            budgets: $context['budgets']
        );

        $llmResponse = $this->callLlmService($llmRequest);

        // Persist assistant message
        $assistantMessage = new ChatMessage();
        $assistantMessage->setContent($llmResponse['message']);
        $assistantMessage->setRole('assistant');
        $assistantMessage->setMetadata($llmResponse['metadata'] ?? null);
        $conversation->addMessage($assistantMessage);
        $conversation->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($assistantMessage);
        $this->entityManager->flush();

        return new ChatResponseDTO(
            id: $assistantMessage->getId(),
            message: $llmResponse['message'],
            role: 'assistant',
            timestamp: $assistantMessage->getCreatedAt()->format('c'),
            conversationId: $conversation->getId(),
            metadata: $llmResponse['metadata'] ?? null
        );
    }

    /**
     * Returns all conversations for the given user.
     */
    public function getUserConversations(User $user): array
    {
        return $this->conversationRepository->findByUser($user);
    }

    /**
     * Returns a conversation with its messages if it belongs to the user.
     */
    public function getConversation(int $id, User $user): ?ChatConversation
    {
        return $this->conversationRepository->findOneByIdAndUser($id, $user);
    }

    /**
     * Deletes a conversation and all its messages.
     */
    public function deleteConversation(ChatConversation $conversation): void
    {
        $this->entityManager->remove($conversation);
        $this->entityManager->flush();
    }

    /**
     * Retrieves an existing conversation or creates a new one.
     */
    private function getOrCreateConversation(ChatRequestDTO $dto, User $user): ChatConversation
    {
        if ($dto->conversationId !== null) {
            $conversation = $this->conversationRepository->findOneByIdAndUser($dto->conversationId, $user);
            if ($conversation !== null) {
                return $conversation;
            }
        }

        $conversation = new ChatConversation();
        $conversation->setUser($user);
        $conversation->setTitle($this->generateTitle($dto->message));

        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        return $conversation;
    }

    /**
     * Generates a conversation title from the first message.
     */
    private function generateTitle(string $message): string
    {
        return mb_strlen($message) > 50
            ? mb_substr($message, 0, 50) . '...'
            : $message;
    }

    /**
     * Calls the LLM Service and returns the response.
     */
    private function callLlmService(LlmChatRequestDTO $request): array
    {
        $response = $this->httpClient->request('POST', $this->llmServiceUrl . '/llm/chat', [
            'json' => $request->toArray(),
            'timeout' => 120,
            'max_duration' => 120,
        ]);

        return $response->toArray();
    }
}