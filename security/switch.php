<?php
/**
 * ==========================================================================
 * security/switch.php  -  demo helper: change the signed-in user
 * ==========================================================================
 * Only whitelisted keys are accepted (see demo_users() in includes/auth.php),
 * so this endpoint cannot be used to forge an arbitrary account.
 * ==========================================================================
 */

require_once __DIR__ . '/../includes/init.php';

$key = (string) ($_REQUEST['act'] ?? '');

if ($key === '') {
    flash('Please choose a demo user.', 'warning');
    redirect('security/');
}

if (switch_user($key)) {
    flash('You are now signed in as ' . current_user()['name'] . '.', 'info');
} else {
    flash('Unknown demo user.', 'danger');
}

redirect('security/');
