<?php
if (!defined('ABSPATH')) exit;

class FFP_Checkout {

    public function __construct() {
        // Ekstra checkout-felt via hooks (standard checkout)
        add_action('woocommerce_before_checkout_billing_form', [$this, 'delivery_type_field'], 5);
        add_action('woocommerce_after_order_notes',           [$this, 'additional_fields']);
        add_action('woocommerce_checkout_create_order',       [$this, 'save_order_meta'], 10, 2);

        // Kun vårt kommentarfelt – fjern Woo standard "order_comments"
        add_filter('woocommerce_enable_order_notes_field', '__return_false');

        // Valider adressefelt ved pickup/delivery
        add_filter('woocommerce_checkout_fields', [$this, 'maybe_make_shipping_optional']);

        // Gebyrer
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_tip_fee']);
        add_action('woocommerce_cart_calculate_fees', [$this, 'add_delivery_fee'], 20);

        // Shortcodes (for Breakdance / custom layout)
        add_shortcode('ffp_delivery_type',       [$this, 'sc_delivery_type']);
        add_shortcode('fastfood_tip_option',     [$this, 'sc_tip']);
        add_shortcode('ffp_delivery_when',       [$this, 'sc_when']);
        add_shortcode('ffp_note',                [$this, 'sc_note']);
        add_shortcode('fastfood_delivery_fee',   [$this, 'sc_delivery_fee']);
        add_shortcode('fastfood_summary',        [$this, 'sc_summary']);
        add_shortcode('track_order',             [$this, 'sc_track']);
    }

    /** Hent butikkmodus */
    private function store_mode(){
        $s = get_option('ffp_settings', []);
        $mode = $s['store_mode'] ?? 'both';
        return in_array($mode, ['takeaway','delivery','both'], true) ? $mode : 'both';
    }

    /** Leveringsvalg (hook) */
    public function delivery_type_field($checkout) {
        if (!is_checkout()) return;
        echo $this->render_delivery_type($checkout->get_value('ffp_delivery_type'));
    }

    /** Ekstra felter (hook) */
    public function additional_fields($checkout) {
        if (!is_checkout()) return;
        ob_start();
        ?>
        <div class="ffp-extra"><h3>Ekstra valg</h3>
            <?php echo $this->render_tip($checkout->get_value('ffp_tip'), $checkout->get_value('ffp_tip_custom')); ?>
            <?php echo $this->render_when($checkout->get_value('ffp_delivery_when')); ?>
            <?php echo $this->render_note($checkout->get_value('ffp_note')); ?>
        </div>
        <?php
        echo ob_get_clean();
        $this->print_checkout_js();
        $this->print_checkout_css();
    }

    /** ========== RENDER HELPERS (brukes av både hooks og shortcodes) ========== */

