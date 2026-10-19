<?php
/**
 * ==========================================================================
 * includes/router.php
 * --------------------------------------------------------------------------
 * A very small front-controller router.
 *
 *   GET /                     -> dashboard/index.php
 *   POST /outlets/register    -> outlets/register.php
 *   POST /production/run      -> production/run.php
 *   POST /flour/order         -> flour/order.php
 *
 * The rules are simple:
 *   "/outlets/register"  tries  outlets/register.php
 *   "/dashboard"         tries  dashboard.php  (does not exist)
 *                          then  dashboard/index.php
 * ==========================================================================
 */

/**
 * Translate a route (URL path) into a real PHP file inside the project.
 *
 * @param string $route e.g. "/outlets/register"
 * @return string|null  full file path, or null when the route does not exist
 */
function resolve_route(string $route): ?string
{
    // remove query string / fragments and normalise the slashes
    $route = strtok($route, '?');
    $route = str_replace('\\', '/', (string) $route);
    $route = trim($route, '/');

    // Reject anything that looks like a path traversal (../) or contains
    // characters we never use in our routes.
    if ($route !== '' && !preg_match('#^[a-zA-Z0-9_\-/]+$#', $route)) {
        return null;
    }
    if (strpos($route, '..') !== false) {
        return null;
    }

    // "/" is the dashboard
    if ($route === '') {
        $route = 'dashboard';
    }

    $candidates = [
        APP_ROOT . '/' . $route . '.php',       // /outlets/register
        APP_ROOT . '/' . $route . '/index.php', // /outlets  or  /
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) {
            // Final safety check: the file must live inside the project
            $real = realpath($file);
            $root = realpath(APP_ROOT);
            if ($real !== false && $root !== false && strpos($real, $root) === 0) {
                return $real;
            }
        }
    }

    return null;
}
