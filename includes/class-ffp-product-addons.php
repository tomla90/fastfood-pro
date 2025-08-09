<?php
if (!defined('ABSPATH')) exit;

/**
 * Produkt-tillegg per produkt (ost, paprika, ekstra kjøtt osv.)
 * Admin: meta-boks i produkt, lagrer JSON med add-ons [ {label, price, id}, ... ]
 * Frontend: render i produkt (via hook) og i cart/checkout.
 */
class FFP_Product_Addons {
    const META_KEY = '_ffp_addons';

    public function __construct() {
        add_action('add_meta_boxes', [$this,'metabox']);
        add_action('save_post_product', [$this,'save'], 10, 2);

        add_action('woocommerce_before_add_to_cart_button', [$this,'render_addons_on_product']);
        add_filter('woocommerce_add_cart_item_data', [$this,'add_addons_to_cart'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this,'display_cart_item_data'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this,'modify_cart_item_price'], 20);
    }

    public function metabox() {
        add_meta_box('ffp_addons', 'FastFood Pro – Tillegg', [$this,'metabox_html'], 'product', 'normal', 'default');
    }

    public function metabox_html($post) {
        $val = get_post_meta($post->ID, self::META_KEY, true);
        $json = is_string($val) && $val ? $val : '[]';
        wp_nonce_field('ffp_addons_save','ffp_addons_nonce');
        echo '<p>Legg inn som JSON-array av objekter: [{"id":"cheese","label":"Ekstra ost","price":15}]</p>';
        echo '<textarea name="ffp_addons_json" style="width:100%;height:160px;">'.esc_textarea($json).'</textarea>';
    }

    public function save($post_id, $post) {
        if (!isset($_POST['ffp_addons_nonce']) || !wp_verify_nonce($_POST['ffp_addons_nonce'],'ffp_addons_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'product') return;

        $json = wp_unslash($_POST['ffp_addons_json'] ?? '');
        // Lett validering
        json_decode($json);
        if (json_last_error() === JSON_ERROR_NONE) {
            update_post_meta($post_id, self::META_KEY, $json);
        }
    }

    public function render_addons_on_product() {
        global $product;
        if (!$product) return;
        $json = get_post_meta($product->get_id(), self::META_KEY, true);
        $addons = $json ? json_decode($json, true) : [];
        if (empty($addons)) return;

        echo '<div class="ffp-addons"><h4>Tillegg</h4>';
        foreach ($addons as $a) {
            $id = esc_attr($a['id']);
            $label = esc_html($a['label']);
            $price = floatval($a['price']);
            echo '<label class="ffp-addon-row"><input type="checkbox" name="ffp_addons[]" value="'.$id.'" data-price="'.$price.'"> '.$label.' ('.wc_price($price).')</label>';
        }
        echo '</div>';
    }

    public function add_addons_to_cart($cart_item_data, $product_id, $variation_id) {
        $json = get_post_meta($product_id, self::META_KEY, true);
        $addons = $json ? json_decode($json, true) : [];
        $map = [];
        foreach ($addons as $a) $map[$a['id']] = $a;

        $chosen = isset($_POST['ffp_addons']) ? (array) $_POST['ffp_addons'] : [];
        $selected = [];
        $extra = 0.0;
        foreach ($chosen as $id) {
            if (isset($map[$id])) {
                $selected[] = [
                    'id' => $id,
                    'label' => sanitize_text_field($map[$id]['label']),
                    'price' => floatval($map[$id]['price']),
                ];
                $extra += floatval($map[$id]['price']);
            }
        }
        if ($selected) {
            $cart_item_data['ffp_addons'] = $selected;
            $cart_item_data['ffp_addons_extra'] = $extra;
            $cart_item_data['unique_key'] = md5(microtime().rand());
        }
        return $cart_item_data;
    }

    public function display_cart_item_data($item_data, $cart_item) {
        if (!empty($cart_item['ffp_addons'])) {
            foreach ($cart_item['ffp_addons'] as $a) {
                $item_data[] = [
                    'name' => $a['label'],
                    'value' => wc_price($a['price']),
                    'display' => wc_price($a['price'])
                ];
            }
        }
        return $item_data;
    }

    public function modify_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (did_action('woocommerce_before_calculate_totals') >= 2) return;

        foreach ($cart->get_cart() as $cart_item) {
            if (!empty($cart_item['ffp_addons_extra'])) {
                $price = $cart_item['data']->get_price();
                $cart_item['data']->set_price( $price + floatval($cart_item['ffp_addons_extra']) );
            }
        }
    }
}
