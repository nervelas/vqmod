<?php
/**
 * Public form handler — contact & admissions.
 * CSRF is already verified by the caller. Uses honeypot + prepared inserts.
 */

declare(strict_types=1);

function handle_public_form(string $slug): void
{
    $action = post('action');

    // Honeypot: bots fill the hidden "website" field.
    if (post('website') !== '') {
        flash('error', 'No se pudo procesar la solicitud.');
        redirect($slug);
    }

    if ($action === 'contacto') {
        _handle_contact($slug);
    } elseif ($action === 'admision') {
        _handle_admission($slug);
    } else {
        flash('error', 'Acción no válida.');
        redirect($slug);
    }
}

function _handle_contact(string $slug): void
{
    $name    = post('name');
    $email   = post('email');
    $phone   = post('phone');
    $subject = post('subject');
    $message = post('message');

    $errors = [];
    if (mb_strlen($name) < 2)       { $errors[] = 'Ingrese su nombre.'; }
    if (!is_email($email))          { $errors[] = 'Ingrese un correo válido.'; }
    if (mb_strlen($message) < 5)    { $errors[] = 'Ingrese un mensaje.'; }

    if ($errors) {
        $_SESSION['_old'] = $_POST;
        flash('error', implode(' ', $errors));
        redirect($slug);
    }

    Database::insert('submissions', [
        'type' => 'contacto',
        'data' => json_encode([
            'nombre'  => $name,
            'correo'  => $email,
            'telefono'=> $phone,
            'asunto'  => $subject,
            'mensaje' => $message,
        ], JSON_UNESCAPED_UNICODE),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    flash('success', '¡Gracias! Tu mensaje ha sido enviado. Te contactaremos pronto.');
    redirect($slug);
}

function _handle_admission(string $slug): void
{
    $fields = [
        'estudiante'        => post('estudiante'),
        'fecha_nacimiento'  => post('fecha_nacimiento'),
        'edad'              => post('edad'),
        'grado'             => post('grado'),
        'jornada'           => post('jornada'),
        'carrera'           => post('carrera'),
        'institucion_anterior' => post('institucion_anterior'),
        'encargado'         => post('encargado'),
        'telefono'          => post('telefono'),
        'correo'            => post('correo'),
        'direccion'         => post('direccion'),
        'como_conocio'      => post('como_conocio'),
    ];

    $errors = [];
    if (mb_strlen($fields['estudiante']) < 3) { $errors[] = 'Ingrese el nombre completo del estudiante.'; }
    if (mb_strlen($fields['encargado']) < 3)  { $errors[] = 'Ingrese el nombre del padre o encargado.'; }
    if ($fields['telefono'] === '')           { $errors[] = 'Ingrese un teléfono de contacto.'; }
    if ($fields['correo'] !== '' && !is_email($fields['correo'])) { $errors[] = 'Ingrese un correo válido.'; }
    if ($fields['grado'] === '')              { $errors[] = 'Indique el grado al que aplica.'; }

    if ($errors) {
        $_SESSION['_old'] = $_POST;
        flash('error', implode(' ', $errors));
        redirect($slug);
    }

    Database::insert('submissions', [
        'type' => 'admision',
        'data' => json_encode($fields, JSON_UNESCAPED_UNICODE),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
    ]);

    flash('success', '¡Solicitud recibida! Un asesor de admisiones te contactará pronto.');
    redirect($slug);
}
