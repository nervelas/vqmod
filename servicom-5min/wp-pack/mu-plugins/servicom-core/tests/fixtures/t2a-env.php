<?php
// SOLO PRUEBAS: simula wordpress.org (sin red) para no ensuciar el log.
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, 'wordpress.org') !== false) {
        return ['headers' => [], 'body' => json_encode(['translations' => [], 'plugins' => [], 'themes' => [], 'no_update' => [], 'offers' => []]), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    }
    return $pre;
}, 10, 3);
