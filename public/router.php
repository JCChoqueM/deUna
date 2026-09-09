<?php
/**
 * DeUna - PHP Router para servidor built-in
 * Reescribe todas las URLs no estáticas a index.php
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$ext = pathinfo($uri, PATHINFO_EXTENSION);

// Serve static files directly
if ($ext !== '' && file_exists(__DIR__ . '/' . $uri)) {
    return false;
}

// Parse URL path and set $_GET['url']
// e.g., /paquetes/crear/123 → $_GET['url'] = 'paquetes/crear/123'
$uri = ltrim($uri, '/');
if ($uri !== '') {
    $_GET['url'] = $uri;
}

// Load the front controller (use require, not require_once,
// so it executes for every request in the PHP built-in server)
require __DIR__ . '/index.php';
