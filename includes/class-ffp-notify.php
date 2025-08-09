<?php
if (!defined('ABSPATH')) exit;

/**
 * OneSignal-notifications ved ny ordre og ved tildeling av sjåfør.
 * Forutsetter at OneSignal App ID og REST API Key er satt i FFP-innstillinger.
 * Segmentering:
 *  - $audience = 'All'    -> included_segments: ['All']
 *  - $audience = 'staff'  -> filters på tag 'role' == 'staff'
 *  - $audience = 'driver' -> filters på tag 'role' == 'driver'
 *
 * NB: For tag-baserte målgrupper må klienten sette OneSignal-taggen 'role' i frontenden,
 *     f.eks. OneSignal.setTag('role','driver') for bud, OneSignal.setTag('role','staff') for ansatte.
 */
class FFP_Notify {
    public function __construct() {
        // Push når ordre fullføres i checkout
        add_action('woocommerce_thankyou', [$this, 'order_created_push'], 20, 1);

        // Push når bud blir tildelt (meta _ffp_driver_id endres/legges til)
        add_action('updated_post_meta',    [$this, 'driver_assigned_push'], 10, 4);
        add_action('added_post_meta',      [$this, 'driver_assigned_push'], 10, 4);
    }

    /**
     * Send OneSignal notification.
     * @param string $title    Tittel (no/en settes likt)
     * @param string $message  Innhold (no/en settes likt)
     * @param string $audience 'All' eller en rolle/tag (f.eks. 'staff', 'driver')
     */
    private function send($title, $message, $audience = 'All') {
        $s        = get_option('ffp_settings', []);
        $app_id   = $s['onesignal_app_id']   ?? '';
        $rest_key = $s['onesignal_rest_key'] ?? '';
        if (!$app_id || !$rest_key) return;

        $body = [
            'app_id'   => $app_id,
            'headings' => ['en' => $title, 'no' => $title],
            'contents' => ['en' => $message, 'no' => $message],
        ];

        // Basic segmentering: alle eller via tag 'role'
        if ($audience === 'All') {
            $body['included_segments'] = ['All'];
        } else {
            $body['filters'] = [
                [
                    "field"    => "tag",
                    "key"      => "role",
                    "relation" => "=",
                    "value"    => $audience
                ]
            ];
        }

        wp_remote_post('https://onesignal.com/api/v1/notifications', [
            'headers' => [
                'Content-Type'  => 'application/json; charset=utf-8',
                'Authorization' => 'Basic ' . $rest_key,
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 12,
        ]);
    }

    /**
     * Når ordren opprettes (etter checkout).
     * Varsler staff/admin via OneSignal-tag 'role=staff'.
     */
    public function order_created_push($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $total = wc_price($order->get_total());
        $this->send(
            __('Ny bestilling', 'fastfood-pro'),
            "Ordre #{$order_id} – Total: {$total}",
            'staff'
        );
    }

    /**
     * Når _ffp_driver_id settes/endres -> push til 'driver'.
     * Fanger både added_post_meta og updated_post_meta.
     */
    public function driver_assigned_push($meta_id, $object_id, $meta_key, $_meta_value) {
        if ($meta_key !== '_ffp_driver_id') return;

        $driver_id = intval($_meta_value);
        if (!$driver_id) return;

        $u    = get_user_by('id', $driver_id);
        $name = $u ? $u->display_name : __('Sjåfør', 'fastfood-pro');

        $this->send(
            __('Levering tildelt', 'fastfood-pro'),
            "Ordre #{$object_id} → {$name}",
            'driver'
        );
    }
}
