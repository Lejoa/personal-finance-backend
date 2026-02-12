<?php

namespace App\DataFixtures;

use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TransactionFixtures extends Fixture
{
    private const USER_ID = 4;
    private const TOTAL_TRANSACTIONS = 200;

    private array $keyValuesExpenses = [
        'Alimentos' => ['Supermercado', 'Restaurante', 'Comida rápida', 'Café', 'Delivery'],
        'Transporte' => ['Gasolina', 'Transporte público', 'Mantenimiento auto'],
        'Entretenimiento' => ['Cine', 'Teatro', 'Concierto', 'Videojuegos', 'Citas de pareja'],
        'Salud' => ['Farmacia', 'Dentista', 'Médico', 'Seguro'],
        'Educación' => ['Libros', 'Curso online'],
        'Otros' => ['Regalos', 'Colaboración', 'Lavandería', 'Peluquería']
    ];

    private array $keyValuesIncomes = [
        'Salario' => ['Salario mensual', 'Bono anual'],
        'Freelance' => ['Freelance proyecto', 'Trabajo extra', 'Consultoría'],
        'Inversiones' => ['Dividendos', 'Intereses banco'],
        'Ventas' => ['Venta artículos', 'Reembolso', 'Devolución impuestos']
    ];

    private array $syncStatus = [
        'pending',
        'done',
        'rejected'
    ];

    public function load(ObjectManager $manager): void
    {
        $user = $manager->getRepository(User::class)->find(self::USER_ID);

        if (!$user) {
            throw new \RuntimeException('Usuario con ID ' . self::USER_ID . ' no encontrado');
        }

        $categoriesMap = $this->buildCategoriesMap($manager);

        for ($i = 0; $i < self::TOTAL_TRANSACTIONS; $i++) {
            $isExpense = rand(0, 100) < 70;
            $type = $isExpense ? 'gasto' : 'ingreso';

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
            $keyRand =array_rand($this->syncStatus);
            $transaction->setSynchronized($this->syncStatus[$keyRand]);

            if (isset($categoriesMap[$categoryName])) {
                $transaction->setCategory($categoriesMap[$categoryName]);
            }

            if (rand(0, 100) < 30) {
                $transaction->setNote($this->generateNote($isExpense));
            }

            $manager->persist($transaction);
        }

        $manager->flush();
    }

    private function buildCategoriesMap(ObjectManager $manager): array
    {
        $categories = $manager->getRepository(Category::class)->findAll();
        $map = [];

        foreach ($categories as $category) {
            $map[$category->getName()] = $category;
        }

        return $map;
    }

    private function generateAmount(bool $isExpense): float
    {
        if ($isExpense) {
            $ranges = [
                [5000, 50000, 40],
                [50000, 200000, 35],
                [200000, 500000, 15],
                [500000, 2000000, 10]
            ];
        } else {
            $ranges = [
                [100000, 500000, 20],
                [500000, 2000000, 30],
                [2000000, 5000000, 35],
                [5000000, 15000000, 15]
            ];
        }

        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($ranges as [$min, $max, $probability]) {
            $cumulative += $probability;
            if ($rand <= $cumulative) {
                return round(rand($min, $max), 2);
            }
        }

        return round(rand(10000, 100000), 2);
    }

    private function generateRandomDate(): \DateTime
    {
        $start = new \DateTime('-6 months');
        $end = new \DateTime('+3 months');
        $randomTimestamp = rand($start->getTimestamp(), $end->getTimestamp());

        return (new \DateTime())->setTimestamp($randomTimestamp);
    }

    private function generateNote(bool $isExpense): string
    {
        $expenseNotes = [
            'Compra mensual', 'Pago recurrente', 'Gasto necesario',
            'Compra imprevista', 'Oferta especial', 'Pago urgente'
        ];

        $incomeNotes = [
            'Pago recibido', 'Transferencia completada', 'Depósito confirmado',
            'Ingreso adicional', 'Pago por servicios'
        ];

        $notes = $isExpense ? $expenseNotes : $incomeNotes;
        return $notes[array_rand($notes)];
    }
}
