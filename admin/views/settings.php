<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
  <h1>FastFood Pro – Innstillinger</h1>
  <form method="post" action="options.php">
    <?php
      settings_fields('ffp_settings_group');
      do_settings_sections('ffp_settings');
      submit_button();
    ?>
  </form>
</div>
