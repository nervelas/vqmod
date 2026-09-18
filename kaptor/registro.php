<?php
/**
 * Kaptor - El registro público no existe.
 *
 * Las cuentas se crean únicamente desde el panel (Panel > Usuarios). Este
 * archivo se mantiene, y no se borra, porque al actualizar Kaptor se
 * descomprime el ZIP encima de la instalación anterior: así el formulario
 * de registro que existía en versiones antiguas queda sustituido por esta
 * redirección y no puede volver a usarse.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

cr_redirigir('login.php');
