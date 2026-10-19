<?php
/**
 * ==========================================================================
 * deliveries/save.php  -  ENDPOINT:  POST /deliveries/save
 * ==========================================================================
 * Records one morning or evening delivery to an outlet.
 *
 * The unique key (outlet_id, production_date, session_name) makes the
 * endpoint idempotent: recording the same slot twice updates the quantity
 * instead of creating a second delivery row.
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    flash('This endpoint only accepts POST requests.', 'warning');
    redirect('deliveries/');
}

$errors = [];

$outletId   = input_int('outlet_id', $errors, 1, 100000000, 'Outlet');
$actionDate = input_date('production_date', $errors, 'Delivery date');
$session    = input_choice('session_name', ['morning', 'evening'], $errors, 'Session');
$units      = input_int('units_delivered', $errors, 0, 10000000, 'Units delivered');

// ---- the outlet must exist (and the demo cashier may only record
//      deliveries for HIS OWN outlet - the patched check) -------------
$outlet = null;
if ($outletId !== null) {
    require_outlet_access($outletId);

    $stmt  = stream_query('SELECT id, name, unit_price_kobo FROM outlets WHERE id = ?', [$outletId]);
    $outlet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$outlet) {
        $errors['outlet_id'] = 'That outlet does not exist.';
    }
}

if ($errors) {
    set_form_errors($errors);
    remember_old($_POST);
    flash('Delivery was NOT saved - please check the form.', 'danger');
    redirect('deliveries/');
}

// ---- revenue in kobo (integer), never a floating point money value ---
$revenueKobo = (int) round($units * (int) $outlet['unit_price_kobo']);

// ---- link the delivery to the production run of that date (may be NULL)
$runId = scalar('SELECT id FROM production_runs WHERE production_date = ?', [$actionDate]);
$runId = $runId === null ? null : (int) $runId;

$pdo = db();

// ---- idempotent upsert ---------------------------------------------
$check = $pdo->prepare(
    'SELECT id, units_delivered FROM deliveries
      WHERE outlet_id = ? AND production_date = ? AND session_name = ?'
);
$check->execute([$outletId, $actionDate, $session]);
$existing = $check->fetch(PDO::FETCH_ASSOC);

if ($existing && (int) $existing['units_delivered'] === $units) {
    flash(sprintf(
        'Delivery already recorded: %s units to %s on %s (%s) - nothing changed.',
        number_format($units),
        $outlet['name'],
        $actionDate,
        $session
    ));
} elseif ($existing) {
    $update = $pdo->prepare(
        'UPDATE deliveries
            SET units_delivered = ?, revenue_kobo = ?, production_run_id = ?
          WHERE id = ?'
    );
    $update->execute([$units, $revenueKobo, $runId, (int) $existing['id']]);
    flash(sprintf(
        'Delivery updated: %s -> %s units to %s on %s (%s).',
        number_format((int) $existing['units_delivered']),
        number_format($units),
        $outlet['name'],
        $actionDate,
        $session
    ));
} else {
    $insert = $pdo->prepare(
        'INSERT INTO deliveries
            (outlet_id, production_date, session_name, units_delivered,
             revenue_kobo, delivered_at, production_run_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $outletId,
        $actionDate,
        $session,
        $units,
        $revenueKobo,
        date('Y-m-d H:i:s'),
        $runId,
    ]);
    flash(sprintf(
        'Delivery recorded: %s units to %s on %s (%s), revenue %s.',
        number_format($units),
        $outlet['name'],
        $actionDate,
        $session,
        naira($revenueKobo)
    ));
}

forget_old();
redirect('deliveries/');
