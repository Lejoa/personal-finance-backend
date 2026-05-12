<?php

namespace App\Service;

use App\Constants\TipReasonMessages;
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
     * Creates a new Tip entity from the given DTO and persists it to the database.
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
     * Applies non-null DTO fields to an existing Tip and flushes the change.
     * Fields not present in the DTO (null) are left unchanged.
     */
    public function updateTip(Tip $tip, UpdateTipRequest $dto): Tip
    {
        $dto->applyTo($tip);
        $this->entityManager->flush();

        return $tip;
    }

    /**
     * Deletes the given Tip from the database.
     */
    public function deleteTip(Tip $tip): void
    {
        $this->entityManager->remove($tip);
        $this->entityManager->flush();
    }

    /**
     * Returns all tips ordered by creation date descending.
     *
     * @return Tip[]
     */
    public function getAllTips(): array
    {
        return $this->tipRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Finds a single Tip by its primary key. Returns null if not found.
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
     * @return array<int, array{tip: Tip, reason: string}>
     */
    public function getRecommendedTips(User $user): array
    {
        $summary = $this->transactionRepository->getUserFinancialSummary($user);

        $income = $summary['total_income'];
        $expenses = $summary['total_expenses'];
        $topCategories = $summary['top_expense_categories'];

        $tags = $topCategories;
        $primaryReason = TipReasonMessages::RECENT_FALLBACK;

        if ($expenses > $income) {
            $tags = array_merge(['ahorro', 'gastos'], $tags);
            $primaryReason = TipReasonMessages::HIGH_EXPENSES;
        } elseif ($income > 0 && ($expenses / $income) < 0.5) {
            $tags[] = 'inversión';
            $primaryReason = TipReasonMessages::HIGH_SAVINGS;
        }

        $tags = array_unique($tags);

        if (!empty($tags)) {
            $tips = $this->tipRepository->findByTags($tags, 3);
            if (!empty($tips)) {
                return $this->attachReasons($tips, $tags, $topCategories, $primaryReason);
            }
        }

        return array_map(
            static fn (Tip $tip) => ['tip' => $tip, 'reason' => TipReasonMessages::RECENT_FALLBACK],
            $this->tipRepository->findRecent(3)
        );
    }

    /**
     * Attaches a contextual reason string to each tip based on which tag matched.
     *
     * @param Tip[] $tips
     * @param string[] $tags Full list of tags used for matching
     * @param string[] $topCategories Top expense category names (lowercase)
     *
     * @return array<int, array{tip: Tip, reason: string}>
     */
    private function attachReasons(array $tips, array $tags, array $topCategories, string $primaryReason): array
    {
        return array_map(static function (Tip $tip) use ($topCategories, $primaryReason) {
            $tipTags = array_map('trim', explode(',', $tip->getTags() ?? ''));

            foreach ($topCategories as $category) {
                if (\in_array($category, $tipTags, true)) {
                    return ['tip' => $tip, 'reason' => \sprintf(TipReasonMessages::TOP_CATEGORY, ucfirst($category))];
                }
            }

            return ['tip' => $tip, 'reason' => $primaryReason];
        }, $tips);
    }
}
