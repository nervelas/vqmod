<?php
/**
 * Servicom — Punto de entrada unico del sitio publico.
 */
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/form_handler.php';

// ---------------------------------------------------------------- Ruta ------
$route = (string) ($_GET['route'] ?? '');
if ($route === '') {
    $uri   = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';
    if (BASE_PATH !== '' && str_starts_with($uri, BASE_PATH)) {
        $uri = substr($uri, strlen(BASE_PATH));
    }
    $route = trim($uri, '/');
}
$route   = trim(preg_replace('#[^a-zA-Z0-9/_\-.]#', '', $route) ?? '', '/');
$segments = $route === '' ? [] : explode('/', $route);

// ------------------------------------------------------------ Sitemap -------
if ($route === 'sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    exit;
}

// -------------------------------------------------- Modo mantenimiento ------
if (Settings::bool('maintenance') && !Auth::check()) {
    http_response_code(503);
    header('Retry-After: 3600');
    $view = 'maintenance';
    require __DIR__ . '/templates/maintenance.php';
    exit;
}

// --------------------------------------------------------- Resolucion -------
$view    = '404';
$page    = null;
$service = null;
$postRow = null;
$status  = 200;

if ($segments === []) {
    $page = Content::page('inicio');
    $view = 'home';
} elseif ($segments[0] === 'servicios') {
    if (count($segments) === 1) {
        $page = Content::page('servicios');
        $view = 'services';
    } else {
        $service = Content::service($segments[1]);
        if ($service !== null) {
            $view = 'service';
        }
    }
} elseif ($segments[0] === 'actualidad-web') {
    if (count($segments) === 1) {
        $page = Content::page('actualidad-web');
        $view = 'blog';
    } else {
        $postRow = Content::post($segments[1]);
        if ($postRow !== null) {
            $view = 'post';
        }
    }
} elseif (count($segments) === 1) {
    $page = Content::page($segments[0]);
    if ($page !== null) {
        $tpl  = (string) $page['template'];
        $view = in_array($tpl, ['home', 'services', 'about', 'portfolio', 'blog', 'contact', 'page'], true) ? $tpl : 'page';
        if ($view === 'home') {
            $view = 'page';
        }
    }
}

if ($view === '404') {
    $status = 404;
    http_response_code(404);
}

// ------------------------------------------------------------- SEO ----------
Seo::set(['type' => $view === 'post' ? 'article' : 'website']);

if ($page !== null) {
    Seo::set([
        'title'       => $page['meta_title'] ?: $page['title'],
        'description' => $page['meta_description'] ?: excerpt((string) $page['body'], 180),
        'keywords'    => $page['meta_keywords'],
        'image'       => $page['og_image'] ?: '',
        'robots'      => $page['robots'] ?: '',
        'canonical'   => $page['slug'] === 'inicio' ? url() : url($page['slug'] . '/'),
    ]);
    if ($page['slug'] !== 'inicio') {
        Seo::breadcrumbs([
            ['name' => 'Inicio', 'url' => '/'],
            ['name' => (string) $page['title'], 'url' => $page['slug'] . '/'],
        ]);
    }
}

if ($service !== null) {
    Seo::set([
        'title'       => $service['meta_title'] ?: $service['title'],
        'description' => $service['meta_description'] ?: $service['excerpt'],
        'keywords'    => $service['meta_keywords'],
        'image'       => $service['image'] ?: '',
        'canonical'   => url('servicios/' . $service['slug'] . '/'),
    ]);
    Seo::breadcrumbs([
        ['name' => 'Inicio', 'url' => '/'],
        ['name' => 'Servicios', 'url' => 'servicios/'],
        ['name' => (string) $service['title'], 'url' => 'servicios/' . $service['slug'] . '/'],
    ]);
    Seo::addSchema([
        '@type' => 'Service',
        'name'  => $service['title'],
        'description' => $service['excerpt'],
        'serviceType' => $service['title'],
        'provider'    => ['@id' => url('#business')],
        'areaServed'  => ['@type' => 'Country', 'name' => 'Guatemala'],
    ]);
}

if ($postRow !== null) {
    Seo::set([
        'title'       => $postRow['meta_title'] ?: $postRow['title'],
        'description' => $postRow['meta_description'] ?: $postRow['excerpt'],
        'keywords'    => $postRow['meta_keywords'],
        'image'       => $postRow['image'] ?: '',
        'canonical'   => url('actualidad-web/' . $postRow['slug'] . '/'),
    ]);
    Seo::breadcrumbs([
        ['name' => 'Inicio', 'url' => '/'],
        ['name' => 'Actualidad Web', 'url' => 'actualidad-web/'],
        ['name' => (string) $postRow['title'], 'url' => 'actualidad-web/' . $postRow['slug'] . '/'],
    ]);
    Seo::addSchema([
        '@type'         => 'BlogPosting',
        'headline'      => $postRow['title'],
        'description'   => $postRow['excerpt'],
        'datePublished' => date('c', strtotime((string) $postRow['published_at']) ?: time()),
        'author'        => ['@type' => 'Organization', 'name' => $postRow['author'] ?: Settings::get('site_name')],
        'publisher'     => ['@id' => url('#business')],
        'mainEntityOfPage' => url('actualidad-web/' . $postRow['slug'] . '/'),
        'inLanguage'    => 'es-GT',
    ]);
}

if ($view === '404') {
    Seo::set(['title' => 'Página no encontrada', 'robots' => 'noindex, follow']);
}

// --------------------------------------------------------- Renderizado ------
$templateFile = __DIR__ . '/templates/' . $view . '.php';
if (!is_file($templateFile)) {
    $templateFile = __DIR__ . '/templates/404.php';
}

// El cuerpo se genera primero para que las secciones puedan aportar sus datos
// estructurados (FAQ, servicios, artículos) antes de imprimir el <head>.
ob_start();
require $templateFile;
$bodyHtml = ob_get_clean();

require __DIR__ . '/templates/layout/header.php';
echo $bodyHtml;
require __DIR__ . '/templates/layout/footer.php';
