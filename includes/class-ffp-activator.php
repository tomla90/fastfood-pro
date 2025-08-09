<?php
if (!defined('ABSPATH')) exit;

class FFP_Activator {
    public static function activate() {
        // Roller
        add_role('fastfood_admin', 'FastFood Admin', [
            'read' => true,
            'manage_fastfood' => true,
            'manage_woocommerce' => true,
        ]);
        add_role('fastfood_staff', 'FastFood Ansatt', [
            'read' => true,
            'ffp_view_orders' => true,
            'ffp_update_orders' => true,
        ]);
        add_role('driver', 'Delivery Driver', [
            'read' => true,
            'ffp_driver' => true,
        ]);

        // Standardinnstillinger
        $defaults = [
            'store_mode' => 'takeaway', // takeaway|delivery|both
            'order_sound' => true,
            'onesignal_app_id' => '',
            'onesignal_rest_key' => '',
            'license_key' => '',
            'license_server' => '', // f.eks. https://licenses.granbergdigital.no
            'update_endpoint' => '/wp-json/ffpls/v1/update', // relativ til server
        ];
        add_option('ffp_settings', $defaults);

        // Flush permalinks for REST routes (etter init)
        add_action('init', function(){ flush_rewrite_rules(); });
    }
}
