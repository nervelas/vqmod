<?php
declare(strict_types=1);

/**
 * Plantillas de mensajes predeterminadas. %1$s = nombre de la "cita" en minúscula según la profesión
 * (consulta, sesión, cita...). Variables disponibles: {nombre} {servicio} {fecha} {hora} {profesional} {direccion} {enlace} {negocio} {telefono}
 */
return static function (string $cita): array {
    $f = static fn(string $s): string => sprintf($s, $cita);
    return [
        'confirmation' => [
            'name' => 'Confirmación de ' . $cita, 'subject' => 'Tu ' . $cita . ' está confirmada · {fecha} {hora}',
            'email' => $f("Hola {nombre},\n\nTu %1\$s de {servicio} con {profesional} quedó confirmada.\n\nFecha: {fecha}\nHora: {hora}\nLugar: {direccion}\n\nPuedes confirmar, reprogramar o cancelar desde este enlace:\n{enlace}\n\nGracias por elegir {negocio}."),
            'wa' => $f("Hola {nombre}, tu %1\$s de {servicio} con {profesional} quedó confirmada para el {fecha} a las {hora}. Lugar: {direccion}. Gestiona tu %1\$s aquí: {enlace} — {negocio}"),
        ],
        'pending' => [
            'name' => 'Solicitud recibida (pendiente)', 'subject' => 'Recibimos tu solicitud de ' . $cita . ' · {fecha} {hora}',
            'email' => $f("Hola {nombre},\n\nRecibimos tu solicitud de %1\$s de {servicio} para el {fecha} a las {hora}. Está pendiente de confirmación y te avisaremos muy pronto.\n\nPuedes revisar o cancelar tu solicitud aquí:\n{enlace}\n\n{negocio}"),
            'wa' => $f("Hola {nombre}, recibimos tu solicitud de %1\$s de {servicio} para el {fecha} a las {hora}. Te confirmaremos pronto. Detalle: {enlace} — {negocio}"),
        ],
        'reminder_24h' => [
            'name' => 'Recordatorio 24 horas', 'subject' => 'Recordatorio: tu ' . $cita . ' es mañana a las {hora}',
            'email' => $f("Hola {nombre},\n\nTe recordamos tu %1\$s de {servicio} con {profesional}.\n\nFecha: {fecha}\nHora: {hora}\nLugar: {direccion}\n\nSi necesitas reprogramar o cancelar: {enlace}\n\n{negocio}"),
            'wa' => $f("Hola {nombre}, te recordamos tu %1\$s de {servicio} con {profesional}: {fecha} a las {hora}. Lugar: {direccion}. Reprogramar o cancelar: {enlace} — {negocio}"),
        ],
        'reminder_2h' => [
            'name' => 'Recordatorio 2 horas', 'subject' => 'Tu ' . $cita . ' es en 2 horas · {hora}',
            'email' => $f("Hola {nombre},\n\nTu %1\$s de {servicio} es hoy a las {hora}. Lugar: {direccion}.\n\nTe esperamos. {enlace}\n\n{negocio}"),
            'wa' => $f("Hola {nombre}, tu %1\$s de {servicio} es hoy a las {hora}. Lugar: {direccion}. ¡Te esperamos! — {negocio}"),
        ],
        'cancellation' => [
            'name' => 'Cancelación', 'subject' => 'Tu ' . $cita . ' fue cancelada',
            'email' => $f("Hola {nombre},\n\nTu %1\$s de {servicio} del {fecha} a las {hora} fue cancelada.\n\nSi deseas agendar una nueva, hazlo aquí: {enlace}\n\n{negocio}"),
            'wa' => $f("Hola {nombre}, tu %1\$s de {servicio} del {fecha} a las {hora} fue cancelada. Para agendar otra: {enlace} — {negocio}"),
        ],
        'reschedule' => [
            'name' => 'Reprogramación', 'subject' => 'Tu ' . $cita . ' fue reprogramada · {fecha} {hora}',
            'email' => $f("Hola {nombre},\n\nTu %1\$s de {servicio} con {profesional} quedó reprogramada.\n\nNueva fecha: {fecha}\nNueva hora: {hora}\nLugar: {direccion}\n\nGestiónala aquí: {enlace}\n\n{negocio}"),
            'wa' => $f("Hola {nombre}, tu %1\$s de {servicio} quedó reprogramada: {fecha} a las {hora}. Lugar: {direccion}. Detalle: {enlace} — {negocio}"),
        ],
        'followup' => [
            'name' => 'Seguimiento', 'subject' => '¿Cómo te fue en tu ' . $cita . '?',
            'email' => $f("Hola {nombre},\n\nGracias por tu visita a {negocio}. Esperamos que tu %1\$s de {servicio} haya sido de tu agrado. Si necesitas algo más, estamos para ayudarte: {enlace}"),
            'wa' => $f("Hola {nombre}, gracias por tu visita a {negocio}. ¿Cómo te fue en tu %1\$s de {servicio}? Si necesitas algo más, escríbenos. Agenda de nuevo: {enlace}"),
        ],
        'review_request' => [
            'name' => 'Solicitud de reseña', 'subject' => '¿Nos cuentas tu experiencia?',
            'email' => "Hola {nombre},\n\nTu opinión nos ayuda a mejorar y a que otras personas nos conozcan. ¿Nos regalas un minuto para calificar tu experiencia con {profesional}?\n\n{enlace}\n\nGracias, {negocio}",
            'wa' => "Hola {nombre}, ¿nos regalas un minuto para calificar tu experiencia con {profesional}? {enlace} — {negocio}",
        ],
        'waitlist_offer' => [
            'name' => 'Lista de espera: horario liberado', 'subject' => 'Se liberó un horario para ti · {fecha} {hora}',
            'email' => $f("Hola {nombre},\n\nSe liberó un horario para {servicio}: {fecha} a las {hora} con {profesional}.\n\nResérvalo antes de que otra persona lo tome (disponible por tiempo limitado):\n{enlace}\n\n{negocio}"),
            'wa' => $f("Hola {nombre}, se liberó un horario para {servicio}: {fecha} a las {hora} con {profesional}. Resérvalo aquí (por tiempo limitado): {enlace} — {negocio}"),
        ],
        'staff_new' => [
            'name' => 'Aviso interno: nueva ' . $cita, 'subject' => 'Nueva ' . $cita . ': {nombre} · {fecha} {hora}',
            'email' => "Nueva {servicio} agendada.\n\nCliente: {nombre} ({telefono})\nProfesional: {profesional}\nFecha: {fecha} {hora}\n\nVer en el panel: {enlace}",
            'wa' => '',
        ],
    ];
};
