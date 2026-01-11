<?php

namespace App\Service;

use App\DTO\CreateTipRequest;
use App\DTO\UpdateTipRequest;
use App\Entity\Tip;
use App\Repository\TipRepository;
use Doctrine\ORM\EntityManagerInterface;

class TipService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TipRepository $tipRepository
    ) {
    }

    /**
     * Create a new tip
     */
    public function createTip(CreateTipRequest $dto): Tip
    {
        $tip = new Tip();
        $tip->setTitle($dto->title);
        $tip->setAuthor($dto->author);
        $tip->setDescription($dto->description);

        $this->entityManager->persist($tip);
        $this->entityManager->flush();

        return $tip;
    }

    /**
     * Update an existing tip
     */
    public function updateTip(Tip $tip, UpdateTipRequest $dto): Tip
    {
        if ($dto->title !== null) {
            $tip->setTitle($dto->title);
        }

        if ($dto->author !== null) {
            $tip->setAuthor($dto->author);
        }

        if ($dto->description !== null) {
            $tip->setDescription($dto->description);
        }

        $this->entityManager->flush();

        return $tip;
    }

    /**
     * Delete a tip
     */
    public function deleteTip(Tip $tip): void
    {
        $this->entityManager->remove($tip);
        $this->entityManager->flush();
    }

    /**
     * Get all tips
     */
    public function getAllTips(): array
    {
        return $this->tipRepository->findBy(
            [],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Get tip by ID
     */
    public function getTipById(int $id): ?Tip
    {
        return $this->tipRepository->find($id);
    }
}