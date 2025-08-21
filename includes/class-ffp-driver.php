<?php
if (!defined('ABSPATH')) exit;

class FFP_Driver {

    const OPT_CAPS_SET = 'ffp_driver_caps_set';

    public function __construct() {
        add_action('init', [__CLASS__, 'ensure_role_caps']);
        add_filter('user_has_cap', [$this,'grant_driver_caps'], 10, 3);

        // Shortcodes
        add_shortcode('ffp_driver_portal', [$this,'shortcode_portal']);
        add_shortcode('ffp_login',         [$this,'shortcode_login']);

        // NY: eneste du trenger – en logout-knapp som alltid redirecter riktig
        add_shortcode('ffp_logout_button', [$this,'shortcode_logout_button']);
        add_action('template_redirect',    [$this,'maybe_do_button_logout']); // håndterer ?ffp_btn_logout=1

        add_action('wp_enqueue_scripts',   [$this,'maybe_enqueue_assets']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this,'admin_driver_info']);
    }

    public static function ensure_role_caps() {
        if (!get_option(self::OPT_CAPS_SET)) {
            if (!get_role('driver')) add_role('driver', __('Driver', 'fastfood-pro'), ['read' => true]);
            if ($role = get_role('driver')) $role->add_cap('ffp_view_orders');
            update_option(self::OPT_CAPS_SET, 1);
        }
    }

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

    public function shortcode_portal() {
        if (!is_user_logged_in() || !current_user_can('ffp_view_orders')) {
            return '<div class="ffp-login"><p>' .
                   esc_html__('Logg inn som sjåfør for å se dine leveringer.', 'fastfood-pro') .
                   '</p>' . wp_login_form(['echo'=>false]) . '</div>';
        }

        $logout_url = wp_logout_url(get_permalink());

        return '<div id="ffp-driver-app" class="ffp-card">'.
                 '<div class="ffp-driver-header" style="display:flex;gap:.5rem;align-items:center;justify-content:space-between;">' .
                    '<h3 style="margin:0">' . esc_html__('Leveringer', 'fastfood-pro') . '</h3>' .
                    '<div class="ffp-driver-actions">' .
                      '<button class="button" id="ffp-refresh" type="button">'.esc_html__('Oppdater','fastfood-pro').'</button> ' .
                      '<a class="button" href="'.esc_url($logout_url).'">'.esc_html__('Logg ut','fastfood-pro').'</a>' .
                    '</div>' .
                 '</div>'.
                 '<div id="ffp-driver-list" style="margin-top:.75rem">Laster…</div>'.
               '</div>';
    }

    public function shortcode_login($atts = []) {
        if (is_user_logged_in()) return '<p>' . esc_html__('Du er allerede innlogget.', 'fastfood-pro') . '</p>';
        $a = shortcode_atts([
            'redirect' => home_url(add_query_arg([], $_SERVER['REQUEST_URI'] ?? '')),
        ], $atts);
        ob_start();
        wp_login_form(['echo'=>true, 'redirect'=>esc_url_raw($a['redirect'])]);
        return ob_get_clean();
    }

    /** Eneste du trenger i builderen:
     * [ffp_logout_button redirect="/driver-portal/" label="Logg ut" class="button"]
     * Vises kun når innlogget. Logger ut uten å trigge wp_logout-hooks og redirecter dit du ber om.
     */
    public function shortcode_logout_button($atts = []) {
        if (!is_user_logged_in()) return ''; // vis ikke når utlogget
        $a = shortcode_atts([
            'redirect' => '/driver-portal/',
            'label'    => __('Logg ut', 'fastfood-pro'),
            'class'    => 'button',
        ], $atts);

        // Normaliser target
        $target = (strpos($a['redirect'], 'http') === 0) ? $a['redirect'] : home_url($a['redirect']);

        // Pek til vår egen "lette" logout-handler (ingen rewrite nødvendig)
        $url = add_query_arg([
            'ffp_btn_logout' => 1,
            'redirect_to'    => $target,
        ], home_url('/'));

        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($url),
            esc_attr($a['class']),
            esc_html($a['label'])
        );
    }

    /** Utfør "lett" logout når ?ffp_btn_logout=1 – omgår andre wp_logout-redirects */
    public function maybe_do_button_logout() {
        if (!isset($_GET['ffp_btn_logout']) || (int) $_GET['ffp_btn_logout'] !== 1) return;

        // Bare hvis innlogget
        if (is_user_logged_in()) {
            if (function_exists('wp_destroy_current_session')) {
                wp_destroy_current_session();
            }
            wp_clear_auth_cookie();
            wp_set_current_user(0);
            /**
             * Ikke kall wp_logout() – den fyrer 'wp_logout' som andre hooks kan hijacke.
             * Vi har allerede logget ut ved å rydde cookies/sesjon.
             */
        }

        // Bestem redirect (tillat full URL på samme domene, eller relativ sti)
        $home  = home_url('/');
        $redir = isset($_GET['redirect_to']) ? wp_unslash($_GET['redirect_to']) : $home;

        // Relativ → gjør om til absolutt
        if (strpos($redir, '/') === 0) {
            $redir = home_url($redir);
        }

        // Sjekk at domenet matcher
        $home_host  = wp_parse_url($home, PHP_URL_HOST);
        $redir_host = wp_parse_url($redir, PHP_URL_HOST);
        if (!$redir_host || strcasecmp($home_host, $redir_host) !== 0) {
            $redir = $home;
        }

        wp_safe_redirect($redir);
        exit;
    }

    public function maybe_enqueue_assets() {
        if (!is_singular()) return;
        $post = get_post(); if (!$post) return;

        $has_sc = has_shortcode($post->post_content, 'ffp_driver_portal') || is_page('driver-portal');
        if (!$has_sc) return;

        $js_path = FFP_DIR . 'assets/js/driver-portal.js';
        $ver     = file_exists($js_path) ? filemtime($js_path) : (defined('FFP_VERSION') ? FFP_VERSION : '1.0.0');

        wp_enqueue_script('ffp-driver-portal', FFP_URL . 'assets/js/driver-portal.js', ['jquery'], $ver, true);
        wp_localize_script('ffp-driver-portal', 'ffpDriver', [
            'restUrl' => esc_url_raw(rtrim(rest_url(), '/')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'userId'  => get_current_user_id(),
        ]);

        wp_enqueue_style('ffp-driver-portal', FFP_URL . 'assets/css/styles.css', [], $ver);
    }

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
