<?php
/**
 * Front controller — public website router.
 * Clean URLs are rewritten to index.php?p=<slug> by .htaccess.
 */

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

// Load page/content models.
require_once __DIR__ . '/includes/Content.php';

$slug = get('p');
if ($slug === '') {
    $slug = current_slug();
}
$slug = trim($slug, '/');
if ($slug === '' || $slug === 'index.php') {
    $slug = 'inicio';
}

// Maintenance mode (still lets admins browse if logged in).
if (Settings::bool('maintenance_mode') && !Auth::check()) {
    http_response_code(503);
    require __DIR__ . '/templates/maintenance.php';
    exit;
}

// Handle form submissions posted to the public site.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verifyPost();
    require __DIR__ . '/includes/form_handler.php';
    handle_public_form($slug);
    // handle_public_form redirects; fallthrough is a safety net.
}

// Gallery album detail: galeria/<album-slug>
$albumSlug = null;
if (strpos($slug, 'galeria/') === 0) {
    $albumSlug = substr($slug, strlen('galeria/'));
    $slug = 'galeria';
}

$page = Content::page($slug);

if (!$page) {
    http_response_code(404);
    $page = ['title' => 'Página no encontrada', 'template' => '404', 'h1' => 'Página no encontrada'];
    require __DIR__ . '/templates/layout/header.php';
    require __DIR__ . '/templates/404.php';
    require __DIR__ . '/templates/layout/footer.php';
    exit;
}

$sections = Content::sections((int)$page['id']);
$GLOBALS['current_album_slug'] = $albumSlug;

$template = $page['template'] ?? 'page';
$templateFile = __DIR__ . '/templates/' . basename($template) . '.php';
if (!file_exists($templateFile)) {
    $templateFile = __DIR__ . '/templates/page.php';
}

require __DIR__ . '/templates/layout/header.php';
require $templateFile;
require __DIR__ . '/templates/layout/footer.php';
