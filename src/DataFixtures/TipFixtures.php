<?php

namespace App\DataFixtures;

use App\Entity\Tip;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds 10 financial tips divided into two groups based on the user's financial profile:
 *
 * - 5 tips for users whose expenses exceed their income (tags: ahorro, gastos, deuda).
 * - 5 tips for users with healthy finances and surplus income (tags: inversión, ingresos).
 *
 * Tags are aligned with the logic in TipService::getRecommendedTips() so that
 * the recommendation engine can match tips from the very first run.
 *
 * Safe to run with --append: tips are skipped if their title already exists.
 */
class TipFixtures extends Fixture
{
    /**
     * Persists all tips that do not already exist in the database.
     * Uniqueness is checked by title before inserting each record.
     */
    public function load(ObjectManager $manager): void
    {
        $tips = [
            // Tips for users with expenses > income (spending control focus)
            [
                'title' => 'Gasta menos de lo que ganas',
                'shortDescription' => 'Aprende a controlar tus gastos mediante un presupuesto claro. Identifica los gastos innecesarios y destina una parte fija al ahorro cada mes.',
                'description' => "Aprender a gastar menos de lo que ganas es la base de una buena salud financiera. El primer paso consiste en elaborar un presupuesto mensual donde identifiques tus ingresos y los dividas en gastos esenciales, ahorro y ocio responsable.\n\nUna regla sencilla pero poderosa es la del 50/30/20: destina el 50% de tus ingresos a necesidades básicas, el 30% a deseos o estilo de vida y el 20% al ahorro o pago de deudas.\n\nCon disciplina y constancia, gastar menos de lo que ganas se convierte en un hábito que reduce el estrés financiero y aumenta tu libertad económica.",
                'author' => 'Juan Pérez',
                'authorTitle' => 'Educador Financiero',
                'imageSrc' => null,
                'tags' => 'ahorro,gastos',
            ],
            [
                'title' => 'Crea un fondo de emergencia',
                'shortDescription' => 'Un fondo de emergencia te da tranquilidad ante imprevistos. Ahorra entre tres y seis meses de tus gastos esenciales.',
                'description' => "Contar con un fondo de emergencia es fundamental para enfrentar imprevistos sin comprometer tus finanzas. Este fondo actúa como un colchón para cubrir gastos inesperados como una reparación del vehículo, una emergencia médica o la pérdida temporal del empleo.\n\nLo ideal es ahorrar entre tres y seis meses de tus gastos esenciales. Empieza con una meta pequeña, como un mes, y ve aumentando progresivamente.\n\nGuarda este dinero en una cuenta separada de fácil acceso pero que no te tiente a usarla para gastos cotidianos.",
                'author' => 'María López',
                'authorTitle' => 'Consultora en Finanzas Personales',
                'imageSrc' => null,
                'tags' => 'ahorro,gastos',
            ],
            [
                'title' => 'Evita las deudas innecesarias',
                'shortDescription' => 'No todas las deudas son malas, pero las innecesarias pueden frenar tu progreso. Distingue entre deuda buena y mala.',
                'description' => "Las deudas pueden ser herramientas útiles si se manejan correctamente, pero también pueden convertirse en una carga si se usan de forma impulsiva. Antes de adquirir una deuda, pregúntate si realmente es necesaria y si tu presupuesto puede soportarla.\n\nLa diferencia entre una deuda buena y una mala está en el propósito: una deuda buena genera valor o ingresos a futuro. En cambio, una deuda mala solo satisface deseos temporales.\n\nAprende a controlar tus hábitos de consumo y utiliza el crédito de manera inteligente.",
                'author' => 'Carlos Gómez',
                'authorTitle' => 'Asesor de Crédito Responsable',
                'imageSrc' => null,
                'tags' => 'deuda,gastos',
            ],
            [
                'title' => 'Registra cada gasto, por pequeño que sea',
                'shortDescription' => 'Los pequeños gastos diarios se acumulan y pueden representar una parte importante de tu presupuesto.',
                'description' => "El primer paso para controlar tus finanzas es saber exactamente a dónde va tu dinero. Llevar un registro de cada gasto, incluso el café de la mañana, te revela patrones de consumo que de otro modo pasarían inadvertidos.\n\nUsa una aplicación, una hoja de cálculo o incluso un cuaderno. Lo importante es la constancia. Al final del mes, analiza los rubros con mayor gasto y decide cuáles puedes reducir.\n\nEsta práctica por sí sola puede generar ahorros significativos sin que sientas que te estás privando de nada.",
                'author' => 'Ana Rodríguez',
                'authorTitle' => 'Planificadora Financiera',
                'imageSrc' => null,
                'tags' => 'ahorro,gastos',
            ],
            [
                'title' => 'Aplica la regla de las 24 horas antes de comprar',
                'shortDescription' => 'Espera un día antes de cualquier compra no planificada para evitar gastos impulsivos.',
                'description' => "Las compras impulsivas son uno de los principales enemigos del ahorro. Cuando sientas el impulso de adquirir algo que no está en tu presupuesto, espera 24 horas antes de tomar la decisión.\n\nEn la mayoría de los casos, al día siguiente el deseo habrá disminuido y podrás evaluar si realmente lo necesitas. Esta simple regla puede ahorrarte una cantidad considerable al mes.\n\nSi después de 24 horas aún consideras que la compra vale la pena, evalúa si puedes pagarla sin afectar tu ahorro ni generar deuda.",
                'author' => 'Luis Herrera',
                'authorTitle' => 'Coach de Hábitos Financieros',
                'imageSrc' => null,
                'tags' => 'ahorro,gastos,deuda',
            ],

            // Tips for users with good financial health (growth and investment focus)
            [
                'title' => 'Invierte a largo plazo',
                'shortDescription' => 'Invertir no es solo para expertos. Enfócate en el largo plazo y diversifica para aprovechar el interés compuesto.',
                'description' => "La inversión es una herramienta poderosa para hacer crecer tu dinero, pero requiere paciencia y conocimiento. No se trata de buscar ganancias rápidas, sino de construir riqueza de forma sostenida. La clave está en comenzar cuanto antes.\n\nEmpieza aprendiendo sobre productos básicos como fondos indexados, bonos o fondos mutuos. Diversificar tus inversiones reduce el riesgo y aumenta la probabilidad de obtener rendimientos estables.\n\nLo más importante es mantener una mentalidad de largo plazo. Los mercados fluctúan, pero la constancia premia a quienes mantienen el rumbo.",
                'author' => 'Laura Martínez',
                'authorTitle' => 'Analista de Inversiones',
                'imageSrc' => null,
                'tags' => 'inversión',
            ],
            [
                'title' => 'Aumenta tus fuentes de ingreso',
                'shortDescription' => 'Depender de un solo ingreso limita tu crecimiento. Explora el freelancing, inversiones o emprendimientos complementarios.',
                'description' => "Tener múltiples fuentes de ingreso es una estrategia inteligente para lograr estabilidad y crecimiento financiero. Depender únicamente de un salario puede ser riesgoso, especialmente en tiempos de incertidumbre económica.\n\nExisten muchas formas de generar ingresos adicionales: trabajar como freelance, ofrecer asesorías, vender productos digitales o físicos, o invertir en instrumentos financieros.\n\nAl diversificar tus ingresos no solo aumentas tus ganancias, sino también tus oportunidades de crecimiento personal.",
                'author' => 'Andrés Torres',
                'authorTitle' => 'Coach en Emprendimiento y Finanzas',
                'imageSrc' => null,
                'tags' => 'ingresos,inversión',
            ],
            [
                'title' => 'Ahorra e invierte con propósito',
                'shortDescription' => 'Define metas claras y elige instrumentos de inversión acordes a tu perfil de riesgo. Deja que tu dinero trabaje por ti.',
                'description' => "La diferencia entre ahorrar e invertir con propósito y hacerlo sin una meta clara es enorme. Cuando tienes objetivos definidos, como un fondo de retiro, la compra de vivienda o un viaje, es más fácil mantener la disciplina.\n\nElige instrumentos de inversión que se adapten a tu horizonte de tiempo y tolerancia al riesgo. Para metas a corto plazo, opta por productos conservadores; para metas a largo plazo, puedes asumir más riesgo a cambio de mayores rendimientos.\n\nRevisa tu portafolio periódicamente y ajústalo según cambien tus circunstancias.",
                'author' => 'Sofía Vargas',
                'authorTitle' => 'Asesora de Patrimonio',
                'imageSrc' => null,
                'tags' => 'inversión,ahorro',
            ],
            [
                'title' => 'Diversifica tus ingresos pasivos',
                'shortDescription' => 'Los ingresos pasivos trabajan por ti mientras duermes. Explora dividendos, renta de activos o productos digitales.',
                'description' => "Un ingreso pasivo es aquel que genera dinero con poca o ninguna intervención continua de tu parte. Ejemplos incluyen dividendos de acciones, intereses de bonos, renta de propiedades o regalías de contenido digital.\n\nConstruir ingresos pasivos requiere inversión inicial de tiempo o dinero, pero con el tiempo puede generar flujo de caja que complementa tu salario y acelera tu independencia financiera.\n\nEmpieza en pequeño: identifica un activo que puedas desarrollar en los próximos seis meses y trabaja hacia él de forma constante.",
                'author' => 'Ricardo Flores',
                'authorTitle' => 'Inversor y Emprendedor Digital',
                'imageSrc' => null,
                'tags' => 'ingresos,inversión',
            ],
            [
                'title' => 'Planifica tu retiro desde hoy',
                'shortDescription' => 'Cuanto antes empieces a ahorrar para el retiro, más poderoso será el efecto del interés compuesto.',
                'description' => "El retiro puede parecer lejano, pero cada año que pospones el ahorro para esa etapa te cuesta mucho más al final. El interés compuesto funciona exponencialmente: ahorrar 100.000 COP mensuales durante 30 años genera mucho más que hacerlo durante 20.\n\nExplora opciones como fondos de pensiones voluntarias, cuentas de ahorro a largo plazo o portafolios de inversión diversificados orientados al retiro.\n\nDefine la edad a la que quieres retirarte y calcula cuánto necesitas acumular. Ese número, dividido entre los años que tienes disponibles, te dará tu meta de ahorro mensual.",
                'author' => 'Gloria Mendoza',
                'authorTitle' => 'Especialista en Planeación para el Retiro',
                'imageSrc' => null,
                'tags' => 'inversión,ahorro,ingresos',
            ],
        ];

        $repo = $manager->getRepository(Tip::class);

        foreach ($tips as $data) {
            // Skip if a tip with the same title already exists (--append safety).
            if ($repo->findOneBy(['title' => $data['title']])) {
                continue;
            }

            $tip = new Tip();
            $tip->setTitle($data['title']);
            $tip->setShortDescription($data['shortDescription']);
            $tip->setDescription($data['description']);
            $tip->setAuthor($data['author']);
            $tip->setAuthorTitle($data['authorTitle']);
            $tip->setImageSrc($data['imageSrc']);
            $tip->setTags($data['tags']);

            $manager->persist($tip);
        }

        $manager->flush();
    }
}
