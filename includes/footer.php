<?php
/**
 * ==========================================================================
 * includes/footer.php
 * --------------------------------------------------------------------------
 * Closing tags + JavaScript libraries (all stored locally in assets/vendor,
 * so the project also works without an internet connection).
 * ==========================================================================
 */
?>
        </main>

        <footer class="app-footer text-center text-muted small px-3 py-3 border-top">
            Centralized Bakery Production &amp; Flour Supply Chain Ledger &middot;
            PHP <?= e(PHP_VERSION) ?> &middot; PDO prepared statements &middot;
            Built for local use with Laragon
        </footer>

    </div><!-- /.app-main -->
</div><!-- /.app-shell -->

<script src="<?= e(url('assets/vendor/bootstrap.bundle.min.js')) ?>"></script>
<?php // extra page specific scripts are added with $extraScripts ?>
<?php if (!empty($extraScripts)): ?>
    <?php foreach ((array) $extraScripts as $script): ?>
<script src="<?= e(url($script)) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
