<?php
/**
 * ==========================================================================
 * includes/functions.php
 * --------------------------------------------------------------------------
 * Small helper functions used everywhere:
 *   - URL building / redirecting
 *   - output escaping
 *   - input validation (never trust the browser)
 *   - the bakery formulas
 * ==========================================================================
 */

/* ==================================================================
 * 1. URL + OUTPUT HELPERS
 * ================================================================== */

/**
 * Detect the base URL path of this project from the document root.
 * Examples: "" (project inside the web root) or "/bakey-ledgerr"
 *
 * @return string
 */
function detect_base_url(): string
{
    // 1) manual override in config/config.php
    if (APP_BASE_URL !== '') {
        return rtrim(APP_BASE_URL, '/');
    }

    $docRoot = '';
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    }

    $projectPath = str_replace('\\', '/', APP_ROOT);

    // 2) automatic: strip the document root from the project folder
    if ($docRoot !== '' && stripos($projectPath, $docRoot) === 0) {
        return rtrim(substr($projectPath, strlen($docRoot)), '/');
    }

    // 3) last fallback: derive it from the address bar
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $base   = preg_replace('#/[^/]*\.php$#', '', $script);
        $base   = preg_replace('#/(outlets|suppliers|production|flour|deliveries|dashboard|security|audit)/?$#', '', $base);
        return rtrim($base, '/');
    }

    return '';
}

/**
 * Build a link to a page inside the project.
 * url('outlets/')  ->  http://localhost/bakey-ledgerr/outlets/
 *
 * @param string $path
 * @return string
 */
function url(string $path = ''): string
{
    return APP_BASE . '/' . ltrim($path, '/');
}

/**
 * Build the URL of an API/FORM ENDPOINT such as
 *   POST /outlets/register
 *   POST /production/run
 *   POST /flour/order
 *
 * With USE_CLEAN_URLS = true the .htaccess file forwards the clean URL to
 * index.php (the front controller / router). If rewrites are not available
 * the same endpoint is reached with index.php?route=/outlets/register
 *
 * @param string $path e.g. "/outlets/register"
 * @return string
 */
function endpoint(string $path): string
{
    $path = '/' . trim($path, '/');

    if (USE_CLEAN_URLS) {
        return url(ltrim($path, '/'));
    }

    return url('index.php') . '?route=' . rawurlencode($path);
}

/**
 * Redirect the browser (used after every POST - the Post/Redirect/Get pattern).
 *
 * NOTE: no return type on purpose, so the file also runs on PHP 8.0.
 *
 * @param string $path
 * @return void
 */
function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Escape anything before printing it into HTML (XSS protection).
 *
 * @param mixed $value
 * @return string
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ==================================================================
 * 2. FLASH MESSAGES
 * ================================================================== */

/**
 * Store a message for the next page load.
 *
 * @param string $message
 * @param string $type    success | danger | warning | info
 */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

/**
 * Return AND clear all stored messages (so they show only once).
 *
 * @return array
 */
function flash_messages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ==================================================================
 * 3. INPUT VALIDATION  (server side - the browser can be faked)
 * ================================================================== */

/**
 * Read an integer from $_POST.
 *
 * @return int|null null when invalid (an error is added to $errors)
 */
function input_int(string $key, array &$errors, int $min = 0, int $max = 1000000000, string $label = ''): ?int
{
    $label = $label !== '' ? $label : $key;
    $raw   = trim((string) ($_POST[$key] ?? ''));

    if ($raw === '' || !preg_match('/^-?\d+$/', $raw)) {
        $errors[$key] = $label . ' must be a whole number.';
        return null;
    }

    $value = (int) $raw;
    if ($value < $min || $value > $max) {
        $errors[$key] = $label . ' must be between ' . $min . ' and ' . number_format($max) . '.';
        return null;
    }
    return $value;
}

/**
 * Read a decimal number from $_POST (used for kilograms).
 *
 * @return float|null
 */
