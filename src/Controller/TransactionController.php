<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/transactions')]
class TransactionController extends AbstractController
{
    /**
     * Crear una nueva transacción
     *
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route('', name: 'api_transaction_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Usuario no autenticado'], Response::HTTP_UNAUTHORIZED);
        }

        // Decodificar el JSON del request
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'error' => 'Datos inválidos',
                'message' => 'El cuerpo de la petición debe ser JSON válido'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Crear la entidad Transaction
        $transaction = new Transaction();
        $transaction->setUser($user);

        // Asignar campos
        if (isset($data['nombre'])) {
            $transaction->setNombre($data['nombre']);
        }

        if (isset($data['tipo'])) {
            $transaction->setTipo($data['tipo']);
        }

        if (isset($data['monto'])) {
            $transaction->setMonto((float) $data['monto']);
        }

        if (isset($data['fecha'])) {
            try {
                $fecha = new \DateTime($data['fecha']);
                $transaction->setFecha($fecha);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Formato de fecha inválido',
                    'message' => 'La fecha debe estar en formato YYYY-MM-DD'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if (isset($data['nota'])) {
            $transaction->setNota($data['nota']);
        }

        // Validar la entidad
        $errors = $validator->validate($transaction);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'error' => 'Validación fallida',
                'violations' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Guardar en la base de datos
        $entityManager->persist($transaction);
        $entityManager->flush();

        // Retornar la transacción creada
        return $this->json([
            'message' => 'Transacción creada exitosamente',
            'transaction' => [
                'id' => $transaction->getId(),
                'nombre' => $transaction->getNombre(),
                'tipo' => $transaction->getTipo(),
                'monto' => $transaction->getMonto(),
                'fecha' => $transaction->getFecha()->format('Y-m-d'),
                'nota' => $transaction->getNota(),
                'createdAt' => $transaction->getCreatedAt()->format('Y-m-d H:i:s')
            ]
        ], Response::HTTP_CREATED);
    }
}
