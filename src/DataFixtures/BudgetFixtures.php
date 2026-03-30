<?php

namespace App\DataFixtures;

use App\Entity\Budget;
use App\Entity\BudgetCategory;
use App\Entity\Category;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class BudgetFixtures extends Fixture implements DependentFixtureInterface
{
    private const USER_EMAIL = 'alejandrogoa@gmail.com';

    // Límites mensuales por categoría de gasto (COP)
    private const CATEGORY_LIMITS = [
        'Alimentos'      => 800000,
        'Transporte'     => 400000,
        'Entretenimiento'=> 300000,
        'Salud'          => 250000,
        'Educación'      => 200000,
        'Otros'          => 150000,
    ];

    public function load(ObjectManager $manager): void
    {
        $user = $manager->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);

        if (!$user) {
            throw new \RuntimeException(
                'Usuario "' . self::USER_EMAIL . '" no encontrado. Haz login con Google primero y luego ejecuta los fixtures.'
            );
        }

        // Cargar categorías de gasto indexadas por nombre
        $categories = $manager->getRepository(Category::class)->findBy(['type' => 'gasto']);
        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat->getName()] = $cat;
        }

        // Crear un budget por cada mes de 2026 (enero a diciembre)
        for ($month = 1; $month <= 12; $month++) {
            $startDate = new \DateTime(sprintf('2026-%02d-01', $month));
            $endDate   = (clone $startDate)->modify('last day of this month');

            $budget = new Budget();
            $budget->setUser($user);
            $budget->setStartDate($startDate);
            $budget->setEndDate($endDate);

            foreach (self::CATEGORY_LIMITS as $categoryName => $limit) {
                if (!isset($categoryMap[$categoryName])) {
                    continue;
                }

                $budgetCategory = new BudgetCategory();
                $budgetCategory->setCategory($categoryMap[$categoryName]);
                $budgetCategory->setAmount((float) $limit);
                $budget->addBudgetCategory($budgetCategory);
            }

            $manager->persist($budget);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [CategoryFixtures::class];
    }
}