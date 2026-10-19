<?php
/**
 * ==========================================================================
 * audit/index.php  -  PRODUCTION AUDIT LOG (read only)
 * ==========================================================================
 * Shows every change ever made to a production run.
 *
 * WHY NORMAL USERS CANNOT EDIT OR DELETE THESE RECORDS
 * ----------------------------------------------------
 * 1. This application contains NO update/delete code for this table.
 * 2. Two MySQL triggers refuse UPDATE and DELETE statements
 *    (see database/bakery.sql: trg_audit_no_update / trg_audit_no_delete).
 * 3. The foreign key production_run_id uses ON DELETE RESTRICT, so a run
 *    that already has an audit entry cannot be deleted either.
 * 4. In production you connect with a database account that only has
 *    SELECT + INSERT on this table (see README -> deployment).
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

$auditCount = (int) scalar('SELECT COUNT(*) FROM production_audit_log');
$runCount   = (int) scalar('SELECT COUNT(*) FROM production_runs');

$pageTitle = 'Production Audit Log';
$activeNav = 'audit';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Production Audit Log</h1>
        <p class="text-muted mb-0">Append-only history of every production plan change.</p>
    </div>
    <a class="btn btn-outline-primary btn-sm" href="<?= e(url('production/')) ?>">Back to production &rarr;</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Audit entries</div>
            <div class="stat-value"><?= number_format($auditCount) ?></div>
            <div class="stat-sub">rows in <code>production_audit_log</code></div>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Production runs</div>
            <div class="stat-value"><?= number_format($runCount) ?></div>
            <div class="stat-sub">rows in <code>production_runs</code></div>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="stat-body">
            <div class="stat-label">Edit / delete buttons</div>
            <div class="stat-value text-success">none</div>
            <div class="stat-sub">the UI offers no way to alter history</div>
        </div></div>
    </div>
</div>

<div class="alert alert-light border mb-3">
    <strong>Protected by design:</strong>
    <code>BEFORE UPDATE</code> and <code>BEFORE DELETE</code> triggers raise an error, and the
    foreign key is <code>ON DELETE RESTRICT</code>. Try
    <code>UPDATE production_audit_log SET action='x' WHERE id=1;</code> in phpMyAdmin &mdash;
    MySQL will refuse it.
</div>

<div class="table-card">
    <div class="p-3 pb-2 d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">History</h2>
        <span class="badge text-bg-secondary"><?= number_format($auditCount) ?> entries</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th>#</th>
                <th>When</th>
                <th>Run</th>
                <th>Action</th>
                <th>By</th>
                <th>Old values</th>
                <th>New values</th>
            </tr>
            </thead>
            <tbody>
            <?php
            // STREAMING: one audit entry at a time (no fetchAll anywhere)
            $stmt = stream_query(
                'SELECT a.id, a.action, a.old_values, a.new_values, a.performed_by,
                        a.created_at, a.production_run_id,
                        p.production_date
                   FROM production_audit_log a
                   JOIN production_runs p ON p.id = a.production_run_id
                  ORDER BY a.id DESC
                  LIMIT 100'
            );
            $hasRows = false;
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $hasRows = true;
                $isCreate = $row['action'] === 'CREATE';
                ?>
                <tr>
                    <td class="text-muted"><?= (int) $row['id'] ?></td>
                    <td class="small"><?= e($row['created_at']) ?></td>
                    <td>
                        <span class="badge text-bg-dark">#<?= (int) $row['production_run_id'] ?></span>
                        <span class="small"><?= e($row['production_date']) ?></span>
                    </td>
                    <td>
                        <span class="badge <?= $isCreate ? 'badge-ok' : 'text-bg-primary' ?>">
                            <?= e($row['action']) ?>
                        </span>
                    </td>
                    <td class="small"><?= e($row['performed_by']) ?></td>
                    <td class="small text-muted"><?= $row['old_values'] === null ? '-' : e($row['old_values']) ?></td>
                    <td class="small"><?= e($row['new_values']) ?></td>
                </tr>
                <?php
            }
            if (!$hasRows) {
                echo '<tr><td colspan="7" class="text-center text-muted py-4">'
                    . 'No audit entries yet - save a production run first.</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
