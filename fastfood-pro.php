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

// Autoload klasser fra includes/
require_once FFP_DIR . 'includes/autoload.php';

/** -------- Hooks MÅ bruke navngitte callbacks (ikke Closures) -------- */

function ffp_activate() {
    foreach (['administrator','shop_manager'] as $role_name) {
        if ($r = get_role($role_name)) {
            $r->add_cap('ffp_view_orders');
            $r->add_cap('ffp_update_orders');
        }
    }
    add_role('driver', 'Sjåfør', [
        'read'            => true,
        'ffp_view_orders' => true,
    ]);
}
register_activation_hook(__FILE__, 'ffp_activate');

function ffp_uninstall() {
    // Behold data – legg evt. clean-up her senere
}
register_uninstall_hook(__FILE__, 'ffp_uninstall');

/** ---------------------------- Init ---------------------------- */

function ffp_bootstrap() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'ffp_notice_wc_required');
        return;
    }

    // Start aggregator for å laste alt (addons, settings osv.)
    if (class_exists('FFP_Plugin')) {
        FFP_Plugin::instance();
    }

    // Alltid registrer statusene slik at knapper/JS fungerer
    if (class_exists('FFP_Statuses')) {
        new FFP_Statuses();
    }

    // Sørg for at REST alltid er registrert
    if (class_exists('FFP_REST')) {
        new FFP_REST();
    }

    // Driver-portal: kun hvis ikke aggregator allerede har lagt til shortcoden
    add_action('init', function () {
        if (!shortcode_exists('ffp_driver_portal') && class_exists('FFP_Driver')) {
            new FFP_Driver();
        }
    });
}
add_action('plugins_loaded', 'ffp_bootstrap');

function ffp_notice_wc_required() {
    echo '<div class="notice notice-error"><p><strong>FastFood Pro:</strong> WooCommerce er påkrevd.</p></div>';
}

/** -------------------- Admin assets (Live Orders) -------------------- */

function ffp_admin_assets($hook) {
    if (strpos($hook, 'ffp-orders') === false) return;

    $path = FFP_DIR.'assets/js/restaurant-orders.js';
    $ver  = file_exists($path) ? filemtime($path) : FFP_VERSION;

    wp_enqueue_script(
        'ffp-restaurant-orders',
        FFP_URL.'assets/js/restaurant-orders.js',
        ['jquery'],
        $ver,
        true
    );
    wp_localize_script('ffp-restaurant-orders', 'ffpOrders', [
        'restUrl' => esc_url_raw( rtrim( rest_url(), '/' ) ),
        'nonce'   => wp_create_nonce('wp_rest'),
        'sound'   => true,
    ]);

    wp_enqueue_style(
        'ffp-admin-orders',
        FFP_URL.'assets/css/styles.css',
        [],
        FFP_VERSION
    );
}
add_action('admin_enqueue_scripts', 'ffp_admin_assets');
