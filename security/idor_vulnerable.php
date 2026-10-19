<?php
/**
 * ==========================================================================
 * security/idor_vulnerable.php
 * --------------------------------------------------------------------------
 * !! INTENTIONALLY VULNERABLE - DO NOT COPY THIS PATTERN INTO REAL CODE !!
 *
 * Assignment requirement: "Create a clearly labelled demonstration of an
 * IDOR vulnerability ... Show an intentionally vulnerable example where an
 * outlet ID supplied by a user can access another outlet's demand."
 *
 * WHAT IS WRONG HERE?
 *   The outlet id comes straight from the query string and is used in the
 *   SQL statement. There is NO authorisation check, so a cashier who is
 *   only allowed to see outlet 1 can read outlet 2 and 3 by changing the
 *   number in the URL.
 *
 *   (It still uses a PDO prepared statement - that is the point of the
 *    exercise: prepared statements stop SQL injection, NOT IDOR.)
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

// --- demo context: sign in as the limited user (whitelisted keys only) ---
if (isset($_GET['act'])) {
    switch_user((string) $_GET['act']);
}

// --- the id supplied by the user ------------------------------------
$outletId = (int) ($_GET['outlet_id'] ?? 0);

$signedIn = current_user();

// ------------------------------------------------------------------
// VULNERABILITY: no require_outlet_access() / can_access_outlet() here!
// ------------------------------------------------------------------
$outlet     = null;
$deliveries = [];
$queryUsed  = '';

if ($outletId > 0) {
    // Prepared statement => safe from SQL injection, but the AUTHORIZATION
    // is completely missing => broken access control / IDOR.
    $queryUsed = 'SELECT id, name, daily_demand_units FROM outlets WHERE id = ?';
    $stmt      = stream_query($queryUsed, [$outletId]);
    $outlet    = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($outlet) {
        $dStmt = stream_query(
            'SELECT production_date, session_name, units_delivered
               FROM deliveries
              WHERE outlet_id = ?
              ORDER BY production_date DESC, session_name
              LIMIT 5',
            [$outletId]
        );
        while ($d = $dStmt->fetch(PDO::FETCH_ASSOC)) {
            $deliveries[] = $d;
        }
    }
}

$pageTitle = 'IDOR - VULNERABLE example';
$activeNav = 'security';
require __DIR__ . '/../includes/header.php';
?>

<div class="alert alert-danger border-0 shadow-sm mb-4">
    <h1 class="h4 mb-1">&#9888; INTENTIONALLY VULNERABLE PAGE &mdash; IDOR DEMONSTRATION</h1>
    <p class="mb-0 small">
        This file exists only for the assignment. The normal application uses
        <code>security/idor_secure.php</code> instead.
    </p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="table-card p-3">
            <h2 class="h6 text-uppercase text-muted">Who is signed in</h2>
            <p class="mb-1"><strong><?= current_user_label() ?></strong></p>
            <p class="small text-muted mb-0">
                He is allowed to open only outlet(s):
                <code><?= $signedIn['outlet_ids'] === null ? 'ALL' : implode(', ', $signedIn['outlet_ids']) ?></code>
            </p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="table-card p-3">
            <h2 class="h6 text-uppercase text-muted">Request</h2>
            <p class="mb-1 small"><code>?outlet_id=<?= $outletId ?></code></p>
            <p class="small text-muted mb-0">
                There is no check between the user input and the query.
            </p>
        </div>
    </div>
</div>

<?php if ($outletId <= 0): ?>

    <div class="alert alert-warning">Add <code>?outlet_id=3</code> to the URL.</div>

<?php elseif (!$outlet): ?>

    <div class="alert alert-warning mb-3">
        Outlet <strong>#<?= $outletId ?></strong> does not exist.
        Try <code>?outlet_id=1</code>, <code>2</code> or <code>3</code>.
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('security/')) ?>">&larr; back to the demonstration</a>

<?php else: ?>

    <?php
    $allowed = can_access_outlet($outletId);
    $leak    = !$allowed;
    ?>

    <div class="alert <?= $leak ? 'alert-danger' : 'alert-success' ?> border-0 shadow-sm">
        <strong><?= $leak ? 'DATA LEAKED!' : 'No leak for this request.' ?></strong><br>
        <?= $leak
            ? 'Outlet #' . $outletId . ' does <u>not</u> belong to the signed-in user, yet the page returned its data. '
              . 'That is the Insecure Direct Object Reference.'
            : 'The signed-in user is allowed to open this outlet, so nothing sensitive was disclosed here. '
              . 'Change <code>outlet_id</code> to demonstrate the leak.' ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="table-card vuln-card p-3 h-100">
                <div class="stat-label">Outlet name</div>
                <div class="stat-value"><?= e($outlet['name']) ?></div>
                <div class="stat-sub">id = <?= (int) $outlet['id'] ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="table-card vuln-card p-3 h-100">
                <div class="stat-label">Daily demand (confidential figure)</div>
                <div class="stat-value text-danger"><?= number_format((int) $outlet['daily_demand_units']) ?> units</div>
                <div class="stat-sub">returned without authorisation</div>
            </div>
        </div>
    </div>

    <div class="table-card mb-4">
        <div class="p-3 pb-2">
            <h2 class="h5 mb-0">Deliveries of this outlet (also leaked)</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr><th>Date</th><th>Session</th><th class="text-end">Units</th></tr>
                </thead>
                <tbody>
                <?php if (!$deliveries): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No deliveries.</td></tr>
                <?php else: ?>
                    <?php foreach ($deliveries as $d): ?>
                        <tr>
                            <td><?= e($d['production_date']) ?></td>
                            <td><?= e(ucfirst($d['session_name'])) ?></td>
                            <td class="text-end"><?= number_format((int) $d['units_delivered']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="code-block mb-3">SQL executed: <?= e($queryUsed) ?>
Parameter:   <?= $outletId ?>
Auth check:  <span class="bad">MISSING</span></div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-success"
           href="<?= e(url('security/idor_secure.php?act=' . $signedIn['key'] . '&outlet_id=' . $outletId)) ?>">
            Send the SAME request to the patched version
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(url('security/')) ?>">&larr; back</a>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
