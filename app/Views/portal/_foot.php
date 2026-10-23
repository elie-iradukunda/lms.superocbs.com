</main>
<footer class="portal-wrap text-muted small text-center mb-4">
  <?= e(company_name()) ?><?php $phone = \Models\Settings::get('company_phone', ''); ?><?= $phone !== '' ? ' · ' . e($phone) : '' ?><?php $mail = \Models\Settings::get('company_email', ''); ?><?= $mail !== '' ? ' · ' . e($mail) : '' ?>
</footer>
</body>
</html>
