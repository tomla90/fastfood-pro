<?php
if (!defined('ABSPATH')) exit;

class FFP_Admin_Menu {
    public function __construct() {
        add_action('admin_menu', [$this,'menu']);
    }

    public function menu() {
        add_menu_page(
            'FastFood Pro', 'FastFood Pro',
            'manage_fastfood', 'ffp-orders',
            [$this,'render_orders_page'], 'dashicons-food', 56
        );
        add_submenu_page('ffp-orders','Innstillinger','Innstillinger','manage_fastfood','ffp-settings',[$this,'render_settings']);
        add_submenu_page('ffp-orders','Shortcodes','Shortcodes','manage_fastfood','ffp-shortcodes',[$this,'render_shortcodes']);
    }

    public function render_orders_page() {
        if (!current_user_can('ffp_view_orders')) wp_die('Ingen tilgang');
        echo '<div class="wrap"><h1>Live Bestillinger</h1>
        <div id="ffp-orders-app" class="ffp-card">
          <p>Laster inn ordre... (live)</p>
        </div></div>';
    }

    public function render_settings() {
        if (!current_user_can('manage_fastfood')) wp_die('Ingen tilgang');
        echo '<div class="wrap"><h1>Innstillinger</h1>
        <form method="post" action="options.php">';
        settings_fields('ffp_settings_group');
        do_settings_sections('ffp_settings');
        submit_button();
        echo '</form></div>';
    }

    public function render_shortcodes() {
        echo '<div class="wrap"><h1>Tilgjengelige Shortcodes</h1>
        <table class="widefat striped"><thead><tr><th>Shortcode</th><th>Beskrivelse</th></tr></thead><tbody>
        <tr><td>[fastfood_summary]</td><td>Sammendrag (pickup/levering), tid, notat.</td></tr>
        <tr><td>[fastfood_tip_option]</td><td>Viser tipsvalg ved checkout.</td></tr>
        <tr><td>[fastfood_delivery_fee]</td><td>Viser/dynamisk leveringsgebyr.</td></tr>
        <tr><td>[track_order order_id="1234"]</td><td>Kundens sporing + kart (adresse).</td></tr>
        <tr><td>[ffp_driver_portal]</td><td>Driver-portal for å se/ta leveringer og oppdatere status.</td></tr>
        <tr><td>[ffp_login]</td><td>Frontend login for kunde/ansatt/driver.</td></tr>
        </tbody></table></div>';
    }
}
