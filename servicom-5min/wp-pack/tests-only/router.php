<?php
// SOLO PARA PRUEBAS: router de php -S para permalinks /%postname%/ (equivale al .htaccess de WordPress).
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$f = $_SERVER['DOCUMENT_ROOT'] . $p;
if ($p !== '/' && is_file($f)) {
	return false;
}
if (is_dir($f) && is_file(rtrim($f, '/') . '/index.php') && $p !== '/') {
	chdir($f);
	require rtrim($f, '/') . '/index.php';
	return true;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . '/index.php';
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
