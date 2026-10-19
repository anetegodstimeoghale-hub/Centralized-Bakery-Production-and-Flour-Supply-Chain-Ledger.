<?php
/**
 * ==========================================================================
 * config/config.php
 * --------------------------------------------------------------------------
 * Central configuration for the bakery application.
 * Change the database settings below if your Laragon MySQL uses a
 * different user/password.
 * ==========================================================================
 */

/* ---------------------------------------------------------------------
 * DATABASE CONNECTION
 * ------------------------------------------------------------------ */
define('DB_HOST', '127.0.0.1');   // Laragon MySQL host (localhost also works)
define('DB_PORT', '3306');        // default MySQL port
define('DB_NAME', 'bakery_ledger');
define('DB_USER', 'root');
define('DB_PASS', '');            // Laragon default: empty password
define('DB_CHARSET', 'utf8mb4');

/* ---------------------------------------------------------------------
 * BUSINESS CONSTANTS (the formulas used by the assignment)
 * ------------------------------------------------------------------ */

// PLANNED PRODUCTION = total outlet demand x 1.10
// The extra 10% is the MANDATORY production buffer.
define('PRODUCTION_BUFFER_RATE', 1.10);

// REORDER POINT = daily flour usage x supplier lead time x 1.20
// The extra 20% is the safety stock factor.
define('REORDER_SAFETY_RATE', 1.20);

// If waste % is greater than this value we show "INVESTIGATION REQUIRED"
define('WASTE_ALERT_PERCENT', 5.0);

// Number of past days used to calculate "average daily flour usage"
define('FLOUR_USAGE_WINDOW_DAYS', 7);

/* ---------------------------------------------------------------------
 * MONEY
 * ------------------------------------------------------------------
 * Every money amount in this project is stored in KOBBO (1 Naira = 100 kobo)
 * inside the database, e.g. 45000 kobo = 450.00 Naira.
 * We never store floating point money in the database.
 * ------------------------------------------------------------------ */
define('CURRENCY_SYMBOL', "\u{20A6}"); // Naira sign

/* ---------------------------------------------------------------------
 * URL / ROUTING
 * ------------------------------------------------------------------
 * USE_CLEAN_URLS = true  ->  forms post to  /bakey-ledgerr/outlets/register
 * USE_CLEAN_URLS = false ->  forms post to  /bakey-ledgerr/index.php?route=...
 *
 * Keep it "true" for Laragon (the bundled .htaccess handles the rewrite).
 * If you ever get "404 Not Found" when you submit a form, change it to false.
 * ------------------------------------------------------------------ */
define('USE_CLEAN_URLS', true);

// Leave empty for automatic detection, or set manually e.g. '/bakey-ledgerr'
define('APP_BASE_URL', '');

/* ---------------------------------------------------------------------
 * DEVELOPMENT SETTINGS
 * ------------------------------------------------------------------ */
define('APP_DEBUG', true);   // set to false before showing the project publicly
