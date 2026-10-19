<?php
/**
 * ==========================================================================
 * suppliers/register.php  -  ENDPOINT:  POST /suppliers/register
 * ==========================================================================
 * Creates a new flour supplier.
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    flash('This endpoint only accepts POST requests.', 'warning');
    redirect('suppliers/');
}

$errors = [];

$name       = input_text('name', $errors, 2, 120, 'Supplier name');
$leadTime   = input_int('lead_time_days', $errors, 1, 90, 'Lead time');

if ($name !== null) {
    $count = (int) scalar(
        'SELECT COUNT(*) FROM suppliers WHERE LOWER(name) = LOWER(?)',
        [$name]
    );
    if ($count > 0) {
        $errors['name'] = 'A supplier called "' . $name . '" is already registered.';
    }
}

if ($errors) {
    set_form_errors($errors);
    remember_old($_POST);
    flash('Supplier was NOT saved - please check the form.', 'danger');
    redirect('suppliers/');
}

stream_query(
    'INSERT INTO suppliers (name, lead_time_days) VALUES (?, ?)',
    [$name, $leadTime]
);

forget_old();
flash(sprintf('Supplier "%s" registered with a lead time of %d day(s).', $name, $leadTime));

redirect('suppliers/');
