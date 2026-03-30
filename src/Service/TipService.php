<?php

namespace App\Service;

use App\DTO\CreateTipRequest;
use App\DTO\UpdateTipRequest;
use App\Entity\Tip;
use App\Entity\User;
use App\Repository\TipRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class TipService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TipRepository $tipRepository,
        private readonly TransactionRepository $transactionRepository
    ) {
    }

    /**
     * Create a new tip
     */
    public function createTip(CreateTipRequest $dto): Tip
    {
        $tip = new Tip();
        $tip->setTitle($dto->title);
        $tip->setAuthor($dto->author);
        $tip->setDescription($dto->description);
        $tip->setShortDescription($dto->shortDescription);
        $tip->setAuthorTitle($dto->authorTitle);
        $tip->setImageSrc($dto->imageSrc);
        $tip->setTags($dto->tags);

        $this->entityManager->persist($tip);
        $this->entityManager->flush();

        return $tip;
    }

    /**
     * Update an existing tip
     */
    public function updateTip(Tip $tip, UpdateTipRequest $dto): Tip
    {
        if ($dto->title !== null) {
            $tip->setTitle($dto->title);
        }

        if ($dto->author !== null) {
            $tip->setAuthor($dto->author);
        }

        if ($dto->description !== null) {
            $tip->setDescription($dto->description);
        }

        if ($dto->shortDescription !== null) {
            $tip->setShortDescription($dto->shortDescription);
        }

        if ($dto->authorTitle !== null) {
            $tip->setAuthorTitle($dto->authorTitle);
        }

        if ($dto->imageSrc !== null) {
            $tip->setImageSrc($dto->imageSrc);
        }

        if ($dto->tags !== null) {
            $tip->setTags($dto->tags);
        }

        $this->entityManager->flush();

        return $tip;
    }

    /**
     * Delete a tip
     */
    public function deleteTip(Tip $tip): void
    {
        $this->entityManager->remove($tip);
        $this->entityManager->flush();
    }

    /**
     * Get all tips
     */
    public function getAllTips(): array
    {
        return $this->tipRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Get tip by ID
     */
    public function getTipById(int $id): ?Tip
    {
        return $this->tipRepository->find($id);
    }

    /**
     * Get recommended tips based on the user's financial behaviour (last 30 days).
     * - If expenses > income: prioritise tags 'ahorro', 'gastos'
     * - If expenses/income < 0.5: prioritise tag 'inversión'
     * - Always include tags matching the user's top expense categories
     * Falls back to the 3 most recent tips when no tag match is found.
     *
     * @return Tip[]
     */
    public function getRecommendedTips(User $user): array
    {
        $summary = $this->transactionRepository->getUserFinancialSummary($user);

        $income   = $summary['total_income'];
        $expenses = $summary['total_expenses'];
        $topCategories = $summary['top_expense_categories'];

        $tags = $topCategories;

        if ($expenses > $income) {
            $tags = array_merge(['ahorro', 'gastos'], $tags);
        } elseif ($income > 0 && ($expenses / $income) < 0.5) {
            $tags[] = 'inversión';
        }

        $tags = array_unique($tags);

        if (!empty($tags)) {
            $tips = $this->tipRepository->findByTags($tags, 3);
            if (!empty($tips)) {
                return $tips;
            }
        }

        return $this->tipRepository->findRecent(3);
    }
}