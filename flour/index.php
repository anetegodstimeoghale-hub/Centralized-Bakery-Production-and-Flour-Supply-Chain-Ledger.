<?php
/**
 * ==========================================================================
 * flour/index.php  -  FLOUR SUPPLY, USAGE AND REORDER POINT
 * ==========================================================================
 *  * records flour orders      -> POST /flour/order
 *  * records flour usage       -> POST /flour/usage
 *  * shows the reorder point of every supplier
 *
 *      reorder point = daily flour usage x supplier lead time x 1.20
 *
 * Money is stored in kobo (1 Naira = 100 kobo).
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

$errors = pull_form_errors();

$dailyUsage   = avg_daily_flour_usage();      // kg per day
$onHand       = flour_on_hand();              // kg available now
$incoming     = flour_incoming();             // kg on the way
$reorderCount = 0;                            // suppliers below their point

$pageTitle = 'Flour Supply & Usage';
$activeNav = 'flour';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-1">Flour Supply &amp; Usage</h1>
<p class="text-muted mb-3">
    All quantities are kilograms stored as <code>DECIMAL(12,3)</code>, all money is stored in kobo.
</p>

<?php show_errors($errors); ?>

<!-- ================= STAT CARDS ================= -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Flour on hand</div>
            <div class="stat-value"><?= number_format($onHand, 3) ?> kg</div>
            <div class="stat-sub">delivered &minus; used</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Daily flour usage</div>
            <div class="stat-value"><?= number_format($dailyUsage, 3) ?> kg</div>
            <div class="stat-sub">average of the last <?= FLOUR_USAGE_WINDOW_DAYS ?> days</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Flour on the way</div>
            <div class="stat-value"><?= number_format($incoming, 3) ?> kg</div>
            <div class="stat-sub">orders without delivery date</div>
        </div></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Suppliers to reorder</div>
            <div class="stat-value" id="reorder-count">-</div>
            <div class="stat-sub">below reorder point</div>
        </div></div>
    </div>
</div>

<!-- ================= REORDER POINT TABLE ================= -->
<div class="table-card mb-4">
    <div class="p-3 pb-2">
        <h2 class="h5 mb-1">Flour reorder status</h2>
        <p class="text-muted small mb-0">
            <code>reorder point = daily flour usage &times; supplier lead time (days) &times; 1.20</code>
        </p>
    </div>

    <div class="formula-box mx-3 mb-3">
        Right now: <strong><?= number_format($dailyUsage, 3) ?> kg/day</strong>
        &times; lead time &times; <strong>1.20</strong> (20% safety stock).
        The bakery pools all flour, so the same <strong><?= number_format($onHand, 3) ?> kg</strong>
        on hand is compared with each supplier's own point (their lead times are different).
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>Supplier</th>
                <th class="text-end">Lead time (days)</th>
                <th class="text-end">Daily usage (kg)</th>
                <th class="text-end">Reorder point (kg)</th>
                <th class="text-end">On hand (kg)</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php
            // STREAMING: suppliers are read one row at a time
            $supStmt = stream_query('SELECT id, name, lead_time_days FROM suppliers ORDER BY name');
            $hasSup  = false;
            while ($sup = $supStmt->fetch(PDO::FETCH_ASSOC)) {
                $hasSup = true;
                $lead     = (int) $sup['lead_time_days'];
                $point    = reorder_point($dailyUsage, $lead);   // FORMULA
                $needs    = $onHand < $point;
                if ($needs) {
                    $reorderCount++;
                }
                ?>
                <tr class="<?= $needs ? 'table-danger' : '' ?>">
                    <td class="fw-semibold"><?= e($sup['name']) ?></td>
                    <td class="text-end"><?= $lead ?></td>
                    <td class="text-end"><?= number_format($dailyUsage, 3) ?></td>
                    <td class="text-end fw-semibold"><?= number_format($point, 3) ?></td>
                    <td class="text-end"><?= number_format($onHand, 3) ?></td>
                    <td>
                        <?php if ($needs): ?>
                            <span class="badge badge-reorder">REORDER REQUIRED</span>
                        <?php else: ?>
                            <span class="badge badge-ok">OK - stock above point</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            if (!$hasSup) {
                echo '<tr><td colspan="6" class="text-center text-muted py-4">No suppliers registered yet.</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // keep the "suppliers to reorder" card in sync with the table above
    document.addEventListener('DOMContentLoaded', function () {
        var box = document.getElementById('reorder-count');
        if (box) { box.textContent = '<?= (int) $reorderCount ?>'; }
    });
</script>

<div class="row g-3 mb-4">

    <!-- ============ FLOUR ORDER FORM ============ -->
    <div class="col-lg-4">
        <div class="table-card p-3">
            <h2 class="h5 mb-3">Order flour</h2>

            <form method="post" action="<?= e(endpoint('/flour/order')) ?>" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="supplier_id">Supplier</label>
                    <select class="form-select<?= field_error($errors, 'supplier_id') ? ' is-invalid' : '' ?>"
                            id="supplier_id" name="supplier_id" required>
                        <option value="">-- choose a supplier --</option>
                        <?php
                        $supOpts = stream_query('SELECT id, name FROM suppliers ORDER BY name');
                        while ($s = $supOpts->fetch(PDO::FETCH_ASSOC)) {
                            $selected = old('supplier_id') == $s['id'] ? ' selected' : '';
                            echo '<option value="' . (int) $s['id'] . '"' . $selected . '>'
                                . e($s['name']) . '</option>';
                        }
                        ?>
                    </select>
                    <?php if (field_error($errors, 'supplier_id')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'supplier_id') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="quantity_kg">Quantity (kg)</label>
                    <input type="number" step="0.001" min="0.001" max="1000000"
                           class="form-control<?= field_error($errors, 'quantity_kg') ? ' is-invalid' : '' ?>"
                           id="quantity_kg" name="quantity_kg" required placeholder="e.g. 1500.500"
                           value="<?= old('quantity_kg') ?>">
                    <?php if (field_error($errors, 'quantity_kg')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'quantity_kg') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="unit_price_kobo">Price per kg (kobo)</label>
                    <input type="number" step="1" min="0" max="100000000"
                           class="form-control<?= field_error($errors, 'unit_price_kobo') ? ' is-invalid' : '' ?>"
                           id="unit_price_kobo" name="unit_price_kobo" required placeholder="e.g. 45000"
                           value="<?= old('unit_price_kobo', '45000') ?>">
                    <div class="form-text">Money is stored in kobo: 45000 kobo = &#8358;450.00 per kg.</div>
                    <?php if (field_error($errors, 'unit_price_kobo')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'unit_price_kobo') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="ordered_at">Ordered at</label>
                    <input type="datetime-local"
                           class="form-control<?= field_error($errors, 'ordered_at') ? ' is-invalid' : '' ?>"
                           id="ordered_at" name="ordered_at"
                           value="<?= old('ordered_at', date('Y-m-d\TH:i')) ?>">
                    <?php if (field_error($errors, 'ordered_at')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'ordered_at') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="delivered_at">Delivered at</label>
                    <input type="datetime-local"
                           class="form-control<?= field_error($errors, 'delivered_at') ? ' is-invalid' : '' ?>"
                           id="delivered_at" name="delivered_at" value="<?= old('delivered_at') ?>">
                    <div class="form-text">Leave empty while the flour is still on the way.</div>
                    <?php if (field_error($errors, 'delivered_at')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'delivered_at') ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100">Place flour order</button>
            </form>
        </div>
    </div>

    <!-- ============ ORDER LIST (STREAMED) ============ -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="p-3 pb-2">
                <h2 class="h5 mb-0">Flour orders</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Supplier</th>
                        <th class="text-end">Quantity</th>
                        <th class="text-end">Price/kg</th>
                        <th class="text-end">Total cost</th>
                        <th>Ordered at</th>
                        <th>Delivered at</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    // STREAMING: one order at a time (fetchAll is never used)
                    $orderStmt = stream_query(
                        'SELECT o.id, o.quantity_kg, o.unit_price_kobo, o.total_cost_kobo,
                                o.ordered_at, o.delivered_at, s.name AS supplier_name
                           FROM flour_orders o
                           JOIN suppliers s ON s.id = o.supplier_id
                          ORDER BY o.ordered_at DESC, o.id DESC
                          LIMIT 50'
                    );
                    $hasOrders = false;
                    while ($o = $orderStmt->fetch(PDO::FETCH_ASSOC)) {
                        $hasOrders = true;
                        ?>
                        <tr>
                            <td class="text-muted"><?= (int) $o['id'] ?></td>
                            <td class="fw-semibold"><?= e($o['supplier_name']) ?></td>
                            <td class="text-end"><?= kg($o['quantity_kg']) ?></td>
                            <td class="text-end"><?= naira($o['unit_price_kobo']) ?></td>
                            <td class="text-end"><?= naira($o['total_cost_kobo']) ?></td>
                            <td><?= e($o['ordered_at']) ?></td>
                            <td>
                                <?php if ($o['delivered_at']): ?>
                                    <span class="badge badge-ok"><?= e($o['delivered_at']) ?></span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">on the way</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    }
                    if (!$hasOrders) {
                        echo '<tr><td colspan="7" class="text-center text-muted py-4">No flour orders yet.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- ============ USAGE FORM ============ -->
    <div class="col-lg-4">
        <div class="table-card p-3">
            <h2 class="h5 mb-3">Record flour usage</h2>

            <form method="post" action="<?= e(endpoint('/flour/usage')) ?>" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="production_run_id">Production run</label>
                    <select class="form-select<?= field_error($errors, 'production_run_id') ? ' is-invalid' : '' ?>"
                            id="production_run_id" name="production_run_id" required>
                        <option value="">-- choose a production run --</option>
                        <?php
                        $runOpts = stream_query(
                            'SELECT id, production_date, planned_units, actual_units
                               FROM production_runs ORDER BY production_date DESC LIMIT 60'
                        );
                        while ($r = $runOpts->fetch(PDO::FETCH_ASSOC)) {
                            $selected = old('production_run_id') == $r['id'] ? ' selected' : '';
                            echo '<option value="' . (int) $r['id'] . '"' . $selected . '>'
                                . e($r['production_date'])
                                . ' - actual ' . number_format((int) $r['actual_units'])
                                . '</option>';
                        }
                        ?>
                    </select>
                    <?php if (field_error($errors, 'production_run_id')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'production_run_id') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="flour_kg">Flour used (kg)</label>
                    <input type="number" step="0.001" min="0.001" max="1000000"
                           class="form-control<?= field_error($errors, 'flour_kg') ? ' is-invalid' : '' ?>"
                           id="flour_kg" name="flour_kg" required placeholder="e.g. 605.500"
                           value="<?= old('flour_kg') ?>">
                    <?php if (field_error($errors, 'flour_kg')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'flour_kg') ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100">Record usage</button>
            </form>

            <div class="formula-box mt-3">
                <strong>Reorder point</strong><br>
                <code>daily usage &times; lead time &times; 1.20</code>
            </div>
        </div>
    </div>

    <!-- ============ USAGE LIST (STREAMED) ============ -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="p-3 pb-2">
                <h2 class="h5 mb-0">Flour usage history</h2>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Production date</th>
                        <th class="text-end">Flour (kg)</th>
                        <th class="text-end">Units produced</th>
                        <th class="text-end">kg per unit</th>
                        <th>Recorded at</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $usageStmt = stream_query(
                        'SELECT fu.id, fu.flour_kg, fu.created_at, pr.production_date, pr.actual_units
                           FROM flour_usage fu
                           JOIN production_runs pr ON pr.id = fu.production_run_id
                          ORDER BY pr.production_date DESC, fu.id DESC
                          LIMIT 50'
                    );
                    $hasUsage = false;
                    while ($u = $usageStmt->fetch(PDO::FETCH_ASSOC)) {
                        $hasUsage = true;
                        $perUnit  = (int) $u['actual_units'] > 0
                            ? (float) $u['flour_kg'] / (int) $u['actual_units']
                            : 0;
                        ?>
                        <tr>
                            <td class="text-muted"><?= (int) $u['id'] ?></td>
                            <td class="fw-semibold"><?= e($u['production_date']) ?></td>
                            <td class="text-end"><?= kg($u['flour_kg']) ?></td>
                            <td class="text-end"><?= number_format((int) $u['actual_units']) ?></td>
                            <td class="text-end"><?= number_format($perUnit, 3) ?> kg</td>
                            <td><?= e($u['created_at']) ?></td>
                        </tr>
                        <?php
                    }
                    if (!$hasUsage) {
                        echo '<tr><td colspan="6" class="text-center text-muted py-4">No flour usage recorded yet.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
