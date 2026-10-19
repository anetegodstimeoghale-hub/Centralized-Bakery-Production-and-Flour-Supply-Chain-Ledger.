<?php
/**
 * ==========================================================================
 * index.php  -  FRONT CONTROLLER (single entry point + router)
 * ==========================================================================
 * Every clean URL of the application is forwarded here by the .htaccess
 * file, for example:
 *
 *   POST /outlets/register   ->  index.php?route=/outlets/register
 *   POST /production/run     ->  index.php?route=/production/run
 *   POST /flour/order        ->  index.php?route=/flour/order
 *   GET  /dashboard          ->  index.php?route=/dashboard
 *
 * The router simply includes the matching file.
 * (Every page can ALSO be opened directly, e.g. /outlets/index.php)
 * ==========================================================================
 */

require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/router.php';

// Where do we want to go?
$route = $_GET['route'] ?? '/';

// Support Apache PATH_INFO as well (index.php/outlets/register)
if ($route === '/' && !empty($_SERVER['PATH_INFO'])) {
    $route = $_SERVER['PATH_INFO'];
}

$file = resolve_route($route);

if ($file === null) {
    http_response_code(404);
    require __DIR__ . '/includes/header.php';
    echo '<div class="row justify-content-center"><div class="col-lg-8 text-center py-5">';
    echo '<h1 class="display-5">404</h1>';
    echo '<p class="lead">The page <code>' . e($route) . '</code> does not exist.</p>';
    echo '<a class="btn btn-primary" href="' . e(url('')) . '">Go to the dashboard</a>';
    echo '</div></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Run the requested page / endpoint
require $file;
