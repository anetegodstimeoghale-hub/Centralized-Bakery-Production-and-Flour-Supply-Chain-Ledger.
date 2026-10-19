<?php
/**
 * ==========================================================================
 * router.php  -  OPTIONAL development router for PHP's built-in web server
 * ==========================================================================
 * Laragon (Apache) uses .htaccess for the clean URLs. If you ever want to
 * run the project WITHOUT Apache, the built-in PHP server can use this file
 * as its router, which does exactly the same job:
 *
 *     php -S localhost:8000 router.php
 *
 * It is only a development helper - the application itself never uses it.
 * ==========================================================================
 */

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = $path === '' ? '/' : $path;

// Real files (assets, css, js, direct .php pages) are returned to the server.
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

// Real folders (for example /outlets/) are handled by the server as well.
if ($path !== '/' && is_dir(__DIR__ . $path)) {
    return false;
}

// Everything else is a route for the front controller.
$_GET['route'] = '/' . trim($path, '/');

require __DIR__ . '/index.php';
