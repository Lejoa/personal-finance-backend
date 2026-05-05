<?php

namespace App\Controller;

use App\DTO\CreateLoanCalculationRequest;
use App\Entity\LoanCalculation;
use App\Entity\User;
use App\Service\LoanCalculationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/loan-calculations')]
#[IsGranted('ROLE_USER')]
class LoanCalculationController extends AbstractController
{
    public function __construct(
        private readonly LoanCalculationService $loanService,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'api_loan_calc_list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $calculations = $this->loanService->getByUser($user);

        return $this->json([
            'data'  => array_map(fn(LoanCalculation $c) => $this->serialize($c), $calculations),
            'total' => count($calculations),
        ]);
    }

    #[Route('', name: 'api_loan_calc_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $dto = $this->serializer->deserialize($request->getContent(), CreateLoanCalculationRequest::class, 'json');

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(
                    ['error' => 'Validation failed', 'violations' => $this->formatErrors($errors)],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $calc = $this->loanService->create($user, $dto);

            return $this->json(
                ['message' => 'Cálculo guardado correctamente.', 'calculation' => $this->serialize($calc)],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al guardar el cálculo.', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/{id}', name: 'api_loan_calc_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->loanService->delete($id, $user);
            return $this->json(['message' => 'Cálculo eliminado correctamente.']);
        } catch (NotFoundHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (AccessDeniedHttpException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Error al eliminar el cálculo.', 'message' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function serialize(LoanCalculation $calc): array
    {
        return [
            'id'              => $calc->getId(),
            'name'            => $calc->getName(),
            'amount'          => $calc->getAmount(),
            'annualRate'      => $calc->getAnnualRate(),
            'termMonths'      => $calc->getTermMonths(),
            'extraPayment'    => $calc->getExtraPayment(),
            'frequency'       => $calc->getFrequency(),
            'periodicPayment' => $calc->getPeriodicPayment(),
            'totalInterest'   => $calc->getTotalInterest(),
            'totalPaid'       => $calc->getTotalPaid(),
            'createdAt'       => $calc->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    private function formatErrors(ConstraintViolationListInterface $errors): array
    {
        $formatted = [];
        foreach ($errors as $error) {
            $formatted[$error->getPropertyPath()] = $error->getMessage();
        }
        return $formatted;
    }
}
