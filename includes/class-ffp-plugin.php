<?php
if (!defined('ABSPATH')) exit;

class FFP_Plugin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        // Last moduler
        new FFP_Settings();
        new FFP_Admin_Menu();
        new FFP_Orders();
        new FFP_Product_Addons();
        new FFP_Checkout();
        new FFP_Frontend();
        new FFP_Driver();
        new FFP_REST();
        new FFP_Licensing_Client(); // Lisens og oppdateringer

        // Assets
        add_action('admin_enqueue_scripts', [$this,'admin_assets']);
        add_action('wp_enqueue_scripts', [$this,'frontend_assets']);

        // Sikre roller ved behov uten å fjerne på deaktivering
        add_action('init', [$this,'ensure_roles']);
    }

    public function admin_assets($hook) {
        // Last kun der vi trenger
        if (isset($_GET['page']) && $_GET['page'] === 'ffp-orders') {
            wp_enqueue_style('ffp-admin', FFP_URL.'assets/css/styles.css', [], FFP_VERSION);
            wp_enqueue_script('ffp-orders', FFP_URL.'assets/js/restaurant-orders.js', ['jquery','wp-api'], FFP_VERSION, true);
            wp_localize_script('ffp-orders', 'FFP_ORDERS', [
                'nonce' => wp_create_nonce('wp_rest'),
                'rest'  => esc_url_raw( rest_url('ffp/v1') ),
                'sound' => (bool) (get_option('ffp_settings')['order_sound'] ?? true),
            ]);
        }
    }

    public function frontend_assets() {
        wp_enqueue_style('ffp-frontend', FFP_URL.'assets/css/styles.css', [], FFP_VERSION);
        wp_enqueue_script('ffp-driver', FFP_URL.'assets/js/driver-portal.js', ['jquery','wp-api'], FFP_VERSION, true);
        wp_localize_script('ffp-driver', 'FFP_DRIVER', [
            'nonce' => wp_create_nonce('wp_rest'),
            'rest'  => esc_url_raw( rest_url('ffp/v1') ),
        ]);
    }

    public function ensure_roles() {
        // Re-add in case host fjerner
        if (!get_role('driver')) {
            add_role('driver','Delivery Driver',['read'=>true,'ffp_driver'=>true]);
        }
        // Capabilities til admin
        $roles = ['administrator','fastfood_admin'];
        foreach ($roles as $r) {
            $role = get_role($r);
            if ($role) {
                $role->add_cap('manage_fastfood');
                $role->add_cap('ffp_view_orders');
                $role->add_cap('ffp_update_orders');
            }
        }
    }
}
