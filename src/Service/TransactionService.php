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
        private readonly CategoryRepository $categoryRepository,
        private readonly FinancialDigestService $digestService
    ) {
    }

    /**
     * Creates and persists a new transaction for the given user.
     * For SMS-sourced transactions, deduplicates by note before inserting —
     * returns the existing transaction if a match is found.
     * Invalidates the financial digest cache after creation.
     */
    public function createTransaction(CreateTransactionRequest $dto, User $user): Transaction
    {
        if ($dto->source === 'sms' && !empty($dto->note)) {
            $existing = $this->transactionRepository->findExistingByNote($user, $dto->note);
            if ($existing !== null) {
                return $existing;
            }
        }

        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setName($dto->name);
        $transaction->setType($dto->type);
        $transaction->setAmount($dto->amount);
        $transaction->setDate(new \DateTime($dto->date));

        if ($dto->note) {
            $transaction->setNote($dto->note);
        }

        if ($dto->source) {
            $transaction->setSource($dto->source);
        }

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        $this->digestService->invalidate($user);

        return $transaction;
    }

    /**
     * Applies non-null scalar DTO fields to the transaction via applyTo(), then resolves
     * the category lookup if a categoryId was provided. Flushes all changes in a single call.
     * Throws InvalidArgumentException if the date string is not parseable.
     */
    public function updateTransaction(Transaction $transaction, UpdateTransactionRequest $dto): Transaction
    {
        $dto->applyTo($transaction);

        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            if ($category) {
                $transaction->setCategory($category);
            }
        }

        $this->entityManager->flush();

        return $transaction;
    }

    /**
     * Removes the transaction from the database and invalidates the financial digest cache.
     */
    public function deleteTransaction(Transaction $transaction): void
    {
        $user = $transaction->getUser();
        $this->entityManager->remove($transaction);
        $this->entityManager->flush();
        $this->digestService->invalidate($user);
    }

    /**
     * Returns all transactions for the given user matching the optional filters.
     * Date strings are converted to DateTime objects before being passed to the repository.
     *
     * @return Transaction[]
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
        $end   = $endDate   ? new \DateTime($endDate)   : null;

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
     * Assigns a category to an existing transaction and flushes the change.
     */
    public function assignCategory(Transaction $transaction, Category $category): void
    {
        $transaction->setCategory($category);
        $this->entityManager->flush();
    }

    /**
     * Generates a formative, motivational feedback message after a transaction is confirmed.
     * Tone is always positive and data-driven — never alarmist or judgmental.
     * Returns null only when no feedback case is matched (should not occur in practice).
     */
    public function buildFormativeFeedback(Transaction $transaction, User $user): ?string
    {
        $category = $transaction->getCategory();
        $amount   = $transaction->getAmount();

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

        $categoryId   = $category->getId();
        $categoryName = $category->getName();

        $history            = $this->transactionRepository->getMonthlyCategorySpending($user, $categoryId, 3);
        $currentMonthTotal  = $this->transactionRepository->getCurrentMonthCategorySpending($user, $categoryId);

        if (empty($history)) {
            return sprintf(FeedbackMessages::EXPENSE_FIRST_TIME, $categoryName);
        }

        $average = array_sum(array_column($history, 'total')) / count($history);
        $delta   = $average > 0 ? (($currentMonthTotal - $average) / $average) : 0;

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

    /**
     * Returns a transaction by ID only if it belongs to the given user.
     * Delegates the ownership check to the repository (SQL level) to prevent IDOR.
     */
    public function getTransactionById(int $id, User $user): ?Transaction
    {
        return $this->transactionRepository->findByIdForUser($id, $user);
    }

    /**
     * Formats a float amount as a Colombian peso string (e.g. $1.250.000).
     */
    private function formatCOP(float $amount): string
    {
        return '$' . number_format($amount, 0, ',', '.');
    }
}
