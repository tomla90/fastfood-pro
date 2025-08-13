<?php
if (!defined('ABSPATH')) exit;

/**
 * Driver-portal (shortcode) + caps + admin-informasjon.
 * Bruker REST fra FFP_REST (ingen egne ruter her).
 */
class FFP_Driver {

    /** Option som markerer at caps/rolle er satt én gang */
    const OPT_CAPS_SET = 'ffp_driver_caps_set';

    public function __construct() {
        // Sørg for at rolle + cap finnes (kun 1 gang per site)
        add_action('init', [__CLASS__, 'ensure_role_caps']);

        // Caps for både "driver" og evt. legacy "ffp_driver"
        add_filter('user_has_cap', [$this,'grant_driver_caps'], 10, 3);

        // Shortcodes
        add_shortcode('ffp_driver_portal', [$this,'shortcode_portal']);
        add_shortcode('ffp_login',         [$this,'shortcode_login']);

        // Enqueue driver-portal assets når portalen er på siden (med fallback)
        add_action('wp_enqueue_scripts', [$this,'maybe_enqueue_assets']);

        // Admin: vis sjåfør i ordre
        add_action('woocommerce_admin_order_data_after_order_details', [$this,'admin_driver_info']);
    }

    /**
     * Kjør én gang: opprett "driver"-rollen (om mangler) og gi cap "ffp_view_orders".
     */
    public static function ensure_role_caps() {
        if (!get_option(self::OPT_CAPS_SET)) {
            // Opprett rolle hvis den ikke finnes
            if (!get_role('driver')) {
                add_role('driver', __('Driver', 'fastfood-pro'), ['read' => true]);
            }
            // Gi capability
            if ($role = get_role('driver')) {
                $role->add_cap('ffp_view_orders');
            }
            update_option(self::OPT_CAPS_SET, 1);
        }
    }

    /**
     * Gi brukere med rolleen "driver" (eller legacy "ffp_driver") de nødvendige capsene.
     */
    public function grant_driver_caps($allcaps, $caps, $args) {
        $user = get_user_by('id', $args[1] ?? 0);
        if (!$user) return $allcaps;

        $roles = (array) $user->roles;
        if (in_array('driver', $roles, true) || in_array('ffp_driver', $roles, true)) {
            $allcaps['ffp_view_orders'] = true;
            $allcaps['read']            = true;
        }
        return $allcaps;
    }

    /** [ffp_driver_portal] – viser login hvis ikke innlogget */
    public function shortcode_portal() {
        if (!is_user_logged_in() || !current_user_can('ffp_view_orders')) {
            return '<div class="ffp-login"><p>' .
                   esc_html__('Logg inn som sjåfør for å se dine ordrer.', 'fastfood-pro') .
                   '</p>' . wp_login_form(['echo'=>false]) . '</div>';
        }
        return '<div id="ffp-driver-app" class="ffp-card"><p>' .
               esc_html__('Laster ordrer…', 'fastfood-pro') .
               '</p></div>';
    }

    /** [ffp_login redirect="/driver-portal/"] – valgfri separat login-shortcode */
    public function shortcode_login($atts = []) {
        if (is_user_logged_in()) {
            return '<p>' . esc_html__('Du er allerede innlogget.', 'fastfood-pro') . '</p>';
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

    /**
     * Enqueue JS/CSS for driver-portalen når shortcoden finnes.
     * Har fallback for page-buildere (slug/ID-sjekk).
     */
    public function maybe_enqueue_assets() {
        if (!is_singular()) return;
        $post = get_post();
        if (!$post) return;

        $has_sc = has_shortcode($post->post_content, 'ffp_driver_portal');

        // Fallback: last på spesifikk slug/ID selv om shortcoden ikke sees i the_content
        // Endre 'driver-portal' til din faktiske slug om nødvendig, eller legg inn post-ID.
        if (!$has_sc && (is_page('driver-portal'))) {
            $has_sc = true;
        }

        if (!$has_sc) return;

        $js_path = FFP_DIR . 'assets/js/driver-portal.js';
        $ver     = file_exists($js_path) ? filemtime($js_path) : FFP_VERSION;

        wp_enqueue_script('ffp-driver-portal', FFP_URL . 'assets/js/driver-portal.js', ['jquery'], $ver, true);
        wp_localize_script('ffp-driver-portal', 'ffpDriver', [
            'restUrl' => esc_url_raw(rtrim(rest_url(), '/')),
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_style('ffp-driver-portal', FFP_URL . 'assets/css/styles.css', [], FFP_VERSION);
    }

    /** Vis tildelt sjåfør på WooCommerce-ordre i admin */
    public function admin_driver_info($order) {
        $driver_id = (int) get_post_meta($order->get_id(), '_ffp_driver_id', true);
        if ($driver_id) {
            $u = get_user_by('id', $driver_id);
            if ($u) {
                echo '<p><strong>' . esc_html__('Sjåfør:', 'fastfood-pro') . '</strong> ' .
                    esc_html($u->display_name) . ' (#' . (int) $driver_id . ')</p>';
            }
        }
    }
}