function input_decimal(string $key, array &$errors, float $min = 0.0, float $max = 1000000.0, string $label = ''): ?float
{
    $label = $label !== '' ? $label : $key;
    $raw   = trim((string) ($_POST[$key] ?? ''));

    if ($raw === '' || !is_numeric($raw)) {
        $errors[$key] = $label . ' must be a number (example: 125.5).';
        return null;
    }

    $value = (float) $raw;
    if ($value < $min || $value > $max) {
        $errors[$key] = $label . ' must be between ' . $min . ' and ' . number_format($max, 3) . '.';
        return null;
    }
    return $value;
}

/**
 * Read a required text value from $_POST.
 *
 * @return string|null
 */
function input_text(string $key, array &$errors, int $min = 1, int $max = 255, string $label = ''): ?string
{
    $label = $label !== '' ? $label : $key;
    $raw   = trim((string) ($_POST[$key] ?? ''));
    // remove control characters, keep normal text
    $raw   = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw);

    if ($raw === '') {
        $errors[$key] = $label . ' is required.';
        return null;
    }
    if (mb_strlen($raw) < $min) {
        $errors[$key] = $label . ' must be at least ' . $min . ' characters.';
        return null;
    }
    if (mb_strlen($raw) > $max) {
        $errors[$key] = $label . ' must not be longer than ' . $max . ' characters.';
        return null;
    }
    return $raw;
}

/**
 * Read and validate a date (YYYY-MM-DD) from $_POST.
 *
 * @return string|null
 */
function input_date(string $key, array &$errors, string $label = ''): ?string
{
    $label = $label !== '' ? $label : $key;
    $raw   = trim((string) ($_POST[$key] ?? ''));

    $parts = explode('-', $raw);
    if (count($parts) !== 3 || !checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
        $errors[$key] = $label . ' must be a valid date in YYYY-MM-DD format.';
        return null;
    }

    // do not allow production dates too far in the past/future
    $date = $raw;
    $diff = (strtotime($date) - strtotime('today')) / 86400;
    if ($diff < -730 || $diff > 366) {
        $errors[$key] = $label . ' must be within the last 2 years or the next year.';
        return null;
    }
    return $date;
}

/**
 * Read a select value and make sure it is one of the allowed options.
 *
 * @return string|null
 */
function input_choice(string $key, array $allowed, array &$errors, string $label = ''): ?string
{
    $label = $label !== '' ? $label : $key;
    $raw   = (string) ($_POST[$key] ?? '');

    if (!in_array($raw, $allowed, true)) {
        $errors[$key] = $label . ' must be one of: ' . implode(', ', $allowed) . '.';
        return null;
    }
    return $raw;
}

/**
 * Read a date + time from an <input type="datetime-local">.
 * Returns "YYYY-MM-DD HH:MM:SS" or NULL when the field is empty/invalid.
 *
 * @return string|null
 */
function input_datetime_local(string $key, array &$errors, bool $required = false, string $label = ''): ?string
{
    $label = $label !== '' ? $label : $key;
    $raw   = trim((string) ($_POST[$key] ?? ''));

    if ($raw === '') {
        if ($required) {
            $errors[$key] = $label . ' is required.';
        }
        return null;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $raw)) {
        $errors[$key] = $label . ' must be a valid date and time.';
        return null;
    }

    try {
        $date = new DateTime(str_replace('T', ' ', $raw));
    } catch (Exception $ex) {
        $errors[$key] = $label . ' must be a valid date and time.';
        return null;
    }

    return $date->format('Y-m-d H:i:s');
}

/**
 * Convert an <input type="date"> value into "YYYY-MM-DD HH:MM:SS".
 *
 * @param string $dateValue
 * @return string
 */
function datetime_from_date(string $dateValue): string
{
    return $dateValue . ' 00:00:00';
}

/**
 * Keep the values the user typed so the form can be re-filled after an
 * error (we redirect after every POST).
 *
 * @param array $data usually $_POST
 */
function remember_old(array $data): void
{
    $_SESSION['old_input'] = $data;
}

