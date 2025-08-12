<?php
if (!defined('ABSPATH')) exit;

/**
 * Driver-portal (shortcode) + caps + admin-informasjon.
 * Bruker REST fra FFP_REST (ingen egne ruter her).
 */
class FFP_Driver {

    public function __construct() {
        // Caps for både "driver" og "ffp_driver"
        add_filter('user_has_cap', [$this,'grant_driver_caps'], 10, 3);

        // Shortcodes
        add_shortcode('ffp_driver_portal', [$this,'shortcode_portal']);
        add_shortcode('ffp_login',         [$this,'shortcode_login']);   // ← NY

        // Enqueue driver-portal assets kun når [ffp_driver_portal] er på siden
        add_action('wp_enqueue_scripts', [$this,'maybe_enqueue_assets']);

        // Admin: vis sjåfør i ordre
        add_action('woocommerce_admin_order_data_after_order_details', [$this,'admin_driver_info']);
    }

    public function grant_driver_caps($allcaps, $caps, $args) {
        $user = get_user_by('id', $args[1] ?? 0);
        if (!$user) return $allcaps;

        $roles = (array) $user->roles;
        if (in_array('driver', $roles, true) || in_array('ffp_driver', $roles, true)) {
            $allcaps['ffp_view_orders'] = true;
            $allcaps['read'] = true;
        }
        return $allcaps;
    }

    /** [ffp_driver_portal] – viser login hvis ikke innlogget */
    public function shortcode_portal() {
        if (!is_user_logged_in() || !current_user_can('ffp_view_orders')) {
            return '<div class="ffp-login"><p>Logg inn som sjåfør for å se dine ordrer.</p>' . wp_login_form(['echo'=>false]) . '</div>';
        }
        return '<div id="ffp-driver-app" class="ffp-card"><p>Laster ordrer…</p></div>';
    }

    /** [ffp_login redirect="/driver-portal/"] – valgfri separat login-shortcode */
    public function shortcode_login($atts = []) {
        if (is_user_logged_in()) {
            return '<p>Du er allerede innlogget.</p>';
        }
        $a = shortcode_atts([
            'redirect' => home_url(add_query_arg([], $_SERVER['REQUEST_URI'] ?? '')),
        ], $atts);

        ob_start();
        wp_login_form([
            'echo'     => true,
            'redirect' => esc_url_raw($a['redirect']),
        ]);
        return ob_get_clean();
    }

    public function maybe_enqueue_assets() {
        if (!is_singular()) return;
        $post = get_post();
        if (!$post || stripos($post->post_content, '[ffp_driver_portal]') === false) return;

        $js_path = FFP_DIR.'assets/js/driver-portal.js';
        $ver     = file_exists($js_path) ? filemtime($js_path) : FFP_VERSION;

        wp_enqueue_script('ffp-driver-portal', FFP_URL.'assets/js/driver-portal.js', ['jquery'], $ver, true);
        wp_localize_script('ffp-driver-portal', 'ffpDriver', [
            'restUrl' => esc_url_raw( rtrim( rest_url(), '/' ) ),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_style('ffp-driver-portal', FFP_URL.'assets/css/styles.css', [], FFP_VERSION);
    }

    public function admin_driver_info($order) {
        $driver_id = (int) get_post_meta($order->get_id(), '_ffp_driver_id', true);
        if ($driver_id) {
            $u = get_user_by('id', $driver_id);
            if ($u) {
                echo '<p><strong>Sjåfør:</strong> ' . esc_html($u->display_name) . ' (#'.(int)$driver_id.')</p>';
            }
        }
    }
}
