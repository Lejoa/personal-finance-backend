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
                [
                    'title'  => 'Glosario técnico: presupuesto y flujo de caja personal',
                    'author' => 'Equipo Educativo',
                    'type'   => 'essay',
                    'year'   => 2024,
                    'url'    => null,
                    'excerpt' => 'El flujo de caja personal es la diferencia entre el total de ingresos y el total de egresos durante un período determinado (generalmente un mes). Un flujo de caja positivo indica que los ingresos superan los gastos y queda excedente para ahorro o inversión; uno negativo indica que se está gastando más de lo que se gana, lo cual solo es sostenible recurriendo a ahorros previos o deuda. Los gastos se clasifican en fijos —montos recurrentes y relativamente estables como arriendo, servicios públicos o cuotas de crédito— y variables —fluctúan según el consumo, como alimentación, transporte o entretenimiento—. El punto de equilibrio personal es el nivel de ingreso en el que los gastos esenciales quedan exactamente cubiertos, sin excedente ni déficit; superar ese punto de forma sostenida es lo que permite empezar a ahorrar e invertir. La regla 50/30/20 es un método de presupuesto donde el 50% del ingreso neto se destina a necesidades básicas, el 30% a gastos discrecionales o estilo de vida, y el 20% a ahorro o pago de deudas.',
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
                [
                    'title'  => 'Glosario técnico: liquidez e instrumentos de ahorro',
                    'author' => 'Equipo Educativo',
                    'type'   => 'essay',
                    'year'   => 2024,
                    'url'    => null,
                    'excerpt' => 'La liquidez es la facilidad y rapidez con la que un activo puede convertirse en efectivo sin perder valor en el proceso. El efectivo es el activo más líquido por definición; un inmueble o una inversión a largo plazo son activos ilíquidos porque venderlos rápido implica descuentos de precio o tiempos de espera. Un fondo de emergencia debe mantenerse en instrumentos de alta liquidez, como una cuenta de ahorros tradicional, no en productos que inmovilizan el capital. Un CDT (Certificado de Depósito a Término) es un instrumento donde el ahorrador entrega su dinero al banco por un plazo fijo (30, 90, 180, 360 días) a cambio de una tasa de interés generalmente más alta que la cuenta de ahorros; retirar el dinero antes del vencimiento implica penalidades o pérdida de rendimientos, por lo que un CDT no es adecuado para un fondo de emergencia. Las cuentas de ahorro de alto rendimiento ofrecen una tasa mejor que la cuenta tradicional manteniendo liquidez inmediata, siendo la opción técnica recomendada para este propósito.',
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
                [
                    'title'  => 'Glosario técnico: costo del crédito y amortización',
                    'author' => 'Equipo Educativo',
                    'type'   => 'essay',
                    'year'   => 2024,
                    'url'    => null,
                    'excerpt' => 'La tasa de interés efectiva anual (E.A.) es el costo real de un crédito expresado en términos anuales, incluyendo el efecto de la capitalización periódica de intereses; es la cifra correcta para comparar el costo de distintos créditos, no la tasa nominal mensual. La amortización es el proceso mediante el cual se paga gradualmente una deuda: cada cuota se compone de una parte de interés (el costo de tener el dinero prestado) y una parte de abono a capital (que reduce el saldo de la deuda); al inicio del crédito la mayor parte de la cuota suele cubrir interés, y hacia el final cubre principalmente capital. El apalancamiento financiero es el uso de deuda para aumentar la capacidad de inversión o consumo más allá del capital propio disponible; es una herramienta poderosa cuando el activo financiado genera un retorno superior al costo de la deuda, y una trampa cuando no es así. Antes de endeudarse conviene calcular la cuota total, el plazo y el costo financiero total (CFT), que suma intereses, seguros y comisiones administrativas.',
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
                [
                    'title'  => 'Glosario técnico: categorización de gastos',
                    'author' => 'Equipo Educativo',
                    'type'   => 'essay',
                    'year'   => 2024,
                    'url'    => null,
                    'excerpt' => 'El presupuesto base cero es una técnica en la que cada peso del ingreso se asigna deliberadamente a una categoría específica —gasto, ahorro o inversión— antes de que comience el mes, de modo que ingresos menos asignaciones sea igual a cero; ningún peso queda "sin planificar". El gasto hormiga son erogaciones pequeñas, recurrentes y de bajo valor individual —el café diario, una suscripción de streaming olvidada, propinas— que pasan desapercibidas pero que, acumuladas en un mes o un año, representan un porcentaje significativo del presupuesto. Categorizar los gastos (vivienda, alimentación, transporte, entretenimiento, salud, deudas) permite calcular qué porcentaje del ingreso consume cada rubro y compararlo con referencias saludables, como la regla 50/30/20, para identificar desviaciones antes de que se conviertan en un problema estructural.',
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
                [
                    'title'  => 'Glosario técnico: costo de oportunidad',
                    'author' => 'Equipo Educativo',
                    'type'   => 'essay',
                    'year'   => 2024,
                    'url'    => null,
                    'excerpt' => 'El costo de oportunidad es el valor de la mejor alternativa a la que se renuncia al tomar una decisión económica. Cada compra no planificada tiene un costo de oportunidad: el mismo dinero podría destinarse al fondo de emergencia, a abonar una deuda con interés o a una inversión que crece con el tiempo mediante interés compuesto. Los sesgos cognitivos son atajos mentales sistemáticos que distorsionan decisiones racionales; en el consumo, el sesgo de disponibilidad hace sobreestimar la importancia de una compra por estar recién expuestos a ella (publicidad, redes sociales), y el sesgo de descuento hiperbólico lleva a preferir una recompensa inmediata pequeña —la compra ahora— sobre una recompensa mayor pero futura —el ahorro—. Reconocer estos sesgos es la base de la regla de las 24 horas: crear una pausa deliberada que le da tiempo al razonamiento consciente de evaluar el verdadero costo de oportunidad de la compra.',
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
                [
                    'title'  => 'Glosario técnico: acciones, bonos y fondos indexados',
                    'author' => 'Equipo Educativo',
                    'type'   => 'essay',
                    'year'   => 2024,
                    'url'    => null,
                    'excerpt' => 'Una acción es un título que representa una fracción de propiedad de una empresa; quien la posee tiene derecho a una parte de las utilidades (vía dividendos) y se beneficia si el precio de la acción sube en el mercado, pero también asume el riesgo de perder valor si la empresa o el mercado caen. Las acciones pertenecen a la categoría de renta variable porque su retorno no está garantizado ni es predecible. Un bono es un instrumento de deuda: el inversionista le presta dinero a un gobierno o empresa (el emisor) a cambio de pagos periódicos de interés, llamados cupones, y la devolución del capital (principal) al vencimiento del plazo pactado. Los bonos pertenecen a la renta fija porque, salvo incumplimiento del emisor, sus pagos son conocidos de antemano; por eso suelen considerarse menos riesgosos que las acciones, aunque también ofrecen menor retorno esperado en el largo plazo. Un fondo indexado es un vehículo de inversión colectiva que replica pasivamente la composición de un índice de mercado —por ejemplo el S&P 500 en Estados Unidos o el COLCAP en Colombia— comprando proporcionalmente todas las acciones que lo componen. Esto ofrece diversificación instantánea (el riesgo de una sola empresa se diluye entre cientos) a un costo de administración mucho menor que un fondo gestionado activamente. La relación riesgo-retorno es el principio central de la inversión: instrumentos con mayor potencial de ganancia (acciones) exhiben también mayor volatilidad y riesgo de pérdida, mientras que instrumentos más estables (bonos, CDT) ofrecen retornos más modestos pero predecibles; el portafolio ideal combina ambos según el horizonte de tiempo y la tolerancia al riesgo del inversionista.',
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
                [
                    'title'  => 'Glosario técnico: productos digitales vs. productos físicos',
                    'author' => 'Equipo Educativo',
                    'type'   => 'essay',
                    'year'   => 2024,
                    'url'    => null,
                    'excerpt' => 'Un producto digital es un bien intangible que se entrega y consume en formato electrónico —cursos en línea, ebooks, plantillas, software, membresías de contenido— y cuyo costo marginal de producir una unidad adicional es cercano a cero una vez creado: vender la copia número 1.000 no cuesta mucho más que vender la primera. Esto le da a los productos digitales una escalabilidad muy alta y márgenes de ganancia elevados, aunque requiere una inversión inicial de tiempo y conocimiento para crearlos y suele depender de canales digitales (redes sociales, marketplaces, email) para llegar a los clientes. Un producto físico es un bien tangible que implica costos de materia prima, fabricación, empaque, inventario y logística de envío por cada unidad vendida; los márgenes suelen ser menores porque el costo variable por unidad no desaparece, pero a menudo tiene una percepción de valor y una disposición a pagar más alta por parte del cliente. El ingreso activo requiere intercambiar tiempo de trabajo continuo por dinero (un salario, un servicio de freelance); el ingreso pasivo sigue generando flujo de caja con poca intervención continua una vez creado el activo (regalías de un producto digital, dividendos, rentas). Diversificar fuentes de ingreso implica combinar ingresos activos y pasivos, y productos digitales y físicos, para reducir la dependencia de una sola fuente de flujo de caja.',
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
                [
                    'title'  => 'Glosario técnico: horizonte de inversión y perfil de riesgo',
                    'author' => 'Equipo Educativo',
                    'type'   => 'essay',
                    'year'   => 2024,
                    'url'    => null,
                    'excerpt' => 'El horizonte de inversión es el tiempo que falta antes de que el inversionista necesite disponer del capital invertido. Un horizonte corto (menos de 2 años, como ahorrar para unas vacaciones) exige instrumentos conservadores y líquidos —cuenta de ahorros, CDT a corto plazo— porque no hay tiempo de recuperarse de una caída del mercado. Un horizonte largo (10 años o más, como el retiro) permite asumir más riesgo con instrumentos de renta variable —acciones, fondos indexados— porque la volatilidad de corto plazo tiende a diluirse y compensarse con mayor retorno esperado en el tiempo. El perfil de riesgo o tolerancia al riesgo es la capacidad y disposición de una persona para soportar pérdidas temporales en su portafolio a cambio de un mayor retorno potencial; se clasifica típicamente en conservador, moderado y agresivo, y depende tanto de factores objetivos (edad, ingresos, obligaciones) como subjetivos (tranquilidad emocional ante la volatilidad). Un portafolio bien construido combina el horizonte de tiempo de cada meta financiera con el perfil de riesgo del inversionista y con diversificación entre renta fija y renta variable.',
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
                [
                    'title'  => 'Glosario técnico: dividendos, renta fija y regalías',
                    'author' => 'Equipo Educativo',
                    'type'   => 'essay',
                    'year'   => 2024,
                    'url'    => null,
                    'excerpt' => 'Un dividendo es la porción de las utilidades de una empresa que la junta directiva decide distribuir periódicamente entre sus accionistas, en lugar de reinvertirla en el negocio; no todas las empresas pagan dividendos —algunas prefieren reinvertir todo el capital para crecer más rápido— por lo que es una decisión que varía según la estrategia de cada compañía. La renta fija agrupa instrumentos —bonos, CDT— cuyos pagos de interés y fecha de devolución del capital se conocen de antemano, ofreciendo previsibilidad. La renta variable agrupa instrumentos —acciones, fondos accionarios, fondos indexados— cuyo retorno depende del desempeño del mercado y no está garantizado, pudiendo generar tanto ganancias como pérdidas. Una regalía es un pago recurrente que recibe el propietario de un activo de propiedad intelectual —un libro, una canción, una patente, un curso digital— cada vez que un tercero lo usa, reproduce o vende bajo licencia; es una forma clásica de ingreso pasivo porque el trabajo de crear el activo ya se hizo una vez, y los pagos continúan mientras el activo siga generando valor.',
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
                [
                    'title'  => 'Glosario técnico: pensión obligatoria, voluntaria y tasa de reemplazo',
                    'author' => 'Equipo Educativo',
                    'type'   => 'essay',
                    'year'   => 2024,
                    'url'    => null,
                    'excerpt' => 'El fondo de pensiones obligatorio recibe un porcentaje del salario que, por ley, todo trabajador formal debe aportar durante su vida laboral, y financia la mesada pensional al momento del retiro. La pensión voluntaria consiste en aportes adicionales, opcionales, que una persona hace por encima del aporte obligatorio a un fondo de pensiones o a otro vehículo de inversión de largo plazo, generalmente con beneficios tributarios (deducción de renta) que incentivan este ahorro complementario. La tasa de reemplazo es el porcentaje del último salario que efectivamente cubre la pensión recibida; si alguien gana 4.000.000 COP mensuales y su pensión es de 1.800.000 COP, la tasa de reemplazo es del 45%. Cuando la tasa de reemplazo del sistema obligatorio es insuficiente para mantener el nivel de vida deseado en el retiro, la brecha debe cerrarse con ahorro voluntario, portafolios de inversión diversificados (renta fija y variable) o activos generadores de renta, iniciados con la mayor anticipación posible para maximizar el efecto del interés compuesto.',
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
