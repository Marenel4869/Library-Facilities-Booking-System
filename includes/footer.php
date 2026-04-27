    </div><!-- /container -->
  </main>
    </div><!-- /page-content -->
    <footer class="app-footer" role="contentinfo">
      <span>&copy; <?= date('Y') ?> <?= APP_NAME ?>.</span>
      <span class="footer-sep hide-mobile">·</span>
      <span class="hide-mobile">All rights reserved.</span>
    </footer>
  </div><!-- /main-wrapper -->
</div><!-- /app-wrapper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php $mainJsVer = @filemtime(__DIR__ . '/../assets/js/main.js') ?: time(); ?>
<script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= $mainJsVer ?>"></script>
</body>
</html>