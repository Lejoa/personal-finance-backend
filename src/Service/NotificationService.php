<?php

namespace App\Service;

use App\Entity\FinancialAnalysis;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationRepository $notificationRepository
    ) {
    }

    public function createForAnalysis(User $user, FinancialAnalysis $analysis): Notification
    {
        $label   = $analysis->getCheckpoint() === 'mid' ? 'mediados' : 'cierre';
        $month   = (new \DateTime($analysis->getPeriod() . '-01'))->format('F');
        $message = "Tu análisis de {$label} de {$month} está listo";

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('analysis_ready');
        $notification->setReferenceId($analysis->getId());
        $notification->setMessage($message);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->countUnreadByUser($user);
    }

    /**
     * @return Notification[]
     */
    public function getUnread(User $user): array
    {
        return $this->notificationRepository->findUnreadByUser($user);
    }

    public function markAllRead(User $user): void
    {
        $notifications = $this->notificationRepository->findUnreadByUser($user);

        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }

        $this->entityManager->flush();
    }
}