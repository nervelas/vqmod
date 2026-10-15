<?php
declare(strict_types=1);
/* Punto de entrada 2 de 2 con PHP activo: guarda copia del formulario de contacto y avisa por correo. */
define('GX', 1);
define('GX_ROOT', __DIR__);
require __DIR__ . '/_core/lib/bootstrap.php';
require_once __DIR__ . '/_core/lib/install.php';
require_once __DIR__ . '/_core/lib/form.php';
gx_security_headers(true);
gx_ensure_installed();
gx_guard();
gx_form_handle();
