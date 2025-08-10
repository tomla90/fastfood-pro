<?php
if (!defined('ABSPATH')) exit;

class FFP_REST {
    public function __construct() {
        add_action('rest_api_init', [$this, 'routes']);
    }

    private static function is_driver_role(): bool {
        $u = wp_get_current_user();
        return $u && is_user_logged_in() && in_array('ffp_driver', (array) $u->roles, true);
    }

    public function routes() {

        // GET /wp-json/ffp/v1/orders
        register_rest_route('ffp/v1', '/orders', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return is_user_logged_in() && (
                    current_user_can('manage_options') ||
                    current_user_can('manage_woocommerce') ||
                    current_user_can('ffp_view_orders') ||
                    self::is_driver_role()
                );
            },
            'args' => [
                'status' => [
                    'description' => 'Comma-separated list of statuses',
                    'type'        => 'string',
                    'required'    => false,
                    'sanitize_callback' => function($v){
                        return implode(',', array_map('sanitize_key', array_filter(array_map('trim', explode(',', (string)$v)))));
                    }
                ],
                'limit' => [
                    'description' => 'Max number of orders (1-100)',
                    'type'        => 'integer',
                    'required'    => false,
                    'validate_callback' => function($v){ return (int)$v > 0 && (int)$v <= 100; },
                ],
            ],
            'callback' => function (WP_REST_Request $req) {
                return rest_ensure_response( FFP_Orders::get_open_orders([
                    'status' => $req->get_param('status'),
                    'limit'  => $req->get_param('limit'),
                ]) );
            }
        ]);

        // POST /wp-json/ffp/v1/orders/{id}/status
        register_rest_route('ffp/v1', '/orders/(?P<id>\d+)/status', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return is_user_logged_in() && (
                    current_user_can('manage_options') ||
                    current_user_can('manage_woocommerce') ||
                    current_user_can('ffp_update_orders') ||
                    self::is_driver_role()
                );
            },
            'callback' => [$this, 'update_status']
        ]);

        // POST /wp-json/ffp/v1/orders/{id}/claim
        register_rest_route('ffp/v1', '/orders/(?P<id>\d+)/claim', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return is_user_logged_in() && (
                    current_user_can('manage_options') ||
                    current_user_can('manage_woocommerce') ||
                    self::is_driver_role()
                );
            },
            'callback' => [$this, 'claim_order']
        ]);

        // GET /wp-json/ffp/v1/geo?address=...
        register_rest_route('ffp/v1', '/geo', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return is_user_logged_in() && (
                    current_user_can('manage_options') ||
                    current_user_can('manage_woocommerce') ||
                    self::is_driver_role() ||
                    current_user_can('ffp_view_orders')
                );
            },
            'callback' => function (WP_REST_Request $req) {
                $addr = sanitize_text_field($req->get_param('address'));
                if (!$addr) return new WP_Error('bad_request', 'Missing address', ['status'=>400]);
                // NB: riktig metodenavn
                $g = FFP_Geo::geocode($addr);
                return $g ?: new WP_Error('geo_fail', 'Geocode failed', ['status'=>500]);
            }
        ]);
    }

    public function update_status(WP_REST_Request $req) {
        $id = (int) $req['id'];
        $order = wc_get_order($id);
        if (!$order) return new WP_Error('ffp_not_found', 'Order not found', ['status'=>404]);

        $status  = sanitize_key($req->get_param('status'));
        $allowed = ['ffp-preparing','ffp-ready','ffp-out-for-delivery','completed','processing','on-hold'];
        if (!in_array($status, $allowed, true)) {
            return new WP_Error('ffp_bad_status', 'Invalid status', ['status'=>400]);
        }

        $order->update_status($status, 'FFP admin changed status');
        return ['ok'=>true, 'id'=>$id, 'status'=>$status];
    }

    public function claim_order(WP_REST_Request $req) {
        $id = (int) $req['id'];
        $order = wc_get_order($id);
        if (!$order) return new WP_Error('ffp_not_found', 'Order not found', ['status'=>404]);

        $uid = get_current_user_id();
        if (!$uid) return new WP_Error('ffp_auth', 'Login required', ['status'=>403]);

        $existing = (int) $order->get_meta('_ffp_driver_id', true);
        if ($existing && $existing !== $uid && !current_user_can('ffp_update_orders') && !current_user_can('manage_woocommerce')) {
            return new WP_Error('ffp_taken', 'Already assigned', ['status'=>409]);
        }

        $order->update_meta_data('_ffp_driver_id', $uid);
        $order->save();
        return ['ok'=>true, 'id'=>$id, 'driver'=>$uid];
    }
}
