<?php
/**
 * ==========================================================================
 * flour/usage.php  -  ENDPOINT:  POST /flour/usage
 * ==========================================================================
 * Records how much flour a production run consumed.
 * This value feeds the "daily flour usage" and the reorder point.
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    flash('This endpoint only accepts POST requests.', 'warning');
    redirect('flour/');
}

$errors = [];

$runId   = input_int('production_run_id', $errors, 1, 100000000, 'Production run');
$flourKg = input_decimal('flour_kg', $errors, 0.001, 1000000, 'Flour used');

if ($runId !== null) {
    $exists = (int) scalar('SELECT COUNT(*) FROM production_runs WHERE id = ?', [$runId]);
    if ($exists === 0) {
        $errors['production_run_id'] = 'That production run does not exist.';
    }
}

if ($errors) {
    set_form_errors($errors);
    remember_old($_POST);
    flash('Flour usage was NOT saved - please check the form.', 'danger');
    redirect('flour/');
}

stream_query(
    'INSERT INTO flour_usage (production_run_id, flour_kg) VALUES (?, ?)',
    [$runId, $flourKg]
);

forget_old();
flash(sprintf('Flour usage of %s kg recorded for production run #%d.', number_format($flourKg, 3), $runId));

redirect('flour/');
