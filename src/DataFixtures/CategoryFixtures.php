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
            // Categorías de gasto (nombres únicos)
            ['name' => 'Alimentos',       'description' => 'Gastos en comida y bebidas',                    'type' => 'gasto'],
            ['name' => 'Transporte',      'description' => 'Movilidad, gasolina y transporte público',      'type' => 'gasto'],
            ['name' => 'Entretenimiento', 'description' => 'Ocio, diversión y recreación',                  'type' => 'gasto'],
            ['name' => 'Salud',           'description' => 'Gastos médicos, medicinas y cuidado personal',  'type' => 'gasto'],
            ['name' => 'Educación',       'description' => 'Cursos, libros y material educativo',           'type' => 'gasto'],
            ['name' => 'Otros',           'description' => 'Gastos varios no clasificados',                 'type' => 'gasto'],
            // Categorías de ingreso (nombres únicos)
            ['name' => 'Salario',         'description' => 'Ingresos por empleo fijo o temporal',           'type' => 'ingreso'],
            ['name' => 'Freelance',       'description' => 'Ingresos por trabajos independientes',          'type' => 'ingreso'],
            ['name' => 'Inversiones',     'description' => 'Dividendos, intereses y rendimientos',          'type' => 'ingreso'],
            ['name' => 'Ventas',          'description' => 'Venta de artículos o reembolsos',               'type' => 'ingreso'],
        ];

        // Cargar solo categorías que no existan aún (evita duplicados en --append)
        $repo = $manager->getRepository(Category::class);

        foreach ($categories as $data) {
            $existing = $repo->findOneBy(['name' => $data['name'], 'type' => $data['type']]);
            if ($existing) {
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
