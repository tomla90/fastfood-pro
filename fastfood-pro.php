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

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
define('FFP_DEV_LICENSE_BYPASS', true);
define('FFP_VERSION', '1.0.0');
define('FFP_FILE', __FILE__);
define('FFP_DIR', plugin_dir_path(__FILE__));
define('FFP_URL', plugin_dir_url(__FILE__));

// -----------------------------------------------------------------------------
// Autoload klasser fra includes/
// -----------------------------------------------------------------------------
require_once FFP_DIR . 'includes/autoload.php';

// -----------------------------------------------------------------------------
// Activation / Uninstall (må være navngitte callbacks – ikke Closures)
// -----------------------------------------------------------------------------
function ffp_activate() {
    // Administrator: gi ALLE våre ffp-caps
    if ($r = get_role('administrator')) {
        foreach (['ffp_view_orders','ffp_update_orders','ffp_assign_driver','ffp_manage_settings'] as $cap) {
            $r->add_cap($cap);
        }
        $r->add_cap('read');
    }

    // Shop manager: grunnleggende caps
    if ($r = get_role('shop_manager')) {
        foreach (['ffp_view_orders','ffp_update_orders'] as $cap) {
            $r->add_cap($cap);
        }
    }

    // Opprett driver-rolle hvis mangler (med grunnleggende cap)
    if (!get_role('driver')) {
        add_role('driver', __('Sjåfør', 'fastfood-pro'), [
            'read'            => true,
            'ffp_view_orders' => true,
        ]);
    } else {
        // Sørg for at eksisterende driver får cap
        if ($r = get_role('driver')) {
            $r->add_cap('ffp_view_orders');
        }
    }
}
register_activation_hook(__FILE__, 'ffp_activate');

// Self-repair: sørg for at caps er på plass ved innlasting (f.eks. etter import/rolle-endringer)
add_action('init', function () {
    if ($r = get_role('administrator')) {
        foreach (['ffp_view_orders','ffp_update_orders','ffp_assign_driver','ffp_manage_settings'] as $cap) {
            $r->add_cap($cap);
        }
        $r->add_cap('read');
    }
    if ($r = get_role('shop_manager')) {
        foreach (['ffp_view_orders','ffp_update_orders'] as $cap) {
            $r->add_cap($cap);
        }
    }
}, 5);

function ffp_uninstall() {
    // Behold data – legg evt. clean-up her senere (f.eks. delete_option(...))
}
register_uninstall_hook(__FILE__, 'ffp_uninstall');

// -----------------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------------
function ffp_bootstrap() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'ffp_notice_wc_required');
        return;
    }

    // Aggregator (laster addons, settings, osv) – hvis du har denne klassen
    if (class_exists('FFP_Plugin')) {
        FFP_Plugin::instance();
    }

    // Registrer egendefinerte Woo-statusverdier (knapper/JS forventer disse)
    if (class_exists('FFP_Statuses')) {
        new FFP_Statuses();
    }

    // REST-endepunkter (ordrefeed, claim/status, geocode)
    if (class_exists('FFP_REST')) {
        new FFP_REST();
    }

    // Driver-portal (shortcodes og enqueuing skjer kun når shortcoden finnes)
    if (class_exists('FFP_Driver')) {
        new FFP_Driver();
    }
}
add_action('plugins_loaded', 'ffp_bootstrap');

function ffp_notice_wc_required() {
    echo '<div class="notice notice-error"><p><strong>FastFood Pro:</strong> ' .
         esc_html__('WooCommerce er påkrevd.', 'fastfood-pro') .
         '</p></div>';
}

// -----------------------------------------------------------------------------
// Admin assets – Live Bestillinger (FFP → Live Bestillinger)
// -----------------------------------------------------------------------------
function ffp_admin_assets($hook) {
    // Bare på vår live-ordre-side (page=ffp-orders). $hook kan variere, så sjekk query arg.
    if (empty($_GET['page']) || $_GET['page'] !== 'ffp-orders') {
        return;
    }

    $js_rel = 'assets/js/restaurant-orders.js';
    $css_rel = 'assets/css/styles.css';

    $js_abs  = FFP_DIR . $js_rel;
    $css_abs = FFP_DIR . $css_rel;

    $js_ver  = file_exists($js_abs)  ? filemtime($js_abs)  : FFP_VERSION;
    $css_ver = file_exists($css_abs) ? filemtime($css_abs) : FFP_VERSION;

    wp_enqueue_script(
        'ffp-restaurant-orders',
        FFP_URL . $js_rel,
        ['jquery'],
        $js_ver,
        true
    );

    wp_localize_script('ffp-restaurant-orders', 'ffpOrders', [
        'restUrl' => esc_url_raw(rtrim(rest_url(), '/')),
        'nonce'   => wp_create_nonce('wp_rest'),
        'sound'   => true,
    ]);

    wp_enqueue_style(
        'ffp-admin-orders',
        FFP_URL . $css_rel,
        [],
        $css_ver
    );
}
add_action('admin_enqueue_scripts', 'ffp_admin_assets');
