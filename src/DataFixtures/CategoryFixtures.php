<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds the ten base categories used across the application:
 * six expense categories and four income categories.
 *
 * Safe to run with --append: each category is only inserted if no existing
 * record with the same name and type is found, preventing duplicates.
 */
class CategoryFixtures extends Fixture
{
    /**
     * Persists all categories that do not already exist in the database.
     * Checks uniqueness by (name, type) pair before inserting.
     */
    public function load(ObjectManager $manager): void
    {
        $categories = [
            // Expense categories
            ['name' => 'Alimentos',       'description' => 'Gastos en comida y bebidas',                    'type' => 'gasto'],
            ['name' => 'Transporte',      'description' => 'Movilidad, gasolina y transporte público',      'type' => 'gasto'],
            ['name' => 'Entretenimiento', 'description' => 'Ocio, diversión y recreación',                  'type' => 'gasto'],
            ['name' => 'Salud',           'description' => 'Gastos médicos, medicinas y cuidado personal',  'type' => 'gasto'],
            ['name' => 'Educación',       'description' => 'Cursos, libros y material educativo',           'type' => 'gasto'],
            ['name' => 'Otros',           'description' => 'Gastos varios no clasificados',                 'type' => 'gasto'],
            // Income categories
            ['name' => 'Salario',         'description' => 'Ingresos por empleo fijo o temporal',           'type' => 'ingreso'],
            ['name' => 'Freelance',       'description' => 'Ingresos por trabajos independientes',          'type' => 'ingreso'],
            ['name' => 'Inversiones',     'description' => 'Dividendos, intereses y rendimientos',          'type' => 'ingreso'],
            ['name' => 'Ventas',          'description' => 'Venta de artículos o reembolsos',               'type' => 'ingreso'],
        ];

        $repo = $manager->getRepository(Category::class);

        foreach ($categories as $data) {
            // Skip if a category with the same name and type already exists.
            if ($repo->findOneBy(['name' => $data['name'], 'type' => $data['type']])) {
                continue;
            }

            $category = new Category();
            $category->setName($data['name']);
            $category->setDescription($data['description']);
            $category->setType($data['type']);
            $manager->persist($category);
        }

        $manager->flush();
    }
}
