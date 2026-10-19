<?php
/**
 * ==========================================================================
 * deliveries/index.php  -  MORNING / EVENING OUTLET DELIVERIES
 * ==========================================================================
 * The bakery delivers to every outlet twice a day: MORNING and EVENING.
 *
 *   - a form to record a delivery        -> POST /deliveries/save
 *   - a daily summary (morning vs evening)
 *   - the full list of deliveries
 *
 * The pair (outlet, date, session) is UNIQUE, so recording the same
 * delivery twice only updates it - it never duplicates the record.
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

$errors = pull_form_errors();

$today = date('Y-m-d');

// ---- totals for today ----------------------------------------------
$todayStmt = stream_query(
    'SELECT session_name,
            COALESCE(SUM(units_delivered), 0) AS units,
            COALESCE(SUM(revenue_kobo), 0) AS kobo,
            COUNT(*) AS cnt
       FROM deliveries
      WHERE production_date = ?
      GROUP BY session_name',
    [$today]
);
$todayTotals = ['morning' => ['units' => 0, 'kobo' => 0, 'cnt' => 0],
                'evening' => ['units' => 0, 'kobo' => 0, 'cnt' => 0]];
while ($t = $todayStmt->fetch(PDO::FETCH_ASSOC)) {
    $key = $t['session_name'];
    if (isset($todayTotals[$key])) {
        $todayTotals[$key] = $t;
    }
}

$deliveryCount = (int) scalar('SELECT COUNT(*) FROM deliveries');
$activeOutlets = (int) scalar('SELECT COUNT(*) FROM outlets');

$pageTitle = 'Deliveries (Morning / Evening)';
$activeNav = 'deliveries';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-1">Outlet Deliveries</h1>
<p class="text-muted mb-3">
    Every outlet is served <strong>twice a day</strong>: morning and evening.
</p>

<!-- ================= TODAY CARDS ================= -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Today - morning</div>
            <div class="stat-value text-warning"><?= number_format((int) $todayTotals['morning']['units']) ?> units</div>
            <div class="stat-sub">
                <?= naira($todayTotals['morning']['kobo']) ?> &middot;
                <?= (int) $todayTotals['morning']['cnt'] ?> stops
            </div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Today - evening</div>
            <div class="stat-value text-primary"><?= number_format((int) $todayTotals['evening']['units']) ?> units</div>
            <div class="stat-sub">
                <?= naira($todayTotals['evening']['kobo']) ?> &middot;
                <?= (int) $todayTotals['evening']['cnt'] ?> stops
            </div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Today - total delivered</div>
            <div class="stat-value">
                <?= number_format((int) $todayTotals['morning']['units'] + (int) $todayTotals['evening']['units']) ?> units
            </div>
            <div class="stat-sub">morning + evening</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Delivery records</div>
            <div class="stat-value"><?= number_format($deliveryCount) ?></div>
            <div class="stat-sub">rows in <code>deliveries</code></div>
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">

    <!-- ================= FORM ================= -->
    <div class="col-lg-4">
        <div class="table-card p-3">
            <h2 class="h5 mb-3">Record a delivery</h2>
            <?php show_errors($errors); ?>

            <form method="post" action="<?= e(endpoint('/deliveries/save')) ?>" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="outlet_id">Outlet</label>
                    <select class="form-select<?= field_error($errors, 'outlet_id') ? ' is-invalid' : '' ?>"
                            id="outlet_id" name="outlet_id" required>
                        <option value="">-- choose an outlet --</option>
                        <?php
                        $outOpts = stream_query('SELECT id, name FROM outlets ORDER BY name');
                        while ($o = $outOpts->fetch(PDO::FETCH_ASSOC)) {
                            $selected = old('outlet_id') == $o['id'] ? ' selected' : '';
                            echo '<option value="' . (int) $o['id'] . '"' . $selected . '>'
                                . e($o['name']) . '</option>';
                        }
                        ?>
                    </select>
                    <?php if (field_error($errors, 'outlet_id')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'outlet_id') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="production_date">Delivery date</label>
                    <input type="date" class="form-control<?= field_error($errors, 'production_date') ? ' is-invalid' : '' ?>"
                           id="production_date" name="production_date" required
                           value="<?= old('production_date', $today) ?>">
                    <?php if (field_error($errors, 'production_date')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'production_date') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label">Session</label>
                    <div class="btn-group w-100" role="group" aria-label="Delivery session">
                        <input type="radio" class="btn-check" name="session_name" id="sessionMorning"
                               value="morning" <?= old('session_name') !== 'evening' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-warning" for="sessionMorning">Morning</label>

                        <input type="radio" class="btn-check" name="session_name" id="sessionEvening"
                               value="evening" <?= old('session_name') === 'evening' ? 'checked' : '' ?>>
                        <label class="btn btn-outline-primary" for="sessionEvening">Evening</label>
                    </div>
                    <?php if (field_error($errors, 'session_name')): ?>
                        <div class="text-danger small mt-1"><?= field_error($errors, 'session_name') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="units_delivered">Units delivered</label>
                    <input type="number" class="form-control<?= field_error($errors, 'units_delivered') ? ' is-invalid' : '' ?>"
                           id="units_delivered" name="units_delivered" min="0" max="10000000" step="1"
                           required placeholder="e.g. 200"
                           value="<?= old('units_delivered') ?>">
                    <?php if (field_error($errors, 'units_delivered')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'units_delivered') ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100">Save delivery</button>
            </form>

            <div class="formula-box mt-3">
                <strong>Revenue (stored in kobo)</strong><br>
                <code>revenue_kobo = units &times; outlet unit price (kobo)</code>
            </div>
        </div>
    </div>

    <!-- ================= 7 DAY SUMMARY ================= -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="p-3 pb-2">
                <h2 class="h5 mb-0">Morning vs evening - last 7 days</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-end">Morning units</th>
                        <th class="text-end">Evening units</th>
                        <th class="text-end">Day total</th>
                        <th class="text-end">Revenue</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sumStmt = stream_query(
                        "SELECT production_date,
                                COALESCE(SUM(CASE WHEN session_name = 'morning' THEN units_delivered ELSE 0 END), 0) AS morning_units,
                                COALESCE(SUM(CASE WHEN session_name = 'evening' THEN units_delivered ELSE 0 END), 0) AS evening_units,
                                COALESCE(SUM(units_delivered), 0) AS total_units,
                                COALESCE(SUM(revenue_kobo), 0) AS total_kobo
                           FROM deliveries
                          WHERE production_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                          GROUP BY production_date
                          ORDER BY production_date DESC"
                    );
                    $hasSum = false;
                    while ($s = $sumStmt->fetch(PDO::FETCH_ASSOC)) {
                        $hasSum = true;
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= e($s['production_date']) ?></td>
                            <td class="text-end text-warning-emphasis"><?= number_format((int) $s['morning_units']) ?></td>
                            <td class="text-end text-primary-emphasis"><?= number_format((int) $s['evening_units']) ?></td>
                            <td class="text-end fw-semibold"><?= number_format((int) $s['total_units']) ?></td>
                            <td class="text-end"><?= naira($s['total_kobo']) ?></td>
                        </tr>
                        <?php
                    }
                    if (!$hasSum) {
                        echo '<tr><td colspan="5" class="text-center text-muted py-4">No deliveries in the last 7 days.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ================= RECENT DELIVERIES ================= -->
<div class="table-card">
    <div class="p-3 pb-2 d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Recent deliveries</h2>
        <span class="badge text-bg-secondary"><?= number_format($deliveryCount) ?> records</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Outlet</th>
                <th>Session</th>
                <th class="text-end">Units</th>
                <th class="text-end">Revenue</th>
                <th>Created</th>
            </tr>
            </thead>
            <tbody>
            <?php
            // STREAMING: one row at a time
            $recentStmt = stream_query(
                'SELECT d.id, d.production_date, d.session_name, d.units_delivered,
                        d.revenue_kobo, d.created_at, o.name AS outlet_name
                   FROM deliveries d
                   JOIN outlets o ON o.id = d.outlet_id
                  ORDER BY d.production_date DESC, d.session_name DESC, d.id DESC
                  LIMIT 50'
            );
            $hasRecent = false;
            while ($d = $recentStmt->fetch(PDO::FETCH_ASSOC)) {
                $hasRecent = true;
                $isMorning = $d['session_name'] === 'morning';
                ?>
                <tr>
                    <td class="text-muted"><?= (int) $d['id'] ?></td>
                    <td><?= e($d['production_date']) ?></td>
                    <td class="fw-semibold"><?= e($d['outlet_name']) ?></td>
                    <td>
                        <span class="badge <?= $isMorning ? 'text-bg-warning' : 'text-bg-primary' ?>">
                            <?= $isMorning ? 'Morning' : 'Evening' ?>
                        </span>
                    </td>
                    <td class="text-end"><?= number_format((int) $d['units_delivered']) ?></td>
                    <td class="text-end"><?= naira($d['revenue_kobo']) ?></td>
                    <td class="text-muted small"><?= e($d['created_at']) ?></td>
                </tr>
                <?php
            }
            if (!$hasRecent) {
                echo '<tr><td colspan="7" class="text-center text-muted py-4">No deliveries recorded yet.</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
