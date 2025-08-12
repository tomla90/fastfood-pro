<?php
if (!defined('ABSPATH')) exit;

class FFP_Orders {
    public function __construct() {}

    /**
     * Hent åpne ordrer (evt. filtrert fra REST-args).
     * @param array $args ['status'=>string|array, 'limit'=>int]
     * @return array<int, array<string,mixed>>
     */
    public static function get_open_orders(array $args = []) {
        if (!class_exists('WC_Order_Query')) return [];

        $statuses = $args['status'] ?? [
            'pending','on-hold','processing',
            'ffp-preparing','ffp-ready','ffp-out-for-delivery',
        ];
        if (is_string($statuses)) $statuses = array_map('trim', explode(',', $statuses));
        $statuses = array_values(array_filter(array_map('sanitize_key', (array) $statuses)));

        $limit = isset($args['limit']) ? (int)$args['limit'] : 20;
        if ($limit <= 0 || $limit > 100) $limit = 20;

        $q = new WC_Order_Query([
            'status'  => $statuses,
            'limit'   => $limit,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'objects',
        ]);

        $orders = $q->get_orders();
        $out = [];

        foreach ($orders as $o) {
            /** @var WC_Order $o */
            $billing_name = method_exists($o, 'get_formatted_billing_full_name')
                ? $o->get_formatted_billing_full_name()
                : trim($o->get_billing_first_name() . ' ' . $o->get_billing_last_name());

            $shipping_parts = array_filter([
                $o->get_shipping_address_1(),
                $o->get_shipping_postcode(),
                $o->get_shipping_city(),
            ]);
            $shipping_address = implode(' ', $shipping_parts);

            $items_arr = [];
            foreach ($o->get_items('line_item') as $item) {
                $qty  = method_exists($item, 'get_quantity') ? (int)$item->get_quantity() : 1;
                $name = method_exists($item, 'get_name') ? $item->get_name() : '';
                $items_arr[] = sprintf('%s x %d', $name, $qty);
            }

            $out[] = [
                'id'               => (int)$o->get_id(),
                'status'           => sanitize_key($o->get_status()),
                'total'            => wc_format_decimal($o->get_total(), wc_get_price_decimals()),
                'currency'         => $o->get_currency(),
                'date_created'     => $o->get_date_created() ? $o->get_date_created()->date_i18n('Y-m-d H:i') : '',
                'billing_name'     => $billing_name ?: __('Ukjent kunde', 'fastfood-pro'),
                'shipping_address' => $shipping_address,
                'items'            => array_values($items_arr),
                'note'             => (string)$o->get_customer_note(),
                'ffp_tip'          => (float)($o->get_meta('_ffp_tip', true) ?: 0),
                'ffp_eta'          => (string)($o->get_meta('_ffp_eta', true) ?: ''),
                'ffp_delivery_when'=> (string)($o->get_meta('_ffp_delivery_when', true) ?: ''),
                'driver_id'        => $o->get_meta('_ffp_driver_id', true) ? (int)$o->get_meta('_ffp_driver_id', true) : null,
            ];
        }

        return $out;
    }
}
