<?php
if (!defined('ABSPATH')) exit;

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
        $addons = json_decode(get_post_meta($post->ID, self::META_KEY, true), true);
        if (!is_array($addons)) $addons = [];

        wp_nonce_field('ffp_addons_save','ffp_addons_nonce');

        echo '<div id="ffp-addons-container">';
        foreach ($addons as $addon) {
            echo '<div class="ffp-addon-row">';
            echo '<input type="text" name="ffp_addons_label[]" placeholder="Navn" value="'.esc_attr($addon['label']).'" />';
            echo '<input type="number" step="0.01" name="ffp_addons_price[]" placeholder="Pris" value="'.esc_attr($addon['price']).'" />';
            echo '<button type="button" class="button remove-addon">Fjern</button>';
            echo '</div>';
        }
        echo '</div>';

        echo '<button type="button" class="button" id="add-addon">Legg til tillegg</button>';

        // Enkel JS for å legge til nye rader
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            document.getElementById('add-addon').addEventListener('click', function(){
                let c = document.getElementById('ffp-addons-container');
                let row = document.createElement('div');
                row.classList.add('ffp-addon-row');
                row.innerHTML = '<input type="text" name="ffp_addons_label[]" placeholder="Navn" /> ' +
                                '<input type="number" step="0.01" name="ffp_addons_price[]" placeholder="Pris" /> ' +
                                '<button type="button" class="button remove-addon">Fjern</button>';
                c.appendChild(row);
            });
            document.addEventListener('click', function(e){
                if(e.target && e.target.classList.contains('remove-addon')){
                    e.target.closest('.ffp-addon-row').remove();
                }
            });
        });
        </script>
        <style>
        .ffp-addon-row { margin-bottom: 5px; }
        .ffp-addon-row input { margin-right: 5px; }
        </style>
        <?php
    }

    public function save($post_id, $post) {
        if (!isset($_POST['ffp_addons_nonce']) || !wp_verify_nonce($_POST['ffp_addons_nonce'],'ffp_addons_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== 'product') return;

        $labels = $_POST['ffp_addons_label'] ?? [];
        $prices = $_POST['ffp_addons_price'] ?? [];

        $addons = [];
        foreach ($labels as $i => $label) {
            $label = sanitize_text_field($label);
            $price = floatval($prices[$i] ?? 0);
            if ($label !== '') {
                $addons[] = [
                    'id'    => sanitize_title($label),
                    'label' => $label,
                    'price' => $price
                ];
            }
        }

        update_post_meta($post_id, self::META_KEY, wp_json_encode($addons));
    }

  public function render_addons_on_product() {
    global $product;
    if (!$product) return;

    $addons = json_decode(get_post_meta($product->get_id(), self::META_KEY, true), true);
    if (empty($addons)) return;

    echo '<div class="ffp-addons"><h4>Tillegg</h4>';
    foreach ($addons as $a) {
        $id    = esc_attr($a['id']);
        $label = esc_html($a['label']);
        $price = floatval($a['price']);

        echo '<label class="ffp-addon-row">';
        echo '  <input type="checkbox" name="ffp_addons[]" value="'.$id.'" data-price="'.$price.'"> ';
        echo '  <span class="ffp-addon-label">'.$label.'</span>';
        echo '  <span class="ffp-addon-price">'.wc_price($price).'</span>';
        echo '</label>';
    }
    echo '</div>';
}

    public function add_addons_to_cart($cart_item_data, $product_id, $variation_id) {
        $addons = json_decode(get_post_meta($product_id, self::META_KEY, true), true);
        $map = [];
        foreach ($addons as $a) $map[$a['id']] = $a;

        $chosen = isset($_POST['ffp_addons']) ? (array) $_POST['ffp_addons'] : [];
        $selected = [];
        $extra = 0.0;
        foreach ($chosen as $id) {
            if (isset($map[$id])) {
                $selected[] = [
                    'id'    => $id,
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
                $cart_item['data']->set_price($price + floatval($cart_item['ffp_addons_extra']));
            }
        }
    }
}
