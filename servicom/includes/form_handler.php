<?php
declare(strict_types=1);

/**
 * Procesa el formulario de contacto: CSRF, honeypot, validacion y guardado.
 * Define $FORM (estado) para las plantillas.
 */

$FORM = ['sent' => false, 'errors' => [], 'old' => [], 'message' => ''];

if (is_post() && post('form') === 'contacto') {
    Csrf::verify();

    $old = [
        'nombre'   => post('nombre'),
        'email'    => post('email'),
        'telefono' => post('telefono'),
        'servicio' => post('servicio'),
        'asunto'   => post('asunto'),
        'mensaje'  => post('mensaje'),
    ];
    $FORM['old'] = $old;
    $errors = [];

    // Trampa antispam (campo oculto que un humano nunca completa).
    if (post('website') !== '' || post('empresa_fax') !== '') {
        $FORM['sent'] = true;
        $FORM['message'] = Settings::get('form_success', 'Gracias por su mensaje.');
        return;
    }

    // Tiempo minimo de llenado (defensa contra bots).
    $started = (int) ($_SESSION['_form_started'] ?? 0);
    if ($started > 0 && (time() - $started) < 2) {
        $errors['mensaje'] = 'Envío demasiado rápido. Intente nuevamente.';
    }

    if (mb_strlen($old['nombre']) < 2) {
        $errors['nombre'] = 'Escriba su nombre.';
    }
    if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Escriba un correo electrónico válido.';
    }
    if ($old['telefono'] !== '' && strlen(digits($old['telefono'])) < 8) {
        $errors['telefono'] = 'Escriba un teléfono válido.';
    }
    if (mb_strlen($old['mensaje']) < 10) {
        $errors['mensaje'] = 'Cuéntenos un poco más sobre su proyecto (mínimo 10 caracteres).';
    }
    if (mb_strlen($old['mensaje']) > 4000) {
        $errors['mensaje'] = 'El mensaje es demasiado largo.';
    }

    if ($errors !== []) {
        $FORM['errors']  = $errors;
        $FORM['message'] = Settings::get('form_error', 'Revise los campos marcados.');
    } else {
        try {
            Database::insert('submissions', [
                'name'    => $old['nombre'],
                'email'   => $old['email'],
                'phone'   => $old['telefono'],
                'service' => $old['servicio'],
                'subject' => $old['asunto'] !== '' ? $old['asunto'] : 'Solicitud desde el sitio web',
                'message' => $old['mensaje'],
                'page'    => substr((string) ($_SERVER['REQUEST_URI'] ?? '/'), 0, 200),
                'ip'      => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 60),
                'is_read' => 0,
                'created_at' => Database::now(),
            ]);
        } catch (Throwable $ex) {
            error_log('[Servicom] No se pudo guardar el mensaje: ' . $ex->getMessage());
        }

        // Aviso por correo (no bloquea el exito del formulario si falla).
        $to = Settings::get('email', defined('MAIL_TO') ? MAIL_TO : '');
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $subject = '[Sitio web] ' . ($old['asunto'] !== '' ? $old['asunto'] : 'Nueva solicitud de cotización');
            $bodyMail = "Nueva solicitud recibida desde " . SITE_URL . "\n\n"
                . "Nombre: {$old['nombre']}\n"
                . "Correo: {$old['email']}\n"
                . "Teléfono: {$old['telefono']}\n"
                . "Servicio: {$old['servicio']}\n\n"
                . "Mensaje:\n{$old['mensaje']}\n";
            $from    = defined('MAIL_FROM') && MAIL_FROM !== '' ? MAIL_FROM : 'no-reply@' . (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');
            $headers = "From: Sitio web <{$from}>\r\n"
                . 'Reply-To: ' . str_replace(["\r", "\n"], '', $old['email']) . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n";
            @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyMail, $headers);
        }

        $FORM['sent']    = true;
        $FORM['old']     = [];
        $FORM['message'] = Settings::get('form_success', 'Gracias por su mensaje.');
    }
}

$_SESSION['_form_started'] = time();
