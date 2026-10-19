<?php
/**
 * ==========================================================================
 * includes/init.php
 * --------------------------------------------------------------------------
 * This file is required at the very top of EVERY page of the application.
 * It loads the configuration, the database connection and all helpers.
 * ==========================================================================
 */

// ------------------------------------------------------------------
// Error reporting (turn APP_DEBUG to false in config/config.php for
// a public/deployment build)
// ------------------------------------------------------------------
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// ------------------------------------------------------------------
// Base folders
// ------------------------------------------------------------------
define('APP_ROOT', dirname(__DIR__));               // project folder
define('APP_INCLUDES', APP_ROOT . '/includes');

// ------------------------------------------------------------------
// Configuration + helpers
// ------------------------------------------------------------------
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/auth.php';

// ------------------------------------------------------------------
// Detect the base URL of the project (works in any folder name)
// e.g. http://localhost/bakey-ledgerr
// ------------------------------------------------------------------
define('APP_BASE', detect_base_url());

// ------------------------------------------------------------------
// Session (needed for flash messages and the demo login/IDOR demo)
// ------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone used for production dates / delivery timestamps
date_default_timezone_set('Africa/Lagos');
