<?php
if (!defined('ABSPATH')) exit;

/**
 * Henter og formatterer åpne WooCommerce-ordrer for FastFood Pro (admin + driver)
 */
class FFP_Orders {

    public function __construct() {}

    /**
     * Hent åpne ordrer (evt. filtrert fra REST-args).
     * @param array $args ['status'=>string|array, 'limit'=>int]
     * @return array<int, array<string,mixed>>
     */
    public static function get_open_orders(array $args = []) {
        if ( ! class_exists('WC_Order_Query') ) {
            return [];
        }

        // Status: komma-separert streng eller array. Fallback = "åpne" statuser.
        $statuses = $args['status'] ?? [
            'pending','on-hold','processing',
            'ffp-preparing','ffp-ready','ffp-out-for-delivery',
        ];
        if (is_string($statuses)) {
            $statuses = array_map('trim', explode(',', $statuses));
        }
        $statuses = array_values(array_filter(array_map('sanitize_key', (array) $statuses)));

        // Limit: 1–100, default 20.
        $limit = isset($args['limit']) ? (int) $args['limit'] : 20;
        if ($limit <= 0 || $limit > 100) $limit = 20;

        $query = new WC_Order_Query([
            'status'  => $statuses,
            'limit'   => $limit,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'objects',
        ]);

        $orders = $query->get_orders();
        $out    = [];

        foreach ($orders as $o) {
            /** @var WC_Order $o */
            // Kunde/levering
            $billing_name = method_exists($o, 'get_formatted_billing_full_name')
                ? $o->get_formatted_billing_full_name()
                : trim($o->get_billing_first_name() . ' ' . $o->get_billing_last_name());

            $shipping_parts = array_filter([
                $o->get_shipping_address_1(),
                $o->get_shipping_postcode(),
                $o->get_shipping_city(),
            ]);
            $shipping_address = implode(' ', $shipping_parts);

            // Varelinjer (visningsstrenger) + subtotal (linjetotaler ekskl. frakt)
            $items_arr      = [];
            $items_subtotal = 0.0;
            foreach ($o->get_items() as $item) {
                $qty  = method_exists($item, 'get_quantity') ? (int) $item->get_quantity() : 1;
                $name = method_exists($item, 'get_name') ? $item->get_name() : '';
                $line_total = method_exists($item, 'get_total') ? (float) $item->get_total() : 0;
                $items_arr[] = sprintf('%s x %d', $name, $qty);
                $items_subtotal += $line_total;
            }

            // Fraktmetoder (navn)
            $shipping_methods = [];
            foreach ($o->get_shipping_methods() as $ship_item) {
                $shipping_methods[] = method_exists($ship_item, 'get_name') ? $ship_item->get_name() : '';
            }

            // Diverse totals & info
            $total           = wc_format_decimal($o->get_total(), wc_get_price_decimals());
            $currency        = $o->get_currency();
            $discount_total  = method_exists($o, 'get_discount_total') ? (float) $o->get_discount_total() : 0.0;
            $shipping_total  = method_exists($o, 'get_shipping_total') ? (float) $o->get_shipping_total() : 0.0;
            $tax_total       = method_exists($o, 'get_total_tax') ? (float) $o->get_total_tax() : 0.0;
            $payment_method  = method_exists($o, 'get_payment_method_title') ? (string) $o->get_payment_method_title() : '';
            $coupon_codes    = method_exists($o, 'get_coupon_codes') ? (array) $o->get_coupon_codes() : [];

            // Status label (menneskelig lesbar)
            $status_key   = sanitize_key($o->get_status());
            $status_label = function_exists('wc_get_order_status_name')
                ? wc_get_order_status_name('wc-' . $status_key)
                : ucfirst($status_key);

            $out[] = [
                // Kjerne
                'id'               => (int) $o->get_id(),
                'status'           => $status_key,
                'status_label'     => $status_label,
                'date_created'     => $o->get_date_created() ? $o->get_date_created()->date_i18n('Y-m-d H:i') : '',

                // Beløp
                'total'            => $total,
                'currency'         => $currency,
                'items_subtotal'   => (float) $items_subtotal,
                'shipping_total'   => (float) $shipping_total,
                'tax_total'        => (float) $tax_total,
                'discount_total'   => (float) $discount_total,

                // Kunde/kontakt/levering
                'billing_name'     => $billing_name ?: __('Ukjent kunde', 'fastfood-pro'),
                'billing_phone'    => (string) $o->get_billing_phone(),
                'billing_email'    => (string) $o->get_billing_email(),
                'shipping_address' => $shipping_address,
                'shipping_methods' => array_values(array_filter($shipping_methods)),

                // Varelinjer (for visning)
                'items'            => array_values($items_arr),

                // Betaling/kuponger
                'payment_method'   => $payment_method,
                'coupon_codes'     => array_values($coupon_codes),

                // Notater/meta
                'note'             => (string) $o->get_customer_note(),
                'ffp_tip'          => (float) ($o->get_meta('_ffp_tip', true) ?: 0),
                'ffp_eta'          => (string) ($o->get_meta('_ffp_eta', true) ?: ''),
                'ffp_delivery_when'=> (string) ($o->get_meta('_ffp_delivery_when', true) ?: ''),
                'driver_id'        => $o->get_meta('_ffp_driver_id', true) ? (int) $o->get_meta('_ffp_driver_id', true) : null,
            ];
        }

        return $out;
    }
}
