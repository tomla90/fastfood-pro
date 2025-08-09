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
        new FFP_Notify();           // OneSignal push
        new FFP_Geo();              // Geografi
        new FFP_PWA();              // Manifest + service worker (fil/manifest-ender)
        new FFP_SSE();              // Live events (SSE)
        new FFP_Zones();            // SONER/leveringssoner
        new FFP_Webhooks();         // Webhooks

        // Assets
        add_action('admin_enqueue_scripts', [$this,'admin_assets']);
        add_action('wp_enqueue_scripts',    [$this,'frontend_assets']);

        // Sikre roller
        add_action('init', [$this,'ensure_roles']);
    }

    public function admin_assets($hook) {
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

    // FRONTEND: CSS/JS + Map + PWA service worker i footer
    public function frontend_assets() {
        wp_enqueue_style('ffp-frontend', FFP_URL.'assets/css/styles.css', [], FFP_VERSION);

        $s = get_option('ffp_settings', []);

        // Mapbox GL når valgt og token finnes
        if (!empty($s['map_provider']) && $s['map_provider'] === 'mapbox' && !empty($s['mapbox_token'])) {
            wp_enqueue_style('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css', [], '2.15.0');
            wp_enqueue_script('mapbox-gl', 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js', [], '2.15.0', true);
        }

        wp_enqueue_script('ffp-driver', FFP_URL.'assets/js/driver-portal.js', ['jquery','wp-api'], FFP_VERSION, true);

        wp_localize_script('ffp-driver', 'FFP_DRIVER', [
            'nonce' => wp_create_nonce('wp_rest'),
            'rest'  => esc_url_raw( rest_url('ffp/v1') ),
            'map'   => [
                'provider'      => $s['map_provider'] ?? 'mapbox',
                'mapbox_token'  => $s['mapbox_token'] ?? '',
                'store'         => [
                    'lat' => isset($s['store_lat']) ? floatval($s['store_lat']) : 0,
                    'lng' => isset($s['store_lng']) ? floatval($s['store_lng']) : 0,
                ],
            ],
        ]);

        // PWA registrering i footer (kun frontend)
        add_action('wp_footer', function(){ ?>
            <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('<?php echo esc_js( home_url('/ffp-sw.js') ); ?>').catch(()=>{});
            }
            </script>
        <?php });
    }

    public function ensure_roles() {
        if (!get_role('driver')) {
            add_role('driver', 'Delivery Driver', ['read'=>true, 'ffp_driver'=>true]);
        }
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
