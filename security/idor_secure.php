<?php
/**
 * ==========================================================================
 * security/idor_secure.php  -  THE PATCHED VERSION
 * ==========================================================================
 * Same page, same SQL statement, but with the authorisation check that was
 * missing in security/idor_vulnerable.php:
 *
 *      if (!can_access_outlet($outletId)) { ... HTTP 403 ... }
 *
 * This is the implementation that the rest of the application uses
 * (see includes/auth.php -> can_access_outlet / require_outlet_access
 *  and outlets/view.php).
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

// --- demo context: sign in as the limited user (whitelisted keys only) ---
if (isset($_GET['act'])) {
    switch_user((string) $_GET['act']);
}

$outletId = (int) ($_GET['outlet_id'] ?? 0);
$signedIn = current_user();

// ------------------------------------------------------------------
// *** THE PATCH: authorisation check BEFORE touching the database ***
// ------------------------------------------------------------------
require_outlet_access($outletId);

$outlet = null;
if ($outletId > 0) {
    $stmt   = stream_query('SELECT id, name, daily_demand_units FROM outlets WHERE id = ?', [$outletId]);
    $outlet = $stmt->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = 'IDOR - PATCHED example';
$activeNav = 'security';
require __DIR__ . '/../includes/header.php';
?>

<div class="alert alert-success border-0 shadow-sm mb-4">
    <h1 class="h4 mb-1">&#10003; PATCHED (SECURE) VERSION &mdash; IDOR FIXED</h1>
    <p class="mb-0 small">
        Identical SQL, but the requested id is compared with the outlets the signed-in user owns.
    </p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="table-card p-3">
            <h2 class="h6 text-uppercase text-muted">Who is signed in</h2>
            <p class="mb-1"><strong><?= current_user_label() ?></strong></p>
            <p class="small text-muted mb-0">
                Allowed outlet(s): <code><?= $signedIn['outlet_ids'] === null ? 'ALL' : implode(', ', $signedIn['outlet_ids']) ?></code>
            </p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="table-card p-3">
            <h2 class="h6 text-uppercase text-muted">Request</h2>
            <p class="mb-1 small"><code>?outlet_id=<?= $outletId ?></code></p>
            <p class="small text-muted mb-0">Checked by <code>can_access_outlet()</code> before any query.</p>
        </div>
    </div>
</div>

<?php if ($outletId <= 0 || !$outlet): ?>

    <div class="alert alert-warning">
        Outlet <strong>#<?= $outletId ?></strong> does not exist or no id was given.
    </div>

<?php else: ?>

    <div class="alert alert-success border-0 shadow-sm">
        <strong>AUTHORISED.</strong>
        The signed-in user really owns outlet #<?= $outletId ?>, so the data may be returned.
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="table-card secure-card p-3 h-100">
                <div class="stat-label">Outlet name</div>
                <div class="stat-value"><?= e($outlet['name']) ?></div>
                <div class="stat-sub">id = <?= (int) $outlet['id'] ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="table-card secure-card p-3 h-100">
                <div class="stat-label">Daily demand</div>
                <div class="stat-value text-success"><?= number_format((int) $outlet['daily_demand_units']) ?> units</div>
                <div class="stat-sub">returned after the ownership check</div>
            </div>
        </div>
    </div>

    <div class="code-block mb-3">SQL executed: SELECT id, name, daily_demand_units FROM outlets WHERE id = ?
Parameter:   <?= $outletId ?>
Auth check:  <span class="good">PASSED - can_access_outlet(<?= $outletId ?>) === true</span></div>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-danger"
           href="<?= e(url('security/idor_vulnerable.php?act=' . $signedIn['key'] . '&outlet_id=' . $outletId)) ?>">
            Send the same request to the vulnerable version
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(url('security/')) ?>">&larr; back</a>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