/**
 * Read a previously submitted value ("" when there is none).
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function old(string $key, $default = '')
{
    $value = $_SESSION['old_input'][$key] ?? $default;
    return is_string($value) ? e($value) : $value;
}

/**
 * Forget the saved values after a successful submission.
 */
function forget_old(): void
{
    unset($_SESSION['old_input']);
}

/**
 * Store validation errors so the next (redirected) page can show them.
 *
 * @param array $errors
 */
function set_form_errors(array $errors): void
{
    $_SESSION['form_errors'] = $errors;
}

/**
 * Read the stored validation errors once and remove them from the session.
 *
 * @return array
 */
function pull_form_errors(): array
{
    $errors = $_SESSION['form_errors'] ?? [];
    unset($_SESSION['form_errors']);
    return $errors;
}

/**
 * Show all validation errors above a form.
 *
 * @param array $errors
 */
function show_errors(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<div class="alert alert-danger py-2"><strong>Please fix the following:</strong><ul class="mb-0">';
    foreach ($errors as $message) {
        echo '<li>' . e($message) . '</li>';
    }
    echo '</ul></div>';
}

/**
 * Get an inline error message for one field ("" when the field is fine).
 *
 * @param array  $errors
 * @param string $key
 * @return string
 */
function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? e($errors[$key]) : '';
}

/* ==================================================================
 * 4. FORMATTING
 * ================================================================== */

/**
 * Format kobo as Naira: 45000 -> "₦450.00"
 *
 * @param int|string $kobo
 * @return string
 */
function naira($kobo): string
{
    return CURRENCY_SYMBOL . number_format(((int) $kobo) / 100, 2);
}

/**
 * Format kilograms with 3 decimals: 125.5 -> "125.500 kg"
 *
 * @param int|float|string $kg
 * @return string
 */
function kg($kg): string
{
    return number_format((float) $kg, 3) . ' kg';
}

/**
 * Format a percentage: 5.77 -> "5.77%"
 *
 * @param float|null $value
 * @return string
 */
function pct($value): string
{
    return $value === null ? '-' : number_format($value, 2) . '%';
}

/* ==================================================================
 * 5. THE BAKERY FORMULAS  (learn these for the defence)
 * ================================================================== */

/**
 * FORMULA 1 - TOTAL DAILY DEMAND
 *   total daily demand = SUM(daily_demand_units) of every outlet
 *
 * @return int
 */
function total_daily_demand(): int
{
    $value = scalar('SELECT COALESCE(SUM(daily_demand_units), 0) FROM outlets');
    return (int) $value;
}

/**
 * FORMULA 2 - PLANNED PRODUCTION (with the 10% buffer)
 *
 *   planned production = total outlet demand x 1.10
 *
 * Example: 1,000 x 1.10 = 1,100 units
 *
 * We use ceil() so the plan is always a whole number of units.
 *
 * @param int $totalDemand
 * @return int
 */
function planned_production(int $totalDemand): int
{
    return (int) ceil($totalDemand * PRODUCTION_BUFFER_RATE);
}

/**
 * FORMULA 3 - WASTE PERCENTAGE
 *
 *   waste % = waste units / actual production units x 100
 *
 * @param int $wasteUnits
 * @param int $actualUnits
 * @return float|null null when actual production is 0 (cannot divide by 0)
 */
function waste_percent(int $wasteUnits, int $actualUnits): ?float
{
    if ($actualUnits <= 0) {
        return null;
    }
    return round(($wasteUnits / $actualUnits) * 100, 2);
}

/**
 * Is the waste percentage above the 5% limit?
 *
 * @param float|null $percent
 * @return bool
 */
function waste_investigation_required($percent): bool
{
    return $percent !== null && $percent > WASTE_ALERT_PERCENT;
}

/**
 * FORMULA 4 - AVERAGE DAILY FLOUR USAGE (kg per day)
 *
 *   daily flour usage = flour used in the last N days / number of production days
 *
 * @return float
 */
