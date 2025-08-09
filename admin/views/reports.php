<?php if (!defined('ABSPATH')) exit;
$total_today = wc_get_orders(['limit' => -1, 'date_created' => 'today', 'return'=>'ids']);
$total_month = wc_get_orders(['limit' => -1, 'date_created' => 'first day of this month', 'return'=>'ids']);
?>
<div class="wrap">
  <h1>Rapporter (enkel)</h1>
  <div class="ffp-card">
    <p><strong>Antall ordre i dag:</strong> <?php echo count($total_today); ?></p>
    <p><strong>Antall ordre denne måneden:</strong> <?php echo count($total_month); ?></p>
    <p>For detaljerte rapporter, bruk WooCommerce → Rapporter/Analytics. Vi kan bygge ut egne grafer her senere.</p>
  </div>
</div>
