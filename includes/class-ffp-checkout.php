<?php
if (!defined('ABSPATH')) exit;

/**
 * Checkout-felter: leveringsmetode (pickup/levering), tips, leveringstidspunkt, notat,
 * samt gebyrhåndtering (tips + leveringsgebyr via sone/distanse).
 */
class FFP_Checkout {

    public function __construct() {
        // Ekstra checkout-felt
        add_action('woocommerce_after_order_notes',        [$this, 'additional_fields']);
        add_action('woocommerce_checkout_create_order',    [$this, 'save_order_meta'], 10, 2);

        // Gebyrer
        add_action('woocommerce_cart_calculate_fees',      [$this, 'add_tip_fee']);             // Tips
        add_action('woocommerce_cart_calculate_fees',      [$this, 'add_delivery_fee'], 20);    // Leveringsgebyr

        // Kortkoder
        add_shortcode('fastfood_tip_option',               [$this, 'shortcode_tip']);
        add_shortcode('fastfood_delivery_fee',             [$this, 'shortcode_delivery_fee']);
        add_shortcode('fastfood_summary',                  [$this, 'shortcode_summary']);
        add_shortcode('track_order',                       [$this, 'shortcode_track']);
    }

    /** Ekstra felter i checkout */
    public function additional_fields($checkout) {
        echo '<div class="ffp-extra"><h3>FastFood</h3>';

        // 1) Leveringsmetode (pickup eller levering)
        woocommerce_form_field('ffp_delivery_type', [
            'type'    => 'radio',
            'label'   => 'Leveringsmetode',
            'class'   => ['form-row-wide'],
            'options' => [
                'pickup'   => 'Hent selv',
                'delivery' => 'Levering',
            ],
            'default' => 'pickup',
        ], $checkout->get_value('ffp_delivery_type'));

        // 2) Tips – pen knappegruppe
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

        // Egendefinert tips (vises kun når valgt)
        woocommerce_form_field('ffp_tip_custom', [
            'type'             => 'number',
            'label'            => 'Egendefinert tips (kr)',
            'class'            => ['form-row-wide ffp-tip-custom-row'],
            'custom_attributes'=> ['min' => '0', 'step' => '1'],
        ], $checkout->get_value('ffp_tip_custom'));

        // 3) Ønsket tid
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

        // 4) Notat
        woocommerce_form_field('ffp_note', [
            'type'  => 'textarea',
            'label' => 'Notat til kjøkken/sjåfør',
            'class' => ['form-row-wide'],
        ], $checkout->get_value('ffp_note'));

        echo '</div>';

        // JS: toggle egendefinert tips + adressefelt ved pickup/delivery, og oppdater totals
        ?>
        <script>
        (function(){
            const $ = window.jQuery;

            function toggleTipCustom(){
                const tip = $('input[name="ffp_tip"]:checked').val();
                const $row = $('.ffp-tip-custom-row');
                const $inp = $('input[name="ffp_tip_custom"]');
                if(tip === 'custom'){
                    $row.show();
                    $inp.prop('disabled', false);
                } else {
                    $row.hide();
                    $inp.val('').prop('disabled', true);
                }
            }

            function toggleAddressFields(){
                const type = $('input[name="ffp_delivery_type"]:checked').val();
                const addressSelectors = [
                    '#shipping_first_name_field',
                    '#shipping_last_name_field',
                    '#shipping_company_field',
                    '#shipping_country_field',
                    '#shipping_address_1_field',
                    '#shipping_address_2_field',
                    '#shipping_postcode_field',
                    '#shipping_city_field',
                    '#shipping_state_field'
                ];
                if(type === 'pickup'){
                    addressSelectors.forEach(sel => { jQuery(sel).hide(); });
                } else {
                    addressSelectors.forEach(sel => { jQuery(sel).show(); });
                }
            }

            function bind(){
                $(document.body).on('change', 'input[name="ffp_delivery_type"], input[name="ffp_tip"], input[name="ffp_tip_custom"], select[name="ffp_delivery_when"]', function(){
                    toggleTipCustom();
                    toggleAddressFields();
                    $(document.body).trigger('update_checkout');
                });
            }

            $(function(){
                toggleTipCustom();
                toggleAddressFields();
                bind();
            });
        })();
        </script>
        <style>
            /* Tips pill-knapper */
            .ffp-tip-group{
                display:flex; flex-wrap:wrap; gap:8px; margin-top:6px;
            }
            .ffp-tip-pill{
                display:inline-flex; align-items:center; gap:8px;
                border:1px solid #ddd; border-radius:999px; padding:6px 12px;
                cursor:pointer; user-select:none; background:#fff;
            }
            .ffp-tip-pill input{ display:none; }
            .ffp-tip-pill span{ font-weight:600; font-size:.95em; }
            .ffp-tip-pill input:checked + span{
                color:#fff;
                /* tema-uavhengig highlight */
                background:#333; border-radius:999px; padding:4px 10px;
            }
            .ffp-tip-custom-row{ display:none; }
        </style>
        <?php
    }

