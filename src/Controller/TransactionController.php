<?php

namespace App\Controller;

use App\DTO\CreateTransactionRequest;
use App\DTO\UpdateTransactionRequest;
use App\Entity\User;
use App\Service\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/transactions')]
#[IsGranted('ROLE_USER')]
class TransactionController extends AbstractController
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Create a new transaction for authenticated user
     */
    #[Route('', name: 'api_transaction_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                CreateTransactionRequest::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validación fallida', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $transaction = $this->transactionService->createTransaction($dto, $user);

            return $this->json(
                [
                    'message' => 'Transacción creada exitosamente',
                    'transaction' => $this->serializeTransaction($transaction)
                ],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al crear transacción', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get all transactions for authenticated user
     */
    #[Route('', name: 'api_transaction_list', methods: ['GET'])]
    public function getAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $tipo = $request->query->get('tipo');
        $transactions = $this->transactionService->getUserTransactions($user, $tipo);

        return $this->json([
            'data' => array_map(
                fn($transaction) => $this->serializeTransaction($transaction),
                $transactions
            ),
            'total' => count($transactions)
        ]);
    }

    /**
     * Get a specific transaction by ID
     */
    #[Route('/{id}', name: 'api_transaction_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        
        $transaction = $this->transactionService->getTransactionById($id, $user);

        if (!$transaction) {
            return $this->json(
                ['error' => 'Transacción no encontrada'],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json([
            'transaction' => $this->serializeTransaction($transaction)
        ]);
    }

    /**
     * Update an existing transaction
     */
    #[Route('/{id}', name: 'api_transaction_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $transaction = $this->transactionService->getTransactionById($id, $user);

        if (!$transaction) {
            return $this->json(
                ['error' => 'Transacción no encontrada'],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                UpdateTransactionRequest::class,
                'json'
            );

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validación fallida', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $updatedTransaction = $this->transactionService->updateTransaction($transaction, $dto);

            return $this->json([
                'message' => 'Transacción actualizada exitosamente',
                'transaction' => $this->serializeTransaction($updatedTransaction)
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al actualizar transacción', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Delete a transaction
     */
    #[Route('/{id}', name: 'api_transaction_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $transaction = $this->transactionService->getTransactionById($id, $user);

        if (!$transaction) {
            return $this->json(
                ['error' => 'Transacción no encontrada'],
                Response::HTTP_NOT_FOUND
            );
        }

        try {
            $this->transactionService->deleteTransaction($transaction);

            return $this->json([
                'message' => 'Transacción eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al eliminar transacción', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
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

    /**
     * Serialize transaction to array
     */
    private function serializeTransaction($transaction): array
    {
        return [
            'id' => $transaction->getId(),
            'nombre' => $transaction->getNombre(),
            'tipo' => $transaction->getTipo(),
            'monto' => $transaction->getMonto(),
            'fecha' => $transaction->getFecha()->format('Y-m-d'),
            'nota' => $transaction->getNota(),
            'createdAt' => $transaction->getCreatedAt()->format('Y-m-d H:i:s')
        ];
    }
}
