<?php
/**
 * ==========================================================================
 * includes/auth.php
 * --------------------------------------------------------------------------
 * A small demo authentication / authorisation layer.
 *
 * WHY IT EXISTS
 * -------------
 * The assignment asks for an IDOR demonstration. To show a realistic
 * "broken access control" we need users that are only allowed to see SOME
 * outlets:
 *
 *   manager .............. may see every outlet
 *   cashier_igando ....... may only see outlet 1 (Igando Branch)
 *   cashier_agege ........ may only see outlet 3 (Agege Central Store)
 *
 * can_access_outlet() is the PATCHED, SECURE check. Every normal page of
 * this application uses it (see outlets/view.php).
 * security/idor_vulnerable.php deliberately SKIPS it - that is the exploit.
 * ==========================================================================
 */

/**
 * All demo users of the system.
 * outlet_ids = null  ->  allowed to open EVERY outlet (manager/rights)
 * outlet_ids = [1]   ->  allowed to open outlet 1 only
 *
 * @return array<string, array>
 */
function demo_users(): array
{
    return [
        'manager' => [
            'name'       => 'Chinwe Okafor (Production Manager)',
            'role'       => 'manager',
            'outlet_ids' => null,          // null = all outlets
        ],
        'cashier_igando' => [
            'name'       => 'Bola Ade (Igando Outlet Cashier)',
            'role'       => 'cashier',
            'outlet_ids' => [1],           // only the Igando Branch
        ],
        'cashier_agege' => [
            'name'       => 'Musa Bello (Agege Outlet Cashier)',
            'role'       => 'cashier',
            'outlet_ids' => [3],           // only the Agege Central Store
        ],
    ];
}

/**
 * Who is currently signed in? (defaults to the manager)
 *
 * @return array{name:string, role:string, outlet_ids:?array}
 */
function current_user(): array
{
    $users = demo_users();
    $key   = $_SESSION['demo_user'] ?? 'manager';

    if (!isset($users[$key])) {
        $key = 'manager';
    }

    $user           = $users[$key];
    $user['key']    = $key;
    return $user;
}

/**
 * Switch the signed-in demo user (only whitelisted keys are accepted).
 *
 * @param string $key
 * @return bool
 */
function switch_user(string $key): bool
{
    $users = demo_users();
    if (!isset($users[$key])) {
        return false;
    }
    $_SESSION['demo_user'] = $key;
    return true;
}

/**
 * THE SECURE CHECK (PATCHED VERSION OF THE IDOR BUG).
 *
 * Returns TRUE only when the signed-in user is really allowed to open
 * the requested outlet.
 *
 * @param int $outletId
 * @return bool
 */
function can_access_outlet(int $outletId): bool
{
    $user = current_user();

    // Managers (and the system administrator) may open every outlet.
    if ($user['outlet_ids'] === null) {
        return true;
    }

    // Everyone else may only open the outlet that belongs to them.
    return in_array($outletId, $user['outlet_ids'], true);
}

/**
 * Stop the request with HTTP 403 when the user is not allowed.
 *
 * @param int $outletId
 * @return void
 */
function require_outlet_access(int $outletId)
{
    if (can_access_outlet($outletId)) {
        return;
    }

    http_response_code(403);
    $_SESSION['flash'][] = [
        'message' => 'ACCESS DENIED (403): outlet #' . $outletId
            . ' does not belong to "' . current_user()['name'] . '".',
        'type'    => 'danger',
    ];

    require __DIR__ . '/header.php';
    echo '<div class="row justify-content-center"><div class="col-lg-8">';
    echo '<div class="alert alert-danger border-0 shadow-sm">';
    echo '<h1 class="h3 text-danger">403 - FORBIDDEN</h1>';
    echo '<p class="mb-2">The secure controller refused the request.</p>';
    echo '<p class="mb-0">Only the owner of an outlet (or a manager) may read its demand. ';
    echo 'This is exactly what the vulnerable example does <strong>not</strong> do.</p>';
    echo '</div>';
    echo '<a class="btn btn-outline-secondary" href="' . e(url('outlets/')) . '">Back to outlets</a> ';
    echo '<a class="btn btn-primary" href="' . e(url('security/')) . '">Open the security demonstration</a>';
    echo '</div></div>';
    require __DIR__ . '/footer.php';
    exit;
}

/**
 * Print the current user as a small badge (used in the top bar).
 *
 * @return string
 */
function current_user_label(): string
{
    $user = current_user();
    return e($user['name']);
}
