<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Root fixture class required by DoctrineFixturesBundle.
 * All domain-specific data is loaded by the dedicated fixture classes:
 *
 * - CategoryFixtures  — base expense and income categories (no dependencies)
 * - TipFixtures       — financial tips matched to TipService recommendation tags (no dependencies)
 * - TransactionFixtures — randomised transactions for the last 5 months (depends on CategoryFixtures)
 * - BudgetFixtures    — monthly budgets covering the same date window as transactions (depends on CategoryFixtures)
 *
 * TransactionFixtures and BudgetFixtures require the dev user to exist in the database,
 * which means the user must have logged in via Google OAuth before running those fixtures.
 */
class AppFixtures extends Fixture
{
    /** No data is loaded here; this class exists as the entry point for the fixture runner. */
    public function load(ObjectManager $manager): void
    {
        $manager->flush();
    }
}
