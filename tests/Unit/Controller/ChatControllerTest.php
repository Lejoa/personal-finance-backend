<?php

namespace App\Tests\Unit\Controller;

use App\Controller\ChatController;
use App\DTO\ChatRequestDTO;
use App\DTO\ChatResponseDTO;
use App\Entity\ChatConversation;
use App\Service\ChatService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ChatControllerTest extends AbstractControllerTestCase
{
    private ChatService&MockObject $chatService;
    private ValidatorInterface&MockObject $validator;
    private SerializerInterface&MockObject $serializer;
    private ChatController&MockObject $controller;

    protected function setUp(): void
    {
        $this->chatService = $this->createMock(ChatService::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);

        $this->controller = $this->makeControllerMock(
            ChatController::class,
            [$this->chatService, $this->validator, $this->serializer],
        );
    }

    public function testSendReturns200WithAssistantResponse(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);

        $dto = new ChatRequestDTO();
        $responseDto = new ChatResponseDTO(1, 'Hello!', 'assistant', '2025-01-01T00:00:00+00:00', 5);

        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->chatService->method('processMessage')->willReturn($responseDto);

        $response = $this->controller->send($this->makeRequest(['message' => 'Hello']));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('conversationId', $data);
    }

    public function testSendReturns400WhenValidationFails(): void
    {
        $this->serializer->method('deserialize')->willReturn(new ChatRequestDTO());
        $this->validator->method('validate')->willReturn($this->makeViolations(['message' => 'Required.']));

        $response = $this->controller->send($this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testSendReturns422WhenMessageIsRejectedByGuardrails(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);

        $this->serializer->method('deserialize')->willReturn(new ChatRequestDTO());
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->chatService->method('processMessage')
            ->willThrowException(new \RuntimeException('Content policy violation', 422));

        $response = $this->controller->send($this->makeRequest(['message' => 'bad message']));

        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('message_rejected', $data['error']);
    }

    public function testSendReturns500WhenRuntimeExceptionWithoutCode422(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);

        $this->serializer->method('deserialize')->willReturn(new ChatRequestDTO());
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->chatService->method('processMessage')
            ->willThrowException(new \RuntimeException('LLM timeout', 503));

        $response = $this->controller->send($this->makeRequest(['message' => 'Hi']));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testSendReturns500WhenGenericExceptionThrown(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);

        $this->serializer->method('deserialize')->willReturn(new ChatRequestDTO());
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->chatService->method('processMessage')
            ->willThrowException(new \Exception('Unexpected error'));

        $response = $this->controller->send($this->makeRequest(['message' => 'Hi']));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testConversationsReturnsDataAndTotal(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        // getUserConversations now returns [{conversation, messageCount}] rows
        $this->chatService->method('getUserConversations')->willReturn([
            ['conversation' => $this->makeConversation(1), 'messageCount' => 3],
            ['conversation' => $this->makeConversation(2), 'messageCount' => 7],
        ]);

        $response = $this->controller->conversations();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['data']);
        $this->assertArrayHasKey('messageCount', $data['data'][0]);
    }

    public function testGetConversationReturns200WithMessages(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->chatService->method('getConversation')->willReturn($this->makeConversation(5));
        // getConversationMessages is called with pagination params
        $this->chatService->method('getConversationMessages')->willReturn([
            'messages' => [],
            'total'    => 0,
        ]);

        // getConversation now requires a Request for pagination query params
        $response = $this->controller->getConversation(5, $this->makeRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('conversation', $data);
        $this->assertArrayHasKey('messages', $data);
        $this->assertArrayHasKey('pagination', $data);
    }

    public function testGetConversationReturns404WhenNotFound(): void
    {
        $this->chatService->method('getConversation')->willReturn(null);

        $response = $this->controller->getConversation(99, $this->makeRequest());

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Conversation not found', $data['error']);
    }

    public function testDeleteConversationReturns200(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->chatService->method('getConversation')->willReturn($this->makeConversation(5));
        $this->chatService->expects($this->once())->method('deleteConversation');

        $response = $this->controller->deleteConversation(5);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDeleteConversationReturns404WhenNotFound(): void
    {
        $this->chatService->method('getConversation')->willReturn(null);

        $response = $this->controller->deleteConversation(99);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteConversationReturns500WhenServiceThrows(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->chatService->method('getConversation')->willReturn($this->makeConversation(5));
        $this->chatService->method('deleteConversation')->willThrowException(new \Exception('DB error'));

        $response = $this->controller->deleteConversation(5);

        $this->assertSame(500, $response->getStatusCode());
    }

    private function makeConversation(int $id = 1): ChatConversation
    {
        $conv = new ChatConversation();
        $conv->setTitle('Test conversation');

        $ref = new \ReflectionProperty(ChatConversation::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($conv, $id);

        return $conv;
    }
}
