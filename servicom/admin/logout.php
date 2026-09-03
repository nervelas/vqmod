<?php
declare(strict_types=1);
require __DIR__ . '/includes/admin.php';
Auth::logout();
flash('Sesión cerrada correctamente.');
redirect('admin/login.php');
