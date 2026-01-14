<?php

namespace App\Controller;

use App\DTO\CreateBudgetRequest;
use App\DTO\UpdateBudgetRequest;
use App\Entity\User;
use App\Service\BudgetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/budgets')]
#[IsGranted('ROLE_USER')]
class BudgetController extends AbstractController
{
    public function __construct(
        private readonly BudgetService $budgetService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Create a new budget
     */
    #[Route('', name: 'api_budgets_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                CreateBudgetRequest::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validación fallida', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            /** @var User $user */
            $user = $this->getUser();
            $budget = $this->budgetService->createBudget($dto, $user);

            return $this->json(
                $this->serializeBudget($budget),
                Response::HTTP_CREATED
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al crear el presupuesto', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get all budgets for the authenticated user
     */
    #[Route('', name: 'api_budgets_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $budgets = $this->budgetService->getUserBudgets($user);

        return $this->json(
            array_map([$this, 'serializeBudget'], $budgets)
        );
    }

    /**
     * Get a specific budget by ID
     */
    #[Route('/{id}', name: 'api_budgets_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $budget = $this->budgetService->getUserBudgetById($id, $user);

        if (!$budget) {
            return $this->json(
                ['error' => 'Presupuesto no encontrado'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json($this->serializeBudget($budget));
    }

    /**
     * Update a budget (PUT - full update)
     */
    #[Route('/{id}', name: 'api_budgets_update_put', methods: ['PUT'])]
    public function updatePut(int $id, Request $request): JsonResponse
    {
        return $this->updateBudget($id, $request, true);
    }

    /**
     * Update a budget (PATCH - partial update)
     */
    #[Route('/{id}', name: 'api_budgets_update_patch', methods: ['PATCH'])]
    public function updatePatch(int $id, Request $request): JsonResponse
    {
        return $this->updateBudget($id, $request, false);
    }

    /**
     * Delete a budget
     */
    #[Route('/{id}', name: 'api_budgets_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $budget = $this->budgetService->getUserBudgetById($id, $user);

        if (!$budget) {
            return $this->json(
                ['error' => 'Presupuesto no encontrado'],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->budgetService->deleteBudget($budget);

        return $this->json(
            ['message' => 'Presupuesto eliminado exitosamente'],
            Response::HTTP_OK
        );
    }

    /**
     * Update budget logic (shared between PUT and PATCH)
     */
    private function updateBudget(int $id, Request $request, bool $fullUpdate): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $this->getUser();
            $budget = $this->budgetService->getUserBudgetById($id, $user);

            if (!$budget) {
                return $this->json(
                    ['error' => 'Presupuesto no encontrado'],
                    Response::HTTP_NOT_FOUND
                );
            }

            $dto = $this->serializer->deserialize(
                $request->getContent(),
                UpdateBudgetRequest::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validación fallida', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $budget = $this->budgetService->updateBudget($budget, $dto);

            return $this->json($this->serializeBudget($budget));
        } catch (\InvalidArgumentException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al actualizar el presupuesto', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Serialize Budget entity to array (manual serialization for security)
     */
    private function serializeBudget($budget): array
    {
        $categories = [];
        foreach ($budget->getBudgetCategories() as $budgetCategory) {
            $categories[] = [
                'id' => $budgetCategory->getId(),
                'categoryId' => $budgetCategory->getCategory()->getId(),
                'categoryName' => $budgetCategory->getCategory()->getName(),
                'categoryDescription' => $budgetCategory->getCategory()->getDescription(),
                'amount' => $budgetCategory->getAmount()
            ];
        }

        return [
            'id' => $budget->getId(),
            'startDate' => $budget->getStartDate()->format('Y-m-d'),
            'endDate' => $budget->getEndDate()->format('Y-m-d'),
            'categories' => $categories,
            'createdAt' => $budget->getCreatedAt()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Format validation errors
     */
    private function formatErrors($errors): array
    {
        $formatted = [];
        foreach ($errors as $error) {
            $formatted[$error->getPropertyPath()] = $error->getMessage();
        }
        return $formatted;
    }
}
