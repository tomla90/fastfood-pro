<?php if (!defined('ABSPATH')) exit;
$drivers = get_users(['role' => 'driver']);
?>
<div class="wrap">
  <h1>Sjåfører</h1>
  <p>Oversikt over brukere med rollen <code>driver</code>. For å opprette nye, gå til <em>Brukere → Legg til ny</em> og velg rolle <strong>Delivery Driver</strong>.</p>

  <table class="widefat striped">
    <thead><tr><th>Navn</th><th>E-post</th><th>Telefon</th><th>Tildelte åpne ordre</th></tr></thead>
    <tbody>
    <?php if ($drivers): foreach ($drivers as $u):
        $orders = wc_get_orders([
          'limit' => -1,
          'status' => ['processing','on-hold','pending','ffp-preparing','ffp-ready','ffp-out-for-delivery'],
          'meta_key' => '_ffp_driver_id',
          'meta_value' => $u->ID,
          'return' => 'ids'
        ]);
      ?>
      <tr>
        <td><?php echo esc_html($u->display_name); ?></td>
        <td><a href="mailto:<?php echo esc_attr($u->user_email); ?>"><?php echo esc_html($u->user_email); ?></a></td>
        <td><?php echo esc_html(get_user_meta($u->ID, 'billing_phone', true)); ?></td>
        <td><?php echo $orders ? implode(', ', array_map('intval', $orders)) : '—'; ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="4">Ingen sjåfører funnet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <p style="margin-top:1rem;">
    <a class="button button-primary" href="<?php echo esc_url(admin_url('user-new.php')); ?>">Legg til ny sjåfør</a>
  </p>
</div>
