<?php

namespace App\Service;

use App\DTO\CreateBudgetRequest;
use App\DTO\UpdateBudgetRequest;
use App\Entity\Budget;
use App\Entity\BudgetCategory;
use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class BudgetService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BudgetRepository $budgetRepository,
        private readonly CategoryRepository $categoryRepository
    ) {
    }

    /**
     * Create a new budget for a user
     */
    public function createBudget(CreateBudgetRequest $dto, User $user): Budget
    {
        $budget = new Budget();
        $budget->setUser($user);
        $budget->setStartDate(new \DateTime($dto->startDate));
        $budget->setEndDate(new \DateTime($dto->endDate));

        // Add budget categories with amounts
        foreach ($dto->categories as $categoryData) {
            $category = $this->categoryRepository->find($categoryData['categoryId']);

            if (!$category) {
                throw new \InvalidArgumentException(
                    "La categoría con ID {$categoryData['categoryId']} no existe."
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
     * Update an existing budget
     */
    public function updateBudget(Budget $budget, UpdateBudgetRequest $dto): Budget
    {
        if ($dto->startDate !== null) {
            try {
                $startDate = new \DateTime($dto->startDate);
                $budget->setStartDate($startDate);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Formato de fecha de inicio inválido. Use YYYY-MM-DD');
            }
        }

        if ($dto->endDate !== null) {
            try {
                $endDate = new \DateTime($dto->endDate);
                $budget->setEndDate($endDate);
            } catch (\Exception $e) {
                throw new \InvalidArgumentException('Formato de fecha de fin inválido. Use YYYY-MM-DD');
            }
        }

        // Update categories if provided
        if ($dto->categories !== null) {
            // Remove all existing budget categories
            foreach ($budget->getBudgetCategories() as $budgetCategory) {
                $budget->removeBudgetCategory($budgetCategory);
                $this->entityManager->remove($budgetCategory);
            }

            // Add new budget categories
            foreach ($dto->categories as $categoryData) {
                $category = $this->categoryRepository->find($categoryData['categoryId']);

                if (!$category) {
                    throw new \InvalidArgumentException(
                        "La categoría con ID {$categoryData['categoryId']} no existe."
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
     * Delete a budget
     */
    public function deleteBudget(Budget $budget): void
    {
        $this->entityManager->remove($budget);
        $this->entityManager->flush();
    }

    /**
     * Get all budgets for a user
     */
    public function getUserBudgets(User $user): array
    {
        return $this->budgetRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Get a specific budget by ID for a user
     */
    public function getUserBudgetById(int $id, User $user): ?Budget
    {
        $budget = $this->budgetRepository->find($id);

        if (!$budget || $budget->getUser()->getId() !== $user->getId()) {
            return null;
        }

        return $budget;
    }
}
