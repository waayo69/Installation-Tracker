<?php
// includes/layout_footer.php
?>
            </div> <!-- end .page-content -->
        </main> <!-- end .main-content -->
    </div> <!-- end .app-layout -->

    <script>
        window.BASE_URL = '<?= BASE_URL ?>';
        window.USER_ROLE = '<?= htmlspecialchars($_SESSION['role'] ?? '', ENT_QUOTES) ?>';
        window.TECH_ID = <?= (int)($_SESSION['technician_id'] ?? 0) ?>;
    </script>
    <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
    <?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>
