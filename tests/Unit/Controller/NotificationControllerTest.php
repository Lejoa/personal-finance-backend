<?php

namespace App\Tests\Unit\Controller;

use App\Controller\NotificationController;
use App\Entity\Notification;
use App\Service\NotificationService;
use PHPUnit\Framework\MockObject\MockObject;

class NotificationControllerTest extends AbstractControllerTestCase
{
    private NotificationService&MockObject $notificationService;
    private NotificationController&MockObject $controller;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);

        $this->controller = $this->makeControllerMock(
            NotificationController::class,
            [$this->notificationService],
        );
    }

    public function testUnreadReturnsCountAndList(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->notificationService->method('getUnread')->willReturn([
            $this->makeNotification(1),
            $this->makeNotification(2),
        ]);

        $response = $this->controller->unread();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['count']);
        $this->assertCount(2, $data['notifications']);
    }

    public function testUnreadReturnsZeroWhenNoNotifications(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->notificationService->method('getUnread')->willReturn([]);

        $response = $this->controller->unread();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['count']);
        $this->assertCount(0, $data['notifications']);
    }

    public function testMarkAllReadReturns200(): void
    {
        $user = $this->makeUser();
        $this->controller->method('getUser')->willReturn($user);
        $this->notificationService->expects($this->once())->method('markAllRead')->with($this->anything());

        $response = $this->controller->markAllRead();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('read', $data['message']);
    }

    private function makeNotification(int $id = 1): Notification
    {
        $notification = new Notification();
        $notification->setMessage('You have a new analysis!');

        $ref = new \ReflectionProperty(Notification::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($notification, $id);

        return $notification;
    }
}
