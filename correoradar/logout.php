<?php
/** CorreoRadar - Cierre de sesión. */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

Auth::salir();
cr_iniciar_sesion();
cr_flash('info', 'Has cerrado la sesión.');
cr_redirigir('index.php');
