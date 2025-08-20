<?php
if (!defined('ABSPATH')) exit;

class FFP_Plugin {
  private static $instance = null;

  public static function instance() {
    if (self::$instance === null) self::$instance = new self();
    return self::$instance;
  }

  private function __construct() {
    // Statusklasser må lastes tidlig
    new FFP_Statuses();

    // Moduler
    new FFP_Settings();
    new FFP_Admin_Menu();
    new FFP_Orders();
    new FFP_Product_Addons();
    new FFP_Checkout();
    new FFP_Frontend();
    new FFP_Driver();
    new FFP_REST();
    new FFP_Licensing_Client();
    new FFP_Notify();
    new FFP_Geo();
    new FFP_PWA();
    new FFP_SSE();
    new FFP_Zones();
    new FFP_Webhooks();

    add_action('admin_enqueue_scripts', [$this,'admin_assets']);
  }

  public function admin_assets($hook) {
    if (isset($_GET['page']) && $_GET['page'] === 'ffp-orders') {
      wp_enqueue_style('ffp-admin', FFP_URL.'assets/css/styles.css', [], FFP_VERSION);
      wp_enqueue_script('ffp-orders', FFP_URL.'assets/js/restaurant-orders.js', ['jquery','wp-api'], FFP_VERSION, true);

      $s = get_option('ffp_settings', []);
      wp_localize_script('ffp-orders', 'ffpOrders', [
        'nonce'    => wp_create_nonce('wp_rest'),
        'restUrl'  => esc_url_raw( rest_url() ),
        'sound'    => (bool) ($s['order_sound'] ?? true),
        'soundSrc' => esc_url_raw( $s['order_sound_src'] ?? '' ),
      ]);
    }
  }
}
