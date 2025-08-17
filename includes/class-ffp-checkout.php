<?php
if (!defined('ABSPATH')) exit;

class FFP_Checkout {

    public function __construct() {
        // Ekstra checkout-felt
        add_action('woocommerce_before_checkout_billing_form', [$this, 'delivery_type_field'], 5);
        add_action('woocommerce_after_order_notes',           [$this, 'additional_fields']);
        add_action('woocommerce_checkout_create_order',       [$this, 'save_order_meta'], 10, 2);

        // Kun vårt kommentarfelt – fjern Woo standard "order_comments"
        add_filter('woocommerce_enable_order_notes_field', '__return_false');
        add_filter('woocommerce_checkout_fields', [$this, 'maybe_make_shipping_optional']);

        // Gebyrer
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_tip_fee']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_delivery_fee'], 20);

        // Kortkoder (placeholder)
        add_shortcode('fastfood_tip_option',  [$this, 'shortcode_tip']);
        add_shortcode('fastfood_delivery_fee',[$this, 'shortcode_delivery_fee']);
        add_shortcode('fastfood_summary',     [$this, 'shortcode_summary']);
        add_shortcode('track_order',          [$this, 'shortcode_track']);
    }

    /** Leveringsvalg øverst i checkout */
    public function delivery_type_field($checkout) {
        echo '<div class="ffp-extra"><h3>Leveringsvalg</h3>';
        woocommerce_form_field('ffp_delivery_type', [
            'type'    => 'radio',
            'label'   => 'Hvordan vil du få maten?',
            'class'   => ['form-row-wide', 'ffp-delivery-type'],
            'options' => [ 'pickup'=>'Hent selv', 'delivery'=>'Levering' ],
            'default' => 'pickup',
        ], $checkout->get_value('ffp_delivery_type'));
        echo '</div>';
    }

    /** Ekstra felter (tips, tidspunkt, notat) */
    public function additional_fields($checkout) {
        echo '<div class="ffp-extra"><h3>Ekstra valg</h3>';

        // Tips
        echo '<div class="form-row form-row-wide"><label>Tips til sjåfør</label>';
        echo '<div class="ffp-tip-group" role="radiogroup">';
        $tip_current = $checkout->get_value('ffp_tip') ?: '0';
        $tip_opts = ['0'=>'Ingen','5'=>'5 kr','10'=>'10 kr','20'=>'20 kr','custom'=>'Egendefinert'];
        foreach ($tip_opts as $val => $label) {
            $checked = checked($tip_current, $val, false);
            echo '<label class="ffp-tip-pill">';
            echo '<input type="radio" name="ffp_tip" value="'.esc_attr($val).'" '.$checked.'>';
            echo '<span>'.esc_html($label).'</span>';
            echo '</label>';
        }
        echo '</div></div>';

        woocommerce_form_field('ffp_tip_custom', [
            'type'             => 'number',
            'label'            => 'Egendefinert tips (kr)',
            'class'            => ['form-row-wide ffp-tip-custom-row'],
            'custom_attributes'=> ['min' => '0', 'step' => '1'],
        ], $checkout->get_value('ffp_tip_custom'));

        // Tidspunkt
        woocommerce_form_field('ffp_delivery_when', [
            'type'    => 'select',
            'label'   => 'Når ønsker du levering/uthenting?',
            'class'   => ['form-row-wide'],
            'options' => [
                'asap' => 'Så fort som mulig',
                '15'   => '+15 min',
                '30'   => '+30 min',
                '45'   => '+45 min',
                '60'   => '+60 min',
            ],
            'default' => 'asap',
        ], $checkout->get_value('ffp_delivery_when'));

        // Én kommentar (vårt felt – erstatter Woo sitt)
        woocommerce_form_field('ffp_note', [
            'type'  => 'textarea',
            'label' => 'Notat til kjøkken/sjåfør',
            'class' => ['form-row-wide'],
        ], $checkout->get_value('ffp_note'));

        echo '</div>';

        // UI toggles (tips + adresse)
        ?>
        <script>
        (function($){
            function toggleTipCustom(){
                const tip = $('input[name="ffp_tip"]:checked').val();
                const $row = $('.ffp-tip-custom-row');
                const $inp = $('input[name="ffp_tip_custom"]');
                if(tip === 'custom'){ $row.show(); $inp.prop('disabled', false); }
                else { $row.hide(); $inp.val('').prop('disabled', true); }
            }
            function toggleAddressFields(){
                const type = $('input[name="ffp_delivery_type"]:checked').val();
                const sels = [
                    '#ship-to-different-address',
                    '#shipping_first_name_field','#shipping_last_name_field',
                    '#shipping_company_field','#shipping_country_field',
                    '#shipping_address_1_field','#shipping_address_2_field',
                    '#shipping_postcode_field','#shipping_city_field','#shipping_state_field'
                ];
                if(type === 'pickup'){ sels.forEach(s => $(s).hide().find('input,select').prop('disabled',true)); }
                else { sels.forEach(s => $(s).show().find('input,select').prop('disabled',false)); }
            }
            function bind(){
                $(document.body).on('change','input[name="ffp_delivery_type"], input[name="ffp_tip"], input[name="ffp_tip_custom"], select[name="ffp_delivery_when"]',function(){
                    toggleTipCustom(); toggleAddressFields(); $(document.body).trigger('update_checkout');
                });
            }
            $(function(){ toggleTipCustom(); toggleAddressFields(); bind(); });
        })(jQuery);
        </script>
        <?php
    }

    /** Server-side: shipping valgfritt ved pickup */
    public function maybe_make_shipping_optional($fields){
        if (isset($_POST['ffp_delivery_type']) && $_POST['ffp_delivery_type'] === 'pickup') {
            if (!empty($fields['shipping'])) {
                foreach ($fields['shipping'] as &$f) { $f['required'] = false; }
            }
        }
        return $fields;
    }

    /** Lagre meta + kunde-notat */
    public function save_order_meta($order, $data) {
        $type       = isset($_POST['ffp_delivery_type']) ? wc_clean($_POST['ffp_delivery_type']) : 'pickup';
        $tip_choice = isset($_POST['ffp_tip']) ? wc_clean($_POST['ffp_tip']) : '0';
        $tip_custom = isset($_POST['ffp_tip_custom']) ? floatval($_POST['ffp_tip_custom']) : 0;
        $when       = isset($_POST['ffp_delivery_when']) ? wc_clean($_POST['ffp_delivery_when']) : 'asap';
        $note       = isset($_POST['ffp_note']) ? wp_kses_post($_POST['ffp_note']) : '';

        $final_tip = ($tip_choice === 'custom') ? max(0, $tip_custom) : floatval($tip_choice);

        $order->update_meta_data('_ffp_delivery_type', $type);
        $order->update_meta_data('_ffp_tip', $final_tip);
        $order->update_meta_data('_ffp_delivery_when', $when);

        if ($note) { $order->set_customer_note($note); }

        // Enkel ETA (kan raffineres)
        $eta = ($when === 'asap') ? 20 : (20 + intval($when));
        $order->update_meta_data('_ffp_eta', $eta);
    }

    /** Tips-gebyr */
    public function add_tip_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        $tip = 0.0;
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $p);
            if (!empty($p['ffp_tip'])) {
                $tip = ($p['ffp_tip'] === 'custom') ? floatval($p['ffp_tip_custom'] ?? 0) : floatval($p['ffp_tip']);
            }
        }
        if ($tip > 0) $cart->add_fee(__('Tips', 'fastfood-pro'), max(0,$tip), false);
    }

    /** Leveringsgebyr (fallback 39 kr) */
    public function add_delivery_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        $mode = get_option('ffp_settings')['store_mode'] ?? 'both';
        if (!in_array($mode, ['delivery','both'], true)) return;

        $p = [];
        if (isset($_POST['post_data'])) parse_str($_POST['post_data'], $p);

        if (!empty($p['ffp_delivery_type']) && $p['ffp_delivery_type'] === 'pickup') return;

        $s       = get_option('ffp_settings', []);
        $origin  = $s['store_address'] ?? '';
        $addr    = trim(($p['shipping_address_1'] ?? '').' '.($p['shipping_postcode'] ?? '').' '.($p['shipping_city'] ?? ''));
        $postcode= trim($p['shipping_postcode'] ?? '');

        if (!$addr || !$origin) { $cart->add_fee(__('Leveringsgebyr', 'fastfood-pro'), 39, false); return; }

        $km = FFP_Geo::distance_km($origin, $addr);
        if ($km === null) { $cart->add_fee(__('Leveringsgebyr', 'fastfood-pro'), 39, false); return; }

        $fee = FFP_Zones::calc_fee($km, $postcode);
        $cart->add_fee(__('Leveringsgebyr', 'fastfood-pro'), $fee, false);
    }

    // Kortkoder – valgfritt å fylle
    public function shortcode_tip(){ return ''; }
    public function shortcode_delivery_fee(){ return ''; }
    public function shortcode_summary(){ return ''; }
    public function shortcode_track($atts){ return ''; }
}
