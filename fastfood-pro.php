    <?php
    /**
     * Plugin Name: FastFood Pro
     * Description: WooCommerce-basert takeaway med tips, leveringstid, produkt-tillegg, live-ordre, driver-portal, og lisens/auto-oppdateringer.
     * Version: 1.0.0
     * Author: Granberg Digital
     * Text Domain: fastfood-pro
     * GLC-Product: fastfood-pro
     */

    if (!defined('ABSPATH')) exit;

    define('FFP_DEV_LICENSE_BYPASS', true);
    define('FFP_VERSION', '1.0.0');
    define('FFP_FILE', __FILE__);
    define('FFP_DIR', plugin_dir_path(__FILE__));
    define('FFP_URL', plugin_dir_url(__FILE__));

    // Autoload
    require_once FFP_DIR . 'includes/autoload.php';

    // Gi caps ved aktivering
    register_activation_hook(__FILE__, function () {
        if ($r = get_role('administrator')) {
            $r->add_cap('ffp_view_orders');
            $r->add_cap('ffp_update_orders');
        }
        if ($r = get_role('shop_manager')) {
            $r->add_cap('ffp_view_orders');
            $r->add_cap('ffp_update_orders');
        }
    });

    register_uninstall_hook(__FILE__, 'ffp_uninstall_plugin');
    function ffp_uninstall_plugin() {
        // Behold data (endres hvis du vil slette)
    }

    // Init plugin
    add_action('plugins_loaded', function(){
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function(){
                echo '<div class="notice notice-error"><p><strong>FastFood Pro:</strong> WooCommerce er påkrevd.</p></div>';
            });
            return;
        }
        FFP_Plugin::instance();
    });

    // Enqueue + localize JS på admin-siden for live-ordre
    add_action('admin_enqueue_scripts', function($hook){
        // Tips: error_log($hook); for å se nøyaktig navn. Vi matcher på slug.
        if (strpos($hook, 'ffp-orders') === false) return;

        wp_enqueue_script(
            'ffp-restaurant-orders',
            FFP_URL . 'assets/js/restaurant-orders.js',
            ['jquery'],
            FFP_VERSION,
            true
        );

        wp_localize_script('ffp-restaurant-orders', 'ffpOrders', [
            'restUrl' => esc_url_raw( rtrim( rest_url(), '/' ) ), // https://site/wp-json
            'nonce'   => wp_create_nonce('wp_rest'),
            'sound'   => true,
        ]);

        // Valgfritt: litt basic styling
        wp_enqueue_style('ffp-admin-orders', FFP_URL . 'assets/css/settings.css', [], FFP_VERSION);
    });
