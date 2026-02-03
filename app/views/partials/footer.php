<?php
$settings = $settings ?? [];
$footer_text = $settings['footer_text'] ?? '© 2026 Adena Medical System ver 1.1';
?>
    </div>
    <footer class="footer no-print">
      <?= e($footer_text) ?>
    </footer>
  </main>
</div>

<script src="<?= e(url('/public/assets/js/app.js')) ?>"></script>
</body>
</html>
