<?php
/**
 * ==========================================================================
 * dashboard/index.php  -  MANAGEMENT DASHBOARD
 * ==========================================================================
 * Shows the key numbers of the bakery:
 *   number of outlets, total daily demand, planned production,
 *   actual production, waste, flour usage and the flour reorder status.
 *
 * Chart.js draws:
 *   1. a LINE chart  -> production (planned / actual) vs demand over time
 *   2. a BAR  chart  -> flour used (kg) per production day
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

/* ------------------------------------------------------------------
 * 1. NUMBERS FOR THE STAT CARDS (each one is a small streaming query)
 * ------------------------------------------------------------------ */
$outletCount  = (int) scalar('SELECT COUNT(*) FROM outlets');
$totalDemand  = total_daily_demand();
// Planned production = total demand x 1.10, rounded to whole units.
// round() instead of ceil() keeps 1,300 x 1.10 = 1,430 (ceil() would give
// 1,431 because 1.10 cannot be stored exactly in a computer float).
$plannedTotal = (int) round($totalDemand * PRODUCTION_BUFFER_RATE);

$todayPlan = null;
$t = stream_query(
    'SELECT production_date, planned_units, actual_units, waste_units
       FROM production_runs ORDER BY production_date DESC LIMIT 1'
);
$latestRun = $t->fetch(PDO::FETCH_ASSOC);

$todayRow = stream_query(
    'SELECT planned_units, actual_units, waste_units
       FROM production_runs WHERE production_date = ?',
    [date('Y-m-d')]
)->fetch(PDO::FETCH_ASSOC);

$runRow    = $todayRow ?: $latestRun;
$planned   = $runRow ? (int) $runRow['planned_units'] : $plannedTotal;
$actual    = $runRow ? (int) $runRow['actual_units'] : 0;
$waste     = $runRow ? (int) $runRow['waste_units'] : 0;
$wastePct  = waste_percent($waste, $actual);

$dailyUsage = avg_daily_flour_usage();
$onHand     = flour_on_hand();
$incoming   = flour_incoming();

// ---- flour reorder status (streaming) ------------------------------
$reorderStmt = stream_query('SELECT id, name, lead_time_days FROM suppliers ORDER BY name');
$reorderNeeded = [];
$supplierCount = 0;
while ($sup = $reorderStmt->fetch(PDO::FETCH_ASSOC)) {
    $supplierCount++;
    $point = reorder_point($dailyUsage, (int) $sup['lead_time_days']);
    if ($onHand < $point) {
        $reorderNeeded[] = $sup['name'] . ' (point ' . number_format($point, 1) . ' kg)';
    }
}

/* ------------------------------------------------------------------
 * 2. DATA FOR CHART.JS
 *    The chart needs arrays, so here we collect the rows in memory and
 *    encode them as JSON. Lists in the pages are always streamed.
 * ------------------------------------------------------------------ */
$chartStmt = stream_query(
    "SELECT pr.production_date,
            pr.total_demand_units,
            pr.planned_units,
            pr.actual_units,
            pr.waste_units,
            (SELECT COALESCE(SUM(fu.flour_kg), 0)
               FROM flour_usage fu
              WHERE fu.production_run_id = pr.id) AS flour_kg
       FROM production_runs pr
      ORDER BY pr.production_date DESC
      LIMIT 14"
);

$labels = $demandSeries = $plannedSeries = $actualSeries = $flourSeries = [];
while ($row = $chartStmt->fetch(PDO::FETCH_ASSOC)) {
    $labels[]       = $row['production_date'];
    $demandSeries[] = (int) $row['total_demand_units'];
    $plannedSeries[]= (int) $row['planned_units'];
    $actualSeries[] = (int) $row['actual_units'];
    $flourSeries[]  = (float) $row['flour_kg'];
}
// reverse so the chart reads from the oldest to the newest day
$labels        = array_reverse($labels);
$demandSeries  = array_reverse($demandSeries);
$plannedSeries = array_reverse($plannedSeries);
$actualSeries  = array_reverse($actualSeries);
$flourSeries   = array_reverse($flourSeries);

$chartData = [
    'labels'        => $labels,
    'demand'        => $demandSeries,
    'planned'       => $plannedSeries,
    'actual'        => $actualSeries,
    'flour'         => $flourSeries,
    'bufferRate'    => PRODUCTION_BUFFER_RATE,
    'wasteLimit'    => WASTE_ALERT_PERCENT,
];

$extraScripts = [
    'assets/vendor/chart.umd.min.js',   // Chart.js (stored locally)
    'assets/js/dashboard.js',           // draws the charts
];

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-1">Bakery Dashboard</h1>
<p class="text-muted mb-3">
    Central view of demand, production, waste, flour and deliveries.
</p>

<?php if (waste_investigation_required($wastePct)): ?>
    <div class="investigation-banner mb-3 d-block">
        INVESTIGATION REQUIRED &mdash; waste is <?= pct($wastePct) ?> (limit <?= number_format(WASTE_ALERT_PERCENT, 0) ?>%)
    </div>