    private function render_delivery_type($current = '') {
        $mode    = $this->store_mode();
        $options = [];
        if ($mode === 'takeaway' || $mode === 'both') $options['pickup'] = 'Hent selv';
        if ($mode === 'delivery' || $mode === 'both') $options['delivery'] = 'Levering';
        if (!$current) $current = ($mode === 'delivery') ? 'delivery' : 'pickup';

        ob_start(); ?>
        <div class="ffp-extra">
          <h3>Leveringsvalg</h3>
          <div class="ffp-delivery-type">
            <div class="woocommerce-input-wrapper" role="radiogroup">
              <?php foreach ($options as $val => $label): ?>
                <label>
                  <span><?php echo esc_html($label); ?></span>
                  <input type="radio" name="ffp_delivery_type" value="<?php echo esc_attr($val); ?>" <?php checked($current, $val); ?> />
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_tip($current = '0', $custom = '') {
        $tip_current = $current ?: '0';
        $tip_opts = ['0'=>'Ingen','5'=>'5 kr','10'=>'10 kr','20'=>'20 kr','custom'=>'Egendefinert'];
        ob_start(); ?>
        <div class="form-row form-row-wide">
          <label>Tips til sjåfør</label>
          <div class="ffp-tip-group" role="radiogroup">
            <?php foreach ($tip_opts as $val => $label): ?>
              <label class="ffp-tip-pill">
                <input type="radio" name="ffp_tip" value="<?php echo esc_attr($val); ?>" <?php checked($tip_current, $val); ?>>
                <span><?php echo esc_html($label); ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <p class="form-row form-row-wide ffp-tip-custom-row">
          <label for="ffp_tip_custom">Egendefinert tips (kr)</label>
          <input type="number" id="ffp_tip_custom" name="ffp_tip_custom" min="0" step="1" value="<?php echo esc_attr($custom); ?>">
        </p>
        <?php
        return ob_get_clean();
    }

    private function render_when($current = 'asap') {
        $opts = [
            'asap' => 'Så fort som mulig',
            '15'   => '+15 min',
            '30'   => '+30 min',
            '45'   => '+45 min',
            '60'   => '+60 min',
        ];
        ob_start(); ?>
        <p class="form-row form-row-wide">
            <label for="ffp_delivery_when">Når ønsker du levering/uthenting?</label>
            <select name="ffp_delivery_when" id="ffp_delivery_when">
                <?php foreach ($opts as $k => $lbl): ?>
                    <option value="<?php echo esc_attr($k); ?>" <?php selected($current, $k); ?>><?php echo esc_html($lbl); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
        return ob_get_clean();
    }

    private function render_note($current = '') {
        ob_start(); ?>
        <p class="form-row form-row-wide">
            <label for="ffp_note">Notat til kjøkken/sjåfør</label>
            <textarea name="ffp_note" id="ffp_note" rows="3"><?php echo esc_textarea($current); ?></textarea>
        </p>
        <?php
        return ob_get_clean();
    }

    /** ========== SHORTCODES ========== */

    public function sc_delivery_type() {
        if (!is_checkout()) return '';
        $checkout = WC()->checkout();
        return $this->render_delivery_type($checkout->get_value('ffp_delivery_type'));
    }

    public function sc_tip() {
        if (!is_checkout()) return '';
        $checkout = WC()->checkout();
        $html = '<div class="ffp-extra"><h3>Tips</h3>'.$this->render_tip(
            $checkout->get_value('ffp_tip'),
            $checkout->get_value('ffp_tip_custom')
        ).'</div>';
        $html .= $this->inline_update_trigger_js(); // sørger for update_checkout når shortcoden brukes alene
        $html .= $this->print_checkout_css(false);
        return $html;
    }

    public function sc_when() {
        if (!is_checkout()) return '';
        $checkout = WC()->checkout();
        $html = '<div class="ffp-extra"><h3>Tidspunkt</h3>'.$this->render_when(
            $checkout->get_value('ffp_delivery_when')
        ).'</div>';
        $html .= $this->inline_update_trigger_js();
        return $html;
    }

    public function sc_note() {
        if (!is_checkout()) return '';
        $checkout = WC()->checkout();
        return '<div class="ffp-extra"><h3>Notat</h3>'.$this->render_note(
            $checkout->get_value('ffp_note')
        ).'</div>';
    }

    public function sc_delivery_fee() {
        if (!is_checkout()) return '';
        ob_start(); ?>
        <div class="ffp-card" id="ffp-fee-box" aria-live="polite">
            <strong>Leveringsgebyr:</strong> <span class="ffp-fee-amount">—</span>
        </div>
        <script>
        (function($){
          function readFeeFromTotals(){
            // Finn "Leveringsgebyr" i totals-tabellen
            var fee = null;
            $('.woocommerce-checkout-review-order-table .fee, .shop_table .fee').each(function(){
              var $row = $(this);
              var label = ($row.find('th, .name').text() || '').toLowerCase();
              if (label.indexOf('leveringsgebyr') !== -1) {
                fee = $row.find('td, .amount').text().trim();
              }
            });
            if (fee) $('#ffp-fee-box .ffp-fee-amount').text(fee);
          }
          $(document.body).on('updated_checkout', readFeeFromTotals);
          $(readFeeFromTotals);
        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }

    public function sc_summary() {
        if (!is_checkout()) return '';
        ob_start(); ?>
        <div class="ffp-card" id="ffp-summary">
          <h3>Oppsummering</h3>
          <div class="ffp-meta-row"><span>Leveringsvalg:</span><span class="sum-delivery">—</span></div>
          <div class="ffp-meta-row"><span>Adresse:</span><span class="sum-address">—</span></div>
          <div class="ffp-meta-row"><span>Tips:</span><span class="sum-tip">—</span></div>
          <div class="ffp-meta-row"><span>Når:</span><span class="sum-when">—</span></div>
          <div class="ffp-meta-row"><span>Notat:</span><span class="sum-note">—</span></div>
        </div>
        <script>
        (function($){
          function val(name){ var $f=$('[name="'+name+'"]'); if(!$f.length) return '';
            if ($f.is(':radio')) { var v=$('[name="'+name+'"]:checked').val()||''; return v; }
            return $f.val()||'';
          }
          function humanTip(){
            var t = val('ffp_tip');
            if (t==='custom') { var c = Number($('[name="ffp_tip_custom"]').val()||0); return c>0 ? (c+' kr') : 'Ingen'; }
            if (!t || t==='0') return 'Ingen';
            return t+' kr';
          }
          function humanWhen(){
            var m = {asap:'Så fort som mulig','15':'+15 min','30':'+30 min','45':'+45 min','60':'+60 min'};
            var v = val('ffp_delivery_when'); return m[v]||'-';
          }
          function humanDelivery(){
            var v = val('ffp_delivery_type'); return v==='delivery' ? 'Levering' : 'Hent selv';
          }
          function address(){
            var parts=[];
            ['shipping_address_1','shipping_address_2','shipping_postcode','shipping_city'].forEach(function(n){
              var p = val(n); if (p) parts.push(p);
            });
            return parts.join(' ');
          }
          function render(){
            $('#ffp-summary .sum-delivery').text(humanDelivery());
            $('#ffp-summary .sum-address').text(address() || (val('ffp_delivery_type')==='delivery' ? '(oppgi adresse)' : '—'));
            $('#ffp-summary .sum-tip').text(humanTip());
            $('#ffp-summary .sum-when').text(humanWhen());
            $('#ffp-summary .sum-note').text(val('ffp_note')||'—');
          }
          $(document.body).on('updated_checkout change', 'form.checkout', render);
          $(render);
        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }

    public function sc_track($atts) {
        $a = shortcode_atts(['order_id' => ''], $atts);
        $order_id = absint($a['order_id']);
        if (!$order_id) return '<div class="ffp-card"><p>Oppgi <code>order_id</code> i shortcoden, f.eks. [track_order order_id="1234"]</p></div>';

        $o = wc_get_order($order_id);
        if (!$o) return '<div class="ffp-card"><p>Fant ikke ordren.</p></div>';

        $status = wc_get_order_status_name('wc-'.$o->get_status());
        $addr_parts = array_filter([
            $o->get_shipping_address_1(),
            $o->get_shipping_address_2(),
            $o->get_shipping_postcode(),
            $o->get_shipping_city()
        ]);
        $addr = implode(' ', $addr_parts);

        ob_start(); ?>
        <div class="ffp-card">
          <h3>Sporing – ordre #<?php echo (int)$order_id; ?></h3>
          <p>Status: <strong><?php echo esc_html($status); ?></strong></p>
          <?php if ($addr): ?><p>Adresse: <?php echo esc_html($addr); ?></p><?php endif; ?>
          <p>Kontakt: <?php echo esc_html($o->get_billing_phone()); ?> <?php echo esc_html($o->get_billing_email()); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /** ========== SERVER-SIDE LAGRING/VALIDERING ========== */

    public function maybe_make_shipping_optional($fields){
        $mode = $this->store_mode();
        $type = isset($_POST['ffp_delivery_type']) ? sanitize_text_field($_POST['ffp_delivery_type']) : (($mode==='delivery') ? 'delivery' : 'pickup');

        if (!empty($fields['shipping'])) {
            foreach ($fields['shipping'] as $key => &$f) {
                // Påkrevd kun ved levering
                $f['required'] = ($type === 'delivery');
            }
        }
        return $fields;
    }

    public function save_order_meta($order, $data) {
        $mode = $this->store_mode();
        $type       = isset($_POST['ffp_delivery_type']) ? wc_clean($_POST['ffp_delivery_type']) : (($mode==='delivery') ? 'delivery' : 'pickup');
        $tip_choice = isset($_POST['ffp_tip']) ? wc_clean($_POST['ffp_tip']) : '0';
        $tip_custom = isset($_POST['ffp_tip_custom']) ? floatval($_POST['ffp_tip_custom']) : 0;
        $when       = isset($_POST['ffp_delivery_when']) ? wc_clean($_POST['ffp_delivery_when']) : 'asap';
        $note       = isset($_POST['ffp_note']) ? wp_kses_post($_POST['ffp_note']) : '';

        $final_tip = ($tip_choice === 'custom') ? max(0, $tip_custom) : floatval($tip_choice);

        $order->update_meta_data('_ffp_delivery_type', $type);
        $order->update_meta_data('_ffp_tip', $final_tip);
        $order->update_meta_data('_ffp_delivery_when', $when);

        if ($note) { $order->set_customer_note($note); }

        $eta = ($when === 'asap') ? 20 : (20 + intval($when));
        $order->update_meta_data('_ffp_eta', $eta);
    }

    /** ========== FEES ========== */

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

    public function add_delivery_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;

        $mode = $this->store_mode();
        if (!in_array($mode, ['delivery','both'], true)) return;

        $p = [];
        if (isset($_POST['post_data'])) parse_str($_POST['post_data'], $p);

        // Hvis kunden har valgt pickup, ikke legg til leveringsgebyr
        $type = $p['ffp_delivery_type'] ?? (($mode==='delivery') ? 'delivery' : 'pickup');
        if ($type === 'pickup') return;

        $s        = get_option('ffp_settings', []);
        $origin   = $s['store_address'] ?? '';
        $addr     = trim(($p['shipping_address_1'] ?? '').' '.($p['shipping_postcode'] ?? '').' '.($p['shipping_city'] ?? ''));
        $postcode = trim($p['shipping_postcode'] ?? '');

        if (!$addr || !$origin) { $cart->add_fee(__('Leveringsgebyr', 'fastfood-pro'), 39, false); return; }

        $km = FFP_Geo::distance_km($origin, $addr);
        if ($km === null) { $cart->add_fee(__('Leveringsgebyr', 'fastfood-pro'), 39, false); return; }

        $fee = FFP_Zones::calc_fee($km, $postcode);
        $cart->add_fee(__('Leveringsgebyr', 'fastfood-pro'), $fee, false);
    }

    /** ========== UI JS/CSS (felles) ========== */

    private function inline_update_trigger_js(){
        // Liten JS som sikrer update_checkout når shortcodes står alene i Breakdance
        return '<script>(function($){
            function toggleTipCustom(){
                var val = $(\'input[name="ffp_tip"]:checked\').val();
                var $row = $(\'.ffp-tip-custom-row\'); var $inp=$(\'#ffp_tip_custom\');
                if (val === \'custom\'){ $row.show(); $inp.prop(\'disabled\', false); }
                else { $row.hide(); $inp.val(\'\').prop(\'disabled\', true); }
            }
            function toggleAddressFields(){
                var type = $(\'input[name="ffp_delivery_type"]:checked\').val() || \'pickup\';
                var sels = [\'#ship-to-different-address\',\'#shipping_first_name_field\',\'#shipping_last_name_field\',\'#shipping_company_field\',\'#shipping_country_field\',\'#shipping_address_1_field\',\'#shipping_address_2_field\',\'#shipping_postcode_field\',\'#shipping_city_field\',\'#shipping_state_field\'];
                if(type === \'pickup\'){ sels.forEach(s => $(s).hide().find(\'input,select\').prop(\'disabled\',true)); }
                else { sels.forEach(s => $(s).show().find(\'input,select\').prop(\'disabled\',false)); }
            }
            function bind(){
                $(document.body).on(\'change\', \'input[name="ffp_delivery_type"], input[name="ffp_tip"], #ffp_tip_custom, #ffp_delivery_when, #ffp_note, .address-field :input\', function(){
                    toggleTipCustom(); toggleAddressFields(); $(document.body).trigger(\'update_checkout\');
                });
            }
            $(function(){ toggleTipCustom(); toggleAddressFields(); bind(); });
        })(jQuery);</script>';
    }

    private function print_checkout_js(){
        // Skrives kun én gang når vi bruker hooks
        echo $this->inline_update_trigger_js();
    }

    private function print_checkout_css($echo = true){
        $css = '<style>
            .ffp-tip-group{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;}
            .ffp-tip-pill{display:inline-flex;align-items:center;gap:8px;border:1px solid #ddd;border-radius:999px;padding:6px 12px;cursor:pointer;user-select:none;background:#fff;}
            .ffp-tip-pill input{display:none;}
            .ffp-tip-pill span{font-weight:600;font-size:.95em;}
            .ffp-tip-pill input:checked + span{color:#fff;background:#333;border-radius:999px;padding:4px 10px;}
            .ffp-tip-custom-row{display:none;}
            .ffp-delivery-type .woocommerce-input-wrapper{display:flex;gap:8px;flex-wrap:wrap;}
            .ffp-delivery-type .woocommerce-input-wrapper label{display:flex;flex-direction:column-reverse;align-items:flex-start;border:1px solid #ddd;border-radius:12px;padding:8px 12px;background:#fff;cursor:pointer;}
            .ffp-delivery-type .woocommerce-input-wrapper label input[type="radio"]{margin-top:6px;}
            .ffp-delivery-type .optional{display:none;}
        </style>';
        if ($echo) echo $css; else return $css;
    }
}
