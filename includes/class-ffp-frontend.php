<?php
if (!defined('ABSPATH')) exit;

class FFP_Frontend {
    public function __construct() {
        add_shortcode('ffp_driver_portal', [$this,'driver_portal']);
        add_shortcode('ffp_login', [$this,'login_form']);
        add_action('init', [$this,'handle_login']);
        add_action('wp_logout', function(){ wp_safe_redirect(home_url()); exit; });
    }

    public function driver_portal() {
        if (!is_user_logged_in() || !current_user_can('ffp_driver')) {
            return '<div class="ffp-card"><p>Logg inn som sjåfør for å se leveringer.</p>[ffp_login]</div>';
        }
        ob_start(); ?>
        <div id="ffp-driver-app" class="ffp-card">
            <h3>Leveringer</h3>
            <div class="ffp-driver-actions">
                <button class="button" id="ffp-refresh">Oppdater</button>
                <a class="button" href="<?php echo esc_url(wp_logout_url(get_permalink())); ?>">Logg ut</a>
            </div>
            <div id="ffp-driver-list">Laster...</div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function login_form() {
        if (is_user_logged_in()) return '<p>Allerede innlogget.</p>';
        $out = '<form method="post" class="ffp-login"><h3>Logg inn</h3>'.
            wp_nonce_field('ffp_login','ffp_login_nonce', true, false).
            '<p><label>Brukernavn/E-post</label><input type="text" name="log" required></p>
             <p><label>Passord</label><input type="password" name="pwd" required></p>
             <p><button class="button button-primary" type="submit" name="ffp_do_login" value="1">Logg inn</button></p></form>';
        return $out;
    }

    public function handle_login() {
        if (!isset($_POST['ffp_do_login'])) return;
        if (!isset($_POST['ffp_login_nonce']) || !wp_verify_nonce($_POST['ffp_login_nonce'],'ffp_login')) return;

        $creds = [
            'user_login' => sanitize_text_field($_POST['log']),
            'user_password' => $_POST['pwd'],
            'remember' => true
        ];
        $user = wp_signon($creds, false);
        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg('ffp_login','fail', wp_get_referer() ?: home_url()));
            exit;
        }
        wp_safe_redirect(remove_query_arg('ffp_login', wp_get_referer() ?: home_url()));
        exit;
    }
}
