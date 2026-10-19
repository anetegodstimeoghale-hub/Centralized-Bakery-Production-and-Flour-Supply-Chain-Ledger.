<?php
/**
 * ==========================================================================
 * outlets/index.php  -  OUTLET REGISTRY (list + registration form)
 * ==========================================================================
 * Shows every bakery outlet and how much bread each one needs every day.
 * The form posts to the endpoint  POST /outlets/register
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

$errors = pull_form_errors();

// Live totals used by the cards
$totalDemand = total_daily_demand();
$outletCount = (int) scalar('SELECT COUNT(*) FROM outlets');

$pageTitle = 'Outlet Registry';
$activeNav = 'outlets';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Outlet Registry</h1>
        <p class="text-muted mb-0">Bakery outlets and their daily demand in bread units.</p>
    </div>
    <a class="btn btn-outline-primary btn-sm" href="<?= e(url('outlets/view.php')) ?>">
        Secure outlet demand view &rarr;
    </a>
</div>

<div class="row g-3 mb-4">

    <!-- ============ TOTAL DEMAND CARD ============ -->
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="stat-body">
                <div class="stat-label">Registered outlets</div>
                <div class="stat-value"><?= number_format($outletCount) ?></div>
                <div class="stat-sub">rows in <code>outlets</code></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="stat-body">
                <div class="stat-label">Total daily demand</div>
                <div class="stat-value"><?= number_format($totalDemand) ?> units</div>
                <div class="stat-sub">SUM(daily_demand_units)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="stat-body">
                <div class="stat-label">Planned production (+10%)</div>
                <div class="stat-value"><?= number_format(planned_production($totalDemand)) ?> units</div>
                <div class="stat-sub">demand &times; 1.10</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100">
            <div class="stat-body">
                <div class="stat-label">Average demand</div>
                <div class="stat-value">
                    <?= $outletCount > 0 ? number_format($totalDemand / $outletCount, 1) : '0.0' ?> units
                </div>
                <div class="stat-sub">per outlet per day</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- ============ REGISTRATION FORM ============ -->
    <div class="col-lg-4">
        <div class="table-card p-3">
            <h2 class="h5 mb-3">Register a new outlet</h2>

            <?php show_errors($errors); ?>

            <form method="post" action="<?= e(endpoint('/outlets/register')) ?>" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="name">Outlet name</label>
                    <input type="text" class="form-control<?= field_error($errors, 'name') ? ' is-invalid' : '' ?>"
                           id="name" name="name" maxlength="120" required
                           placeholder="e.g. Igando Branch"
                           value="<?= old('name') ?>">
                    <?php if (field_error($errors, 'name')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'name') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="daily_demand_units">Daily demand (units)</label>
                    <input type="number" class="form-control<?= field_error($errors, 'daily_demand_units') ? ' is-invalid' : '' ?>"
                           id="daily_demand_units" name="daily_demand_units" min="0" max="1000000"
                           step="1" required placeholder="e.g. 400"
                           value="<?= old('daily_demand_units') ?>">
                    <div class="form-text">How many units this outlet sells every day.</div>
                    <?php if (field_error($errors, 'daily_demand_units')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'daily_demand_units') ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100">Register outlet</button>
            </form>

            <div class="formula-box mt-3">
                <strong>Formula on this page</strong><br>
                <code>total daily demand = &Sigma; daily_demand_units</code><br>
                <code>planned production = total demand &times; 1.10</code>
            </div>
        </div>
    </div>

    <!-- ============ OUTLET LIST (STREAMED ROW BY ROW) ============ -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="p-3 pb-2 d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Registered outlets</h2>
                <span class="badge text-bg-secondary"><?= number_format($outletCount) ?> outlets</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Outlet name</th>
                        <th class="text-end">Daily demand (units)</th>
                        <th class="text-end">Share of demand</th>
                        <th>Secure view</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    // --------------------------------------------------
                    // STREAMING QUERY: we read ONE ROW AT A TIME.
                    // fetchAll() is not used anywhere in this project.
                    // --------------------------------------------------
                    $stmt = stream_query(
                        'SELECT id, name, daily_demand_units
                           FROM outlets
                          ORDER BY id'
                    );

                    $hasRows = false;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $hasRows = true;
                        $share   = $totalDemand > 0
                            ? ($row['daily_demand_units'] / $totalDemand) * 100
                            : 0;
                        ?>
                        <tr>
                            <td class="text-muted"><?= (int) $row['id'] ?></td>
                            <td class="fw-semibold"><?= e($row['name']) ?></td>
                            <td class="text-end"><?= number_format((int) $row['daily_demand_units']) ?></td>
                            <td class="text-end">
                                <span class="badge text-bg-light"><?= number_format($share, 1) ?>%</span>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="<?= e(url('outlets/view.php?outlet_id=' . (int) $row['id'])) ?>">
                                    open
                                </a>
                            </td>
                        </tr>
                        <?php
                    }

                    if (!$hasRows) {
                        echo '<tr><td colspan="5" class="text-center text-muted py-4">'
                            . 'No outlets yet - register the first one on the left.</td></tr>';
                    }
                    ?>
                    </tbody>
                    <tfoot>
                    <tr class="table-light fw-bold">
                        <td colspan="2">TOTAL</td>
                        <td class="text-end"><?= number_format($totalDemand) ?></td>
                        <td class="text-end">100.0%</td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
