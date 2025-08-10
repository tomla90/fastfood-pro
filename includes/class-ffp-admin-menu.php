<?php
if (!defined('ABSPATH')) exit;

class FFP_Admin_Menu {
    public function __construct() {
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_enqueue_scripts', [$this,'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this,'enqueue_frontend_assets']);
    }

    public function menu() {
        // Hovedmeny
        add_menu_page(
            'FastFood Pro',
            'FastFood Pro',
            'manage_fastfood',
            'ffp-orders',
            [$this,'render_orders_page'],
            'dashicons-store',
            56
        );

        // Undermenyer
        add_submenu_page('ffp-orders', 'Live Bestillinger', 'Live Bestillinger', 'ffp_view_orders', 'ffp-orders', [$this,'render_orders_page']);
        add_submenu_page('ffp-orders', 'Sjåfører', 'Sjåfører', 'manage_fastfood', 'ffp-drivers', [$this,'render_drivers_page']);
        add_submenu_page('ffp-orders', 'Kunder', 'Kunder', 'manage_fastfood', 'ffp-customers', [$this,'render_customers_page']);
        add_submenu_page('ffp-orders', 'Rapporter', 'Rapporter', 'manage_fastfood', 'ffp-reports', [$this,'render_reports_page']);
        add_submenu_page('ffp-orders', 'Verktøy', 'Verktøy', 'manage_fastfood', 'ffp-tools', [$this,'render_tools_page']);
        add_submenu_page('ffp-orders', 'Innstillinger', 'Innstillinger', 'manage_fastfood', 'ffp-settings', [$this,'render_settings']);
        add_submenu_page('ffp-orders', 'Shortcodes', 'Shortcodes', 'manage_fastfood', 'ffp-shortcodes', [$this,'render_shortcodes']);
        add_submenu_page('ffp-orders', 'Lisens', 'Lisens', 'manage_fastfood', 'ffp-licensing', [$this,'render_licensing_page']);
    }

    public function enqueue_admin_assets($hook) {
        // Kun på innstillingssiden i admin
        if (!isset($_GET['page']) || $_GET['page'] !== 'ffp-settings') {
            return;
        }

        wp_enqueue_script(
            'ffp-settings',
            FFP_URL . 'assets/js/settings.js',
            ['jquery'],
            FFP_VERSION,
            true
        );

        wp_enqueue_style(
            'ffp-settings',
            FFP_URL . 'assets/css/settings.css',
            [],
            FFP_VERSION
        );
    }

    public function enqueue_frontend_assets() {
        // Hvis du bare skal laste settings.js på spesifikke sider i frontend,
        // kan du legge inn en betingelse her. Nå lastes den overalt i frontend.
        wp_enqueue_script(
            'ffp-settings',
            FFP_URL . 'assets/js/settings.js',
            ['jquery'],
            FFP_VERSION,
            true
        );
    }

    private function view($file) {
        $path = FFP_DIR . 'admin/views/' . $file;
        if (file_exists($path)) {
            include $path;
        } else {
            echo '<div class="wrap"><h1>Mangler view: '.esc_html($file).'</h1></div>';
        }
    }

    public function render_orders_page() {
        if (!current_user_can('ffp_view_orders')) wp_die('Ingen tilgang');
        $this->view('orders.php');
    }

    public function render_settings() {
        if (!current_user_can('manage_fastfood')) wp_die('Ingen tilgang');
        $this->view('settings.php');
    }

    public function render_shortcodes() {
        if (!current_user_can('manage_fastfood')) wp_die('Ingen tilgang');
        $this->view('shortcodes.php');
    }

    public function render_drivers_page() {
        if (!current_user_can('manage_fastfood')) wp_die('Ingen tilgang');
        $this->view('drivers.php');
    }

    public function render_customers_page() {
        if (!current_user_can('manage_fastfood')) wp_die('Ingen tilgang');
        $this->view('customers.php');
    }

    public function render_reports_page() {
        if (!current_user_can('manage_fastfood')) wp_die('Ingen tilgang');
        $this->view('reports.php');
    }

    public function render_tools_page() {
        if (!current_user_can('manage_fastfood')) wp_die('Ingen tilgang');
        $this->view('tools.php');
    }

    public function render_licensing_page() {
        if (!current_user_can('manage_fastfood')) wp_die('Ingen tilgang');
        $this->view('licensing.php');
    }
}
