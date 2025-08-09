<?php
if (!defined('ABSPATH')) exit;

class FFP_Orders {
    public function __construct() {
        // Ingen ekstra hooks her; REST håndterer feed. Admin JS pinger REST.
    }

    public static function get_open_orders() {
        $args = [
            'limit' => 20,
            'status' => ['processing','on-hold','pending','ffp-preparing','ffp-ready','ffp-out-for-delivery'],
            'orderby'=>'date','order'=>'DESC'
        ];
        if (!class_exists('WC_Order_Query')) return [];
        $q = new WC_Order_Query($args);
        $orders = $q->get_orders();
        $out = [];
        foreach ($orders as $o) {
            $out[] = [
                'id' => $o->get_id(),
                'status' => $o->get_status(),
                'total' => $o->get_total(),
                'currency' => $o->get_currency(),
                'date_created' => $o->get_date_created() ? $o->get_date_created()->date_i18n('Y-m-d H:i') : '',
                'billing_name' => $o->get_formatted_billing_full_name(),
                'shipping_address' => $o->get_shipping_address_1().' '.$o->get_shipping_postcode().' '.$o->get_shipping_city(),
                'items' => array_map(function($item){
                    return $item->get_name() . ' x ' . $item->get_quantity();
                }, $o->get_items()),
                'note' => $o->get_customer_note(),
                'ffp_tip' => $o->get_meta('_ffp_tip', true),
                'ffp_eta' => $o->get_meta('_ffp_eta', true),
                'ffp_delivery_when' => $o->get_meta('_ffp_delivery_when', true),
                'driver_id' => $o->get_meta('_ffp_driver_id', true),
            ];
        }
        return $out;
    }
}
