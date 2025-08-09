<?php if (!defined('ABSPATH')) exit;

$calc = null; $msg = '';
if (!empty($_POST['ffp_test_nonce']) && wp_verify_nonce($_POST['ffp_test_nonce'], 'ffp_tools_test')) {
    $origin = sanitize_text_field($_POST['origin'] ?? '');
    $dest = sanitize_text_field($_POST['dest'] ?? '');
    $pc = sanitize_text_field($_POST['postcode'] ?? '');
    if ($origin && $dest) {
        $km = FFP_Geo::distance_km($origin, $dest);
        if ($km !== null) {
            $fee = FFP_Zones::calc_fee($km, $pc);
            $calc = ['km' => round($km,2), 'fee' => $fee];
        } else {
            $msg = 'Geokoding feilet. Sjekk API-nøkler og adresser.';
        }
    } else {
        $msg = 'Fyll ut begge adresser.';
    }
}
$settings = get_option('ffp_settings', []);
?>
<div class="wrap">
  <h1>Verktøy</h1>
  <div class="ffp-card">
    <h2>Test distanse og leveringsgebyr</h2>
    <form method="post">
      <?php wp_nonce_field('ffp_tools_test','ffp_test_nonce'); ?>
      <table class="form-table">
        <tr>
          <th scope="row"><label>Butikkadresse (origin)</label></th>
          <td><input type="text" class="regular-text" name="origin" value="<?php echo esc_attr($settings['store_address'] ?? ''); ?>"></td>
        </tr>
        <tr>
          <th scope="row"><label>Kundeadresse (dest)</label></th>
          <td><input type="text" class="regular-text" name="dest" value=""></td>
        </tr>
        <tr>
          <th scope="row"><label>Postnummer (valgfritt)</label></th>
          <td><input type="text" class="regular-text" name="postcode" value=""></td>
        </tr>
      </table>
      <?php submit_button('Beregn'); ?>
    </form>
    <?php if ($msg): ?>
      <p><strong><?php echo esc_html($msg); ?></strong></p>
    <?php endif; ?>
    <?php if ($calc): ?>
      <p><strong>Avstand:</strong> <?php echo esc_html($calc['km']); ?> km</p>
      <p><strong>Gebyr:</strong> <?php echo wp_kses_post(wc_price($calc['fee'])); ?></p>
    <?php endif; ?>
  </div>

  <div class="ffp-card" style="margin-top:16px;">
    <h2>Systemstatus</h2>
    <ul>
      <li>Kartleverandør: <strong><?php echo esc_html($settings['map_provider'] ?? 'mapbox'); ?></strong></li>
      <li>Mapbox token satt: <strong><?php echo !empty($settings['mapbox_token']) ? 'Ja' : 'Nei'; ?></strong></li>
      <li>Google API key satt: <strong><?php echo !empty($settings['google_api_key']) ? 'Ja' : 'Nei'; ?></strong></li>
      <li>SSE aktivert: <strong><?php echo !empty($settings['enable_sse']) ? 'Ja' : 'Nei'; ?></strong></li>
      <li>PWA aktivert: <strong><?php echo !empty($settings['enable_pwa']) ? 'Ja' : 'Nei'; ?></strong></li>
    </ul>
  </div>
</div>
