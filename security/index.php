<?php
/**
 * ==========================================================================
 * security/index.php  -  IDOR (INSECURE DIRECT OBJECT REFERENCE) LAB
 * ==========================================================================
 * This page explains the vulnerability, lets you switch to a LIMITED user
 * and then runs the same request twice:
 *
 *   1. security/idor_vulnerable.php?act=cashier_igando&outlet_id=3
 *      -> the data of outlet 3 LEAKS to a cashier of outlet 1
 *
 *   2. security/idor_secure.php?act=cashier_igando&outlet_id=3
 *      -> HTTP 403 FORBIDDEN, no data is returned
 *
 * The rest of the application always uses the patched version
 * (can_access_outlet / require_outlet_access in includes/auth.php).
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

$user = current_user();

$pageTitle = 'Security Demonstration (IDOR)';
$activeNav = 'security';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="h3 mb-1">Security Demonstration &mdash; IDOR</h1>
<p class="text-muted mb-4">
    Insecure Direct Object Reference (Broken Object Level Authorization, OWASP API1:2023).
    The example below is <strong>intentionally broken</strong> on purpose and only exists for this assignment.
</p>

<!-- ================= SIGNED-IN USER ================= -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="table-card p-3">
            <h2 class="h5 mb-1" id="demo-users">1. Choose the demo user</h2>
            <p class="small text-muted mb-2">
                These are the demo users of the system - pick the one you want to sign in as.
                The manager may open every outlet. The cashiers may only open their own outlet.
            </p>

            <!-- ------------------------------------------------------------------
                 DISPLAY ONLY: every available demo user, listed BY NAME.
                 Each button still calls security/switch.php with exactly the same
                 keys as before, so no route, permission or line of logic changed.
                 ------------------------------------------------------------------ -->
            <?php
            // The button captions (kept identical to the ones used before, so the
            // step-by-step guide in README.md keeps working).
            $switchLabels = [
                'manager'        => 'Act as Manager (all outlets)',
                'cashier_igando' => 'Act as Cashier of outlet 1 (Igando)',
                'cashier_agege'  => 'Act as Cashier of outlet 3 (Agege)',
            ];
            ?>
            <ul class="demo-user-list">
                <?php foreach (demo_users() as $demoKey => $demoUser): ?>
                    <?php
                    // Is this the user that is signed in at this moment?
                    $isCurrentUser = ($demoKey === $user['key']);

                    // Little round avatar with the initials: "Chinwe Okafor" -> "CO".
                    $nameWords = preg_split('/\s+/', trim(preg_replace('/[^A-Za-z ]/', '', $demoUser['name'])));
                    $initials  = strtoupper(substr($nameWords[0] ?? '', 0, 1) . substr($nameWords[1] ?? '', 0, 1));
                    ?>
                    <li class="demo-user-row<?= $isCurrentUser ? ' is-current' : '' ?>">
                        <span class="demo-user-avatar" aria-hidden="true"><?= e($initials) ?></span>

                        <span class="demo-user-info">
                            <strong class="demo-user-name"><?= e($demoUser['name']) ?></strong>
                            <?php if ($isCurrentUser): ?>
                                <span class="badge badge-ok demo-user-badge">signed in</span>
                            <?php endif; ?>
                            <span class="demo-user-meta small text-muted">
                                role <code><?= e($demoUser['role']) ?></code> &middot;
                                allowed outlets:
                                <code><?= $demoUser['outlet_ids'] === null ? 'ALL' : e(implode(', ', $demoUser['outlet_ids'])) ?></code>
                            </span>
                        </span>

                        <a class="btn btn-sm <?= $isCurrentUser ? 'btn-dark' : 'btn-outline-dark' ?> demo-user-btn"
                           href="<?= e(url('security/switch.php?act=' . $demoKey)) ?>"
                           <?= $isCurrentUser ? 'aria-current="true"' : '' ?>>
                            <?= e($switchLabels[$demoKey] ?? 'Switch to this user') ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="alert alert-info py-2 small mt-3 mb-0">
                Currently signed in as <strong><?= current_user_label() ?></strong>
                (role: <code><?= e($user['role']) ?></code>,
                allowed outlets: <code><?= $user['outlet_ids'] === null ? 'ALL' : implode(', ', $user['outlet_ids']) ?></code>)
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="table-card p-3 h-100">
            <h2 class="h5 mb-1">2. The attack request</h2>
            <p class="small text-muted mb-2">
                The signed-in cashier of outlet 1 simply changes the id in the URL:
            </p>
            <div class="code-block">GET /outlets/view.php?outlet_id=<span class="bad">3</span>

<span class="comment">// the attacker already knows (or guesses) that
// outlets 1, 2 and 3 exist</span></div>
        </div>
    </div>
</div>

<!-- ================= TWO VERSIONS ================= -->
<div class="row g-3 mb-4">

    <!-- ---------- VULNERABLE ---------- -->
    <div class="col-lg-6">
        <div class="table-card vuln-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 text-danger mb-0">&#9888; Vulnerable version</h2>
                <span class="badge text-bg-danger">DEMONSTRATION ONLY</span>
            </div>
            <p class="small">
                The id from the query string is used directly in the SQL statement.
                There is <strong>no ownership check</strong>, so the prepared statement
                happily returns somebody else's data.
            </p>
            <div class="code-block"><span class="comment">// security/idor_vulnerable.php</span>
<span class="bad">$outletId = (int)($_GET['outlet_id'] ?? 0);   // user input</span>

<span class="comment">// BUG: no can_access_outlet() check at all</span>
$stmt = $pdo->prepare(
  <span class="kw">"SELECT name, daily_demand_units FROM outlets WHERE id = ?"</span>
);
$stmt->execute([<span class="bad">$outletId</span>]);
<span class="bad">// =&gt; ANY signed-in user can read ANY outlet</span></div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <a class="btn btn-danger"
                   href="<?= e(url('security/idor_vulnerable.php?act=cashier_igando&outlet_id=3')) ?>">
                    Run exploit (outlet 3)
                </a>
                <a class="btn btn-outline-danger btn-sm"
                   href="<?= e(url('security/idor_vulnerable.php?act=cashier_igando&outlet_id=1')) ?>">
                    outlet 1</a>
                <a class="btn btn-outline-danger btn-sm"
                   href="<?= e(url('security/idor_vulnerable.php?act=cashier_igando&outlet_id=2')) ?>">
                    outlet 2</a>
            </div>
        </div>
    </div>

    <!-- ---------- SECURE ---------- -->
    <div class="col-lg-6">
        <div class="table-card secure-card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 class="h5 text-success mb-0">&#10003; Patched version</h2>
                <span class="badge badge-ok">USED BY THE REAL APP</span>
            </div>
            <p class="small">
                The identical query, but <strong>after</strong> an authorisation check that compares
                the requested id with the outlets the signed-in user owns.
            </p>
            <div class="code-block"><span class="comment">// security/idor_secure.php  (+ outlets/view.php)</span>
<span class="good">$outletId = (int)($_GET['outlet_id'] ?? 0);</span>

<span class="comment">// THE PATCH: authorisation BEFORE the query</span>
<span class="good">if (!can_access_outlet($outletId)) {</span>
<span class="good">    http_response_code(403);</span>
<span class="good">    exit('Forbidden');</span>
<span class="good">}</span>

$stmt = $pdo-&gt;prepare(
  <span class="kw">"SELECT name, daily_demand_units FROM outlets WHERE id = ?"</span>
);
$stmt-&gt;execute([<span class="good">$outletId</span>]);
<span class="comment">// =&gt; cashier of outlet 1 gets 403 for outlet 3</span></div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <a class="btn btn-success"
                   href="<?= e(url('security/idor_secure.php?act=cashier_igando&outlet_id=3')) ?>">
                    Send the same request
                </a>
                <a class="btn btn-outline-success btn-sm"
                   href="<?= e(url('security/idor_secure.php?act=cashier_igando&outlet_id=1')) ?>">
                    outlet 1 (allowed)</a>
            </div>
        </div>
    </div>
</div>

<!-- ================= COMPARISON ================= -->
<div class="table-card mb-4">
    <div class="p-3 pb-2">
        <h2 class="h5 mb-0">Expected result of the demonstration</h2>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Request</th>
                <th>Vulnerable</th>
                <th>Patched</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><code>cashier_igando</code> &rarr; <code>outlet_id=1</code> (his own)</td>
                <td><span class="badge badge-ok">200 OK - correct</span></td>
                <td><span class="badge badge-ok">200 OK - correct</span></td>
            </tr>
            <tr>
                <td><code>cashier_igando</code> &rarr; <code>outlet_id=3</code> (somebody else)</td>
                <td><span class="badge badge-reorder">200 OK - DATA LEAK</span></td>
                <td><span class="badge badge-reorder">403 FORBIDDEN</span></td>
            </tr>
            <tr>
                <td><code>manager</code> &rarr; any <code>outlet_id</code></td>
                <td><span class="badge badge-ok">200 OK</span></td>
                <td><span class="badge badge-ok">200 OK</span></td>
            </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ================= RULES ================= -->
<div class="row g-3">
    <div class="col-md-6">
        <div class="table-card p-3 h-100">
            <h2 class="h5">Why a prepared statement is NOT enough</h2>
            <p class="small mb-0">
                Both versions use <code>prepare()</code> / <code>execute()</code>, so neither one can be
                broken with SQL injection. The leak happens because <strong>authentication</strong>
                (who are you?) and <strong>authorisation</strong> (what may you see?) are two different
                questions. Input validation and prepared statements answer only the first one.
            </p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="table-card p-3 h-100">
            <h2 class="h5">Where the patched code is used</h2>
            <ul class="small mb-0">
                <li><code>outlets/view.php</code> &rarr; <code>require_outlet_access($id)</code></li>
                <li><code>deliveries/save.php</code> &rarr; same check before inserting</li>
                <li><code>includes/auth.php</code> &rarr; single source of truth</li>
                <li><code>security/idor_vulnerable.php</code> &rarr; the only file without the check</li>
            </ul>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
