<?php

namespace App\Tests\Unit\Controller;

use App\Controller\DevAuthController;
use App\DTO\DevTokenRequest;
use App\Service\DevAuthService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DevAuthControllerTest extends AbstractControllerTestCase
{
    private DevAuthService&MockObject $devAuthService;
    private SerializerInterface&MockObject $serializer;
    private ValidatorInterface&MockObject $validator;

    protected function setUp(): void
    {
        $this->devAuthService = $this->createMock(DevAuthService::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
    }

    public function testGenerateTokenReturns403InProductionEnvironment(): void
    {
        $controller = $this->makeControllerMock(
            DevAuthController::class,
            [$this->devAuthService, $this->serializer, $this->validator],
            null,
            'prod'
        );

        $response = $controller->generateToken($this->makeRequest([]));

        $this->assertSame(403, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('development environment', $data['error']);
    }

    public function testGenerateTokenReturns400WhenValidationFails(): void
    {
        $controller = $this->makeControllerMock(
            DevAuthController::class,
            [$this->devAuthService, $this->serializer, $this->validator],
            null,
            'dev'
        );

        $this->serializer->method('deserialize')->willReturn(new DevTokenRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations(['email' => 'Required.']));

        $response = $controller->generateToken($this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testGenerateTokenReturns200InDevEnvironment(): void
    {
        $controller = $this->makeControllerMock(
            DevAuthController::class,
            [$this->devAuthService, $this->serializer, $this->validator],
            null,
            'dev'
        );

        $dto = new DevTokenRequest();
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->devAuthService->method('generateDevToken')->willReturn([
            'access_token' => 'tok123',
            'user' => ['id' => 1, 'email' => 'test@example.com'],
        ]);

        $response = $controller->generateToken($this->makeRequest(['email' => 'test@example.com']));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('access_token', $data);
    }

    public function testMeReturns403InProductionEnvironment(): void
    {
        $controller = $this->makeControllerMock(
            DevAuthController::class,
            [$this->devAuthService, $this->serializer, $this->validator],
            null,
            'prod'
        );

        $response = $controller->me();

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testMeReturns200InDevEnvironment(): void
    {
        $user = $this->makeUser(1, 'dev@example.com');

        $controller = $this->makeControllerMock(
            DevAuthController::class,
            [$this->devAuthService, $this->serializer, $this->validator],
            $user,
            'dev'
        );

        $response = $controller->me();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('name', $data);
    }
}
