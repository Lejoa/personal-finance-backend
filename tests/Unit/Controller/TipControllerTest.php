<?php

namespace App\Tests\Unit\Controller;

use App\Controller\TipController;
use App\DTO\CreateTipRequest;
use App\DTO\UpdateTipRequest;
use App\Entity\Tip;
use App\Service\TipService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TipControllerTest extends AbstractControllerTestCase
{
    private TipService&MockObject $tipService;
    private SerializerInterface&MockObject $serializer;
    private ValidatorInterface&MockObject $validator;
    private TipController&MockObject $controller;

    protected function setUp(): void
    {
        $this->tipService = $this->createMock(TipService::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->controller = $this->makeControllerMock(
            TipController::class,
            [$this->tipService, $this->serializer, $this->validator],
        );
    }

    public function testCreateReturns201WhenValid(): void
    {
        $tip = $this->makeTip();
        $dto = new CreateTipRequest();

        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->tipService->method('createTip')->willReturn($tip);

        $response = $this->controller->create($this->makeRequest(['title' => 'Save more']));

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('tip', $data);
    }

    public function testCreateReturns400WhenValidationFails(): void
    {
        $this->serializer->method('deserialize')->willReturn(new CreateTipRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations(['title' => 'Required.']));

        $response = $this->controller->create($this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testCreateReturns500WhenServiceThrows(): void
    {
        $this->serializer->method('deserialize')->willReturn(new CreateTipRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->tipService->method('createTip')->willThrowException(new \Exception('DB error'));

        $response = $this->controller->create($this->makeRequest([]));

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testGetAllReturnsDataAndTotal(): void
    {
        $this->tipService->method('getAllTips')->willReturn([
            $this->makeTip(1),
            $this->makeTip(2),
        ]);

        $response = $this->controller->getAll();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['data']);
    }

    public function testRecommendedReturnsDataWithReasonField(): void
    {
        $user = $this->makeUser();
        $tip = $this->makeTip(1);
        $this->controller->method('getUser')->willReturn($user);
        $this->tipService->method('getRecommendedTips')->willReturn([
            ['tip' => $tip, 'reason' => 'You spend a lot on food.'],
        ]);

        $response = $this->controller->recommended();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['total']);
        $this->assertArrayHasKey('reason', $data['data'][0]);
        $this->assertSame('You spend a lot on food.', $data['data'][0]['reason']);
    }

    public function testShowReturns200WhenFound(): void
    {
        $this->tipService->method('getTipById')->willReturn($this->makeTip());

        $response = $this->controller->show(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('tip', $data);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $this->tipService->method('getTipById')->willReturn(null);

        $response = $this->controller->show(99);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Tip not found', $data['error']);
    }

    public function testUpdateReturns200WhenValid(): void
    {
        $tip = $this->makeTip();
        $dto = new UpdateTipRequest();

        $this->tipService->method('getTipById')->willReturn($tip);
        $this->serializer->method('deserialize')->willReturn($dto);
        $this->validator->method('validate')->willReturn($this->makeViolations());
        $this->tipService->method('updateTip')->willReturn($tip);

        $response = $this->controller->update(1, $this->makeRequest(['title' => 'Updated']));

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('tip', $data);
    }

    public function testUpdateReturns404WhenNotFound(): void
    {
        $this->tipService->method('getTipById')->willReturn(null);

        $response = $this->controller->update(99, $this->makeRequest([]));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testUpdateReturns400WhenValidationFails(): void
    {
        $this->tipService->method('getTipById')->willReturn($this->makeTip());
        $this->serializer->method('deserialize')->willReturn(new UpdateTipRequest());
        $this->validator->method('validate')->willReturn($this->makeViolations(['title' => 'Too short.']));

        $response = $this->controller->update(1, $this->makeRequest([]));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testDeleteReturns200WhenFound(): void
    {
        $this->tipService->method('getTipById')->willReturn($this->makeTip());
        $this->tipService->expects($this->once())->method('deleteTip');

        $response = $this->controller->delete(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('deleted', $data['message']);
    }

    public function testDeleteReturns404WhenNotFound(): void
    {
        $this->tipService->method('getTipById')->willReturn(null);

        $response = $this->controller->delete(99);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteReturns500WhenServiceThrows(): void
    {
        $this->tipService->method('getTipById')->willReturn($this->makeTip());
        $this->tipService->method('deleteTip')->willThrowException(new \Exception('DB error'));

        $response = $this->controller->delete(1);

        $this->assertSame(500, $response->getStatusCode());
    }

    private function makeTip(int $id = 1): Tip
    {
        $tip = new Tip();
        $tip->setTitle('Save more money');
        $tip->setAuthor('Expert');
        $tip->setDescription('Tips for saving.');
        $tip->setShortDescription('Save!');

        $ref = new \ReflectionProperty(Tip::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($tip, $id);

        return $tip;
    }
}
