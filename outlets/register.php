<?php
/**
 * ==========================================================================
 * outlets/register.php  -  ENDPOINT:  POST /outlets/register
 * ==========================================================================
 * Creates a new bakery outlet.
 *
 * Security / quality points:
 *   - PDO prepared statement (SQL injection safe)
 *   - every field is validated on the server side
 *   - duplicate outlet names are refused
 *   - Post / Redirect / Get: the browser never re-submits the form
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

// The endpoint only accepts POST requests.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    flash('This endpoint only accepts POST requests.', 'warning');
    redirect('outlets/');
}

$errors = [];

// ---- 1. validate every field --------------------------------------
$name  = input_text('name', $errors, 2, 120, 'Outlet name');
$demand = input_int('daily_demand_units', $errors, 0, 1000000, 'Daily demand');

// ---- 2. business rule: the name must be unique ---------------------
if ($name !== null) {
    $count = (int) scalar(
        'SELECT COUNT(*) FROM outlets WHERE LOWER(name) = LOWER(?)',
        [$name]
    );
    if ($count > 0) {
        $errors['name'] = 'An outlet called "' . $name . '" is already registered.';
    }
}

// ---- 3. something wrong? go back and show the errors ----------------
if ($errors) {
    set_form_errors($errors);
    remember_old($_POST);
    flash('Outlet was NOT saved - please check the form.', 'danger');
    redirect('outlets/');
}

// ---- 4. save with a prepared statement -----------------------------
stream_query(
    'INSERT INTO outlets (name, daily_demand_units) VALUES (?, ?)',
    [$name, $demand]
);

forget_old();
flash(sprintf(
    'Outlet "%s" registered with a daily demand of %s units.',
    $name,
    number_format($demand)
));

redirect('outlets/');