<?php endif; ?>

<?php if ($reorderNeeded): ?>
    <div class="alert alert-danger border-0 shadow-sm py-2">
        <strong>FLOUR REORDER REQUIRED:</strong>
        <?= e(implode(' | ', $reorderNeeded)) ?>
    </div>
<?php endif; ?>

<!-- ===================== ROW 1 ===================== -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Number of outlets</div>
            <div class="stat-value"><?= number_format($outletCount) ?></div>
            <div class="stat-sub"><a href="<?= e(url('outlets/')) ?>">open registry &rarr;</a></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Total daily demand</div>
            <div class="stat-value"><?= number_format($totalDemand) ?> units</div>
            <div class="stat-sub">&Sigma; daily_demand_units</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Planned production (+10%)</div>
            <div class="stat-value text-primary"><?= number_format($plannedTotal) ?> units</div>
            <div class="stat-sub"><?= number_format($totalDemand) ?> &times; 1.10 = <?= number_format($plannedTotal) ?></div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Actual production</div>
            <div class="stat-value"><?= number_format($actual) ?> units</div>
            <div class="stat-sub">
                <?= $actual > 0 ? number_format(($actual / max($planned, 1)) * 100, 1) . '% of plan' : 'no run yet' ?>
            </div>
        </div></div>
    </div>
</div>

<!-- ===================== ROW 2 ===================== -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Waste</div>
            <div class="stat-value <?= waste_investigation_required($wastePct) ? 'text-danger' : '' ?>">
                <?= number_format($waste) ?> units
            </div>
            <div class="stat-sub">
                <?php if ($wastePct === null): ?>
                    -
                <?php elseif (waste_investigation_required($wastePct)): ?>
                    <span class="badge badge-reorder"><?= pct($wastePct) ?> - INVESTIGATION REQUIRED</span>
                <?php else: ?>
                    <?= pct($wastePct) ?> of actual (limit 5%)
                <?php endif; ?>
            </div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Flour usage</div>
            <div class="stat-value"><?= number_format($dailyUsage, 1) ?> kg</div>
            <div class="stat-sub">average per production day</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Flour on hand</div>
            <div class="stat-value"><?= number_format($onHand, 1) ?> kg</div>
            <div class="stat-sub"><?= number_format($incoming, 1) ?> kg on the way</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Flour reorder status</div>
            <div class="stat-value <?= $reorderNeeded ? 'text-danger' : 'text-success' ?>">
                <?= $reorderNeeded ? count($reorderNeeded) . ' to order' : 'OK' ?>
            </div>
            <div class="stat-sub">
                <?= $supplierCount === 0 ? 'no suppliers yet' : count($reorderNeeded) . ' of ' . $supplierCount . ' suppliers below point' ?>
            </div>
        </div></div>
    </div>
</div>

<!-- ===================== CHARTS ===================== -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="chart-box h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h2 class="h5 mb-0">Production vs demand over time</h2>
                    <small class="text-muted">Planned = demand &times; 1.10 (10% buffer)</small>
                </div>
                <span class="badge text-bg-light">Chart.js line chart</span>
            </div>
            <div class="chart-canvas-wrap">
                <canvas id="productionChart" aria-label="Production versus demand line chart"
                        role="img"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="chart-box h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h2 class="h5 mb-0">Flour used per day</h2>
                    <small class="text-muted">kilograms</small>
                </div>
                <span class="badge text-bg-light">bar</span>
            </div>
            <div class="chart-canvas-wrap">
                <canvas id="flourChart" aria-label="Flour usage bar chart" role="img"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- JSON data consumed by assets/js/dashboard.js -->
<script type="application/json" id="bakery-chart-data"><?= json_encode($chartData) ?></script>

<!-- ===================== QUICK LINKS ===================== -->
<div class="row g-3">
    <div class="col-md-4">
        <div class="table-card p-3 h-100">
            <h2 class="h5">Production</h2>
            <p class="small text-muted mb-2">
                Plan = demand &times; 1.10. Waste above 5% shows <strong>INVESTIGATION REQUIRED</strong>.
            </p>
            <a class="btn btn-sm btn-primary" href="<?= e(url('production/')) ?>">Open production planning</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="table-card p-3 h-100">
            <h2 class="h5">Flour</h2>
            <p class="small text-muted mb-2">
                Reorder point = daily usage &times; lead time &times; 1.20.
            </p>
            <a class="btn btn-sm btn-primary" href="<?= e(url('flour/')) ?>">Open flour supply</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="table-card p-3 h-100">
            <h2 class="h5">Security</h2>
            <p class="small text-muted mb-2">
                Live IDOR exploit and its patched version.
            </p>
            <a class="btn btn-sm btn-outline-danger" href="<?= e(url('security/')) ?>">Open IDOR demonstration</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
