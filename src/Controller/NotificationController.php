<?php

namespace App\Controller;

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
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * Get unread notifications count and list for the authenticated user
     */
    #[Route('/unread', name: 'api_notification_unread', methods: ['GET'])]
    public function unread(): JsonResponse
    {
        /** @var User $user */
        $user          = $this->getUser();
        $notifications = $this->notificationService->getUnread($user);

        return $this->json([
            'count'         => count($notifications),
            'notifications' => array_map(fn($n) => [
                'id'          => $n->getId(),
                'message'     => $n->getMessage(),
                'referenceId' => $n->getReferenceId(),
                'createdAt'   => $n->getCreatedAt()->format('Y-m-d H:i:s'),
            ], $notifications),
        ]);
    }

    /**
     * Mark all notifications as read
     */
    #[Route('/read', name: 'api_notification_read_all', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->notificationService->markAllRead($user);

        return $this->json(['message' => 'Notificaciones marcadas como leídas']);
    }
}