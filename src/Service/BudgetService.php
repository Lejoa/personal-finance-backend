<?php

namespace App\Service;

use App\DTO\CreateBudgetRequest;
use App\DTO\UpdateBudgetRequest;
use App\Entity\Budget;
use App\Entity\BudgetCategory;
use App\Entity\User;
use App\Repository\BudgetCategoryRepository;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class BudgetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BudgetRepository $budgetRepository,
        private readonly BudgetCategoryRepository $budgetCategoryRepository,
        private readonly CategoryRepository $categoryRepository
    ) {
    }

    /**
     * Creates a new Budget with its BudgetCategory entries for the given user.
     * Throws InvalidArgumentException if any referenced category ID does not exist.
     */
    public function createBudget(CreateBudgetRequest $dto, User $user): Budget
    {
        $budget = new Budget();
        $budget->setUser($user);
        $budget->setStartDate(new \DateTime($dto->startDate));
        $budget->setEndDate(new \DateTime($dto->endDate));

        foreach ($dto->categories as $categoryData) {
            $category = $this->categoryRepository->find($categoryData['categoryId']);

            if (!$category) {
                throw new \InvalidArgumentException(
                    "Category with ID {$categoryData['categoryId']} does not exist."
                );
            }

            $budgetCategory = new BudgetCategory();
            $budgetCategory->setCategory($category);
            $budgetCategory->setAmount((float) $categoryData['amount']);

            $budget->addBudgetCategory($budgetCategory);
        }

        $this->entityManager->persist($budget);
        $this->entityManager->flush();

        return $budget;
    }

    /**
     * Updates an existing Budget's dates and/or category list.
     * Only fields present (non-null) in the DTO are applied — null fields are left unchanged.
     * When categories are provided, the existing set is fully replaced.
     * Throws InvalidArgumentException if any referenced category ID does not exist or dates are invalid.
     */
    public function updateBudget(Budget $budget, UpdateBudgetRequest $dto): Budget
    {
        if ($dto->startDate !== null) {
            try {
                $budget->setStartDate(new \DateTime($dto->startDate));
            } catch (\Exception) {
                throw new \InvalidArgumentException('Invalid start date format. Use YYYY-MM-DD.');
            }
        }

        if ($dto->endDate !== null) {
            try {
                $budget->setEndDate(new \DateTime($dto->endDate));
            } catch (\Exception) {
                throw new \InvalidArgumentException('Invalid end date format. Use YYYY-MM-DD.');
            }
        }

        if ($dto->categories !== null) {
            foreach ($budget->getBudgetCategories() as $budgetCategory) {
                $budget->removeBudgetCategory($budgetCategory);
                $this->entityManager->remove($budgetCategory);
            }

            foreach ($dto->categories as $categoryData) {
                $category = $this->categoryRepository->find($categoryData['categoryId']);

                if (!$category) {
                    throw new \InvalidArgumentException(
                        "Category with ID {$categoryData['categoryId']} does not exist."
                    );
                }

                $budgetCategory = new BudgetCategory();
                $budgetCategory->setCategory($category);
                $budgetCategory->setAmount((float) $categoryData['amount']);

                $budget->addBudgetCategory($budgetCategory);
            }
        }

        $this->entityManager->flush();

        return $budget;
    }

    /**
     * Deletes a budget and all its associated categories (cascade handled by Doctrine).
     */
    public function deleteBudget(Budget $budget): void
    {
        $this->entityManager->remove($budget);
        $this->entityManager->flush();
    }

    /**
     * Updates the budgeted amount for a single BudgetCategory and persists the change.
     */
    public function updateBudgetCategoryAmount(BudgetCategory $budgetCategory, float $amount): BudgetCategory
    {
        $budgetCategory->setAmount($amount);
        $this->entityManager->flush();

        return $budgetCategory;
    }

    /**
     * Removes a single BudgetCategory from its parent Budget.
     * If no categories remain after removal, the Budget itself is also deleted.
     */
    public function removeBudgetCategory(BudgetCategory $budgetCategory): void
    {
        $budget = $budgetCategory->getBudget();

        $budget->removeBudgetCategory($budgetCategory);
        $this->entityManager->remove($budgetCategory);

        if ($budget->getBudgetCategories()->isEmpty()) {
            $this->entityManager->remove($budget);
        }

        $this->entityManager->flush();
    }

    /**
     * Returns all budgets for the given user, ordered by creation date descending.
     *
     * @return Budget[]
     */
    public function getUserBudgets(User $user): array
    {
        return $this->budgetRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Returns a Budget by ID scoped to the given user.
     * Returns null if the budget does not exist or belongs to a different user.
     */
    public function getUserBudgetById(int $id, User $user): ?Budget
    {
        return $this->budgetRepository->findByIdForUser($id, $user);
    }

    /**
     * Finds a BudgetCategory by ID, scoped to the given budget.
     * Returns null if the category does not exist or belongs to a different budget.
     */
    public function findCategoryInBudget(int $budgetCategoryId, int $budgetId): ?BudgetCategory
    {
        return $this->budgetCategoryRepository->findByIdForBudget($budgetCategoryId, $budgetId);
    }
}