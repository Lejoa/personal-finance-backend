<?php

namespace App\Service;

use App\Constants\FeedbackMessages;
use App\DTO\CreateTransactionRequest;
use App\DTO\UpdateTransactionRequest;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class TransactionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TransactionRepository $transactionRepository,
        private readonly CategoryRepository $categoryRepository
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

        if($dto->source) {
            $transaction->setSource($dto->source);
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

        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            if ($category) {
                $transaction->setCategory($category);
            }
        }

        if ($dto->synchronized !== null) {
            $transaction->setSynchronized($dto->synchronized);
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
     * Get all transactions for a user with optional filters
     */
    public function getUserTransactions(
        User $user,
        ?string $type = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?int $limit = null,
        ?string $synchronized = null
    ): array {
        $start = $startDate ? new \DateTime($startDate) : null;
        $end = $endDate ? new \DateTime($endDate) : null;

        return $this->transactionRepository->findByFilters(
            $user,
            $type,
            $start,
            $end,
            $limit,
            $synchronized
        );
    }

    /**
     * Assigns a category to an existing transaction.
     */
    public function assignCategory(Transaction $transaction, Category $category): void
    {
        $transaction->setCategory($category);
        $this->entityManager->flush();
    }

    /**
     * Generates a formative, motivational feedback message after a transaction is confirmed.
     * Tone is always positive and data-driven — never alarmist or judgmental.
     */
    public function buildFormativeFeedback(Transaction $transaction, User $user): ?string
    {
        $category = $transaction->getCategory();
        $amount = $transaction->getAmount();

        if ($transaction->getType() === 'ingreso') {
            $totalIncome = $this->transactionRepository->getCurrentMonthIncome($user);
            return sprintf(
                FeedbackMessages::INCOME_REGISTERED,
                $this->formatCOP($amount),
                $this->formatCOP($totalIncome)
            );
        }

        if (!$category) {
            return FeedbackMessages::EXPENSE_NO_CATEGORY;
        }

        $categoryId = $category->getId();
        $categoryName = $category->getName();

        $history = $this->transactionRepository->getMonthlyCategorySpending($user, $categoryId, 3);
        $currentMonthTotal = $this->transactionRepository->getCurrentMonthCategorySpending($user, $categoryId);

        if (empty($history)) {
            return sprintf(FeedbackMessages::EXPENSE_FIRST_TIME, $categoryName);
        }

        $average = array_sum(array_column($history, 'total')) / count($history);
        $delta = $average > 0 ? (($currentMonthTotal - $average) / $average) : 0;

        if ($delta < -0.1) {
            return sprintf(
                FeedbackMessages::EXPENSE_BELOW_AVERAGE,
                $this->formatCOP($currentMonthTotal),
                $categoryName,
                $this->formatCOP($average)
            );
        }

        if ($delta <= 0.2) {
            return sprintf(
                FeedbackMessages::EXPENSE_ON_TRACK,
                $categoryName,
                $this->formatCOP($currentMonthTotal),
                $this->formatCOP($average)
            );
        }

        return sprintf(
            FeedbackMessages::EXPENSE_ABOVE_AVERAGE,
            $this->formatCOP($currentMonthTotal),
            $categoryName,
            $this->formatCOP($average)
        );
    }

    private function formatCOP(float $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
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
