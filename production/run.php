<?php
/**
 * ==========================================================================
 * production/run.php  -  ENDPOINT:  POST /production/run
 * ==========================================================================
 * Creates (or updates) ONE production run.
 *
 * IDEMPOTENCY
 * -----------
 * The table production_runs has a UNIQUE KEY on production_date.
 * The handler therefore behaves like this:
 *
 *   date does not exist  -> INSERT the plan + audit entry "CREATE"
 *   date exists, same values -> DO NOTHING (no duplicate plan, no audit entry)
 *   date exists, other values -> UPDATE + audit entry "UPDATE"
 *
 * Submitting the same planning request 100 times still leaves exactly ONE
 * production plan for that date.
 *
 * PLANNED UNITS
 * -------------
 * planned_units is NEVER taken from the browser. It is recalculated here:
 *
 *   planned production = total outlet demand x 1.10
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    flash('This endpoint only accepts POST requests.', 'warning');
    redirect('production/');
}

$errors = [];

// ---------------- 1. validate the input ----------------
$productionDate = input_date('production_date', $errors, 'Production date');
$actualUnits    = input_int('actual_units', $errors, 0, 10000000, 'Actual units');
$wasteUnits     = input_int('waste_units', $errors, 0, 10000000, 'Waste units');

if ($actualUnits !== null && $wasteUnits !== null && $wasteUnits > $actualUnits) {
    $errors['waste_units'] = 'Waste units cannot be larger than the actual production.';
}

if ($errors) {
    set_form_errors($errors);
    remember_old($_POST);
    flash('Production run was NOT saved - please check the form.', 'danger');
    redirect('production/');
}

// ---------------- 2. calculate the plan on the server ----------------
$totalDemand = total_daily_demand();
$plannedUnits = planned_production($totalDemand);   // demand x 1.10
$bufferRate   = PRODUCTION_BUFFER_RATE;
$performedBy  = current_user()['key'];

$pdo = db();

try {
    $pdo->beginTransaction();

    // ---- is there already a plan for this date? -------------------
    $check = $pdo->prepare(
        'SELECT id, total_demand_units, planned_units, actual_units, waste_units
           FROM production_runs
          WHERE production_date = ?
          FOR UPDATE'
    );
    $check->execute([$productionDate]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        // ---------------- CASE A: brand new plan -------------------
        $insert = $pdo->prepare(
            'INSERT INTO production_runs
                (production_date, total_demand_units, planned_units,
                 actual_units, waste_units, buffer_rate, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $productionDate,
            $totalDemand,
            $plannedUnits,
            $actualUnits,
            $wasteUnits,
            $bufferRate,
            $performedBy,
        ]);
        $runId = (int) $pdo->lastInsertId();

        write_audit_log(
            $runId,
            'CREATE',
            null,
            audit_snapshot([
                'production_date'      => $productionDate,
                'total_demand_units'   => $totalDemand,
                'planned_units'        => $plannedUnits,
                'actual_units'         => $actualUnits,
                'waste_units'          => $wasteUnits,
            ]),
            [
                'total_demand_units' => $totalDemand,
                'planned_units'      => $plannedUnits,
                'actual_units'       => $actualUnits,
                'waste_units'        => $wasteUnits,
                'performed_by'       => $performedBy,
            ]
        );

        $message = sprintf(
            'Production run created for %s: demand %s, planned %s (demand x 1.10), actual %s, waste %s.',
            $productionDate,
            number_format($totalDemand),
            number_format($plannedUnits),
            number_format($actualUnits),
            number_format($wasteUnits)
        );
    } else {
        // ---------- CASE B: the date already has a plan -------------
        $sameValues =
            (int) $existing['total_demand_units'] === $totalDemand &&
            (int) $existing['planned_units'] === $plannedUnits &&
            (int) $existing['actual_units'] === $actualUnits &&
            (int) $existing['waste_units'] === $wasteUnits;

        if ($sameValues) {
            // IDEMPOTENT RESULT: nothing is written, no duplicate plan.
            $message = sprintf(
                'Idempotent request ignored: a production plan for %s already exists with exactly the same values (%s planned units). No duplicate was created.',
                $productionDate,
                number_format($plannedUnits)
            );
        } else {
            $oldSnapshot = audit_snapshot($existing);

            $update = $pdo->prepare(
                'UPDATE production_runs
                    SET total_demand_units = ?,
                        planned_units = ?,
                        actual_units = ?,
                        waste_units = ?,
                        buffer_rate = ?,
                        created_by = ?,
                        updated_at = CURRENT_TIMESTAMP
                  WHERE id = ?'
            );
            $update->execute([
                $totalDemand,
                $plannedUnits,
                $actualUnits,
                $wasteUnits,
                $bufferRate,
                $performedBy,
                (int) $existing['id'],
            ]);

            write_audit_log(
                (int) $existing['id'],
                'UPDATE',
                $oldSnapshot,
                audit_snapshot([
                    'production_date'      => $productionDate,
                    'total_demand_units'   => $totalDemand,
                    'planned_units'        => $plannedUnits,
                    'actual_units'         => $actualUnits,
                    'waste_units'          => $wasteUnits,
                ]),
                [
                    'total_demand_units' => $totalDemand,
                    'planned_units'      => $plannedUnits,
                    'actual_units'       => $actualUnits,
                    'waste_units'        => $wasteUnits,
                    'performed_by'       => $performedBy,
                ]
            );

            $wastePct = waste_percent($wasteUnits, $actualUnits);
            $message  = sprintf(
                'Production run %s updated: planned %s, actual %s, waste %s (%s).',
                $productionDate,
                number_format($plannedUnits),
                number_format($actualUnits),
                number_format($wasteUnits),
                pct($wastePct)
            );
            if (waste_investigation_required($wastePct)) {
                flash('INVESTIGATION REQUIRED - waste is ' . pct($wastePct) . ' (limit 5%).', 'danger');
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

forget_old();
flash($message);

// Post / Redirect / Get
redirect('production/');
