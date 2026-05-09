<?php

namespace App\Tests\Unit\Controller;

use App\Controller\AnalysisController;
use App\Entity\FinancialAnalysis;
use App\Service\AnalysisService;
use PHPUnit\Framework\MockObject\MockObject;

class AnalysisControllerTest extends AbstractControllerTestCase
{
    private AnalysisService&MockObject $analysisService;
    private AnalysisController&MockObject $controller;

    protected function setUp(): void
    {
        $this->analysisService = $this->createMock(AnalysisService::class);

        $this->controller = $this->makeControllerMock(
            AnalysisController::class,
            [$this->analysisService],
        );
    }

    public function testListReturnsDataAndTotal(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->analysisService->method('getUserAnalyses')->willReturn([
            $this->makeAnalysis(1),
            $this->makeAnalysis(2),
        ]);

        $response = $this->controller->list();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['data']);
    }

    public function testListReturns500WhenServiceThrows(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->analysisService->method('getUserAnalyses')->willThrowException(new \Exception('DB error'));

        $response = $this->controller->list();

        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testShowReturns200AndMarksAsRead(): void
    {
        $user = $this->makeUser();
        $analysis = $this->makeAnalysis(1);

        $this->controller->method('getUser')->willReturn($user);
        $this->analysisService->method('findByIdAndUser')->willReturn($analysis);
        $this->analysisService->expects($this->once())->method('markAsRead')->with($analysis);

        $response = $this->controller->show(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('analysis', $data);
        $this->assertArrayHasKey('period', $data['analysis']);
        $this->assertArrayHasKey('isRead', $data['analysis']);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->analysisService->method('findByIdAndUser')->willReturn(null);

        $response = $this->controller->show(99);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Analysis not found', $data['error']);
    }

    public function testShowDoesNotCallMarkAsReadWhenNotFound(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->analysisService->method('findByIdAndUser')->willReturn(null);
        $this->analysisService->expects($this->never())->method('markAsRead');

        $this->controller->show(99);
    }

    private function makeAnalysis(int $id = 1): FinancialAnalysis
    {
        $analysis = new FinancialAnalysis();
        $analysis->setPeriod('2025-01');
        $analysis->setCheckpoint('mid');
        $analysis->setContent('Great financial health!');

        $ref = new \ReflectionProperty(FinancialAnalysis::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($analysis, $id);

        return $analysis;
    }
}
