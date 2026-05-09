<?php

namespace App\Tests\Unit\Controller;

use App\Controller\TransactionController;
use App\DTO\CreateTransactionRequest;
use App\DTO\UpdateTransactionRequest;
use App\Service\TransactionService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TransactionControllerTest extends AbstractControllerTestCase
{
    private TransactionService&MockObject $transactionService;
    private SerializerInterface&MockObject $serializer;
    private ValidatorInterface&MockObject $validator;
    private TransactionController&MockObject $controller;

    protected function setUp(): void
    {
        $this->transactionService = $this->createMock(TransactionService::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->controller = $this->makeControllerMock(
            TransactionController::class,
            [$this->transactionService, $this->serializer, $this->validator],
        );
    }

    public function testCreateReturns201WhenDtoIsValid(): void
    {
        $user = $this->makeUser();
        $transaction = $this->makeTransaction($user);
        $dto = new CreateTransactionRequest();

        $this->controller->method('getUser')->willReturn($user);
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->transactionService->method('createTransaction')->willReturn($transaction);

        $response = $this->controller->create($this->makeRequest(['name' => 'Test', 'amount' => 100]));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('transaction', $data);
    }

    public function testCreateReturns400WhenValidationFails(): void
    {
        $dto = new CreateTransactionRequest();
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations(['name' => 'This value should not be blank.']));

        $response = $this->controller->create($this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testCreateReturns500WhenServiceThrows(): void
    {
        $dto = new CreateTransactionRequest();
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->transactionService->method('createTransaction')->willThrowException(new \Exception('DB error'));

        $response = $this->controller->create($this->makeRequest(['name' => 'Test']));

        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testGetAllReturnsDataAndTotal(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->transactionService->method('getUserTransactions')->willReturn([
            $this->makeTransaction($user, 1),
            $this->makeTransaction($user, 2),
        ]);

        $response = $this->controller->getAll($this->makeRequest());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['data']);
    }

    public function testGetAllPassesFiltersToService(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);

        $this->transactionService
            ->expects($this->once())
            ->method('getUserTransactions')
            ->with(
                $this->anything(),
                'ingreso',
                '2025-01-01',
                null,
                10,
                null
            )
            ->willReturn([]);

        $request = new \Symfony\Component\HttpFoundation\Request(
            ['type' => 'ingreso', 'startDate' => '2025-01-01', 'limit' => '10']
        );

        $this->controller->getAll($request);
    }

    public function testShowReturns200WhenFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->transactionService->method('getTransactionById')->willReturn($this->makeTransaction($user));

        $response = $this->controller->show(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('transaction', $data);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $this->transactionService->method('getTransactionById')->willReturn(null);

        $response = $this->controller->show(99);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Transaction not found', $data['error']);
    }

    public function testUpdateReturns200WhenValid(): void
    {
        $user = $this->makeUser();
        $transaction = $this->makeTransaction($user);
        $dto = new UpdateTransactionRequest();

        $this->controller->method('getUser')->willReturn($user);
        $this->transactionService->method('getTransactionById')->willReturn($transaction);
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->transactionService->method('updateTransaction')->willReturn($transaction);

        $response = $this->controller->update(1, $this->makeRequest(['name' => 'Updated']));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('transaction', $data);
    }

    public function testUpdateReturns404WhenNotFound(): void
    {
        $this->transactionService->method('getTransactionById')->willReturn(null);

        $response = $this->controller->update(99, $this->makeRequest([]));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUpdateReturns400WhenValidationFails(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->transactionService->method('getTransactionById')->willReturn($this->makeTransaction($user));
        $this->serializer->method('deserialize')->willReturn(new UpdateTransactionRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations(['amount' => 'Must be positive.']));

        $response = $this->controller->update(1, $this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testFeedbackReturns200WithFeedbackString(): void
    {
        $user = $this->makeUser();
        $transaction = $this->makeTransaction($user);

        $this->controller->method('getUser')->willReturn($user);
        $this->transactionService->method('getTransactionById')->willReturn($transaction);
        $this->transactionService->method('buildFormativeFeedback')->willReturn('Great job saving!');

        $response = $this->controller->feedback(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Great job saving!', $data['feedback']);
    }

    public function testFeedbackReturns404WhenNotFound(): void
    {
        $this->transactionService->method('getTransactionById')->willReturn(null);

        $response = $this->controller->feedback(99);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteReturns200WhenFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->transactionService->method('getTransactionById')->willReturn($this->makeTransaction($user));
        $this->transactionService->expects($this->once())->method('deleteTransaction');

        $response = $this->controller->delete(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('deleted', $data['message']);
    }

    public function testDeleteReturns404WhenNotFound(): void
    {
        $this->transactionService->method('getTransactionById')->willReturn(null);

        $response = $this->controller->delete(99);

        $this->assertSame(404, $response->getStatusCode());
    }
}
