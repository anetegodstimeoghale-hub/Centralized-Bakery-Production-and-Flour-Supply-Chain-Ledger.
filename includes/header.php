<?php
/**
 * ==========================================================================
 * includes/header.php
 * --------------------------------------------------------------------------
 * Opening HTML + THE SHARED NAVIGATION MENU.
 *
 * EVERY page of the application includes this one file, so the menu at the
 * top of the page is written only ONCE here and never duplicated:
 *
 *   outlets/index.php      require __DIR__ . '/../includes/header.php';
 *   production/index.php   require __DIR__ . '/../includes/header.php';
 *   ...
 *
 * Before you include this file you may set:
 *   $pageTitle = 'Outlet Registry';   // browser tab + title bar text
 *   $activeNav = 'outlets';           // key of the menu item to highlight
 * ==========================================================================
 */

$pageTitle = $pageTitle ?? 'Bakery Ledger';
$activeNav = $activeNav ?? '';
$currentUser = current_user();

/* ---------------------------------------------------------------------
 * 1. THE MENU  (single source of truth - edit the menu HERE only)
 * ------------------------------------------------------------------
 *   key   -> the value that a page puts into $activeNav to get highlighted
 *   label -> the text shown in the menu
 *   url   -> the link target, built with url() so it works in ANY folder
 *            name (http://localhost/bakey-ledgerr/, .../outlets/, ...)
 * ------------------------------------------------------------------ */
$menu = [
    'dashboard'  => ['label' => 'Dashboard',         'url' => url('')],
    'outlets'    => ['label' => 'Outlet Registry',   'url' => url('outlets/')],
    'suppliers'  => ['label' => 'Supplier Registry', 'url' => url('suppliers/')],
    'production' => ['label' => 'Production',        'url' => url('production/')],
    'flour'      => ['label' => 'Flour',             'url' => url('flour/')],
    'deliveries' => ['label' => 'Deliveries',        'url' => url('deliveries/')],
    'security'   => ['label' => 'Security / IDOR',   'url' => url('security/')],
];

// Flash messages (success / error after a form submission) are read ONCE
// here so that each message is shown exactly one time.
$messages = flash_messages();

/* The full demo-user label is long ("Chinwe Okafor (Production Manager)").
   On phones only the first name fits in the top bar, so both versions are
   prepared here - the menu markup picks one with Bootstrap's d-md-* classes. */
$navUserFull  = current_user_label();
$navUserName  = explode(' ', trim(strip_tags($navUserFull)))[0] ?? $navUserFull;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> &middot; Bakery Ledger</title>
<script>
/* --------------------------------------------------------------------------
   LIGHT / DARK MODE - applied BEFORE the page is painted.
   The saved choice lives in localStorage, so it follows you to the next
   page. This tiny script only reads one key and sets one attribute on
   <html>; the button itself is wired up in assets/js/theme.js.
   -------------------------------------------------------------------------- */
(function () {
    try {
        var savedTheme = localStorage.getItem('bakery-theme');
        if (savedTheme === 'dark' || savedTheme === 'light') {
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        }
    } catch (err) { /* storage unavailable -> keep the default light theme */ }
})();
</script>
<link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<script src="<?= e(url('assets/js/theme.js')) ?>" defer></script>
<script>
/* --------------------------------------------------------------------------
   "switch demo user ->" lands on security/#demo-users. Some browsers skip
   their automatic jump to a #fragment on the very first load, so the demo
   users are scrolled into view when the page is ready and once more after
   the load event (when the final layout is known). This only moves the
   viewport - it reads no data, changes no URL and runs no application logic.
   -------------------------------------------------------------------------- */
(function () {
    function showDemoUsers() {
        if (window.location.hash !== '#demo-users') {
            return;                                    // not our link -> do nothing
        }
        var section = document.getElementById('demo-users');
        if (section) {
            section.scrollIntoView({ block: 'start' });  // honours scroll-margin-top
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showDemoUsers);
    }
    window.addEventListener('load', function () {
        window.setTimeout(showDemoUsers, 300);        // final layout is known by then
    });
})();
</script>
</head>
<body>

