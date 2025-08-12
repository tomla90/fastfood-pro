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

// -------- Autoload/require klasser --------
require_once FFP_DIR . 'includes/class-ffp-orders.php';
require_once FFP_DIR . 'includes/class-ffp-rest.php';
require_once FFP_DIR . 'includes/class-ffp-statuses.php';
require_once FFP_DIR . 'includes/class-ffp-admin-menu.php';

// -------- Aktivering / uninstall --------
register_activation_hook(__FILE__, function () {
    foreach (['administrator','shop_manager'] as $role_name) {
        if ($r = get_role($role_name)) {
            $r->add_cap('ffp_view_orders');
            $r->add_cap('ffp_update_orders');
        }
    }
});
register_uninstall_hook(__FILE__, function () {
    // Behold data – endre hvis du vil slette options ved uninstall.
});

// -------- Init Woo-avhengighet --------
add_action('plugins_loaded', function(){
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error"><p><strong>FastFood Pro:</strong> WooCommerce er påkrevd.</p></div>';
        });
        return;
    }

    // Start hjelpeklasser
    new FFP_Statuses();
    new FFP_REST();
    new FFP_Admin_Menu();
});

// -------- Registrer assets (én gang) --------
add_action('init', function () {
    // JS
    $path_js = FFP_DIR . 'assets/js/restaurant-orders.js';
    $ver_js  = file_exists($path_js) ? filemtime($path_js) : FFP_VERSION;

    wp_register_script(
        'ffp-restaurant-orders',
        FFP_URL . 'assets/js/restaurant-orders.js',
        ['jquery'],
        $ver_js,
        true
    );

    // CSS (bruker settings.css til enkel styling)
    $path_css = FFP_DIR . 'assets/css/settings.css';
    $ver_css  = file_exists($path_css) ? filemtime($path_css) : FFP_VERSION;

    wp_register_style(
        'ffp-restaurant-orders',
        FFP_URL . 'assets/css/settings.css',
        [],
        $ver_css
    );
});

// -------- Admin: enqueue kun på Live Bestillinger --------
add_action('admin_enqueue_scripts', function ($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_orders_screen = ($screen && strpos($screen->id, 'ffp-orders') !== false) || strpos((string)$hook, 'ffp-orders') !== false;
    if (!$is_orders_screen) return;

    wp_enqueue_script('ffp-restaurant-orders');
    wp_enqueue_style('ffp-restaurant-orders');

    wp_localize_script('ffp-restaurant-orders', 'ffpOrders', [
        'restUrl' => esc_url_raw( rtrim( rest_url(), '/' ) ),
        'nonce'   => wp_create_nonce('wp_rest'),
        'sound'   => true,
        'context' => 'admin',
    ]);
});

// -------- Frontend: shortcode for live-ordre --------
/**
 * [ffp_live_orders] – viser Live Bestillinger i frontend.
 * Tilgjengelig for admin, shop_manager, brukere med cap ffp_view_orders, eller rolle ffp_driver.
 */
add_shortcode('ffp_live_orders', function () {
    $u = wp_get_current_user();
    $is_driver = $u && in_array('ffp_driver', (array) $u->roles, true);

    if ( ! is_user_logged_in() || ! (
        current_user_can('manage_options') ||
        current_user_can('manage_woocommerce') ||
        current_user_can('ffp_view_orders') ||
        $is_driver
    )) {
        return '<div class="ffp-live-orders"><em>Du har ikke tilgang til denne siden.</em></div>';
    }

    wp_enqueue_script('ffp-restaurant-orders');
    wp_enqueue_style('ffp-restaurant-orders');

    wp_localize_script('ffp-restaurant-orders', 'ffpOrders', [
        'restUrl' => esc_url_raw( rtrim( rest_url(), '/' ) ),
        'nonce'   => wp_create_nonce('wp_rest'),
        'sound'   => true,
        'context' => 'frontend',
    ]);

    return '<div id="ffp-orders-app"><p>Laster inn ordre…</p></div>';
});
