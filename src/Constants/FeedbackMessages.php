<?php

namespace App\Constants;

class FeedbackMessages
{
    // Ingreso
    public const INCOME_REGISTERED = "Registraste un ingreso de %s COP. Este mes llevas %s COP en ingresos. Cada registro cuenta para tener un panorama completo de tus finanzas.";

    // Gasto sin categoría
    public const EXPENSE_NO_CATEGORY = "Gasto registrado. Sigue anotando tus movimientos — entre más datos tengas, mejor podrás entender tus hábitos.";

    // Gasto sin historial previo en la categoría
    public const EXPENSE_FIRST_TIME = "Es la primera vez que registras un gasto en %s. Sigue así y pronto tendrás una imagen clara de tus hábitos en esta categoría.";

    // Gasto menor al promedio histórico (delta < -10%)
    public const EXPENSE_BELOW_AVERAGE = "Este mes llevas %s COP en %s, menos de lo habitual. Tu promedio mensual es de %s COP. ¡Vas muy bien!";

    // Gasto similar al promedio histórico (delta entre -10% y +20%)
    public const EXPENSE_ON_TRACK = "Tu gasto en %s está en línea con lo habitual. Llevas %s COP este mes (promedio: %s COP).";

    // Gasto mayor al promedio histórico (delta > +20%)
    public const EXPENSE_ABOVE_AVERAGE = "Este mes llevas %s COP en %s. Tu promedio mensual es de %s COP. ¿Hubo algo especial que lo motivó?";
}