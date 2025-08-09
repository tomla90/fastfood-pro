<?php
/**
 * Plugin Name: FastFood Pro
 * Description: WooCommerce-basert takeaway med tips, leveringstid, produkt-tillegg, live-ordre, driver-portal, og lisens/auto-oppdateringer.
 * Version: 1.0.0
 * Author: Granberg Digital
 * Text Domain: fastfood-pro
 */

if (!defined('ABSPATH')) exit;
define('FFP_DEV_LICENSE_BYPASS', true);

define('FFP_VERSION', '1.0.0');
define('FFP_FILE', __FILE__);
define('FFP_DIR', plugin_dir_path(__FILE__));
define('FFP_URL', plugin_dir_url(__FILE__));

require_once FFP_DIR . 'includes/autoload.php';

register_activation_hook(__FILE__, ['FFP_Activator','activate']);
register_uninstall_hook(__FILE__, 'ffp_uninstall_plugin');

function ffp_uninstall_plugin() {
    // Beholder data som standard (ikke slett ved uninstall).
    // Hvis du vil slette: delete_option('ffp_settings'); etc.
}

add_action('plugins_loaded', function(){
    // Sjekk WooCommerce
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error"><p><strong>FastFood Pro:</strong> WooCommerce er påkrevd.</p></div>';
        });
        return;
    }
    FFP_Plugin::instance();
});
