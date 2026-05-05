<?php

namespace App\Service;

use App\DTO\CreateLoanCalculationRequest;
use App\Entity\LoanCalculation;
use App\Entity\User;
use App\Repository\LoanCalculationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LoanCalculationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoanCalculationRepository $repository
    ) {
    }

    /** @return LoanCalculation[] */
    public function getByUser(User $user): array
    {
        return $this->repository->findByUser($user);
    }

    public function create(User $user, CreateLoanCalculationRequest $dto): LoanCalculation
    {
        $calc = new LoanCalculation();
        $calc->setUser($user);
        $calc->setName($dto->name);
        $calc->setAmount($dto->amount);
        $calc->setAnnualRate($dto->annualRate);
        $calc->setTermMonths($dto->termMonths);
        $calc->setExtraPayment($dto->extraPayment);
        $calc->setFrequency($dto->frequency);
        $calc->setPeriodicPayment($dto->periodicPayment);
        $calc->setTotalInterest($dto->totalInterest);
        $calc->setTotalPaid($dto->totalPaid);

        $this->entityManager->persist($calc);
        $this->entityManager->flush();

        return $calc;
    }

    public function delete(int $id, User $user): void
    {
        $calc = $this->repository->find($id);

        if ($calc === null) {
            throw new NotFoundHttpException('Cálculo no encontrado.');
        }

        if ($calc->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('No tienes permiso para eliminar este cálculo.');
        }

        $this->entityManager->remove($calc);
        $this->entityManager->flush();
    }
}
