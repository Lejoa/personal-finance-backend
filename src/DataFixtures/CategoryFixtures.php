<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CategoryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categories = [
            // Categorías de gasto
            [
                'name' => 'Alimentos',
                'description' => 'Gastos relacionados con comida y bebidas',
                'type' => 'gasto'
            ],
            [
                'name' => 'Transporte',
                'description' => 'Gastos de movilidad, gasolina, transporte público',
                'type' => 'gasto'
            ],
            [
                'name' => 'Entretenimiento',
                'description' => 'Gastos en ocio, diversión y recreación',
                'type' => 'gasto'
            ],
            [
                'name' => 'Salud',
                'description' => 'Gastos médicos, medicinas y cuidado personal',
                'type' => 'gasto'
            ],
            [
                'name' => 'Educación',
                'description' => 'Gastos en cursos, libros y material educativo',
                'type' => 'gasto'
            ],
            [
                'name' => 'Otros',
                'description' => 'Gastos varios no clasificados en otras categorías',
                'type' => 'gasto'
            ],
            // Categorías de ingreso
            [
                'name' => 'Salario',
                'description' => 'Ingresos por empleo fijo o temporal',
                'type' => 'ingreso'
            ],
            [
                'name' => 'Freelance',
                'description' => 'Ingresos por trabajos independientes y consultorías',
                'type' => 'ingreso'
            ],
            [
                'name' => 'Inversiones',
                'description' => 'Ingresos por dividendos, intereses y rendimientos',
                'type' => 'ingreso'
            ],
            [
                'name' => 'Ventas',
                'description' => 'Ingresos por venta de artículos o reembolsos',
                'type' => 'ingreso'
            ]
        ];

        foreach ($categories as $categoryData) {
            $category = new Category();
            $category->setName($categoryData['name']);
            $category->setDescription($categoryData['description']);
            $category->setType($categoryData['type']);

            $manager->persist($category);
        }

        $manager->flush();
    }
}
