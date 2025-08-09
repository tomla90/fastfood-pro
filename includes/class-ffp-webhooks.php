<?php
if (!defined('ABSPATH')) exit;

/**
 * Webhooks + e-postmaler på hendelser.
 * Innstillinger:
 *  - webhook_url (POST JSON)
 *  - email_to (kommaseparert), email_subject_new, email_body_new, email_subject_status, email_body_status
 * Placeholders: {order_id},{status},{total},{customer},{address},{driver}
 */
class FFP_Webhooks {
    public function __construct() {
        add_action('woocommerce_thankyou', [$this,'on_new_order'], 30, 1);
        add_action('transition_post_status', [$this,'on_status_change'], 10, 3);
    }

    private function placeholders($order) {
        $addr = trim($order->get_shipping_address_1().' '.$order->get_shipping_postcode().' '.$order->get_shipping_city());
        $driver = $order->get_meta('_ffp_driver_id', true);
        $driver_name = $driver ? (get_the_author_meta('display_name',$driver) ?: "Driver #$driver") : 'Not assigned';
        return [
            '{order_id}' => $order->get_id(),
            '{status}'   => wc_get_order_status_name($order->get_status()),
            '{total}'    => wc_price($order->get_total()),
            '{customer}' => $order->get_formatted_billing_full_name(),
            '{address}'  => $addr,
            '{driver}'   => $driver_name,
        ];
    }

    private function send_webhook($type, $order) {
        $s = get_option('ffp_settings', []);
        $url = $s['webhook_url'] ?? '';
        if (!$url) return;
        $payload = [
            'type' => $type,
            'order_id' => $order->get_id(),
            'status' => $order->get_status(),
            'total'  => $order->get_total(),
            'currency' => $order->get_currency(),
            'customer' => $order->get_formatted_billing_full_name(),
            'address' => trim($order->get_shipping_address_1().' '.$order->get_shipping_postcode().' '.$order->get_shipping_city()),
            'driver_id' => $order->get_meta('_ffp_driver_id', true),
            'site' => home_url()
        ];
        wp_remote_post($url, [
            'timeout'=>12,
            'headers'=>['Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload)
        ]);
    }

    private function send_email($subject, $body, $order) {
        $s = get_option('ffp_settings', []);
        $to = $s['email_to'] ?? '';
        if (!$to) return;
        $rep = $this->placeholders($order);
        $subject = strtr($subject, $rep);
        $body = nl2br(strtr($body, $rep));
        wp_mail($to, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    public function on_new_order($order_id) {
        $order = wc_get_order($order_id); if (!$order) return;
        $this->send_webhook('new_order', $order);
        $s = get_option('ffp_settings', []);
        $this->send_email($s['email_subject_new'] ?? 'New order #{order_id}', $s['email_body_new'] ?? "Order #{order_id} total {total}", $order);
    }

    public function on_status_change($new, $old, $post) {
        if ($post->post_type !== 'shop_order') return;
        if ($new === $old) return;
        $order = wc_get_order($post->ID); if (!$order) return;
        $this->send_webhook('status_change', $order);
        $s = get_option('ffp_settings', []);
        $this->send_email($s['email_subject_status'] ?? 'Order #{order_id} → {status}', $s['email_body_status'] ?? "Order #{order_id} status is now {status}", $order);
    }
}
