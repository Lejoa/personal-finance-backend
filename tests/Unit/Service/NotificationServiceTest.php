<?php

namespace App\Tests\Unit\Service;

use App\Entity\FinancialAnalysis;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private NotificationRepository&MockObject $notificationRepo;
    private NotificationService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->notificationRepo = $this->createMock(NotificationRepository::class);

        $this->service = new NotificationService($this->em, $this->notificationRepo);
    }

    // ── createForAnalysis ───────────────────────────────────────────────────

    public function testCreateForAnalysisPersistsNotification(): void
    {
        $user = $this->makeUser();
        $analysis = $this->makeAnalysis('mid', '2025-05');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createForAnalysis($user, $analysis);

        $this->assertInstanceOf(Notification::class, $result);
        $this->assertSame($user, $result->getUser());
        $this->assertSame('analysis_ready', $result->getType());
    }

    public function testCreateForAnalysisMessageContainsMediados(): void
    {
        $user = $this->makeUser();
        $analysis = $this->makeAnalysis('mid', '2025-01');

        $this->em->method('persist');
        $this->em->method('flush');

        $result = $this->service->createForAnalysis($user, $analysis);

        $this->assertStringContainsString('mediados', $result->getMessage());
    }

    public function testCreateForAnalysisMessageContainsCierreForEndCheckpoint(): void
    {
        $user = $this->makeUser();
        $analysis = $this->makeAnalysis('end', '2025-01');

        $this->em->method('persist');
        $this->em->method('flush');

        $result = $this->service->createForAnalysis($user, $analysis);

        $this->assertStringContainsString('cierre', $result->getMessage());
    }

    // ── getUnreadCount ──────────────────────────────────────────────────────

    public function testGetUnreadCountDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $this->notificationRepo->method('countUnreadByUser')->with($user)->willReturn(5);

        $result = $this->service->getUnreadCount($user);

        $this->assertSame(5, $result);
    }

    // ── getUnread ───────────────────────────────────────────────────────────

    public function testGetUnreadDelegatesToRepository(): void
    {
        $user = $this->makeUser();
        $notification = $this->makeNotification();
        $this->notificationRepo->method('findUnreadByUser')->with($user)->willReturn([$notification]);

        $result = $this->service->getUnread($user);

        $this->assertCount(1, $result);
        $this->assertSame($notification, $result[0]);
    }

    // ── markAllRead ─────────────────────────────────────────────────────────

    public function testMarkAllReadSetsIsReadAndFlushes(): void
    {
        $user = $this->makeUser();
        $n1 = $this->makeNotification();
        $n2 = $this->makeNotification();
        $this->notificationRepo->method('findUnreadByUser')->willReturn([$n1, $n2]);

        $this->em->expects($this->once())->method('flush');

        $this->service->markAllRead($user);

        $this->assertTrue($n1->isRead());
        $this->assertTrue($n2->isRead());
    }

    public function testMarkAllReadIsIdempotentWhenNoUnread(): void
    {
        $user = $this->makeUser();
        $this->notificationRepo->method('findUnreadByUser')->willReturn([]);

        $this->em->expects($this->once())->method('flush');

        $this->service->markAllRead($user);
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

    private function makeAnalysis(string $checkpoint, string $period): FinancialAnalysis
    {
        $analysis = new FinancialAnalysis();
        $analysis->setCheckpoint($checkpoint);
        $analysis->setPeriod($period);
        $analysis->setContent('Some analysis content');
        $analysis->setUser($this->makeUser());
        $ref = new \ReflectionProperty(FinancialAnalysis::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($analysis, 1);
        return $analysis;
    }

    private function makeNotification(): Notification
    {
        $n = new Notification();
        $n->setMessage('Test notification');
        $n->setType('analysis_ready');
        $n->setUser($this->makeUser());
        return $n;
    }
}
