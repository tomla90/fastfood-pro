<?php
if (!defined('ABSPATH')) exit;

class FFP_Statuses {
  public function __construct() {
    add_action('init', [$this, 'register']);
    add_filter('wc_order_statuses', [$this, 'labels']);
    add_filter('woocommerce_reports_order_statuses', [$this, 'reports']);
  }

  public function register() {
    // Preparing
    register_post_status('wc-ffp-preparing', [
      'label'                     => _x('Preparing', 'Order status', 'fastfood-pro'),
      'public'                    => true,
      'exclude_from_search'       => false,
      'show_in_admin_all_list'    => true,
      'show_in_admin_status_list' => true,
      'label_count'               => _n_noop('Preparing <span class="count">(%s)</span>', 'Preparing <span class="count">(%s)</span>', 'fastfood-pro'),
    ]);

    // Ready
    register_post_status('wc-ffp-ready', [
      'label'                     => _x('Ready', 'Order status', 'fastfood-pro'),
      'public'                    => true,
      'exclude_from_search'       => false,
      'show_in_admin_all_list'    => true,
      'show_in_admin_status_list' => true,
      'label_count'               => _n_noop('Ready <span class="count">(%s)</span>', 'Ready <span class="count">(%s)</span>', 'fastfood-pro'),
    ]);

    // Out for delivery
    register_post_status('wc-ffp-out-for-delivery', [
      'label'                     => _x('Out for delivery', 'Order status', 'fastfood-pro'),
      'public'                    => true,
      'exclude_from_search'       => false,
      'show_in_admin_all_list'    => true,
      'show_in_admin_status_list' => true,
      'label_count'               => _n_noop('Out for delivery <span class="count">(%s)</span>', 'Out for delivery <span class="count">(%s)</span>', 'fastfood-pro'),
    ]);
  }

  public function labels($statuses) {
    // Insert after processing
    $new = [];
    foreach ($statuses as $key => $label) {
      $new[$key] = $label;
      if ($key === 'wc-processing') {
        $new['wc-ffp-preparing']        = _x('Preparing', 'Order status', 'fastfood-pro');
        $new['wc-ffp-ready']            = _x('Ready', 'Order status', 'fastfood-pro');
        $new['wc-ffp-out-for-delivery'] = _x('Out for delivery', 'Order status', 'fastfood-pro');
      }
    }
    return $new;
  }

  // Count these as “paid/active” in reports if you want
  public function reports($st) {
    $st[] = 'ffp-preparing';
    $st[] = 'ffp-ready';
    $st[] = 'ffp-out-for-delivery';
    return $st;
  }
}
