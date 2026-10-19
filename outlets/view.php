<?php
/**
 * ==========================================================================
 * outlets/view.php  -  SECURE (PATCHED) OUTLET DEMAND VIEW
 * ==========================================================================
 * This page reads the demand of ONE outlet given in the query string:
 *
 *     outlets/view.php?outlet_id=3
 *
 * SECURITY: before reading anything we call
 *
 *     require_outlet_access($outletId);
 *
 * so a cashier of outlet 1 gets a 403 when he tries outlet_id=3.
 * Compare this with security/idor_vulnerable.php which has NO such check.
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

$outletId = (int) ($_GET['outlet_id'] ?? 0);

if ($outletId <= 0) {
    flash('Please choose an outlet.', 'warning');
    redirect('outlets/');
}

// ------------------------------------------------------------------
// *** THE PATCH: authorisation check BEFORE the database query ***
// ------------------------------------------------------------------
require_outlet_access($outletId);

// ---- load the outlet (prepared statement) -------------------------
$stmt  = stream_query(
    'SELECT id, name, daily_demand_units FROM outlets WHERE id = ?',
    [$outletId]
);
$outlet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$outlet) {
    flash('Outlet #' . $outletId . ' does not exist.', 'warning');
    redirect('outlets/');
}

$pageTitle = 'Outlet demand: ' . $outlet['name'];
$activeNav = 'outlets';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1"><?= e($outlet['name']) ?></h1>
        <p class="text-muted mb-0">Secure demand view &middot; outlet #<?= (int) $outlet['id'] ?></p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('outlets/')) ?>">&larr; All outlets</a>
</div>

<div class="alert alert-success border-0 shadow-sm">
    <strong>AUTHORISED.</strong>
    <code>can_access_outlet(<?= (int) $outlet['id'] ?>)</code> returned TRUE for
    <em><?= current_user_label() ?></em>.
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Daily demand</div>
            <div class="stat-value"><?= number_format((int) $outlet['daily_demand_units']) ?> units</div>
            <div class="stat-sub">per day</div>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Its share of total demand</div>
            <?php $total = total_daily_demand(); ?>
            <div class="stat-value">
                <?= $total > 0 ? number_format(((int) $outlet['daily_demand_units'] / $total) * 100, 1) : '0.0' ?>%
            </div>
            <div class="stat-sub">of <?= number_format($total) ?> units</div>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Its share of the planned run</div>
            <div class="stat-value">
                <?= number_format((int) $outlet['daily_demand_units']) ?> / <?= number_format(planned_production($total)) ?>
            </div>
            <div class="stat-sub">planned = demand &times; 1.10</div>
        </div></div>
    </div>
</div>

<div class="table-card">
    <div class="p-3 pb-2">
        <h2 class="h5 mb-0">Recent deliveries to this outlet</h2>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Date</th>
                <th>Session</th>
                <th class="text-end">Units</th>
                <th class="text-end">Revenue (kobo)</th>
                <th class="text-end">Value</th>
            </tr>
            </thead>
            <tbody>
            <?php
            // STREAMING: one row at a time, never fetchAll()
            $dStmt = stream_query(
                'SELECT production_date, session_name, units_delivered, revenue_kobo
                   FROM deliveries
                  WHERE outlet_id = ?
                  ORDER BY production_date DESC, session_name
                  LIMIT 20',
                [$outletId]
            );
            $hasRows = false;
            while ($d = $dStmt->fetch(PDO::FETCH_ASSOC)) {
                $hasRows = true;
                ?>
                <tr>
                    <td><?= e($d['production_date']) ?></td>
                    <td><span class="badge <?= $d['session_name'] === 'morning' ? 'text-bg-warning' : 'text-bg-primary' ?>">
                        <?= e(ucfirst($d['session_name'])) ?></span></td>
                    <td class="text-end"><?= number_format((int) $d['units_delivered']) ?></td>
                    <td class="text-end"><?= number_format((int) $d['revenue_kobo']) ?></td>
                    <td class="text-end"><?= naira($d['revenue_kobo']) ?></td>
                </tr>
                <?php
            }
            if (!$hasRows) {
                echo '<tr><td colspan="5" class="text-center text-muted py-4">No deliveries recorded yet.</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
