<?php
namespace MenuGold\Controllers;

use MenuGold\Core\Controller;
use MenuGold\Core\Qr;
use MenuGold\Core\Response;
use MenuGold\Core\Url;
use MenuGold\Models\Landing;

/**
 * Generación de códigos QR.
 * Solo se aceptan direcciones de esta misma instalación: así el
 * generador no se puede usar para códigos de terceros.
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

    /** QR del restaurante de demostración, para el sitio de venta. */
    public function demo()
    {
        $demo = Landing::demoRestaurant();
        $url = $demo ? Url::abs('/r/' . $demo['slug']) : Url::abs('/');
        $png = Qr::png($url, max(3, min(12, $this->request->int('s', 7))));
        return Response::make($png, 200, array(
            'Content-Type'  => 'image/png',
            'Cache-Control' => 'public, max-age=3600',
        ));
    }
}
