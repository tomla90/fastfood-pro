<?php
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
  <h1>FastFood Pro – Innstillinger</h1>
  <form method="post" action="options.php">
    <?php
      settings_fields('ffp_settings_group');  // option group
      do_settings_sections('ffp_settings');   // sidenavnet (må matche i FFP_Settings::register)
      submit_button();
    ?>
  </form>
</div>
