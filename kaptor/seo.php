<?php
/**
 * Kaptor - Análisis SEO.
 *
 * Es el mismo motor del auditor, puesto en modo SEO: en vez de quedarse en la
 * portada, recorre las páginas del sitio, comprueba todos sus enlaces uno a
 * uno y las compara entre sí. La página es la misma (auditor.php) porque lo
 * único que cambia son los textos y la profundidad.
 */
declare(strict_types=1);

$modo = 'seo';
require __DIR__ . '/auditor.php';
