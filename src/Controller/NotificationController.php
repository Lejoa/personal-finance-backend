<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    /**
     * Injects the NotificationService as a read-only dependency via constructor promotion.
     */
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * Returns the count and list of unread notifications for the authenticated user.
     * The response includes both a scalar count and the full notification list.
     */
    #[Route('/unread', name: 'api_notification_unread', methods: ['GET'])]
    public function unread(): JsonResponse
    {
        /** @var User $user */
        $user          = $this->getUser();
        $notifications = $this->notificationService->getUnread($user);

        return $this->json([
            'count'         => count($notifications),
            'notifications' => array_map(
                fn(Notification $n) => $this->serializeNotification($n),
                $notifications
            ),
        ]);
    }

    /**
     * Marks all unread notifications as read for the authenticated user.
     * Always returns 200 — idempotent if there are no unread notifications.
     */
    #[Route('/read', name: 'api_notification_read_all', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->notificationService->markAllRead($user);

        return $this->json(['message' => 'Notifications marked as read']);
    }

    /**
     * Converts a Notification entity into a plain array for JSON serialization.
     * Uses the null-safe operator on getCreatedAt() because the property type is nullable,
     * even though the constructor always initialises it.
     * The response shape is a stable public API contract — do not change field names.
     */
    private function serializeNotification(Notification $notification): array
    {
        return [
            'id'          => $notification->getId(),
            'message'     => $notification->getMessage(),
            'referenceId' => $notification->getReferenceId(),
            'createdAt'   => $notification->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
