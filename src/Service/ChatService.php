<?php

namespace App\Service;

use App\DTO\ChatRequestDTO;
use App\DTO\ChatResponseDTO;
use App\DTO\CreateTransactionRequest;
use App\DTO\LlmChatRequestDTO;
use App\Entity\ChatConversation;
use App\Entity\ChatMessage;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ChatConversationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ChatService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly FinancialContextService $contextService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatConversationRepository $conversationRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TransactionService $transactionService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
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
        $context     = $this->contextService->buildContext($user);
        $contextType = $this->classifyContextNeeds($dto->message);
        $additionalContext = $this->contextService->buildAdditionalContext($user, $contextType);

        $llmRequest = new LlmChatRequestDTO(
            message: $dto->message,
            userContext: $context['userContext'],
            financialSummary: $context['summary'],
            categories: $context['categories'],
            budgets: $context['budgets'],
            additionalContext: $additionalContext,
            contextType: $contextType
        );

        $llmResponse = $this->callLlmService($llmRequest);
        $this->logger->info('LLM response keys: ' . implode(', ', array_keys($llmResponse)));
        $this->logger->info('LLM transaction_action raw: ' . json_encode($llmResponse['transaction_action'] ?? 'KEY_NOT_FOUND'));
        // Enrich metadata with transaction result if the LLM detected one
        $metadata = $llmResponse['metadata'] ?? [];
        $transactionAction = $llmResponse['transaction_action'] ?? null;

        if ($transactionAction !== null) {
            $transactionMetadata = $this->processTransactionAction($transactionAction, $user);
            dump(['transaction_metadata' => $transactionMetadata]);
            $metadata = array_merge($metadata, $transactionMetadata);
        }

        // Persist assistant message with enriched metadata
        $assistantMessage = new ChatMessage();
        $assistantMessage->setContent($llmResponse['message']);
        $assistantMessage->setRole('assistant');
        $assistantMessage->setMetadata($metadata ?: null);
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
            metadata: $metadata ?: null
        );
    }

    /**
     * Orchestrates the transaction creation flow from an LLM-detected action.
     * Returns either transaction_created or pending_categorization metadata.
     */
    private function processTransactionAction(array $transactionAction, User $user): array
    {
        $dto = $this->buildTransactionDto($transactionAction);
        if (!$dto) {
            return []; // Invalid data, skip transaction processing
        }

        try {
            $transaction = $this->transactionService->createTransaction($dto, $user);
            $suggestedCategory = $this->resolveCategory($transactionAction['category_name'] ?? null, $dto->type);

            return $this->buildPendingCategorizationMetadata($transaction, $dto->type, $suggestedCategory);
        } catch (\Exception $e) {
            $this->logger->error('processTransactionAction failed: ' . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'transaction_action' => $transactionAction,
            ]);
            return [];
        }
    }

    /**
     * Builds and validates a CreateTransactionRequest from  the LLM action data.
     * Returns null if validation fails.
     */
    private function buildTransactionDto(array $transactionAction): ?CreateTransactionRequest
    {
        $dto = new CreateTransactionRequest();
        $dto->name   = $transactionAction['name']   ?? null;
        $dto->type   = $transactionAction['type']   ?? null;
        $dto->amount = isset($transactionAction['amount']) ? (float) $transactionAction['amount'] : null;
        $dto->date   = $transactionAction['date']   ?? date('Y-m-d');
        $dto->source = 'chat';

        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            $this->logger->error('buildTransactionDto validation failed: ' . (string) $errors, [
                'dto' => (array) $dto,
            ]);
            return null;
        }

        return $dto;
    }


    /**
     * Looks up a category entity by name and type.
     * Returns null if the name is absent, equals 'Otros', or no match is found.
     */
    private function resolveCategory(?string $categoryName, string $transactionType):  ?\App\Entity\Category
    {
        if (!$categoryName || $categoryName === 'Otros') {
            return null;
        }

        return $this->categoryRepository->findOneBy([
            'name' => $categoryName,
            'type' => $transactionType
        ]);
    }

    /**
     * Builds the transaction_created metadata payload.
     */
    private function buildTransactionCreatedMetadata(
        \App\Entity\Transaction $transaction,
        \App\Entity\Category $category
    ): array {
        return [
            'transaction_created' => [
                'id' => $transaction->getId(),
                'name' => $transaction->getName(),
                'type' => $transaction->getType(),
                'amount' => $transaction->getAmount(),
                'date' => $transaction->getDate()->format('Y-m-d'),
                'categoryId' => $category->getId(),
                'categoryName' => $category->getName(),
            ],
        ];
    }


    /**
     * Builds the pending_categorization metadata payload with the list of
     * available categories so the user can pick one from the chat.
     */
    private function buildPendingCategorizationMetadata(
        \App\Entity\Transaction $transaction,
        string $transactionType,
        ?\App\Entity\Category $suggestedCategory = null
    ): array {
        $available = $this->categoryRepository->findBy(['type' => $transactionType]);

        $suggested = $suggestedCategory !== null 
            ? ['id' => $suggestedCategory->getId(), 'name' => $suggestedCategory->getName()]
            : null;

        $otherCategories = array_values(array_filter(
            $available,
            fn($cat) => $suggestedCategory === null || $cat->getId() !== $suggestedCategory->getId()
        ));

        return [
            'pending_categorization' => [
                'transaction_id'     => $transaction->getId(),
                'name'               => $transaction->getName(),
                'type'               => $transaction->getType(),
                'amount'             => $transaction->getAmount(),
                'date'               => (new \DateTime())->format('Y-m-d'),
                'suggested_category' => $suggested,
                'categories'         => array_map(
                    fn($cat) => ['id' => $cat->getId(), 'name' => $cat->getName()],
                    $otherCategories
                ),
            ],
        ];
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
     * Calls the LLM Service classify-context endpoint.
     * Validates Safety (ToxicLanguage + DetectPII) and classifies the context type.
     * Throws \RuntimeException with the guardrails detail on HTTP 422.
     * Falls back to "none" on network or classification errors so the main flow is never blocked.
     */
    private function classifyContextNeeds(string $message): string
    {
        try {
            $response = $this->httpClient->request('POST', $this->llmServiceUrl . '/llm/classify-context', [
                'json'         => ['message' => $message],
                'timeout'      => 15,
                'max_duration' => 15,
            ]);

            // Propagate Safety rejections (422) — do not swallow them
            if ($response->getStatusCode() === 422) {
                $detail = $response->toArray(false);
                throw new \RuntimeException(
                    $detail['detail']['message'] ?? 'Message rejected by safety guardrails.',
                    422
                );
            }

            $data = $response->toArray();
            return $data['context_type'] ?? 'none';
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->warning('Context classification failed, defaulting to none', [
                'error' => $e->getMessage(),
            ]);
            return 'none';
        }
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