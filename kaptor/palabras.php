<?php
/**
 * Kaptor - Palabras clave del sitio.
 *
 * Es el mismo motor del auditor en modo «claves»: abre la portada, busca el
 * mapa del sitio y recorre todas las páginas, pero sin medir velocidad, sin
 * comprobar enlaces y sin llamar a Google. Solo lee lo que cada página tiene
 * escrito como palabras clave.
 */
declare(strict_types=1);

$modo = 'claves';
require __DIR__ . '/auditor.php';
