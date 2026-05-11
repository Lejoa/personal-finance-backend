<?php

namespace App\Controller;

use App\DTO\CreateTransactionRequest;
use App\DTO\UpdateTransactionRequest;
use App\Entity\Transaction;
use App\Entity\User;
use App\Service\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/transactions')]
#[IsGranted('ROLE_USER')]
class TransactionController extends AbstractController
{
    /**
     * Injects service and infrastructure dependencies via constructor promotion.
     */
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Creates a new transaction for the authenticated user.
     * Validates the DTO before delegating creation to the service layer.
     * Returns 201 with the serialized transaction on success.
     */
    #[Route('', name: 'api_transaction_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $dto = $this->serializer->deserialize($request->getContent(), CreateTransactionRequest::class, 'json');

            $errors = $this->validator->validate($dto);
            if (\count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validation failed', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $transaction = $this->transactionService->createTransaction($dto, $user);

            return $this->json(
                ['message' => 'Transaction created successfully', 'transaction' => $this->serializeTransaction($transaction)],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error creating transaction', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Returns all transactions for the authenticated user with optional filters.
     * Supported query params: type, startDate, endDate, limit, sync.
     * The response envelope follows the project-standard "data / total" shape.
     */
    #[Route('', name: 'api_transaction_list', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $type = $request->query->get('type');
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');
        $limit = $request->query->get('limit');
        $sync = $request->query->get('sync');

        $transactions = $this->transactionService->getUserTransactions(
            $user,
            $type,
            $startDate,
            $endDate,
            $limit ? (int) $limit : null,
            $sync
        );

        return $this->json([
            'data' => array_map(fn (Transaction $transaction) => $this->serializeTransaction($transaction), $transactions),
            'total' => \count($transactions),
        ]);
    }

    /**
     * Returns a single transaction by its ID.
     * Returns 404 if the transaction does not exist or belongs to a different user.
     */
    #[Route('/{id}', name: 'api_transaction_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $transaction = $this->transactionService->getTransactionById($id, $user);

        if (null === $transaction) {
            return $this->json(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['transaction' => $this->serializeTransaction($transaction)]);
    }

    /**
     * Updates an existing transaction using the request body (supports both PUT and PATCH).
     * Only non-null fields in the DTO are applied. Returns 404 if the transaction does not exist
     * or belongs to a different user.
     */
    #[Route('/{id}', name: 'api_transaction_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $transaction = $this->transactionService->getTransactionById($id, $user);

        if (null === $transaction) {
            return $this->json(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $dto = $this->serializer->deserialize($request->getContent(), UpdateTransactionRequest::class, 'json');

            $errors = $this->validator->validate($dto);
            if (\count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validation failed', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $updatedTransaction = $this->transactionService->updateTransaction($transaction, $dto);

            return $this->json([
                'message' => 'Transaction updated successfully',
                'transaction' => $this->serializeTransaction($updatedTransaction),
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error updating transaction', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Returns a formative feedback message for a confirmed transaction.
     * Feedback is data-driven and motivational — never alarmist or judgmental.
     * Returns 404 if the transaction does not exist or belongs to a different user.
     */
    #[Route('/{id}/feedback', name: 'api_transaction_feedback', methods: ['POST'])]
    public function feedback(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $transaction = $this->transactionService->getTransactionById($id, $user);

        if (null === $transaction) {
            return $this->json(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
        }

        $feedback = $this->transactionService->buildFormativeFeedback($transaction, $user);

        return $this->json(['feedback' => $feedback]);
    }

    /**
     * Deletes a transaction by ID.
     * Returns 404 if the transaction does not exist or belongs to a different user.
     */
    #[Route('/{id}', name: 'api_transaction_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $transaction = $this->transactionService->getTransactionById($id, $user);

        if (null === $transaction) {
            return $this->json(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->transactionService->deleteTransaction($transaction);

            return $this->json(['message' => 'Transaction deleted successfully']);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error deleting transaction', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Converts a Transaction entity into a plain array for JSON serialization.
     * Uses the null-safe operator on getDate() and getCreatedAt() because both
     * property types are nullable, even though the constructor always initialises them.
     * The response shape is a stable public API contract — do not change field names.
     */
    private function serializeTransaction(Transaction $transaction): array
    {
        $category = $transaction->getCategory();

        return [
            'id' => $transaction->getId(),
            'name' => $transaction->getName(),
            'type' => $transaction->getType(),
            'amount' => $transaction->getAmount(),
            'date' => $transaction->getDate()?->format('Y-m-d'),
            'note' => $transaction->getNote(),
            'categoryId' => $category?->getId(),
            'categoryName' => $category?->getName(),
            'synchronized' => $transaction->getSynchronized(),
            'source' => $transaction->getSource(),
            'createdAt' => $transaction->getCreatedAt()?->format('Y-m-d H:i:s'),
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
