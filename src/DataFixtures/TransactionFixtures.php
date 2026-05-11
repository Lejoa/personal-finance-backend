<?php

namespace App\DataFixtures;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Generates 200 randomised transactions for the dev user covering the last 5 months.
 * 70% of transactions are expenses distributed across the expense categories;
 * the remaining 30% are income entries spread across income categories.
 *
 * Requires the dev user to exist in the database (created via Google OAuth login)
 * and depends on CategoryFixtures to ensure all categories are available before loading.
 */
class TransactionFixtures extends Fixture implements DependentFixtureInterface
{
    private const USER_EMAIL = 'alejandrogoa@gmail.com';
    private const TOTAL_TRANSACTIONS = 200;

    /** Maps each expense category name to a list of realistic transaction names. */
    private array $keyValuesExpenses = [
        'Alimentos'       => ['Supermercado', 'Restaurante', 'Comida rápida', 'Café', 'Delivery'],
        'Transporte'      => ['Gasolina', 'Transporte público', 'Mantenimiento auto'],
        'Entretenimiento' => ['Cine', 'Teatro', 'Concierto', 'Videojuegos', 'Citas de pareja'],
        'Salud'           => ['Farmacia', 'Dentista', 'Médico', 'Seguro'],
        'Educación'       => ['Libros', 'Curso online'],
        'Otros'           => ['Regalos', 'Colaboración', 'Lavandería', 'Peluquería'],
    ];

    /** Maps each income category name to a list of realistic transaction names. */
    private array $keyValuesIncomes = [
        'Salario'     => ['Salario mensual', 'Bono anual'],
        'Freelance'   => ['Freelance proyecto', 'Trabajo extra', 'Consultoría'],
        'Inversiones' => ['Dividendos', 'Intereses banco'],
        'Ventas'      => ['Venta artículos', 'Reembolso', 'Devolución impuestos'],
    ];

    /**
     * Creates and persists the fixture transactions.
     * Throws RuntimeException if the dev user has not logged in yet and therefore
     * does not exist in the database.
     */
    public function load(ObjectManager $manager): void
    {
        $user = $manager->getRepository(User::class)->findOneBy(['email' => self::USER_EMAIL]);

        if (!$user) {
            throw new \RuntimeException(
                'Usuario "' . self::USER_EMAIL . '" no encontrado. Haz login con Google primero y luego ejecuta los fixtures.'
            );
        }

        $categoriesMap = $this->buildCategoriesMap($manager);

        for ($i = 0; $i < self::TOTAL_TRANSACTIONS; $i++) {
            $isExpense = rand(0, 100) < 70;
            $type = $isExpense ? 'gasto' : 'ingreso';

            // Pick a random category and a matching transaction name from that category.
            $keyValues = $isExpense ? $this->keyValuesExpenses : $this->keyValuesIncomes;
            $categoryNames = array_keys($keyValues);
            $categoryName = $categoryNames[array_rand($categoryNames)];
            $transactionNames = $keyValues[$categoryName];
            $transactionName = $transactionNames[array_rand($transactionNames)];

            $transaction = new Transaction();
            $transaction->setUser($user);
            $transaction->setName($transactionName);
            $transaction->setType($type);
            $transaction->setAmount($this->generateAmount($isExpense));
            $transaction->setDate($this->generateRandomDate());
            $transaction->setSynchronized(rand(0, 1) ? 'done' : 'pending');

            if (isset($categoriesMap[$categoryName])) {
                $transaction->setCategory($categoriesMap[$categoryName]);
            }

            // Add an optional free-text note to 30% of transactions.
            if (rand(0, 100) < 30) {
                $transaction->setNote($this->generateNote($isExpense));
            }

            $manager->persist($transaction);
        }

        $manager->flush();
    }

    /** Returns the fixture classes that must be loaded before this one. */
    public function getDependencies(): array
    {
        return [CategoryFixtures::class];
    }

    /**
     * Builds a name-indexed map of all Category entities currently in the database.
     * Used to assign the correct Category object to each transaction without
     * issuing one query per transaction.
     *
     * @return array<string, Category>
     */
    private function buildCategoriesMap(ObjectManager $manager): array
    {
        $categories = $manager->getRepository(Category::class)->findAll();
        $map = [];

        foreach ($categories as $category) {
            $map[$category->getName()] = $category;
        }

        return $map;
    }

    /**
     * Returns a realistic random amount in COP using weighted probability ranges.
     * Expense amounts are concentrated in the low-to-mid range (5k–200k COP).
     * Income amounts skew higher (500k–5M COP) to reflect salary and freelance patterns.
     */
    private function generateAmount(bool $isExpense): float
    {
        if ($isExpense) {
            // [min, max, weight%] — weights sum to 100
            $ranges = [
                [5000,    50000,   40],
                [50000,   200000,  35],
                [200000,  500000,  15],
                [500000,  2000000, 10],
            ];
        } else {
            $ranges = [
                [100000,   500000,   20],
                [500000,   2000000,  30],
                [2000000,  5000000,  35],
                [5000000,  15000000, 15],
            ];
        }

        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($ranges as [$min, $max, $probability]) {
            $cumulative += $probability;
            if ($rand <= $cumulative) {
                return (float) rand($min, $max);
            }
        }

        return (float) rand(10000, 100000);
    }

    /**
     * Returns a random date within the last 5 months up to today.
     * This window ensures the current month always contains transactions,
     * which is required for the financial digest and analysis features to work.
     */
    private function generateRandomDate(): \DateTime
    {
        $start = new \DateTime('-5 months');
        $start->setTime(0, 0, 0);
        $end = new \DateTime();

        $randomTimestamp = rand($start->getTimestamp(), $end->getTimestamp());

        return (new \DateTime())->setTimestamp($randomTimestamp);
    }

    /**
     * Returns a random short note appropriate for the transaction type.
     * Notes are only added to a subset of transactions (see load()).
     */
    private function generateNote(bool $isExpense): string
    {
        $expenseNotes = [
            'Compra mensual', 'Pago recurrente', 'Gasto necesario',
            'Compra imprevista', 'Oferta especial', 'Pago urgente',
        ];

        $incomeNotes = [
            'Pago recibido', 'Transferencia completada', 'Depósito confirmado',
            'Ingreso adicional', 'Pago por servicios',
        ];

        $notes = $isExpense ? $expenseNotes : $incomeNotes;

        return $notes[array_rand($notes)];
    }
}
