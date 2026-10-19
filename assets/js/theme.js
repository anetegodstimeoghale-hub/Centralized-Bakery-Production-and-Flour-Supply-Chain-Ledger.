/* ==========================================================================
 * assets/js/theme.js
 * --------------------------------------------------------------------------
 * LIGHT / DARK MODE SWITCH (top navigation).
 *
 * How it works
 * --------------------------------------------------------------------------
 *   1. includes/header.php has a small inline script in <head> that reads
 *      the saved mode from localStorage ("bakery-theme") and puts
 *      data-bs-theme="dark" on <html> before anything is painted.
 *   2. THIS file wires up the ☀️ / 🌙 button: it flips the mode, saves it,
 *      updates the button label/pressed state and tells the rest of the UI
 *      (the dashboard charts) to repaint.
 *
 * Everything here is presentation only - no PHP, no database, no logic.
 * ========================================================================== */
(function () {
    'use strict';

    var STORAGE_KEY = 'bakery-theme';

    /** Which mode is on screen right now? ('light' or 'dark') */
    function currentTheme() {
        return document.documentElement.getAttribute('data-bs-theme') === 'dark'
            ? 'dark'
            : 'light';
    }

    /** Show the current state on the button (aria + tooltip). */
    function syncButton(theme) {
        var button = document.getElementById('themeToggle');
        if (!button) {
            return;
        }
        var nextMode = theme === 'dark' ? 'light' : 'dark';
        button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
        button.setAttribute('aria-label', 'Switch to ' + nextMode + ' mode');
        button.setAttribute('title', 'Switch to ' + nextMode + ' mode');
    }

    /** Switch to the given mode, remember it and repaint the UI. */
    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);

        try {
            localStorage.setItem(STORAGE_KEY, theme);   // survives page changes
        } catch (err) { /* private mode / storage blocked: theme still applies */ }

        syncButton(theme);

        // Tell listeners (dashboard.js repainted the chart colours).
        try {
            window.dispatchEvent(new CustomEvent('bakery:theme', {detail: {theme: theme}}));
        } catch (err) { /* very old browser: only the colours stay stale */ }
    }

    function init() {
        var button = document.getElementById('themeToggle');
        if (!button) {
            return;                                   // page without the switch
        }

        syncButton(currentTheme());                   // reflect the saved mode

        button.addEventListener('click', function () {
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
        });
    }

    // theme.js is loaded with defer, so the HTML is ready by now; the
    // fallback keeps it working if it is ever loaded without defer.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
