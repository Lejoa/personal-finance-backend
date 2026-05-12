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

    /**
     * Creates and persists a notification for a newly generated FinancialAnalysis.
     * The message is built from the checkpoint type ("mid" or "end") and the analysis period.
     */
    public function createForAnalysis(User $user, FinancialAnalysis $analysis): Notification
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType('analysis_ready');
        $notification->setReferenceId($analysis->getId());
        $notification->setMessage($this->buildAnalysisMessage($analysis));

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Returns the count of unread notifications for the given user.
     * Used by the header component to display the notification badge count.
     */
    public function getUnreadCount(User $user): int
    {
        return $this->notificationRepository->countUnreadByUser($user);
    }

    /**
     * Returns all unread notifications for the given user, ordered by creation date descending.
     *
     * @return Notification[]
     */
    public function getUnread(User $user): array
    {
        return $this->notificationRepository->findUnreadByUser($user);
    }

    /**
     * Marks all unread notifications for the given user as read and flushes the change.
     * Idempotent — safe to call when there are no unread notifications.
     */
    public function markAllRead(User $user): void
    {
        $notifications = $this->notificationRepository->findUnreadByUser($user);

        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
        }

        $this->entityManager->flush();
    }

    /**
     * Builds the human-readable notification message for a financial analysis.
     * Maps "mid" checkpoint to "mediados" and any other checkpoint to "cierre".
     */
    private function buildAnalysisMessage(FinancialAnalysis $analysis): string
    {
        $label = 'mid' === $analysis->getCheckpoint() ? 'mediados' : 'cierre';
        $month = (new \DateTime($analysis->getPeriod() . '-01'))->format('F');

        return "Tu análisis de {$label} de {$month} está listo";
    }
}
