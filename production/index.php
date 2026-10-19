<?php
/**
 * ==========================================================================
 * production/index.php  -  PRODUCTION PLANNING
 * ==========================================================================
 *  * calculates the total daily demand of every outlet
 *  * plans the production run with the mandatory 10% buffer
 *      planned production = total outlet demand x 1.10
 *  * records actual units and waste
 *  * shows the waste percentage (and "INVESTIGATION REQUIRED" above 5%)
 *
 * The form posts to the endpoint  POST /production/run
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

$errors = pull_form_errors();

$totalDemand   = total_daily_demand();
$plannedUnits  = planned_production($totalDemand);
$runCount      = (int) scalar('SELECT COUNT(*) FROM production_runs');

// Did we already make a plan for today?
$todayPlan = null;
$stmt = stream_query(
    'SELECT id, planned_units, actual_units, waste_units
       FROM production_runs WHERE production_date = ?',
    [date('Y-m-d')]
);
$todayPlan = $stmt->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Production Planning';
$activeNav = 'production';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-1">Production Planning</h1>
<p class="text-muted mb-3">
    The plan is always <strong>total outlet demand &times; 1.10</strong> (10% buffer is mandatory).
</p>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Total daily demand</div>
            <div class="stat-value"><?= number_format($totalDemand) ?> units</div>
            <div class="stat-sub">&Sigma; of every outlet</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100 border-primary"><div class="stat-body">
            <div class="stat-label">Planned production (+10%)</div>
            <div class="stat-value text-primary"><?= number_format($plannedUnits) ?> units</div>
            <div class="stat-sub"><?= number_format($totalDemand) ?> &times; 1.10</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Runs recorded</div>
            <div class="stat-value"><?= number_format($runCount) ?></div>
            <div class="stat-sub">rows in <code>production_runs</code></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Today's plan</div>
            <div class="stat-value">
                <?= $todayPlan ? number_format((int) $todayPlan['planned_units']) . ' units' : 'not yet' ?>
            </div>
            <div class="stat-sub">
                <?= $todayPlan
                    ? 'actual ' . number_format((int) $todayPlan['actual_units'])
                    : 'submit the form to create it' ?>
            </div>
        </div></div>
    </div>
</div>

<div class="row g-3">

    <!-- =================== FORM =================== -->
    <div class="col-lg-4">
        <div class="table-card p-3">
            <h2 class="h5 mb-3">Record a production run</h2>

            <?php show_errors($errors); ?>

            <form method="post" action="<?= e(endpoint('/production/run')) ?>" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="production_date">Production date</label>
                    <input type="date" class="form-control<?= field_error($errors, 'production_date') ? ' is-invalid' : '' ?>"
                           id="production_date" name="production_date" required
                           value="<?= old('production_date', date('Y-m-d')) ?>">
                    <?php if (field_error($errors, 'production_date')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'production_date') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="actual_units">Actual units produced</label>
                    <input type="number" class="form-control<?= field_error($errors, 'actual_units') ? ' is-invalid' : '' ?>"
                           id="actual_units" name="actual_units" min="0" max="10000000" step="1" required
                           placeholder="e.g. 1080"
                           value="<?= old('actual_units') ?>">
                    <?php if (field_error($errors, 'actual_units')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'actual_units') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="waste_units">Waste units (rejects, burnt, damaged)</label>
                    <input type="number" class="form-control<?= field_error($errors, 'waste_units') ? ' is-invalid' : '' ?>"
                           id="waste_units" name="waste_units" min="0" max="10000000" step="1" required
                           placeholder="e.g. 35"
                           value="<?= old('waste_units') ?>">
                    <?php if (field_error($errors, 'waste_units')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'waste_units') ?></div>
                    <?php endif; ?>
                </div>

                <div class="alert alert-info py-2 small mb-3">
                    <strong>planned_units is NOT typed in.</strong><br>
                    The server always calculates:<br>
                    <code><?= number_format($totalDemand) ?> &times; 1.10 = <?= number_format($plannedUnits) ?></code>
                </div>

                <button type="submit" class="btn btn-primary w-100">Save production run</button>
            </form>

            <div class="formula-box mt-3">
                <strong>Formulas used</strong><br>
                <code>planned = total demand &times; 1.10</code><br>
                <code>waste % = waste &divide; actual &times; 100</code><br>
                <code>waste % &gt; 5% &rarr; INVESTIGATION REQUIRED</code>
            </div>

            <p class="small text-muted mt-3 mb-0">
                Submitting the same date twice never creates a duplicate plan
                (unique key on <code>production_date</code>) - that is the idempotency rule.
            </p>
        </div>
    </div>

    <!-- =================== RUN HISTORY =================== -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="p-3 pb-2 d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Production runs</h2>
                <span class="badge text-bg-secondary"><?= number_format($runCount) ?> runs</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-end">Demand</th>
                        <th class="text-end">Planned</th>
                        <th class="text-end">Actual</th>
                        <th class="text-end">Waste</th>
                        <th class="text-end">Waste %</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    // STREAMING: read one row at a time (no fetchAll anywhere)
                    $stmt = stream_query(
                        'SELECT production_date, total_demand_units, planned_units, actual_units, waste_units
                           FROM production_runs
                          ORDER BY production_date DESC
                          LIMIT 60'
                    );
                    $hasRows = false;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $hasRows = true;
                        $wastePct = waste_percent((int) $row['waste_units'], (int) $row['actual_units']);
                        $alert    = waste_investigation_required($wastePct);
                        ?>
                        <tr class="<?= $alert ? 'table-danger' : '' ?>">
                            <td class="fw-semibold"><?= e($row['production_date']) ?></td>
                            <td class="text-end"><?= number_format((int) $row['total_demand_units']) ?></td>
                            <td class="text-end text-primary fw-semibold">
                                <?= number_format((int) $row['planned_units']) ?>
                            </td>
                            <td class="text-end"><?= number_format((int) $row['actual_units']) ?></td>
                            <td class="text-end"><?= number_format((int) $row['waste_units']) ?></td>
                            <td class="text-end fw-semibold"><?= pct($wastePct) ?></td>
                            <td>
                                <?php if ($alert): ?>
                                    <span class="investigation-banner">INVESTIGATION REQUIRED</span>
                                <?php elseif ($wastePct === null): ?>
                                    <span class="badge text-bg-secondary">no actual production yet</span>
                                <?php else: ?>
                                    <span class="badge badge-ok">within limit (&le; 5%)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    }
                    if (!$hasRows) {
                        echo '<tr><td colspan="7" class="text-center text-muted py-4">'
                            . 'No production runs yet - use the form on the left.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="alert alert-light border mt-3 small mb-0">
            <strong>Audit trail:</strong> every INSERT and UPDATE of a production run is written to
            <code>production_audit_log</code>.
            <a href="<?= e(url('audit/')) ?>">Open the audit log &rarr;</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
