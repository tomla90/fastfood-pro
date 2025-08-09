<?php if (!defined('ABSPATH')) exit;
$s = get_option('ffp_settings', []);
?>
<div class="wrap">
  <h1>Lisens</h1>
  <div class="ffp-card">
    <p><strong>DEV-bypass aktiv:</strong>
      <?php echo (defined('FFP_DEV_LICENSE_BYPASS') && FFP_DEV_LICENSE_BYPASS) ? 'Ja' : 'Nei'; ?>
    </p>
    <table class="form-table">
      <tr><th>Lisensserver URL</th><td><?php echo esc_html($s['license_server'] ?? ''); ?></td></tr>
      <tr><th>Endpoint</th><td><?php echo esc_html($s['update_endpoint'] ?? '/wp-json/ffpls/v1/update'); ?></td></tr>
      <tr><th>Lisensnøkkel</th><td><?php echo esc_html($s['license_key'] ?? ''); ?></td></tr>
    </table>
    <p>Oppdater lisens i <a href="<?php echo esc_url(admin_url('admin.php?page=ffp-settings')); ?>">Innstillinger</a>.</p>
  </div>
</div>
