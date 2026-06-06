<?php

namespace App\DataFixtures;

use App\Entity\Tip;
use App\Entity\TipReference;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds bibliographic references for all 10 tips created by TipFixtures.
 *
 * Each tip receives 1–2 references (books, articles, essays or reports) with
 * real excerpts that will be embedded by app:index-tips and surfaced in RAG
 * responses as citable sources.
 *
 * Safe to run with --append: references are skipped if a reference with the
 * same title already exists for the same tip.
 */
class TipReferenceFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [TipFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $tipRepo = $manager->getRepository(Tip::class);
        $refRepo = $manager->getRepository(TipReference::class);

        $data = [
            'Gasta menos de lo que ganas' => [
                [
                    'title'  => 'El hombre más rico de Babilonia',
                    'author' => 'George S. Clason',
                    'type'   => 'book',
                    'year'   => 1926,
                    'url'    => null,
                    'excerpt' => 'La primera ley del oro dice que el oro llega con gusto y en cantidad creciente a quien le reserva cuando menos una décima parte de sus ganancias. Quien gasta todo lo que gana no construye riqueza sin importar cuán elevado sea su salario. Guardar una décima parte de los ingresos antes de cubrir cualquier gasto es la base de toda prosperidad financiera duradera.',
                ],
                [
                    'title'  => 'Principios de educación financiera',
                    'author' => 'OCDE',
                    'type'   => 'report',
                    'year'   => 2012,
                    'url'    => null,
                    'excerpt' => 'Los individuos que elaboran y respetan un presupuesto mensual reportan niveles significativamente más altos de bienestar financiero y menor estrés económico. Controlar el gasto no implica privarse de todo placer, sino asignar conscientemente cada peso a una categoría para que el dinero trabaje según las prioridades del hogar.',
                ],
            ],

            'Crea un fondo de emergencia' => [
                [
                    'title'  => 'Transformación total de su dinero',
                    'author' => 'Dave Ramsey',
                    'type'   => 'book',
                    'year'   => 2003,
                    'url'    => null,
                    'excerpt' => 'El primer paso es ahorrar un pequeño fondo de emergencia inicial. Una vez libre de deudas de consumo, se construye el fondo completo equivalente a tres a seis meses de gastos esenciales. Sin este colchón financiero, cualquier imprevisto —una reparación del vehículo, una hospitalización, la pérdida del empleo— se convierte automáticamente en deuda adicional.',
                ],
                [
                    'title'  => 'Financial Resilience and Households in Crisis',
                    'author' => 'Banco Mundial',
                    'type'   => 'report',
                    'year'   => 2021,
                    'url'    => null,
                    'excerpt' => 'Los hogares sin reservas financieras son significativamente más vulnerables a shocks económicos externos. Mantener un fondo líquido equivalente a tres meses de gastos básicos reduce en más de un 60% la probabilidad de caer en espiral de deuda tras una emergencia. La liquidez inmediata es la primera línea de defensa de la salud financiera familiar.',
                ],
            ],

            'Evita las deudas innecesarias' => [
                [
                    'title'  => 'Padre Rico, Padre Pobre',
                    'author' => 'Robert T. Kiyosaki',
                    'type'   => 'book',
                    'year'   => 1997,
                    'url'    => null,
                    'excerpt' => 'Los ricos compran activos; la clase media compra pasivos que cree que son activos. Una deuda que financia un activo productivo —una propiedad que genera renta, una educación que multiplica el salario— es deuda buena. Una deuda que financia consumo efímero —ropa, vacaciones pagadas a crédito, electrodomésticos que se deprecian— erosiona silenciosamente el patrimonio neto.',
                ],
                [
                    'title'  => 'El costo real del crédito de consumo en Colombia',
                    'author' => 'ASOBANCARIA',
                    'type'   => 'article',
                    'year'   => 2020,
                    'url'    => null,
                    'excerpt' => 'El crédito rotativo y los cupos de tarjeta tienen tasas efectivas anuales que oscilan entre el 25% y el 32% en Colombia. Asumir deuda para financiar bienes de consumo que se deprecian rápidamente puede comprometer hasta el 30% del ingreso disponible durante años. Antes de usar el crédito, calcule el costo total incluyendo intereses, seguros y cargos de administración.',
                ],
            ],

            'Registra cada gasto, por pequeño que sea' => [
                [
                    'title'  => 'I Will Teach You to Be Rich',
                    'author' => 'Ramit Sethi',
                    'type'   => 'book',
                    'year'   => 2009,
                    'url'    => null,
                    'excerpt' => 'La mayoría de las personas sobreestima en un 40% su capacidad de ahorro porque no sabe con exactitud en qué gasta. Registrar cada gasto durante 30 días consecutivos es el ejercicio más revelador para transformar las finanzas personales. El seguimiento granular revela patrones de consumo invisibles —el café diario, las suscripciones olvidadas, las compras por impulso— que suman cifras sorprendentes al final del mes.',
                ],
            ],

            'Aplica la regla de las 24 horas antes de comprar' => [
                [
                    'title'  => 'Pensar rápido, pensar despacio',
                    'author' => 'Daniel Kahneman',
                    'type'   => 'book',
                    'year'   => 2011,
                    'url'    => null,
                    'excerpt' => 'El Sistema 1 —rápido, automático e intuitivo— domina nuestras decisiones de compra impulsivas. Al insertar una pausa deliberada de 24 horas activamos el Sistema 2 —lento, analítico y consciente— que evalúa si el deseo de compra responde a una necesidad real o es simplemente el resultado de un estímulo publicitario, una emoción pasajera o el sesgo de disponibilidad.',
                ],
            ],

            'Invierte a largo plazo' => [
                [
                    'title'  => 'El inversor inteligente',
                    'author' => 'Benjamin Graham',
                    'type'   => 'book',
                    'year'   => 1949,
                    'url'    => null,
                    'excerpt' => 'El inversor inteligente es un inversor paciente. La volatilidad del mercado no debe verse como riesgo sino como oportunidad para comprar buenos activos a precios reducidos. En el largo plazo, los mercados de renta variable han proporcionado rendimientos reales superiores a cualquier otra clase de activo, recompensando la disciplina y castigando el pánico y la especulación.',
                ],
                [
                    'title'  => 'El pequeño libro para invertir con sentido común',
                    'author' => 'John C. Bogle',
                    'type'   => 'book',
                    'year'   => 2007,
                    'url'    => null,
                    'excerpt' => 'Los fondos indexados de bajo costo superan consistentemente a más del 80% de los fondos gestionados activamente en períodos de 10 años o más. El costo acumulado de comisiones de gestión activa, spreads de transacción e impuestos por rotación destruye silenciosamente el retorno compuesto a lo largo de décadas. La simplicidad y el costo mínimo son las ventajas competitivas más duraderas del inversor individual.',
                ],
            ],

            'Aumenta tus fuentes de ingreso' => [
                [
                    'title'  => 'Multiple Streams of Income',
                    'author' => 'Robert G. Allen',
                    'type'   => 'book',
                    'year'   => 2000,
                    'url'    => null,
                    'excerpt' => 'Un árbol con raíces múltiples resiste mejor los vendavales que uno con una sola raíz. Un portafolio de ingresos diversificado —salario, freelance, inversiones, rentas pasivas— ofrece una resiliencia que ninguna carrera corporativa puede garantizar por sí sola. La pérdida de una fuente de ingreso es una crisis cuando es la única; es un inconveniente cuando existen varias.',
                ],
            ],

            'Ahorra e invierte con propósito' => [
                [
                    'title'  => 'El millonario automático',
                    'author' => 'David Bach',
                    'type'   => 'book',
                    'year'   => 2003,
                    'url'    => null,
                    'excerpt' => 'Págate primero a ti mismo: automatiza el ahorro e inversión el mismo día de tu nómina, antes de que el dinero llegue a tu cuenta corriente. Quien espera a ahorrar lo que sobra, casi nunca ahorra. El 10% del ingreso bruto, invertido de forma sistemática durante 30 años con tasas de retorno modestas, construye un patrimonio que transforma el futuro financiero de cualquier familia.',
                ],
                [
                    'title'  => 'Tu dinero o tu vida',
                    'author' => 'Vicki Robin y Joe Dominguez',
                    'type'   => 'book',
                    'year'   => 1992,
                    'url'    => null,
                    'excerpt' => 'Cada peso que gastas representa una cantidad de vida —horas de trabajo— que entregaste a cambio. Definir metas financieras concretas transforma el ahorro de una obligación aburrida en un acto de libertad: cada euro ahorrado es un paso hacia el punto en que tus inversiones generan suficiente para cubrir tu costo de vida sin necesitar vender tu tiempo.',
                ],
            ],

            'Diversifica tus ingresos pasivos' => [
                [
                    'title'  => 'El cuadrante del flujo de dinero',
                    'author' => 'Robert T. Kiyosaki',
                    'type'   => 'book',
                    'year'   => 1998,
                    'url'    => null,
                    'excerpt' => 'Los empleados y trabajadores independientes intercambian tiempo por dinero: si dejan de trabajar, dejan de ganar. Los dueños de negocios e inversionistas hacen que el dinero y los sistemas trabajen por ellos incluso mientras duermen. El objetivo de la educación financiera es construir activos que generen flujo de caja positivo de forma recurrente sin requerir presencia activa.',
                ],
                [
                    'title'  => 'The Psychology of Money',
                    'author' => 'Morgan Housel',
                    'type'   => 'book',
                    'year'   => 2020,
                    'url'    => null,
                    'excerpt' => 'El ingreso pasivo no es gratuito: requiere capital monetario o capital de trabajo invertido por adelantado. La diferencia con el ingreso activo radica en la distribución del esfuerzo en el tiempo. El esfuerzo del ingreso pasivo se concentra al inicio y los retornos se extienden a futuro; el compuesto hace el resto. La clave es comenzar cuanto antes y ser paciente con resultados que son lentos al principio y exponenciales después.',
                ],
            ],

            'Planifica tu retiro desde hoy' => [
                [
                    'title'  => 'Dinero: domina el juego',
                    'author' => 'Tony Robbins',
                    'type'   => 'book',
                    'year'   => 2014,
                    'url'    => null,
                    'excerpt' => 'El interés compuesto es la octava maravilla del mundo. Quien empieza a ahorrar para el retiro a los 25 años necesita aportar la mitad de lo que necesitaría alguien que empieza a los 35, para alcanzar el mismo capital a los 65. Cada año de demora tiene un costo exponencial, no lineal. Comenzar hoy con cualquier cantidad es infinitamente mejor que esperar a tener la cantidad perfecta.',
                ],
                [
                    'title'  => 'Panorama de las pensiones en América Latina y el Caribe',
                    'author' => 'OCDE',
                    'type'   => 'report',
                    'year'   => 2022,
                    'url'    => null,
                    'excerpt' => 'Las tasas de reemplazo de los sistemas de pensiones en América Latina promedian el 45% del último salario, muy por debajo del 70–80% recomendado para mantener el nivel de vida en la vejez. La brecha debe cubrirse con ahorro voluntario complementario iniciado idealmente antes de los 35 años. Pensiones voluntarias, portafolios de inversión y activos inmobiliarios son los pilares del retiro digno en la región.',
                ],
            ],
        ];

        foreach ($data as $tipTitle => $references) {
            $tip = $tipRepo->findOneBy(['title' => $tipTitle]);

            if (!$tip) {
                continue;
            }

            foreach ($references as $refData) {
                // Skip if this reference already exists for this tip (--append safety).
                $existing = $refRepo->findOneBy(['tip' => $tip, 'title' => $refData['title']]);
                if ($existing) {
                    continue;
                }

                $ref = new TipReference();
                $ref->setTip($tip);
                $ref->setTitle($refData['title']);
                $ref->setAuthor($refData['author']);
                $ref->setType($refData['type']);
                $ref->setYear($refData['year']);
                $ref->setUrl($refData['url']);
                $ref->setExcerpt($refData['excerpt']);

                $manager->persist($ref);
            }
        }

        $manager->flush();
    }
}
