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
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$idCounter) {
            $ref = new \ReflectionClass($entity);
            if ($ref->hasProperty('id')) {
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                if ($prop->getValue($entity) === null) {
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
            ->willReturnCallback(function (string $method, string $url) use ($classifyResponse) {
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
        $this->em->method('persist')->willReturnCallback(function ($obj) use (&$persistedClasses, &$idCounter) {
            $persistedClasses[] = get_class($obj);
            $ref = new \ReflectionClass($obj);
            if ($ref->hasProperty('id')) {
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                if ($prop->getValue($obj) === null) {
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
        $this->conversationRepo->method('findByUser')->with($user)->willReturn([$conversation]);

        $result = $this->service->getUserConversations($user);

        $this->assertCount(1, $result);
        $this->assertSame($conversation, $result[0]);
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
            ->willReturnCallback(function (string $method, string $url) use ($classifyResponse, $chatResponse) {
                if (str_contains($url, 'classify-context')) {
                    return $classifyResponse;
                }
                return $chatResponse;
            });
    }

    private function makeContext(): array
    {
        return [
            'userContext' => ['currency' => 'COP', 'locale' => 'es-CO', 'financial_level' => 'principiante'],
            'summary' => ['period' => '2025-05', 'total_income' => 0.0, 'total_expenses' => 0.0, 'savings_rate' => 0.0, 'previous_savings_rate' => 0.0],
            'categories' => [],
            'budgets' => [],
            'top_tip' => null,
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
            ->willReturnCallback(function (string $method, string $url) use ($classifyResponse, $chatResponse) {
                if (str_contains($url, 'classify-context')) {
                    return $classifyResponse;
                }
                return $chatResponse;
            });

        // After flush, assign IDs to any ChatMessage/ChatConversation without one
        $idCounter = 1;
        $this->em->method('flush')->willReturnCallback(function () use (&$idCounter) {});
        $this->em->method('persist')->willReturnCallback(function ($entity) use (&$idCounter) {
            $ref = new \ReflectionClass($entity);
            if ($ref->hasProperty('id')) {
                $prop = $ref->getProperty('id');
                $prop->setAccessible(true);
                if ($prop->getValue($entity) === null) {
                    $prop->setValue($entity, $idCounter++);
                }
            }
        });
    }
}
