<?php

namespace App\Service;

use App\DTO\ChatRequestDTO;
use App\DTO\ChatResponseDTO;
use App\DTO\CreateTransactionRequest;
use App\DTO\LlmChatRequestDTO;
use App\Entity\Category;
use App\Entity\ChatConversation;
use App\Entity\ChatMessage;
use App\Entity\Transaction;
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
    ) {
    }

    /**
     * Processes a user message: persists it, calls the LLM, processes any detected
     * transaction action, persists the assistant reply, and returns a response DTO.
     */
    public function processMessage(ChatRequestDTO $dto, User $user): ChatResponseDTO
    {
        $conversation = $this->getOrCreateConversation($dto, $user);

        $userMessage = new ChatMessage();
        $userMessage->setContent($dto->message);
        $userMessage->setRole('user');
        $conversation->addMessage($userMessage);
        $conversation->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($userMessage);
        $this->entityManager->flush();

        $context           = $this->contextService->buildContext($user);
        $contextType       = $this->classifyContextNeeds($dto->message);
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

        $llmResponse       = $this->callLlmService($llmRequest);
        $metadata          = $llmResponse['metadata'] ?? [];
        $transactionAction = $llmResponse['transaction_action'] ?? null;

        if ($transactionAction !== null) {
            $transactionMetadata = $this->processTransactionAction($transactionAction, $user);
            $metadata            = array_merge($metadata, $transactionMetadata);
        }

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
     * Returns pending_categorization metadata on success, or an empty array on failure.
     */
    private function processTransactionAction(array $transactionAction, User $user): array
    {
        $dto = $this->buildTransactionDto($transactionAction);
        if (!$dto) {
            return [];
        }

        try {
            $transaction       = $this->transactionService->createTransaction($dto, $user);
            $suggestedCategory = $this->resolveCategory($transactionAction['category_name'] ?? null, $dto->type);

            return $this->buildPendingCategorizationMetadata($transaction, $dto->type, $suggestedCategory);
        } catch (\Exception $e) {
            $this->logger->error('processTransactionAction failed: ' . $e->getMessage(), [
                'exception'          => $e->getMessage(),
                'trace'              => $e->getTraceAsString(),
                'transaction_action' => $transactionAction,
            ]);
            return [];
        }
    }

    /**
     * Builds and validates a CreateTransactionRequest from the LLM action data.
     * Returns null if validation fails.
     */
    private function buildTransactionDto(array $transactionAction): ?CreateTransactionRequest
    {
        $dto         = new CreateTransactionRequest();
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
     * Looks up a Category entity by name and type.
     * Returns null if the name is absent, equals 'Otros', or no match is found.
     */
    private function resolveCategory(?string $categoryName, string $transactionType): ?Category
    {
        if (!$categoryName || $categoryName === 'Otros') {
            return null;
        }

        return $this->categoryRepository->findOneBy([
            'name' => $categoryName,
            'type' => $transactionType,
        ]);
    }

    /**
     * Builds the pending_categorization metadata payload with the list of available
     * categories so the user can pick one from the chat UI.
     * Uses the transaction's actual date rather than the current date.
     */
    private function buildPendingCategorizationMetadata(
        Transaction $transaction,
        string $transactionType,
        ?Category $suggestedCategory = null
    ): array {
        $available = $this->categoryRepository->findBy(['type' => $transactionType]);

        $suggested = $suggestedCategory !== null
            ? ['id' => $suggestedCategory->getId(), 'name' => $suggestedCategory->getName()]
            : null;

        $otherCategories = array_values(array_filter(
            $available,
            fn(Category $cat) => $suggestedCategory === null || $cat->getId() !== $suggestedCategory->getId()
        ));

        return [
            'pending_categorization' => [
                'transaction_id'     => $transaction->getId(),
                'name'               => $transaction->getName(),
                'type'               => $transaction->getType(),
                'amount'             => $transaction->getAmount(),
                'date'               => $transaction->getDate()->format('Y-m-d'),
                'suggested_category' => $suggested,
                'categories'         => array_map(
                    fn(Category $cat) => ['id' => $cat->getId(), 'name' => $cat->getName()],
                    $otherCategories
                ),
            ],
        ];
    }

    /**
     * Returns all conversations for the given user.
     *
     * @return ChatConversation[]
     */
    public function getUserConversations(User $user): array
    {
        return $this->conversationRepository->findByUser($user);
    }

    /**
     * Returns a conversation with its messages if it belongs to the given user.
     * Returns null if the conversation does not exist or belongs to a different user.
     */
    public function getConversation(int $id, User $user): ?ChatConversation
    {
        return $this->conversationRepository->findOneByIdAndUser($id, $user);
    }

    /**
     * Deletes a conversation and all its messages (cascade handled by Doctrine).
     */
    public function deleteConversation(ChatConversation $conversation): void
    {
        $this->entityManager->remove($conversation);
        $this->entityManager->flush();
    }

    /**
     * Returns an existing conversation matching the DTO's conversationId,
     * or creates and persists a new one if no ID was provided or the ID is not found.
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
     * Generates a conversation title by truncating the first message to 50 characters.
     */
    private function generateTitle(string $message): string
    {
        return mb_strlen($message) > 50
            ? mb_substr($message, 0, 50) . '...'
            : $message;
    }

    /**
     * Calls the LLM service classify-context endpoint to determine the context type needed.
     * Validates safety guardrails (ToxicLanguage + DetectPII) and throws RuntimeException
     * with code 422 if the message is rejected. Falls back to "none" on network errors
     * so the main message flow is never blocked by a classification failure.
     */
    private function classifyContextNeeds(string $message): string
    {
        try {
            $response = $this->httpClient->request('POST', $this->llmServiceUrl . '/llm/classify-context', [
                'json'         => ['message' => $message],
                'timeout'      => 15,
                'max_duration' => 15,
            ]);

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
     * Sends the chat request payload to the LLM service and returns the decoded response array.
     */
    private function callLlmService(LlmChatRequestDTO $request): array
    {
        $response = $this->httpClient->request('POST', $this->llmServiceUrl . '/llm/chat', [
            'json'         => $request->toArray(),
            'timeout'      => 120,
            'max_duration' => 120,
        ]);

        return $response->toArray();
    }
}
