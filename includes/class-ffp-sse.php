<?php
if (!defined('ABSPATH')) exit;

/**
 * Server-Sent Events for live admin/driver-oppdatering.
 * Fallback til polling i JS hvis SSE ikke er aktivert eller blokkert.
 */
class FFP_SSE {
    public function __construct() {
        add_action('rest_api_init', [$this,'routes']);
    }

    public function routes() {
        register_rest_route('ffp/v1','/events',[
            'methods'=>'GET',
            'permission_callback'=>function(){ return current_user_can('ffp_view_orders') || current_user_can('ffp_driver'); },
            'callback'=>[$this,'stream']
        ]);
    }

    public function stream($req) {
        $s = get_option('ffp_settings', []);
        if (empty($s['enable_sse'])) {
            return new WP_Error('ffp_sse_disabled','SSE disabled', ['status'=>400]);
        }

        ignore_user_abort(true);
        @set_time_limit(0);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no'); // Nginx
        while (!connection_aborted()) {
            $payload = FFP_Orders::get_open_orders();
            echo 'event: orders'."\n";
            echo 'data: '.wp_json_encode($payload)."\n\n";
            @ob_flush(); @flush();
            sleep(10);
        }
        exit;
    }
}
