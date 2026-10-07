<?php
/**
 * Kaptor - El correo tal como va a llegar.
 *
 * Devuelve el HTML final del mensaje para verlo dentro de un marco. Va en su
 * propia dirección y no dentro del panel a propósito: un correo trae su
 * propio <html> y sus propios estilos, y meterlo en la página los mezclaría
 * con los del panel. Dentro de un <iframe> se ve EXACTAMENTE lo que verá
 * quien lo reciba, ni más bonito ni más feo.
 *
 *   ?plantilla=N   la plantilla que se quiere ver
 *   &contacto=N    opcional: con los datos de un contacto de verdad
 *   &campana=N     opcional: toma el buzón que usará esa campaña
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

Auth::exigirAdmin();

$id = (int) cr_get('plantilla', 0);
$plantilla = $id > 0 ? BD::fila('SELECT * FROM `cr_plantillas` WHERE `id` = ?', [$id]) : null;

if (!$plantilla) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><p style="font:15px system-ui;padding:20px">Esa plantilla no existe.</p>');
}

// Un contacto de verdad de la lista, si se pide: así se ve con un nombre real.
$contacto = null;
$cid = (int) cr_get('contacto', 0);
if ($cid > 0) {
    $contacto = BD::fila('SELECT * FROM `cr_contactos` WHERE `id` = ?', [$cid]) ?: null;
}

// El buzón que usará la campaña, para que la firma sea la que será.
$remitente = null;
$camId = (int) cr_get('campana', 0);
if ($camId > 0) {
    $campana = BD::fila('SELECT * FROM `cr_campanas` WHERE `id` = ?', [$camId]);
    if ($campana) {
        $ids = Campana::remitentesDe($campana);
        if ($ids) {
            $remitente = BD::fila('SELECT * FROM `cr_remitentes` WHERE `id` = ?', [(int) $ids[0]]) ?: null;
        }
        if ($contacto === null) {
            $contacto = BD::fila(
                'SELECT * FROM `cr_contactos` WHERE `lista_id` = ? AND `activo` = 1 ORDER BY `id` LIMIT 1',
                [(int) $campana['lista_id']]
            ) ?: null;
        }
    }
}

$previa = Campana::vistaPrevia($plantilla, $contacto, $remitente);

// Se sirve en su propio documento. Sin permitir scripts: el cuerpo del correo
// lo escribe el usuario, pero el marco que lo enseña no tiene por qué confiar.
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src * data:; style-src \'unsafe-inline\'; font-src *');
echo $previa['html'];
