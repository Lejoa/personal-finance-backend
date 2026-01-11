<?php

namespace App\Service;

use App\DTO\CreateTransactionRequest;
use App\DTO\UpdateTransactionRequest;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class TransactionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TransactionRepository $transactionRepository
    ) {
    }

    /**
     * Create a new transaction for a user
     */
    public function createTransaction(CreateTransactionRequest $dto, User $user): Transaction
    {
        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setName($dto->name);
        $transaction->setType($dto->type);
        $transaction->setAmount($dto->amount);
        $transaction->setDate(new \DateTime($dto->date));

        if ($dto->note) {
            $transaction->setNote($dto->note);
        }

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    /**
     * Update an existing transaction
     */
    public function updateTransaction(Transaction $transaction, UpdateTransactionRequest $dto): Transaction
    {
        if ($dto->name !== null) {
            $transaction->setName($dto->name);
        }

        if ($dto->type !== null) {
            $transaction->setType($dto->type);
        }

        if ($dto->amount !== null) {
            $transaction->setAmount($dto->amount);
        }

        if ($dto->date !== null) {
            try {
                $date = new \DateTime($dto->date);
                $transaction->setDate($date);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Formato de fecha inválido. Use YYYY-MM-DD');
            }
        }

        if ($dto->note !== null) {
            $transaction->setNote($dto->note);
        }

        $this->entityManager->flush();

        return $transaction;
    }

    /**
     * Delete a transaction
     */
    public function deleteTransaction(Transaction $transaction): void
    {
        $this->entityManager->remove($transaction);
        $this->entityManager->flush();
    }

    /**
     * Get all transactions for a user with optional type filter
     */
    public function getUserTransactions(User $user, ?string $type = null): array
    {
        $criteria = ['user' => $user];

        if ($type) {
            $criteria['type'] = $type;
        }

        return $this->transactionRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Get transaction by ID and verify ownership
     */
    public function getTransactionById(int $id, User $user): ?Transaction
    {
        $transaction = $this->transactionRepository->find($id);

        if (!$transaction || $transaction->getUser()->getId() !== $user->getId()) {
            return null;
        }

        return $transaction;
    }
}
