<?php
if (!defined('ABSPATH')) exit;

class FFP_Driver {

    public function __construct() {
        // Shortcodes
        add_shortcode('ffp_driver_portal', [$this,'shortcode_portal']);
        add_shortcode('ffp_login',         [$this,'shortcode_login']);

        // Logout-knapp + lett-logout handler
        add_shortcode('ffp_logout_button', [$this,'shortcode_logout_button']);
        add_action('template_redirect',    [$this,'maybe_do_button_logout']); // ?ffp_btn_logout=1

        // Assets + admin
        add_action('wp_enqueue_scripts',   [$this,'maybe_enqueue_assets']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this,'admin_driver_info']);
    }

    /** Hjelper: er innlogget bruker sjåfør? */
    private function is_driver_role(): bool {
        if (!is_user_logged_in()) return false;
        $u = wp_get_current_user();
        $roles = (array) ($u ? $u->roles : []);
        return in_array('driver', $roles, true) || in_array('ffp_driver', $roles, true);
    }

    public function shortcode_portal() {
        // Stram inn slik at bare sjåfører kan se portalen
        if (!$this->is_driver_role()) {
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

    /** [ffp_logout_button redirect="/driver-portal/" label="Logg ut" class="button"] */
    public function shortcode_logout_button($atts = []) {
        if (!is_user_logged_in()) return '';
        $a = shortcode_atts([
            'redirect' => '/driver-portal/',
            'label'    => __('Logg ut', 'fastfood-pro'),
            'class'    => 'button',
        ], $atts);

        $target = (strpos($a['redirect'], 'http') === 0) ? $a['redirect'] : home_url($a['redirect']);

        $url = add_query_arg([
            'ffp_btn_logout' => 1,
            'redirect_to'    => $target,
        ], home_url('/'));

        return sprintf('<a href="%s" class="%s">%s</a>', esc_url($url), esc_attr($a['class']), esc_html($a['label']));
    }

    /** Lett logout: omgår wp_logout-hooks som kan hijacke redirect */
    public function maybe_do_button_logout() {
        if (!isset($_GET['ffp_btn_logout']) || (int) $_GET['ffp_btn_logout'] !== 1) return;

        if (is_user_logged_in()) {
            if (function_exists('wp_destroy_current_session')) wp_destroy_current_session();
            wp_clear_auth_cookie();
            wp_set_current_user(0);
        }

        $home  = home_url('/');
        $redir = isset($_GET['redirect_to']) ? wp_unslash($_GET['redirect_to']) : $home;

        if (strpos($redir, '/') === 0) $redir = home_url($redir);
        $home_host  = wp_parse_url($home, PHP_URL_HOST);
        $redir_host = wp_parse_url($redir, PHP_URL_HOST);
        if (!$redir_host || strcasecmp($home_host, $redir_host) !== 0) $redir = $home;

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
