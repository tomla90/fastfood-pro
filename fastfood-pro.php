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

// Autoload klasser
require_once FFP_DIR . 'includes/autoload.php';

// Aktivering: gi nødvendige caps
register_activation_hook(__FILE__, function () {
    foreach (['administrator','shop_manager'] as $role_name) {
        if ($r = get_role($role_name)) {
            $r->add_cap('ffp_view_orders');
            $r->add_cap('ffp_update_orders');
        }
    }
});

// Init – avhengig av Woo
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error"><p><strong>FastFood Pro:</strong> WooCommerce er påkrevd.</p></div>';
        });
        return;
    }

    // Viktig: instansier klasser så autoloader *laster filene*
    new FFP_Statuses();     // registrerer wc-ffp-* statuser
    new FFP_REST();         // REST-endepunkter for orders etc.
    new FFP_Admin_Menu();   // meny + admin views
});

// Enqueue + localize JS for Live Bestillinger (kun på riktig admin-side)
add_action('admin_enqueue_scripts', function ($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_orders = ($screen && strpos($screen->id, 'ffp-orders') !== false) || strpos((string)$hook, 'ffp-orders') !== false;
    if (!$is_orders) return;

    // Cache-bust med filemtime
    $js_path = FFP_DIR . 'assets/js/restaurant-orders.js';
    $ver     = file_exists($js_path) ? filemtime($js_path) : FFP_VERSION;

    wp_enqueue_script('ffp-restaurant-orders', FFP_URL . 'assets/js/restaurant-orders.js', ['jquery'], $ver, true);
    wp_localize_script('ffp-restaurant-orders', 'ffpOrders', [
        'restUrl' => esc_url_raw( rtrim( rest_url(), '/' ) ),
        'nonce'   => wp_create_nonce('wp_rest'),
        'sound'   => true,
    ]);

    // Enkel styling (kan byttes til egen admin css)
    wp_enqueue_style('ffp-admin-orders', FFP_URL . 'assets/css/settings.css', [], FFP_VERSION);
});