<div class="app-shell">

    <div class="app-main">

        <!-- ==========================================================
             SHARED HEADER = navigation menu + page title bar
             (sticky: it stays visible while you scroll)
             ========================================================== -->
        <div class="app-header">

            <!-- ---------- 1. TOP NAVIGATION MENU (all pages) ----------
                 Brand + signed-in user + theme switch + hamburger are always
                 visible; the 7 menu items live behind the three-line ☰ button
                 on EVERY screen size (desktop and mobile). -->
            <nav class="navbar navbar-dark app-topnav" aria-label="Main navigation">
                <div class="container-fluid px-2 px-md-3">

                    <!-- brand / home link -->
                    <a class="navbar-brand" href="<?= e(url('')) ?>">
                        <span class="app-brand-mark">BL</span>
                        <span>
                            <strong class="d-block lh-1">Bakery Ledger</strong>
                            <small>Production &amp; Flour SCM</small>
                        </span>
                    </a>

                    <!-- right hand side: user, ☀️/🌙 switch, ☰ hamburger -->
                    <div class="app-topnav-tools">

                        <!-- who is signed in (demo user) - stays on screen -->
                        <span class="app-nav-user small">
                            <span class="app-nav-user-prefix d-none d-md-inline">Signed in as</span>
                            <strong class="app-nav-user-name d-none d-md-inline"><?= $navUserFull ?></strong>
                            <strong class="app-nav-user-name d-md-none"><?= $navUserName ?></strong>
                        </span>

                        <!-- light / dark mode switch (the active icon is lit) -->
                        <button id="themeToggle" class="app-theme-toggle" type="button"
                                aria-pressed="false"
                                aria-label="Switch to dark mode"
                                title="Switch to dark mode">
                            <span class="theme-ico theme-ico-sun" aria-hidden="true">&#9728;&#65039;</span>
                            <span class="theme-ico theme-ico-moon" aria-hidden="true">&#127769;</span>
                        </button>

                        <!-- three-line hamburger: opens/closes the menu -->
                        <button class="navbar-toggler" type="button"
                                data-bs-toggle="collapse" data-bs-target="#mainMenu"
                                aria-controls="mainMenu" aria-expanded="false"
                                aria-label="Toggle the navigation menu">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                    </div>

                    <!-- the menu itself (a vertical drop-down panel) -->
                    <div class="collapse navbar-collapse" id="mainMenu">
                        <ul class="navbar-nav mb-0">
                            <?php foreach ($menu as $key => $item): ?>
                                <?php $isActive = ($activeNav === $key); ?>
                                <li class="nav-item">
                                    <a class="nav-link<?= $isActive ? ' active' : '' ?>"
                                       href="<?= e($item['url']) ?>"
                                       <?= $isActive ? 'aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <!-- extra link under a divider: change the demo user.
                             It jumps straight to the "Choose the demo user" list on the
                             Security page, so clicking it immediately shows every demo
                             user by name and lets you switch between them. -->
                        <a class="app-nav-switch"
                           href="<?= e(url('security/')) ?>#demo-users"
                           title="See the demo users and switch between them">switch demo user &rarr;</a>
                    </div>
                </div>
            </nav>

            <!-- ---------- 2. PAGE TITLE BAR ---------- -->
            <header class="app-topbar navbar navbar-expand navbar-light border-bottom px-3 py-2">
                <span class="navbar-text fw-semibold text-truncate">
                    <?= e($pageTitle) ?>
                </span>
                <span class="ms-auto d-none d-sm-inline small text-muted">
                    Today: <?= e(date('D, d M Y')) ?>
                </span>
            </header>

        </div><!-- /.app-header -->

        <?php if ($messages): ?>
        <div class="container-fluid px-3 pt-3">
            <?php foreach ($messages as $m): ?>
                <div class="alert alert-<?= e($m['type']) ?> alert-dismissible fade show shadow-sm mb-3" role="alert">
                    <?= e($m['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <main class="app-content p-3 p-md-4">
