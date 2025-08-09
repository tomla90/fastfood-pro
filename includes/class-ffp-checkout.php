<?php
if (!defined('ABSPATH')) exit;

/**
 * Checkout-felter: tips, leveringstidspunkt, notat, beregning av leveringsgebyr.
 */
class FFP_Checkout {
    public function __construct() {
        add_action('woocommerce_after_order_notes', [$this,'additional_fields']);
        add_action('woocommerce_checkout_create_order', [$this,'save_order_meta'], 10, 2);

        // Tips som egen linje/avgift
        add_action('woocommerce_cart_calculate_fees', [$this,'add_tip_fee']);
        // Leveringsgebyr (dummy – utvid med avstandskalkulering)
        add_action('woocommerce_cart_calculate_fees', [$this,'add_delivery_fee'], 20);

        // Kortkoder
        add_shortcode('fastfood_tip_option', [$this,'shortcode_tip']);
        add_shortcode('fastfood_delivery_fee', [$this,'shortcode_delivery_fee']);
        add_shortcode('fastfood_summary', [$this,'shortcode_summary']);
        add_shortcode('track_order', [$this,'shortcode_track']);
    }

    public function additional_fields($checkout) {
        echo '<div class="ffp-extra"><h3>FastFood</h3>';
        woocommerce_form_field('ffp_tip', [
            'type' => 'select',
            'label' => 'Tips til sjåfør',
            'options' => ['0'=>'Ingen','5'=>'5 kr','10'=>'10 kr','20'=>'20 kr','custom'=>'Egendefinert'],
        ], $checkout->get_value('ffp_tip'));

        woocommerce_form_field('ffp_tip_custom', [
            'type'=>'number','label'=>'Egendefinert tips (kr)','custom_attributes'=>['min'=>'0','step'=>'1']
        ], $checkout->get_value('ffp_tip_custom'));

        woocommerce_form_field('ffp_delivery_when', [
            'type'=>'select','label'=>'Når ønsker du levering/uthenting?',
            'options'=>[
                'asap'=>'Så fort som mulig',
                '15'=>'+15 min','30'=>'+30 min','45'=>'+45 min','60'=>'+60 min'
            ]
        ], $checkout->get_value('ffp_delivery_when'));

        woocommerce_form_field('ffp_note', [
            'type'=>'textarea','label'=>'Notat til kjøkken/sjåfør'
        ], $checkout->get_value('ffp_note'));
        echo '</div>';
    }

    public function save_order_meta($order, $data) {
        $tip = isset($_POST['ffp_tip']) ? wc_clean($_POST['ffp_tip']) : '0';
        $tip_custom = isset($_POST['ffp_tip_custom']) ? floatval($_POST['ffp_tip_custom']) : 0;
        $when = isset($_POST['ffp_delivery_when']) ? wc_clean($_POST['ffp_delivery_when']) : 'asap';
        $note = isset($_POST['ffp_note']) ? wp_kses_post($_POST['ffp_note']) : '';

        $final_tip = ($tip === 'custom') ? $tip_custom : floatval($tip);
        $order->update_meta_data('_ffp_tip', $final_tip);
        $order->update_meta_data('_ffp_delivery_when', $when);
        if ($note) $order->set_customer_note($note);

        // Estimer enkel ETA
        $eta = ($when==='asap') ? 20 : (20 + intval($when));
        $order->update_meta_data('_ffp_eta', $eta);
    }

    public function add_tip_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        $tip = 0.0;
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $p);
            if (!empty($p['ffp_tip'])) {
                $tip = ($p['ffp_tip']==='custom') ? floatval($p['ffp_tip_custom'] ?? 0) : floatval($p['ffp_tip']);
            }
        }
        if ($tip > 0) $cart->add_fee('Tips', $tip, false);
    }

    public function add_delivery_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        // Enkel modell: 39 kr hvis "delivery" modus. Utvid med sonekart/avstand.
        $mode = get_option('ffp_settings')['store_mode'] ?? 'both';
        if (in_array($mode, ['delivery','both'])) {
            $cart->add_fee('Leveringsgebyr', 39, false);
        }
    }

    public function shortcode_tip() {
        ob_start();
        echo '<div class="ffp-tip-shortcode"><label>Tips: </label>
        <select name="ffp_tip_shortcode">
        <option value="0">Ingen</option><option value="5">5</option><option value="10">10</option>
        <option value="20">20</option><option value="custom">Egendefinert</option></select>
        <input type="number" name="ffp_tip_custom_shortcode" min="0" step="1" placeholder="kr">
        <small>Legges til i kassa</small>
        </div>';
        return ob_get_clean();
    }

    public function shortcode_delivery_fee() {
        return '<div class="ffp-delivery-fee">Leveringsgebyr beregnes i kassa.</div>';
    }

    public function shortcode_summary() {
        $mode = esc_html(get_option('ffp_settings')['store_mode'] ?? 'both');
        return '<div class="ffp-summary"><strong>Modus:</strong> '.$mode.' – Tid og notat velges i kassa.</div>';
    }

    public function shortcode_track($atts) {
        $a = shortcode_atts(['order_id'=>0], $atts);
        $order = wc_get_order(intval($a['order_id']));
        if (!$order) return '<div>Ordre ikke funnet.</div>';
        $status = wc_get_order_status_name($order->get_status());
        $addr = $order->get_shipping_address_1().' '.$order->get_shipping_postcode().' '.$order->get_shipping_city();
        $eta = $order->get_meta('_ffp_eta', true);
        $driver = $order->get_meta('_ffp_driver_id', true);
        $driver_name = $driver ? get_the_author_meta('display_name', $driver) : 'Ikke tildelt';
        return '<div class="ffp-track">
          <p><strong>Status:</strong> '.$status.'</p>
          <p><strong>Adresse:</strong> '.esc_html($addr).'</p>
          <p><strong>Forventet tid:</strong> '.intval($eta).' min</p>
          <p><strong>Bud:</strong> '.esc_html($driver_name).'</p>
        </div>';
    }
}
