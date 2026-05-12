<?php

namespace App\Tests\Unit\Controller;

use App\Controller\LoanCalculationController;
use App\DTO\CreateLoanCalculationRequest;
use App\Entity\LoanCalculation;
use App\Service\LoanCalculationService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LoanCalculationControllerTest extends AbstractControllerTestCase
{
    private LoanCalculationService&MockObject $loanService;
    private SerializerInterface&MockObject $serializer;
    private ValidatorInterface&MockObject $validator;
    private LoanCalculationController&MockObject $controller;

    protected function setUp(): void
    {
        $this->loanService = $this->createMock(LoanCalculationService::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->controller = $this->makeControllerMock(
            LoanCalculationController::class,
            [$this->loanService, $this->serializer, $this->validator],
        );
    }

    public function testIndexReturnsDataAndTotal(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->loanService->method('getByUser')->willReturn([
            $this->makeLoan(1),
            $this->makeLoan(2),
        ]);

        $response = $this->controller->index();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['data']);
    }

    public function testCreateReturns201WhenValid(): void
    {
        $user = $this->makeUser();
        $loan = $this->makeLoan();
        $dto = new CreateLoanCalculationRequest();

        $this->controller->method('getUser')->willReturn($user);
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->loanService->method('create')->willReturn($loan);

        $response = $this->controller->create($this->makeRequest(['name' => 'Test loan']));

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('calculation', $data);
    }

    public function testCreateReturns400WhenValidationFails(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->serializer->method('deserialize')->willReturn(new CreateLoanCalculationRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations(['amount' => 'Required.']));

        $response = $this->controller->create($this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testCreateReturns500WhenServiceThrows(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->serializer->method('deserialize')->willReturn(new CreateLoanCalculationRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->loanService->method('create')->willThrowException(new \Exception('DB error'));

        $response = $this->controller->create($this->makeRequest([]));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testDeleteReturns200WhenFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->loanService->expects($this->once())->method('delete');

        $response = $this->controller->delete(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('deleted', $data['message']);
    }

    public function testDeleteReturns404WhenNotFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->loanService->method('delete')->willThrowException(new NotFoundHttpException('Loan not found.'));

        $response = $this->controller->delete(99);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteReturns403WhenNotOwner(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->loanService->method('delete')->willThrowException(new AccessDeniedHttpException('Access denied.'));

        $response = $this->controller->delete(1);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testDeleteReturns500WhenGenericException(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->loanService->method('delete')->willThrowException(new \Exception('Unexpected error'));

        $response = $this->controller->delete(1);

        $this->assertSame(500, $response->getStatusCode());
    }

    private function makeLoan(int $id = 1): LoanCalculation
    {
        $loan = new LoanCalculation();
        $loan->setName('Home loan');
        $loan->setAmount(10000.0);
        $loan->setAnnualRate(5.0);
        $loan->setTermMonths(12);

        $ref = new \ReflectionProperty(LoanCalculation::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($loan, $id);

        return $loan;
    }
}
