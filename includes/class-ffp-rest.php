<?php
if (!defined('ABSPATH')) exit;

class FFP_REST {

  /** Vakt mot trash ved statusendring */
  private static $guarding = false;

  public function __construct() {
    add_action('rest_api_init', [$this, 'routes']);
  }

  private static function is_driver_role(): bool {
    $u = wp_get_current_user();
    $roles = (array) ($u ? $u->roles : []);
    return is_user_logged_in() && (in_array('driver', $roles, true) || in_array('ffp_driver', $roles, true));
  }

  public static function no_trash_guard($trash, $post) {
    if (self::$guarding && $post && $post->post_type === 'shop_order') return false;
    return $trash;
  }

  public function routes() {

    // GET /ffp/v1/orders
    register_rest_route('ffp/v1', '/orders', [
      'methods'  => 'GET',
      'permission_callback' => function () {
        return is_user_logged_in() && (
          current_user_can('manage_options') ||
          current_user_can('manage_woocommerce') ||
          current_user_can('ffp_view_orders') ||
          self::is_driver_role()
        );
      },
      'args' => [
        'status' => [
          'type' => 'string','required' => false,
          'sanitize_callback' => function($v){
            return implode(',', array_map('sanitize_key', array_filter(array_map('trim', explode(',', (string)$v)))));
          }
        ],
        'limit' => [
          'type' => 'integer','required' => false,
          'validate_callback' => function($v){ $v=(int)$v; return $v>0 && $v<=100; },
        ],
      ],
      'callback' => function (WP_REST_Request $req) {
        $status = $req->get_param('status');

        // Sjåfører skal ALDRI få annet enn Ready + Delivery
        if (self::is_driver_role()) {
          $status = 'ffp-ready,ffp-delivery';
        }

        return rest_ensure_response( FFP_Orders::get_open_orders([
          'status' => $status,
          'limit'  => $req->get_param('limit'),
        ]) );
      }
    ]);

    // POST /ffp/v1/orders/{id}/status
    register_rest_route('ffp/v1', '/orders/(?P<id>\d+)/status', [
      'methods'  => 'POST',
      'permission_callback' => function () {
        return is_user_logged_in() && (
          current_user_can('manage_options') ||
          current_user_can('manage_woocommerce') ||
          current_user_can('ffp_update_orders') ||
          self::is_driver_role()
        );
      },
      'callback' => [$this, 'update_status']
    ]);

    // POST /ffp/v1/orders/{id}/claim
    register_rest_route('ffp/v1', '/orders/(?P<id>\d+)/claim', [
      'methods'  => 'POST',
      'permission_callback' => function () {
        return is_user_logged_in() && (
          current_user_can('manage_options') ||
          current_user_can('manage_woocommerce') ||
          self::is_driver_role()
        );
      },
      'callback' => [$this, 'claim_order']
    ]);
  }

  public function update_status(WP_REST_Request $req) {
    $id = (int) $req['id'];
    $order = wc_get_order($id);
    if (!$order) return new WP_Error('ffp_not_found', 'Order not found', ['status'=>404]);

    $status  = sanitize_key($req->get_param('status'));
    $allowed = ['ffp-preparing','ffp-ready','ffp-delivery','completed','processing','on-hold'];
    if (!in_array($status, $allowed, true)) {
      return new WP_Error('ffp_bad_status', 'Invalid status', ['status'=>400]);
    }

    if (get_post_status($id) === 'trash') {
      wp_untrash_post($id);
      clean_post_cache($id);
      $order = wc_get_order($id);
    }

    self::$guarding = true;
    add_filter('pre_trash_post', [__CLASS__, 'no_trash_guard'], 10, 2);

    if ($order->get_status() !== $status) {
      $order->set_status($status);
      $order->save();
    }

    remove_filter('pre_trash_post', [__CLASS__, 'no_trash_guard'], 10);
    self::$guarding = false;

    if (get_post_status($id) === 'trash') {
      wp_untrash_post($id);
      clean_post_cache($id);
      $order = wc_get_order($id);
      $order->set_status($status);
      $order->save();
    }

    return ['ok'=>true, 'id'=>$id, 'status'=>$status];
  }

  public function claim_order(WP_REST_Request $req) {
    $id = (int) $req['id'];
    $order = wc_get_order($id);
    if (!$order) return new WP_Error('ffp_not_found', 'Order not found', ['status'=>404]);

    $uid = get_current_user_id();
    if (!$uid) return new WP_Error('ffp_auth', 'Login required', ['status'=>403]);

    $existing = (int) $order->get_meta('_ffp_driver_id', true);
    if ($existing && $existing !== $uid && !current_user_can('ffp_update_orders') && !current_user_can('manage_woocommerce')) {
      return new WP_Error('ffp_taken', 'Already assigned', ['status'=>409]);
    }

    $order->update_meta_data('_ffp_driver_id', $uid);
    $order->save();

    return ['ok'=>true, 'id'=>$id, 'driver'=>$uid];
  }
}
