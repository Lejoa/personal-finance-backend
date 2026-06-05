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
use App\ValueObject\PeriodHint;
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
        // Build the category catalogue once — reused by both the classifier (so it can
        // normalize user synonyms in period_hint.category) and the chat LLM request.
        $availableCategories = array_map(
            static fn (Category $cat) => $cat->getName(),
            $this->categoryRepository->findAll()
        );

        // Classify before creating the conversation so the title can use context_type
        $classification = $this->classifyContextNeeds($dto->message, $availableCategories);
        $contextType    = $classification['context_type'];
        $periodHint     = PeriodHint::fromArray($classification['period_hint']);

        if (null !== $classification['period_hint'] && null === $periodHint) {
            $this->logger->warning('Classifier returned invalid PeriodHint — using compact snapshot fallback', [
                'raw_period_hint' => $classification['period_hint'],
                'message'         => $dto->message,
                'context_type'    => $contextType,
            ]);
        }

        $conversation = $this->getOrCreateConversation($dto, $user, $contextType);

        $userMessage = new ChatMessage();
        $userMessage->setContent($dto->message);
        $userMessage->setRole('user');
        $conversation->addMessage($userMessage);
        $conversation->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($userMessage);
        $this->entityManager->flush();

        // If the classifier found no period in the current message, check whether this
        // is a follow-up to a recent historical query and inherit that period if so.
        // $contextType is passed by reference — it may be updated to 'historical'.
        $periodHintBeforeResolve = $periodHint;
        $contextTypeBeforeResolve = $contextType;
        $periodHint = $this->resolveEffectivePeriodHint($periodHint, $conversation, $contextType);

        $this->logger->info('resolveEffectivePeriodHint result', [
            'message'                  => mb_substr($dto->message, 0, 80),
            'context_type_before'      => $contextTypeBeforeResolve,
            'context_type_after'       => $contextType,
            'classified_hint'          => $periodHintBeforeResolve ? [
                'from' => $periodHintBeforeResolve->fromMonth,
                'to'   => $periodHintBeforeResolve->toMonth,
            ] : null,
            'resolved_hint'            => $periodHint ? [
                'from' => $periodHint->fromMonth,
                'to'   => $periodHint->toMonth,
            ] : null,
            'assistant_messages_count' => \count(array_filter(
                $conversation->getMessages()->toArray(),
                static fn (ChatMessage $m) => 'assistant' === $m->getRole()
            )),
        ]);

        $context             = $this->contextService->buildContext($user);
        $additionalContext   = $this->contextService->buildAdditionalContext($user, $contextType, $periodHint);
        $conversationHistory = $this->buildConversationHistory($conversation);

        $llmRequest = new LlmChatRequestDTO(
            message: $dto->message,
            userContext: $context['userContext'],
            financialSummary: $context['summary'],
            categories: $context['categories'],
            budgets: $context['budgets'],
            additionalContext: $additionalContext,
            contextType: $contextType,
            conversationHistory: $conversationHistory,
            availableCategories: $availableCategories,
        );

        $llmResponse = $this->callLlmService($llmRequest);
        $metadata = $llmResponse['metadata'] ?? [];
        $transactionAction = $llmResponse['transaction_action'] ?? null;

        if (null !== $transactionAction) {
            $transactionMetadata = $this->processTransactionAction($transactionAction, $user);
            $metadata = array_merge($metadata, $transactionMetadata);
        }

        // Persist the active period_hint in the assistant message metadata so that
        // resolveEffectivePeriodHint can inherit it in follow-up turns.
        if (null !== $periodHint && 'historical' === $contextType) {
            $metadata['period_hint'] = [
                'from_month' => $periodHint->fromMonth,
                'to_month'   => $periodHint->toMonth,
                'category'   => $periodHint->category,
            ];
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
     * Builds and validates a CreateTransactionRequest from the LLM action data.
     * Returns null if validation fails.
     */
    private function buildTransactionDto(array $transactionAction): ?CreateTransactionRequest
    {
        $dto = new CreateTransactionRequest();
        $dto->name = $transactionAction['name'] ?? null;
        $dto->type = $transactionAction['type'] ?? null;
        $dto->amount = isset($transactionAction['amount']) ? (float) $transactionAction['amount'] : null;
        $dto->date = $transactionAction['date'] ?? date('Y-m-d');
        $dto->source = 'chat';

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
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
        if (!$categoryName || 'Otros' === $categoryName) {
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

        $suggested = null !== $suggestedCategory
            ? ['id' => $suggestedCategory->getId(), 'name' => $suggestedCategory->getName()]
            : null;

        $otherCategories = array_values(array_filter(
            $available,
            static fn (Category $cat) => null === $suggestedCategory || $cat->getId() !== $suggestedCategory->getId()
        ));

        return [
            'pending_categorization' => [
                'transaction_id' => $transaction->getId(),
                'name' => $transaction->getName(),
                'type' => $transaction->getType(),
                'amount' => $transaction->getAmount(),
                'date' => $transaction->getDate()->format('Y-m-d'),
                'suggested_category' => $suggested,
                'categories' => array_map(
                    static fn (Category $cat) => ['id' => $cat->getId(), 'name' => $cat->getName()],
                    $otherCategories
                ),
            ],
        ];
    }

    /**
     * Returns all conversations for the given user with their message counts.
     *
     * @return array<array{conversation: ChatConversation, messageCount: int}>
     */
    public function getUserConversations(User $user): array
    {
        return $this->conversationRepository->findByUserWithCount($user);
    }

    /**
     * Returns a conversation if it belongs to the given user.
     * Returns null if the conversation does not exist or belongs to a different user.
     */
    public function getConversation(int $id, User $user): ?ChatConversation
    {
        return $this->conversationRepository->findOneByIdAndUser($id, $user);
    }

    /**
     * Returns a paginated slice of messages for a conversation.
     *
     * @return array{messages: ChatMessage[], total: int}
     */
    public function getConversationMessages(ChatConversation $conversation, int $page = 1, int $pageSize = 50): array
    {
        return $this->conversationRepository->findMessagesPaginated($conversation, $page, $pageSize);
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
     * Builds the conversation history array to send to the LLM service.
     *
     * Uses a sliding window of the last CONVERSATION_HISTORY_LIMIT messages
     * (excluding the user message just added) so the LLM can maintain coherence
     * across multi-turn conversations without growing the context unboundedly.
     * The new user message is excluded because it is sent separately as the
     * main "message" field in the LLM request.
     */
    private function buildConversationHistory(ChatConversation $conversation, int $limit = 6): array
    {
        $messages = $conversation->getMessages()->toArray();

        // Drop the last message — it is the user message just persisted in this turn
        if (!empty($messages)) {
            array_pop($messages);
        }

        $recent = \array_slice($messages, -$limit);

        return array_map(
            static fn (ChatMessage $m) => [
                'role' => $m->getRole(),
                'content' => $m->getContent(),
            ],
            $recent
        );
    }

    /**
     * Resolves the effective PeriodHint for the current turn.
     *
     * If the classifier produced a valid PeriodHint from the current message, it is
     * returned unchanged. Otherwise, the last $lookback assistant messages are inspected
     * for a saved period_hint in their metadata. If one is found, it is inherited and
     * $contextType is updated to 'historical' so that buildAdditionalContext executes
     * the correct historical query.
     *
     * This solves follow-up questions where the period is implicit in the conversation
     * ("dame el detalle", "¿y por categoría?") without modifying the stateless classifier.
     * Inheritance is intentionally limited to $lookback turns to avoid inheriting stale
     * periods from unrelated earlier exchanges.
     */
    private function resolveEffectivePeriodHint(
        ?PeriodHint $classifiedHint,
        ChatConversation $conversation,
        string &$contextType,
        int $lookback = 3,
    ): ?PeriodHint {
        if (null !== $classifiedHint) {
            return $classifiedHint;
        }

        // Do not inherit a historical period for transactions or off-topic messages
        if (\in_array($contextType, ['transaction', 'none'], true)) {
            return null;
        }

        $messages          = $conversation->getMessages()->toArray();
        $assistantMessages = array_values(
            array_filter($messages, static fn (ChatMessage $m) => 'assistant' === $m->getRole())
        );
        $recent = \array_slice($assistantMessages, -$lookback);

        foreach (array_reverse($recent) as $msg) {
            $meta = $msg->getMetadata();
            if (isset($meta['period_hint']['from_month'], $meta['period_hint']['to_month'])) {
                $inherited = PeriodHint::fromArray($meta['period_hint']);
                if (null !== $inherited) {
                    $contextType = 'historical';

                    return $inherited;
                }
            }
        }

        return null;
    }

    /**
     * Returns an existing conversation matching the DTO's conversationId,
     * or creates and persists a new one if no ID was provided or the ID is not found.
     * The contextType is used to generate a descriptive title for new conversations.
     */
    private function getOrCreateConversation(ChatRequestDTO $dto, User $user, string $contextType = 'none'): ChatConversation
    {
        if (null !== $dto->conversationId) {
            $conversation = $this->conversationRepository->findOneByIdAndUser($dto->conversationId, $user);
            if (null !== $conversation) {
                return $conversation;
            }
        }

        $conversation = new ChatConversation();
        $conversation->setUser($user);
        $conversation->setTitle($this->generateTitle($dto->message, $contextType));

        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        return $conversation;
    }

    /**
     * Generates a human-readable conversation title based on the classified context type.
     *
     * Using the pre-classified contextType produces consistent, descriptive titles
     * (e.g. "Consulta de presupuesto") instead of raw message truncations
     * (e.g. "¿cuánto tengo disponible en mi presup..."), at zero extra cost since
     * contextType is already available before this method is called.
     * For transaction and unclassified messages the first 50 chars are used as fallback.
     */
    private function generateTitle(string $message, string $contextType = 'none'): string
    {
        return match ($contextType) {
            'budget'     => 'Consulta de presupuesto',
            'trends'     => 'Análisis de tendencias',
            'savings'    => 'Consulta de ahorro',
            'categories' => 'Análisis por categorías',
            'question'   => 'Consulta financiera',
            'education'  => 'Educación financiera',
            default      => mb_strlen($message) > 50
                ? mb_substr($message, 0, 50) . '...'
                : $message,
        };
    }

    /**
     * Calls the LLM service classify-context endpoint to determine the context type needed.
     * Validates safety guardrails (ToxicLanguage + DetectPII) and throws RuntimeException
     * with code 422 if the message is rejected. Falls back to "none" on network errors
     * so the main message flow is never blocked by a classification failure.
     *
     * @return array{context_type: string, period_hint: ?array}
     */
    private function classifyContextNeeds(string $message, array $availableCategories = []): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->llmServiceUrl . '/llm/classify-context', [
                'json'         => [
                    'message'               => $message,
                    'available_categories'  => $availableCategories,
                ],
                'timeout'      => 15,
                'max_duration' => 15,
            ]);

            if (422 === $response->getStatusCode()) {
                $detail = $response->toArray(false);
                throw new \RuntimeException($detail['detail']['message'] ?? 'Message rejected by safety guardrails.', 422);
            }

            $data = $response->toArray();

            return [
                'context_type' => $data['context_type'] ?? 'none',
                'period_hint'  => $data['period_hint'] ?? null,
            ];
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->warning('Context classification failed, defaulting to none', [
                'error' => $e->getMessage(),
            ]);

            return ['context_type' => 'none', 'period_hint' => null];
        }
    }

    /**
     * Sends the chat request payload to the LLM service and returns the decoded response array.
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
