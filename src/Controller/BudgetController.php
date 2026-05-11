<?php

namespace App\Controller;

use App\DTO\CreateBudgetRequest;
use App\DTO\UpdateBudgetRequest;
use App\Entity\Budget;
use App\Entity\User;
use App\Service\BudgetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/budgets')]
#[IsGranted('ROLE_USER')]
class BudgetController extends AbstractController
{
    /**
     * Injects service and infrastructure dependencies via constructor promotion.
     * BudgetCategoryRepository is NOT injected here — category lookups are delegated to BudgetService.
     */
    public function __construct(
        private readonly BudgetService $budgetService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Creates a new budget with its associated categories for the authenticated user.
     * Validates the request body before delegating creation to the service layer.
     */
    #[Route('', name: 'api_budgets_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize($request->getContent(), CreateBudgetRequest::class, 'json');

            $errors = $this->validator->validate($dto);
            if (\count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validation failed', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            /** @var User $user */
            $user = $this->getUser();
            $budget = $this->budgetService->createBudget($dto, $user);

            return $this->json($this->serializeBudget($budget), Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error creating budget', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Returns all budgets belonging to the authenticated user.
     */
    #[Route('', name: 'api_budgets_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $budgets = $this->budgetService->getUserBudgets($user);

        return $this->json(array_map(fn (Budget $b) => $this->serializeBudget($b), $budgets));
    }

    /**
     * Returns a single budget by ID for the authenticated user.
     * Returns 404 if the budget does not exist or belongs to a different user.
     */
    #[Route('/{id}', name: 'api_budgets_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $budget = $this->budgetService->getUserBudgetById($id, $user);

        if (null === $budget) {
            return $this->json(['error' => 'Budget not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeBudget($budget));
    }

    /**
     * Performs a full replacement update of the budget (PUT semantics).
     */
    #[Route('/{id}', name: 'api_budgets_update_put', methods: ['PUT'])]
    public function updatePut(int $id, Request $request): JsonResponse
    {
        return $this->handleBudgetUpdate($id, $request);
    }

    /**
     * Performs a partial update of the budget (PATCH semantics).
     */
    #[Route('/{id}', name: 'api_budgets_update_patch', methods: ['PATCH'])]
    public function updatePatch(int $id, Request $request): JsonResponse
    {
        return $this->handleBudgetUpdate($id, $request);
    }

    /**
     * Deletes a budget and all its associated categories.
     * Returns 404 if the budget does not exist or belongs to a different user.
     */
    #[Route('/{id}', name: 'api_budgets_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $budget = $this->budgetService->getUserBudgetById($id, $user);

        if (null === $budget) {
            return $this->json(['error' => 'Budget not found'], Response::HTTP_NOT_FOUND);
        }

        $this->budgetService->deleteBudget($budget);

        return $this->json(['message' => 'Budget deleted successfully']);
    }

    /**
     * Updates the amount of a single category within a budget.
     * Verifies that the budget belongs to the authenticated user before proceeding.
     * Returns 404 if the budget or the category entry is not found.
     * Returns 400 if the "amount" field is missing or not a positive number.
     */
    #[Route('/{budgetId}/categories/{budgetCategoryId}', name: 'api_budgets_category_update', methods: ['PATCH'])]
    public function updateCategory(int $budgetId, int $budgetCategoryId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $budget = $this->budgetService->getUserBudgetById($budgetId, $user);

        if (null === $budget) {
            return $this->json(['error' => 'Budget not found'], Response::HTTP_NOT_FOUND);
        }

        $budgetCategory = $this->budgetService->findCategoryInBudget($budgetCategoryId, $budgetId);
        if (null === $budgetCategory) {
            return $this->json(['error' => 'Budget category not found'], Response::HTTP_NOT_FOUND);
        }

        $body = json_decode($request->getContent(), true) ?? [];
        $amount = isset($body['amount']) ? (float) $body['amount'] : null;

        if (null === $amount || $amount <= 0) {
            return $this->json(
                ['error' => 'Field "amount" must be a positive number'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $updated = $this->budgetService->updateBudgetCategoryAmount($budgetCategory, $amount);

        return $this->json([
            'id' => $updated->getId(),
            'categoryId' => $updated->getCategory()->getId(),
            'categoryName' => $updated->getCategory()->getName(),
            'amount' => $updated->getAmount(),
        ]);
    }

    /**
     * Removes a single category entry from a budget.
     * If the budget has no remaining categories after removal, the budget itself is also deleted.
     * Returns 404 if the budget or the category entry is not found.
     */
    #[Route('/{budgetId}/categories/{budgetCategoryId}', name: 'api_budgets_category_delete', methods: ['DELETE'])]
    public function deleteCategory(int $budgetId, int $budgetCategoryId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $budget = $this->budgetService->getUserBudgetById($budgetId, $user);

        if (null === $budget) {
            return $this->json(['error' => 'Budget not found'], Response::HTTP_NOT_FOUND);
        }

        $budgetCategory = $this->budgetService->findCategoryInBudget($budgetCategoryId, $budgetId);
        if (null === $budgetCategory) {
            return $this->json(['error' => 'Budget category not found'], Response::HTTP_NOT_FOUND);
        }

        $this->budgetService->removeBudgetCategory($budgetCategory);

        return $this->json(['message' => 'Category removed from budget successfully']);
    }

    /**
     * Returns a contextual feedback message after a budget category limit is set or updated.
     * Compares the new limit against the average monthly spending over the last 3 months.
     */
    #[Route('/{budgetId}/categories/{budgetCategoryId}/feedback', name: 'api_budgets_category_feedback', methods: ['POST'])]
    public function categoryFeedback(int $budgetId, int $budgetCategoryId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $budget = $this->budgetService->getUserBudgetById($budgetId, $user);

        if (null === $budget) {
            return $this->json(['error' => 'Budget not found'], Response::HTTP_NOT_FOUND);
        }

        $budgetCategory = $this->budgetService->findCategoryInBudget($budgetCategoryId, $budgetId);
        if (null === $budgetCategory) {
            return $this->json(['error' => 'Budget category not found'], Response::HTTP_NOT_FOUND);
        }

        $feedback = $this->budgetService->buildFormativeFeedback($budgetCategory);

        return $this->json(['feedback' => $feedback]);
    }

    /**
     * Shared update logic for PUT and PATCH — both accept the same DTO and validation flow.
     * BudgetService.updateBudget handles partial fields via nullable DTO properties,
     * so no distinction between full and partial update is needed at the controller level.
     */
    private function handleBudgetUpdate(int $id, Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $budget = $this->budgetService->getUserBudgetById($id, $user);

            if (null === $budget) {
                return $this->json(['error' => 'Budget not found'], Response::HTTP_NOT_FOUND);
            }

            $dto = $this->serializer->deserialize($request->getContent(), UpdateBudgetRequest::class, 'json');

            $errors = $this->validator->validate($dto);
            if (\count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validation failed', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $budget = $this->budgetService->updateBudget($budget, $dto);

            return $this->json($this->serializeBudget($budget));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error updating budget', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Converts a Budget entity and its nested BudgetCategory collection into a plain array for JSON output.
     * Uses null-safe operators on date fields because the entity properties are declared nullable.
     * The response shape is a stable public API contract — do not change field names.
     */
    private function serializeBudget(Budget $budget): array
    {
        $categories = [];
        foreach ($budget->getBudgetCategories() as $bc) {
            $categories[] = [
                'id' => $bc->getId(),
                'categoryId' => $bc->getCategory()->getId(),
                'categoryName' => $bc->getCategory()->getName(),
                'categoryDescription' => $bc->getCategory()->getDescription(),
                'amount' => $bc->getAmount(),
            ];
        }

        return [
            'id' => $budget->getId(),
            'startDate' => $budget->getStartDate()?->format('Y-m-d'),
            'endDate' => $budget->getEndDate()?->format('Y-m-d'),
            'categories' => $categories,
            'createdAt' => $budget->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Formats a Symfony ConstraintViolationList into a key-value map of field → message.
     * Used to produce structured 400 responses for invalid request bodies.
     */
    private function formatErrors(ConstraintViolationListInterface $errors): array
    {
        $formatted = [];
        foreach ($errors as $error) {
            $formatted[$error->getPropertyPath()] = $error->getMessage();
        }

        return $formatted;
    }
}
