<?php

namespace App\Constants;

class FeedbackMessages
{
    // Income: user has just recorded a new income transaction.
    public const INCOME_REGISTERED = 'Registraste un ingreso de %s COP. Este mes llevas %s COP en ingresos. Cada registro cuenta para tener un panorama completo de tus finanzas.';

    // Expense without category: transaction recorded but not yet classified.
    public const EXPENSE_NO_CATEGORY = 'Gasto registrado. Sigue anotando tus movimientos — entre más datos tengas, mejor podrás entender tus hábitos.';

    // Expense first time in category: no prior history exists for comparison.
    public const EXPENSE_FIRST_TIME = 'Es la primera vez que registras un gasto en %s. Sigue así y pronto tendrás una imagen clara de tus hábitos en esta categoría.';

    // Expense below historical average: current month total is more than 10% under the average.
    public const EXPENSE_BELOW_AVERAGE = 'Este mes llevas %s COP en %s, menos de lo habitual. Tu promedio mensual es de %s COP. ¡Vas muy bien!';

    // Expense on track: delta between -10% and +20% of the historical average.
    public const EXPENSE_ON_TRACK = 'Tu gasto en %s está en línea con lo habitual. Llevas %s COP este mes (promedio: %s COP).';

    // Expense above historical average: current month total exceeds the average by more than 20%.
    public const EXPENSE_ABOVE_AVERAGE = 'Este mes llevas %s COP en %s. Tu promedio mensual es de %s COP. ¿Hubo algo especial que lo motivó?';

    // Budget first time: the user has never set a budget for this category before.
    public const BUDGET_FIRST_TIME = 'Primera vez que presupuestas la categoría %s. ¡Buen comienzo para tomar control de tus finanzas!';

    // Budget below average: limit is set lower than the 3-month average spend (savings intent).
    public const BUDGET_BELOW_AVERAGE = 'Fijaste un límite de %s COP en %s, por debajo de tu gasto promedio de %s COP en los últimos 3 meses. ¡Buen propósito de ahorro!';

    // Budget above average: limit is set higher than the 3-month average spend.
    public const BUDGET_ABOVE_AVERAGE = 'Fijaste un límite de %s COP en %s, por encima de tu gasto promedio de %s COP en los últimos 3 meses. ¿Planeas gastar más este mes?';
}
