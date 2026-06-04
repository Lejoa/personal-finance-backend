<?php

namespace App\Repository;

use App\Entity\ChatConversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChatConversation>
 */
class ChatConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatConversation::class);
    }

    /**
     * Find all conversations for a user, ordered by most recent, with message count.
     *
     * Uses a single LEFT JOIN + COUNT aggregation to avoid N+1 queries when
     * the frontend needs to display the number of messages per conversation.
     * The count is returned as a HIDDEN scalar alongside each entity.
     *
     * @return array<array{0: ChatConversation, messageCount: int}>
     */
    public function findByUser(User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c', 'COUNT(m.id) AS HIDDEN messageCount')
            ->leftJoin('c.messages', 'm')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->groupBy('c.id')
            ->orderBy('c.updatedAt', 'DESC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all conversations for a user with their message counts as a flat array.
     * Returns pairs of [ChatConversation, messageCount] suitable for serialization.
     *
     * @return array<array{conversation: ChatConversation, messageCount: int}>
     */
    public function findByUserWithCount(User $user, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c', 'COUNT(m.id) AS messageCount')
            ->leftJoin('c.messages', 'm')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->groupBy('c.id')
            ->orderBy('c.updatedAt', 'DESC');

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        $rows = $qb->getQuery()->getResult();

        return array_map(
            static fn (array $row) => [
                'conversation' => $row[0],
                'messageCount' => (int) $row['messageCount'],
            ],
            $rows
        );
    }

    /**
     * Returns a paginated slice of messages for a conversation, ordered chronologically.
     *
     * Pagination is applied at the SQL level to avoid loading the full message collection
     * into memory — important for long-running conversations. Messages are ordered ASC
     * so the chat UI can render them top-to-bottom without reversing the array.
     *
     * @return array{messages: ChatMessage[], total: int}
     */
    public function findMessagesPaginated(ChatConversation $conversation, int $page = 1, int $pageSize = 50): array
    {
        $em = $this->getEntityManager();
        $offset = ($page - 1) * $pageSize;

        $messages = $em->createQueryBuilder()
            ->select('m')
            ->from(\App\Entity\ChatMessage::class, 'm')
            ->where('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->setMaxResults($pageSize)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        $total = (int) $em->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(\App\Entity\ChatMessage::class, 'm')
            ->where('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->getQuery()
            ->getSingleScalarResult();

        return ['messages' => $messages, 'total' => $total];
    }

    /**
     * Find a conversation by id and user (for authorization check).
     */
    public function findOneByIdAndUser(int $id, User $user): ?ChatConversation
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
