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
        $transaction->setNombre($dto->nombre);
        $transaction->setTipo($dto->tipo);
        $transaction->setMonto($dto->monto);
        $transaction->setFecha(new \DateTime($dto->fecha));

        if ($dto->nota) {
            $transaction->setNota($dto->nota);
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
        if ($dto->nombre !== null) {
            $transaction->setNombre($dto->nombre);
        }

        if ($dto->tipo !== null) {
            $transaction->setTipo($dto->tipo);
        }

        if ($dto->monto !== null) {
            $transaction->setMonto($dto->monto);
        }

        if ($dto->fecha !== null) {
            try {
                $fecha = new \DateTime($dto->fecha);
                $transaction->setFecha($fecha);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Formato de fecha inválido. Use YYYY-MM-DD');
            }
        }

        if ($dto->nota !== null) {
            $transaction->setNota($dto->nota);
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
    public function getUserTransactions(User $user, ?string $tipo = null): array
    {
        $criteria = ['user' => $user];

        if ($tipo) {
            $criteria['tipo'] = $tipo;
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
