<?php
/**
 * ==========================================================================
 * suppliers/index.php  -  SUPPLIER REGISTRY (list + registration form)
 * ==========================================================================
 * Flour suppliers and their lead time (how many days an order takes).
 * The lead time is one of the two inputs of the REORDER POINT formula.
 * The form posts to the endpoint  POST /suppliers/register
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

$errors = pull_form_errors();

$supplierCount = (int) scalar('SELECT COUNT(*) FROM suppliers');
$avgLead       = scalar('SELECT AVG(lead_time_days) FROM suppliers');

$pageTitle = 'Supplier Registry';
$activeNav = 'suppliers';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Flour Supplier Registry</h1>
        <p class="text-muted mb-0">Suppliers, their lead time and the flour they have delivered.</p>
    </div>
    <a class="btn btn-outline-primary btn-sm" href="<?= e(url('flour/')) ?>">Reorder status &rarr;</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Registered suppliers</div>
            <div class="stat-value"><?= number_format($supplierCount) ?></div>
            <div class="stat-sub">rows in <code>suppliers</code></div>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Average lead time</div>
            <div class="stat-value"><?= $avgLead === null ? '-' : number_format((float) $avgLead, 1) . ' days' ?></div>
            <div class="stat-sub">AVG(lead_time_days)</div>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Flour orders placed</div>
            <div class="stat-value"><?= number_format((int) scalar('SELECT COUNT(*) FROM flour_orders')) ?></div>
            <div class="stat-sub">rows in <code>flour_orders</code></div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <!-- ============ REGISTRATION FORM ============ -->
    <div class="col-lg-4">
        <div class="table-card p-3">
            <h2 class="h5 mb-3">Register a flour supplier</h2>

            <?php show_errors($errors); ?>

            <form method="post" action="<?= e(endpoint('/suppliers/register')) ?>" novalidate>
                <div class="mb-3">
                    <label class="form-label" for="name">Supplier name</label>
                    <input type="text" class="form-control<?= field_error($errors, 'name') ? ' is-invalid' : '' ?>"
                           id="name" name="name" maxlength="120" required
                           placeholder="e.g. Golden Mills Flour Co."
                           value="<?= old('name') ?>">
                    <?php if (field_error($errors, 'name')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'name') ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="lead_time_days">Lead time (days)</label>
                    <input type="number" class="form-control<?= field_error($errors, 'lead_time_days') ? ' is-invalid' : '' ?>"
                           id="lead_time_days" name="lead_time_days" min="1" max="90" step="1" required
                           placeholder="e.g. 3"
                           value="<?= old('lead_time_days', '3') ?>">
                    <div class="form-text">Days between placing an order and receiving the flour.</div>
                    <?php if (field_error($errors, 'lead_time_days')): ?>
                        <div class="invalid-feedback"><?= field_error($errors, 'lead_time_days') ?></div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100">Register supplier</button>
            </form>

            <div class="formula-box mt-3">
                <strong>Why lead time matters</strong><br>
                <code>reorder point = daily flour usage &times; lead time &times; 1.20</code>
            </div>
        </div>
    </div>

    <!-- ============ SUPPLIER LIST (STREAMED) ============ -->
    <div class="col-lg-8">
        <div class="table-card">
            <div class="p-3 pb-2 d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Registered suppliers</h2>
                <span class="badge text-bg-secondary"><?= number_format($supplierCount) ?> suppliers</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Supplier name</th>
                        <th class="text-end">Lead time (days)</th>
                        <th class="text-end">Orders</th>
                        <th class="text-end">Flour received</th>
                        <th class="text-end">Spend</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    // STREAMING: row by row, never fetchAll()
                    $stmt = stream_query(
                        'SELECT s.id, s.name, s.lead_time_days,
                                (SELECT COUNT(*) FROM flour_orders o WHERE o.supplier_id = s.id) AS order_count,
                                (SELECT COALESCE(SUM(o.quantity_kg),0) FROM flour_orders o
                                  WHERE o.supplier_id = s.id AND o.delivered_at IS NOT NULL) AS received_kg,
                                (SELECT COALESCE(SUM(o.total_cost_kobo),0) FROM flour_orders o
                                  WHERE o.supplier_id = s.id) AS spend_kobo
                           FROM suppliers s
                          ORDER BY s.name'
                    );
                    $hasRows = false;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $hasRows = true;
                        ?>
                        <tr>
                            <td class="text-muted"><?= (int) $row['id'] ?></td>
                            <td class="fw-semibold"><?= e($row['name']) ?></td>
                            <td class="text-end">
                                <span class="badge text-bg-light"><?= (int) $row['lead_time_days'] ?> d</span>
                            </td>
                            <td class="text-end"><?= number_format((int) $row['order_count']) ?></td>
                            <td class="text-end"><?= kg($row['received_kg']) ?></td>
                            <td class="text-end"><?= naira($row['spend_kobo']) ?></td>
                        </tr>
                        <?php
                    }
                    if (!$hasRows) {
                        echo '<tr><td colspan="6" class="text-center text-muted py-4">'
                            . 'No suppliers yet - register the first one on the left.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