function avg_daily_flour_usage(): float
{
    $days = FLOUR_USAGE_WINDOW_DAYS;

    $value = scalar(
        "SELECT COALESCE(SUM(fu.flour_kg), 0) / NULLIF(COUNT(DISTINCT pr.production_date), 0)
           FROM flour_usage fu
           JOIN production_runs pr ON pr.id = fu.production_run_id
          WHERE pr.production_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)",
        [$days]
    );

    $usage = $value === null ? 0.0 : (float) $value;

    // Not enough history yet -> fall back to the full history
    if ($usage <= 0) {
        $value = scalar(
            'SELECT COALESCE(SUM(fu.flour_kg), 0) / NULLIF(COUNT(DISTINCT pr.production_date), 0)
               FROM flour_usage fu
               JOIN production_runs pr ON pr.id = fu.production_run_id'
        );
        $usage = $value === null ? 0.0 : (float) $value;
    }

    return $usage;
}

/**
 * FORMULA 5 - REORDER POINT
 *
 *   reorder point = daily flour usage x supplier lead time (days) x 1.20
 *
 * @param float $dailyUsageKg
 * @param int   $leadTimeDays
 * @return float
 */
function reorder_point(float $dailyUsageKg, int $leadTimeDays): float
{
    return round($dailyUsageKg * $leadTimeDays * REORDER_SAFETY_RATE, 3);
}

/**
 * Kilograms of flour delivered but not yet used.
 *
 * @return float
 */
function flour_on_hand(): float
{
    $delivered = (float) scalar(
        'SELECT COALESCE(SUM(quantity_kg), 0) FROM flour_orders WHERE delivered_at IS NOT NULL'
    );
    $used = (float) scalar('SELECT COALESCE(SUM(flour_kg), 0) FROM flour_usage');
    return round($delivered - $used, 3);
}

/**
 * Kilograms of flour ordered but still on the way.
 *
 * @return float
 */
function flour_incoming(): float
{
    $value = scalar('SELECT COALESCE(SUM(quantity_kg), 0) FROM flour_orders WHERE delivered_at IS NULL');
    return round((float) $value, 3);
}

/* ==================================================================
 * 6. AUDIT LOG
 * ================================================================== */

/**
 * Write one entry into the production audit log.
 *
 * The audit table is APPEND ONLY:
 *   - the application has no UPDATE / DELETE code for this table
 *   - two database triggers refuse UPDATE and DELETE statements
 *   - the foreign key uses ON DELETE RESTRICT, so a production run that
 *     already has an audit record cannot be removed either.
 *
 * @param int         $productionRunId
 * @param string      $action   CREATE | UPDATE
 * @param string|null $oldValues
 * @param string      $newValues
 * @param array       $snapshot total_demand / planned / actual / waste
 */
function write_audit_log(int $productionRunId, string $action, ?string $oldValues, string $newValues, array $snapshot): void
{
    stream_query(
        'INSERT INTO production_audit_log
            (production_run_id, action, old_values, new_values,
             total_demand_units, planned_units, actual_units, waste_units, performed_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $productionRunId,
            $action,
            $oldValues,
            $newValues,
            (int) ($snapshot['total_demand_units'] ?? 0),
            (int) ($snapshot['planned_units'] ?? 0),
            (int) ($snapshot['actual_units'] ?? 0),
            (int) ($snapshot['waste_units'] ?? 0),
            $snapshot['performed_by'] ?? 'system',
        ]
    );
}

/**
 * Build a readable "old values" string for the audit log.
 *
 * @param array $row
 * @return string
 */
function audit_snapshot(array $row): string
{
    return sprintf(
        'date=%s; demand=%d; planned=%d; actual=%d; waste=%d',
        $row['production_date'] ?? '',
        (int) ($row['total_demand_units'] ?? 0),
        (int) ($row['planned_units'] ?? 0),
        (int) ($row['actual_units'] ?? 0),
        (int) ($row['waste_units'] ?? 0)
    );
}