    /** Lagre meta fra ekstra felter */
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

        if ($note) {
            $order->set_customer_note($note);
        }

        // Enkel ETA-estimering
        $eta = ($when === 'asap') ? 20 : (20 + intval($when));
        $order->update_meta_data('_ffp_eta', $eta);
    }

    /** Legg til tips som gebyr (ikke-mva) */
    public function add_tip_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        $tip = 0.0;
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $p);
            if (!empty($p['ffp_tip'])) {
                $tip = ($p['ffp_tip'] === 'custom')
                    ? floatval($p['ffp_tip_custom'] ?? 0)
                    : floatval($p['ffp_tip']);
            }
        }
        if ($tip > 0) {
            $cart->add_fee(__('Tips', 'fastfood-pro'), max(0,$tip), false);
        }
    }

    /**
     * Leveringsgebyr: sone + distanse.
     * Bruker FFP_Zones::calc_fee($km, $postcode) for å beregne.
     * Hopper over gebyr ved "Hent selv".
     */
    public function add_delivery_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        $mode = get_option('ffp_settings')['store_mode'] ?? 'both';
        if (!in_array($mode, ['delivery','both'])) return;

        if (isset($_POST['post_data'])) parse_str($_POST['post_data'], $p); else $p = [];

        if (!empty($p['ffp_delivery_type']) && $p['ffp_delivery_type'] === 'pickup') {
            return; // ingen leveringsgebyr
        }

        $s = get_option('ffp_settings', []);
        $origin = $s['store_address'] ?? '';

        $addr = trim(($p['shipping_address_1'] ?? '').' '.($p['shipping_postcode'] ?? '').' '.($p['shipping_city'] ?? ''));
        $postcode = trim($p['shipping_postcode'] ?? '');

        if (!$addr || !$origin) {
            $cart->add_fee(__('Leveringsgebyr', 'fastfood-pro'), 39, false);
            return;
        }

        $km = FFP_Geo::distance_km($origin, $addr);
        if ($km === null) {
            $cart->add_fee(__('Leveringsgebyr', 'fastfood-pro'), 39, false);
            return;
        }

        $fee = FFP_Zones::calc_fee($km, $postcode);
        $cart->add_fee(__('Leveringsgebyr', 'fastfood-pro'), $fee, false);
    }

    /** Kortkode: enkel tip-widget (info) */
    public function shortcode_tip() {
        ob_start();
        ?>
        <div class="ffp-tip-shortcode">
            <label>Tips: </label>
            <select name="ffp_tip_shortcode">
                <option value="0">Ingen</option>
                <option value="5">5</option>
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="custom">Egendefinert</option>
            </select>
            <input type="number" name="ffp_tip_custom_shortcode" min="0" step="1" placeholder="kr">
            <small>Legges til i kassa</small>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Kortkode: info om leveringsgebyr */
    public function shortcode_delivery_fee() {
        return '<div class="ffp-delivery-fee">Leveringsgebyr beregnes i kassa.</div>';
    }

    /** Kortkode: oppsummering av modus */
    public function shortcode_summary() {
        $mode = esc_html(get_option('ffp_settings')['store_mode'] ?? 'both');
        return '<div class="ffp-summary"><strong>Modus:</strong> '.$mode.' – Pickup/levering, tid og notat velges i kassa.</div>';
    }

    /** Kortkode: enkel ordretracking */
    public function shortcode_track($atts) {
        $a = shortcode_atts(['order_id' => 0], $atts);
        $order = wc_get_order(intval($a['order_id']));
        if (!$order) return '<div>Ordre ikke funnet.</div>';

        $status = wc_get_order_status_name($order->get_status());
        $addr   = trim($order->get_shipping_address_1().' '.$order->get_shipping_postcode().' '.$order->get_shipping_city());
        $eta    = $order->get_meta('_ffp_eta', true);
        $driver = $order->get_meta('_ffp_driver_id', true);
        $driver_name = $driver ? get_the_author_meta('display_name', $driver) : 'Ikke tildelt';
        $type   = $order->get_meta('_ffp_delivery_type', true);

        return '<div class="ffp-track">
          <p><strong>Status:</strong> '.esc_html($status).'</p>
          <p><strong>Leveringsmetode:</strong> '.esc_html($type ?: 'ukjent').'</p>
          <p><strong>Adresse:</strong> '.esc_html($addr).'</p>
          <p><strong>Forventet tid:</strong> '.intval($eta).' min</p>
          <p><strong>Bud:</strong> '.esc_html($driver_name).'</p>
        </div>';
    }
}
