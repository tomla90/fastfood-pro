<?php if (!defined('ABSPATH')) exit;
$orders = wc_get_orders([
  'limit' => 50,
  'orderby' => 'date',
  'order' => 'DESC',
  'return' => 'objects'
]);
?>
<div class="wrap">
  <h1>Kunder (siste 50 ordre)</h1>
  <table class="widefat striped">
    <thead><tr>
      <th>#Ordre</th><th>Dato</th><th>Kunde</th><th>E‑post</th><th>Telefon</th><th>Total</th><th>Status</th>
    </tr></thead>
    <tbody>
    <?php if ($orders): foreach ($orders as $o): ?>
      <tr>
        <td><a href="<?php echo esc_url(get_edit_post_link($o->get_id())); ?>">#<?php echo $o->get_id(); ?></a></td>
        <td><?php echo esc_html($o->get_date_created() ? $o->get_date_created()->date_i18n('Y-m-d H:i') : ''); ?></td>
        <td><?php echo esc_html($o->get_formatted_billing_full_name()); ?></td>
        <td><?php echo esc_html($o->get_billing_email()); ?></td>
        <td><?php echo esc_html($o->get_billing_phone()); ?></td>
        <td><?php echo wp_kses_post(wc_price($o->get_total(), ['currency'=>$o->get_currency()])); ?></td>
        <td><?php echo esc_html(wc_get_order_status_name($o->get_status())); ?></td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7">Ingen ordre funnet.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
