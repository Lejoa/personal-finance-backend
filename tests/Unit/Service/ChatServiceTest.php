<?php

namespace App\Tests\Unit\Service;

use App\DTO\ChatRequestDTO;
use App\Entity\ChatConversation;
use App\Entity\ChatMessage;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ChatConversationRepository;
use App\Service\ChatService;
use App\Service\FinancialContextService;
use App\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ChatServiceTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;
    private FinancialContextService&MockObject $contextService;
    private EntityManagerInterface&MockObject $em;
    private ChatConversationRepository&MockObject $conversationRepo;
    private CategoryRepository&MockObject $categoryRepo;
    private TransactionService&MockObject $transactionService;
    private ValidatorInterface&MockObject $validator;
    private LoggerInterface&MockObject $logger;
    private ChatService $service;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->contextService = $this->createMock(FinancialContextService::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->conversationRepo = $this->createMock(ChatConversationRepository::class);
        $this->categoryRepo = $this->createMock(CategoryRepository::class);
        $this->transactionService = $this->createMock(TransactionService::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ChatService(
            $this->httpClient,
            $this->contextService,
            $this->em,
            $this->conversationRepo,
            $this->categoryRepo,
            $this->transactionService,
            $this->validator,
            $this->logger,
            'http://localhost:9999'
        );
    }

    // ── processMessage ──────────────────────────────────────────────────────

    public function testProcessMessageCreatesNewConversationWhenNoneProvided(): void
    {
        $user = $this->makeUser();
        $dto = $this->makeDto('Hola, ¿cómo estoy gastando?');

        $this->conversationRepo->expects($this->never())->method('findOneByIdAndUser');

        $this->mockContextAndLlm();

        $result = $this->service->processMessage($dto, $user);

        $this->assertSame('assistant', $result->role);
        $this->assertSame('Respuesta del asistente', $result->message);
    }

    public function testProcessMessageReusesExistingConversation(): void
    {
        $user = $this->makeUser();
        $dto = $this->makeDto('Continua', conversationId: 5);

        $conversation = $this->makeConversation($user, 5);
        $this->conversationRepo->method('findOneByIdAndUser')->with(5, $user)->willReturn($conversation);

        $this->mockContextAndLlm();

        $result = $this->service->processMessage($dto, $user);

        $this->assertSame(5, $result->conversationId);
    }

    public function testProcessMessageThrows422WhenSafetyGuardrailRejects(): void
    {
        $user = $this->makeUser();
        $dto = $this->makeDto('Mensaje inapropiado');

        $idCounter = 1;
        $this->em->method('persist')->willReturnCallback(static function ($entity) use (&$idCounter) {
            $ref = new \ReflectionClass($entity);
            if ($ref->hasProperty('id')) {
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                if (null === $prop->getValue($entity)) {
                    $prop->setValue($entity, $idCounter++);
                }
            }
        });
        $this->em->method('flush');

        $this->contextService->method('buildContext')->willReturn($this->makeContext());

        $classifyResponse = $this->createMock(ResponseInterface::class);
        $classifyResponse->method('getStatusCode')->willReturn(422);
        $classifyResponse->method('toArray')->willReturn([
            'detail' => ['message' => 'Content policy violation'],
        ]);

        $this->httpClient->method('request')
            ->willReturnCallback(static function (string $method, string $url) use ($classifyResponse) {
                if (str_contains($url, 'classify-context')) {
                    return $classifyResponse;
                }
                throw new \RuntimeException('Unexpected call');
            });

        $this->expectException(\RuntimeException::class);

        $this->service->processMessage($dto, $user);
    }

    public function testProcessMessagePersistsUserAndAssistantMessages(): void
    {
        $user = $this->makeUser();
        $dto = $this->makeDto('¿Cuánto gasté este mes?');

        $persistedClasses = [];
        $idCounter = 1;
        $this->em->method('persist')->willReturnCallback(static function ($obj) use (&$persistedClasses, &$idCounter) {
            $persistedClasses[] = $obj::class;
            $ref = new \ReflectionClass($obj);
            if ($ref->hasProperty('id')) {
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                if (null === $prop->getValue($obj)) {
                    $prop->setValue($obj, $idCounter++);
                }
            }
        });
        $this->em->method('flush');

        $this->contextService->method('buildContext')->willReturn($this->makeContext());
        $this->contextService->method('buildAdditionalContext')->willReturn('');
        $this->mockLlmHttpOnly();

        $this->service->processMessage($dto, $user);

        $this->assertContains(ChatConversation::class, $persistedClasses);
        $this->assertContains(ChatMessage::class, $persistedClasses);
    }

    public function testProcessMessageResponseContainsConversationId(): void
    {
        $user = $this->makeUser();
        $dto = $this->makeDto('¿Cuánto gasté?');

        $this->mockContextAndLlm();

        $result = $this->service->processMessage($dto, $user);

        $this->assertNotNull($result->conversationId);
    }

    // ── getUserConversations ────────────────────────────────────────────────

    public function testGetUserConversationsDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $conversation = $this->makeConversation($user);
        $row = ['conversation' => $conversation, 'messageCount' => 3];
        $this->conversationRepo->method('findByUserWithCount')->with($user)->willReturn([$row]);

        $result = $this->service->getUserConversations($user);

        $this->assertCount(1, $result);
        $this->assertSame($conversation, $result[0]['conversation']);
        $this->assertSame(3, $result[0]['messageCount']);
    }

    // ── getConversation ─────────────────────────────────────────────────────

    public function testGetConversationDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $conversation = $this->makeConversation($user, 3);
        $this->conversationRepo->method('findOneByIdAndUser')->with(3, $user)->willReturn($conversation);

        $result = $this->service->getConversation(3, $user);

        $this->assertSame($conversation, $result);
    }

    public function testGetConversationReturnsNullWhenNotFound(): void
    {
        $user = $this->makeUser();
        $this->conversationRepo->method('findOneByIdAndUser')->willReturn(null);

        $this->assertNull($this->service->getConversation(99, $user));
    }

    // ── deleteConversation ──────────────────────────────────────────────────

    public function testDeleteConversationRemovesAndFlushes(): void
    {
        $user = $this->makeUser();
        $conversation = $this->makeConversation($user);

        $this->em->expects($this->once())->method('remove')->with($conversation);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteConversation($conversation);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function makeUser(int $id = 1): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setName('Test');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($user, $id);

        return $user;
    }

    private function makeConversation(User $user, int $id = 1): ChatConversation
    {
        $conv = new ChatConversation();
        $conv->setUser($user);
        $conv->setTitle('Test conversation');
        $ref = new \ReflectionProperty(ChatConversation::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($conv, $id);

        return $conv;
    }

    private function makeDto(string $message, ?int $conversationId = null): ChatRequestDTO
    {
        $dto = new ChatRequestDTO();
        $dto->message = $message;
        $dto->conversationId = $conversationId;

        return $dto;
    }

    private function mockLlmHttpOnly(): void
    {
        $classifyResponse = $this->createMock(ResponseInterface::class);
        $classifyResponse->method('getStatusCode')->willReturn(200);
        $classifyResponse->method('toArray')->willReturn(['context_type' => 'none']);

        $chatResponse = $this->createMock(ResponseInterface::class);
        $chatResponse->method('toArray')->willReturn([
            'message' => 'Respuesta del asistente',
            'metadata' => [],
            'transaction_action' => null,
        ]);

        $this->httpClient->method('request')
            ->willReturnCallback(static function (string $method, string $url) use ($classifyResponse, $chatResponse) {
                if (str_contains($url, 'classify-context')) {
                    return $classifyResponse;
                }

                return $chatResponse;
            });
    }

    private function makeContext(): array
    {
        return [
            'userContext'          => ['currency' => 'COP', 'locale' => 'es-CO', 'financial_level' => 'principiante'],
            'summary'              => ['period' => '2025-05', 'total_income' => 0.0, 'total_expenses' => 0.0, 'savings_rate' => 0.0, 'previous_savings_rate' => 0.0],
            'categories'           => [],
            'budgets'              => [],
            'top_tip'              => null,
            'available_categories' => [],
        ];
    }

    private function mockContextAndLlm(): void
    {
        $this->contextService->method('buildContext')->willReturn($this->makeContext());
        $this->contextService->method('buildAdditionalContext')->willReturn('');

        $classifyResponse = $this->createMock(ResponseInterface::class);
        $classifyResponse->method('getStatusCode')->willReturn(200);
        $classifyResponse->method('toArray')->willReturn(['context_type' => 'none']);

        $chatResponse = $this->createMock(ResponseInterface::class);
        $chatResponse->method('toArray')->willReturn([
            'message' => 'Respuesta del asistente',
            'metadata' => [],
            'transaction_action' => null,
        ]);

        $this->httpClient->method('request')
            ->willReturnCallback(static function (string $method, string $url) use ($classifyResponse, $chatResponse) {
                if (str_contains($url, 'classify-context')) {
                    return $classifyResponse;
                }

                return $chatResponse;
            });

        // After flush, assign IDs to any ChatMessage/ChatConversation without one
        $idCounter = 1;
        $this->em->method('flush')->willReturnCallback(static function () use (&$idCounter) {});
        $this->em->method('persist')->willReturnCallback(static function ($entity) use (&$idCounter) {
            $ref = new \ReflectionClass($entity);
            if ($ref->hasProperty('id')) {
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                if (null === $prop->getValue($entity)) {
                    $prop->setValue($entity, $idCounter++);
                }
            }
        });
    }

    // ── resolveEffectivePeriodHint (via processMessage) ──────────────────────

    /**
     * When the LLM classifier returns a historical context_type with a valid period_hint,
     * processMessage must store that period_hint in the assistant message metadata so
     * the next turn can inherit it.
     */
    public function testProcessMessageStoresPeriodHintInAssistantMetadataForHistoricalContext(): void
    {
        $user = $this->makeUser();
        $dto  = $this->makeDto('gastos de los últimos 3 meses');

        $persistedMetadata = null;
        $idCounter = 1;
        $this->em->method('persist')->willReturnCallback(
            static function ($entity) use (&$idCounter, &$persistedMetadata) {
                $ref = new \ReflectionClass($entity);
                if ($ref->hasProperty('id')) {
                    $prop = $ref->getProperty('id');
                    $prop->setAccessible(true);
                    if (null === $prop->getValue($entity)) {
                        $prop->setValue($entity, $idCounter++);
                    }
                }
                // Capture metadata of assistant messages
                if ($entity instanceof ChatMessage && 'assistant' === $entity->getRole()) {
                    $persistedMetadata = $entity->getMetadata();
                }
            }
        );
        $this->em->method('flush');

        $this->contextService->method('buildContext')->willReturn($this->makeContext());
        $this->contextService->method('buildAdditionalContext')->willReturn('historical context');

        $classifyResponse = $this->createMock(ResponseInterface::class);
        $classifyResponse->method('getStatusCode')->willReturn(200);
        $classifyResponse->method('toArray')->willReturn([
            'context_type' => 'historical',
            'period_hint'  => ['from_month' => '2026-03', 'to_month' => '2026-05', 'category' => null],
        ]);

        $chatResponse = $this->createMock(ResponseInterface::class);
        $chatResponse->method('toArray')->willReturn([
            'message'          => 'Tus gastos fueron...',
            'metadata'         => [],
            'transaction_action' => null,
        ]);

        $this->httpClient->method('request')
            ->willReturnCallback(static function (string $method, string $url) use ($classifyResponse, $chatResponse) {
                return str_contains($url, 'classify-context') ? $classifyResponse : $chatResponse;
            });

        $this->service->processMessage($dto, $user);

        $this->assertNotNull($persistedMetadata, 'Assistant message metadata must not be null for historical context');
        $this->assertArrayHasKey('period_hint', $persistedMetadata);
        $this->assertSame('2026-03', $persistedMetadata['period_hint']['from_month']);
        $this->assertSame('2026-05', $persistedMetadata['period_hint']['to_month']);
    }

    /**
     * When the classifier returns no period_hint but a prior assistant message has one
     * stored in metadata, processMessage must inherit that period and call
     * buildAdditionalContext with 'historical' as the context type.
     */
    public function testProcessMessageInheritsPeriodHintFromPreviousAssistantMessage(): void
    {
        $user = $this->makeUser();
        $dto  = $this->makeDto('dame el detalle por categoría', conversationId: 10);

        // Build a conversation that already has an assistant message with a saved period_hint
        $conversation = $this->makeConversation($user, 10);

        $previousAssistant = new ChatMessage();
        $previousAssistant->setRole('assistant');
        $previousAssistant->setContent('Tus gastos de los últimos 3 meses fueron...');
        $previousAssistant->setMetadata([
            'period_hint' => ['from_month' => '2026-03', 'to_month' => '2026-05', 'category' => null],
        ]);
        $refId = new \ReflectionProperty(ChatMessage::class, 'id');
        $refId->setAccessible(true);
        $refId->setValue($previousAssistant, 99);
        $conversation->addMessage($previousAssistant);

        $this->conversationRepo->method('findOneByIdAndUser')->with(10, $user)->willReturn($conversation);

        $capturedContextType = null;
        $capturedPeriodHint  = null;
        $this->contextService->method('buildContext')->willReturn($this->makeContext());
        $this->contextService->method('buildAdditionalContext')
            ->willReturnCallback(
                static function (object $u, string $ct, $hint) use (&$capturedContextType, &$capturedPeriodHint) {
                    $capturedContextType = $ct;
                    $capturedPeriodHint  = $hint;
                    return 'inherited historical context';
                }
            );

        $classifyResponse = $this->createMock(ResponseInterface::class);
        $classifyResponse->method('getStatusCode')->willReturn(200);
        // Classifier returns 'categories' with no period — simulates the follow-up message
        $classifyResponse->method('toArray')->willReturn(['context_type' => 'categories', 'period_hint' => null]);

        $chatResponse = $this->createMock(ResponseInterface::class);
        $chatResponse->method('toArray')->willReturn([
            'message'            => 'El desglose es...',
            'metadata'           => [],
            'transaction_action' => null,
        ]);

        $this->httpClient->method('request')
            ->willReturnCallback(static function (string $method, string $url) use ($classifyResponse, $chatResponse) {
                return str_contains($url, 'classify-context') ? $classifyResponse : $chatResponse;
            });

        $idCounter = 1;
        $this->em->method('persist')->willReturnCallback(static function ($entity) use (&$idCounter) {
            $ref = new \ReflectionClass($entity);
            if ($ref->hasProperty('id')) {
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                if (null === $prop->getValue($entity)) {
                    $prop->setValue($entity, $idCounter++);
                }
            }
        });
        $this->em->method('flush');

        $this->service->processMessage($dto, $user);

        $this->assertSame('historical', $capturedContextType, 'context_type must be promoted to historical when a period is inherited');
        $this->assertNotNull($capturedPeriodHint, 'Inherited PeriodHint must not be null');
        $this->assertSame('2026-03', $capturedPeriodHint->fromMonth);
        $this->assertSame('2026-05', $capturedPeriodHint->toMonth);
    }

    /**
     * When the classifier returns context_type = 'transaction', period inheritance
     * must not occur even if a prior assistant message has a saved period_hint.
     */
    public function testProcessMessageDoesNotInheritPeriodHintForTransactionMessages(): void
    {
        $user = $this->makeUser();
        $dto  = $this->makeDto('gasté 50000 en almuerzo', conversationId: 11);

        $conversation = $this->makeConversation($user, 11);

        $previousAssistant = new ChatMessage();
        $previousAssistant->setRole('assistant');
        $previousAssistant->setContent('Tus gastos de los últimos 3 meses...');
        $previousAssistant->setMetadata([
            'period_hint' => ['from_month' => '2026-03', 'to_month' => '2026-05', 'category' => null],
        ]);
        $refId = new \ReflectionProperty(ChatMessage::class, 'id');
        $refId->setAccessible(true);
        $refId->setValue($previousAssistant, 98);
        $conversation->addMessage($previousAssistant);

        $this->conversationRepo->method('findOneByIdAndUser')->with(11, $user)->willReturn($conversation);

        $capturedContextType = null;
        $this->contextService->method('buildContext')->willReturn($this->makeContext());
        $this->contextService->method('buildAdditionalContext')
            ->willReturnCallback(static function (object $u, string $ct) use (&$capturedContextType) {
                $capturedContextType = $ct;
                return '';
            });

        $classifyResponse = $this->createMock(ResponseInterface::class);
        $classifyResponse->method('getStatusCode')->willReturn(200);
        $classifyResponse->method('toArray')->willReturn(['context_type' => 'transaction', 'period_hint' => null]);

        $chatResponse = $this->createMock(ResponseInterface::class);
        $chatResponse->method('toArray')->willReturn([
            'message'            => 'Registré tu gasto',
            'metadata'           => [],
            'transaction_action' => null,
        ]);

        $this->httpClient->method('request')
            ->willReturnCallback(static function (string $method, string $url) use ($classifyResponse, $chatResponse) {
                return str_contains($url, 'classify-context') ? $classifyResponse : $chatResponse;
            });

        $idCounter = 1;
        $this->em->method('persist')->willReturnCallback(static function ($entity) use (&$idCounter) {
            $ref = new \ReflectionClass($entity);
            if ($ref->hasProperty('id')) {
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                if (null === $prop->getValue($entity)) {
                    $prop->setValue($entity, $idCounter++);
                }
            }
        });
        $this->em->method('flush');

        $this->service->processMessage($dto, $user);

        $this->assertSame('transaction', $capturedContextType, 'context_type must remain transaction — period inheritance must not occur');
    }
}
