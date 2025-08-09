<?php
if (!defined('ABSPATH')) exit;

class FFP_REST {
    public function __construct() {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes() {
        // Hent åpne ordrer
        register_rest_route('ffp/v1', '/orders', [
            'methods'             => 'GET',
            'permission_callback' => function() {
                return current_user_can('ffp_view_orders') || current_user_can('ffp_driver');
            },
            'callback'            => function($req) {
                return rest_ensure_response(FFP_Orders::get_open_orders());
            }
        ]);

        // Oppdater ordrestatus
        register_rest_route('ffp/v1', '/orders/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'permission_callback' => function() {
                return current_user_can('ffp_update_orders') || current_user_can('ffp_driver');
            },
            'callback'            => [$this, 'update_status']
        ]);

        // Claim ordre som sjåfør
        register_rest_route('ffp/v1', '/orders/(?P<id>\d+)/claim', [
            'methods'             => 'POST',
            'permission_callback' => function() {
                return current_user_can('ffp_driver');
            },
            'callback'            => [$this, 'claim_order']
        ]);

        // Nytt endepunkt for geokoding (driver-kart)
        register_rest_route('ffp/v1', '/geo', [
            'methods'             => 'GET',
            'permission_callback' => function() {
                return current_user_can('ffp_driver') || current_user_can('ffp_view_orders');
            },
            'callback'            => function($req) {
                $addr = sanitize_text_field($req->get_param('address'));
                if (!$addr) {
                    return new WP_Error('bad_request', 'Missing address', ['status' => 400]);
                }
                $g = FFP_Geo::geocode($addr);
                return $g ?: new WP_Error('geo_fail', 'Geocode failed', ['status' => 500]);
            }
        ]);
    }

    public function update_status($req) {
        $id    = intval($req['id']);
        $order = wc_get_order($id);
        if (!$order) {
            return new WP_Error('ffp_not_found', 'Order not found', ['status' => 404]);
        }

        $status  = sanitize_key($req->get_param('status'));
        $allowed = [
            'ffp-preparing',
            'ffp-ready',
            'ffp-out-for-delivery',
            'completed',
            'processing',
            'on-hold'
        ];
        if (!in_array($status, $allowed, true)) {
            return new WP_Error('ffp_bad_status', 'Invalid status', ['status' => 400]);
        }

        $order->set_status($status);
        $order->save();

        return [
            'ok'     => true,
            'id'     => $id,
            'status' => $status
        ];
    }

    public function claim_order($req) {
        $id    = intval($req['id']);
        $order = wc_get_order($id);
        if (!$order) {
            return new WP_Error('ffp_not_found', 'Order not found', ['status' => 404]);
        }

        $uid = get_current_user_id();
        if (!$uid) {
            return new WP_Error('ffp_auth', 'Login required', ['status' => 403]);
        }

        $existing = $order->get_meta('_ffp_driver_id', true);
        if ($existing && intval($existing) !== $uid && !current_user_can('ffp_update_orders')) {
            return new WP_Error('ffp_taken', 'Already assigned', ['status' => 409]);
        }

        $order->update_meta_data('_ffp_driver_id', $uid);
        $order->save();

        return [
            'ok'     => true,
            'id'     => $id,
            'driver' => $uid
        ];
    }
}
