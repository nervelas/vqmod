<?php
namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Core\Qr;
use MenuGold\Core\Response;
use MenuGold\Core\Url;

/**
 * Generación de códigos QR.
 *
 * Solo se aceptan direcciones de esta misma instalación: así el generador
 * no se puede usar para fabricar códigos que apunten a otros sitios.
 */
class QrController extends Controller
{
    public function png()
    {
        $data = $this->request->str('d', '');
        if ($data === '' || strpos($data, Url::root()) !== 0) {
            return Response::text('Dirección no permitida.', 400);
        }
        $size = max(3, min(14, $this->request->int('s', 8)));
        $png = Qr::png($data, $size);
        if ($png === '') {
            return Response::text('No se pudo generar el código.', 500);
        }
        return Response::make($png, 200, array(
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ));
    }
}
