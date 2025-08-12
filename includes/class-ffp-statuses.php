<?php
if (!defined('ABSPATH')) exit;

class FFP_Statuses {
  public function __construct() {
    add_action('init', [$this, 'register']); // WP-level post_status
    add_filter('woocommerce_register_shop_order_post_statuses', [$this, 'wc_register']); // Woo-level
    add_filter('wc_order_statuses', [$this, 'labels']); // pene etiketter i UI
    add_filter('woocommerce_reports_order_statuses', [$this, 'reports']); // ta med i rapporter (valgfritt)
  }

  public function register() {
    register_post_status('wc-ffp-preparing', [
      'label' => _x('Preparing', 'Order status', 'fastfood-pro'),
      'public' => true,
      'exclude_from_search' => false,
      'show_in_admin_all_list' => true,
      'show_in_admin_status_list' => true,
      'label_count' => _n_noop('Preparing <span class="count">(%s)</span>', 'Preparing <span class="count">(%s)</span>', 'fastfood-pro'),
    ]);
    register_post_status('wc-ffp-ready', [
      'label' => _x('Ready', 'Order status', 'fastfood-pro'),
      'public' => true,
      'exclude_from_search' => false,
      'show_in_admin_all_list' => true,
      'show_in_admin_status_list' => true,
      'label_count' => _n_noop('Ready <span class="count">(%s)</span>', 'Ready <span class="count">(%s)</span>', 'fastfood-pro'),
    ]);
    register_post_status('wc-ffp-out-for-delivery', [
      'label' => _x('Out for delivery', 'Order status', 'fastfood-pro'),
      'public' => true,
      'exclude_from_search' => false,
      'show_in_admin_all_list' => true,
      'show_in_admin_status_list' => true,
      'label_count' => _n_noop('Out for delivery <span class="count">(%s)</span>', 'Out for delivery <span class="count">(%s)</span>', 'fastfood-pro'),
    ]);
  }

  public function wc_register($statuses) {
    $statuses['wc-ffp-preparing'] = [
      'label' => _x('Preparing', 'Order status', 'fastfood-pro'),
      'public' => true,
      'exclude_from_search' => false,
      'show_in_admin_all_list' => true,
      'show_in_admin_status_list' => true,
      'label_count' => _n_noop('Preparing <span class="count">(%s)</span>', 'Preparing <span class="count">(%s)</span>', 'fastfood-pro'),
    ];
    $statuses['wc-ffp-ready'] = [
      'label' => _x('Ready', 'Order status', 'fastfood-pro'),
      'public' => true,
      'exclude_from_search' => false,
      'show_in_admin_all_list' => true,
      'show_in_admin_status_list' => true,
      'label_count' => _n_noop('Ready <span class="count">(%s)</span>', 'Ready <span class="count">(%s)</span>', 'fastfood-pro'),
    ];
    $statuses['wc-ffp-out-for-delivery'] = [
      'label' => _x('Out for delivery', 'Order status', 'fastfood-pro'),
      'public' => true,
      'exclude_from_search' => false,
      'show_in_admin_all_list' => true,
      'show_in_admin_status_list' => true,
      'label_count' => _n_noop('Out for delivery <span class="count">(%s)</span>', 'Out for delivery <span class="count">(%s)</span>', 'fastfood-pro'),
    ];
    return $statuses;
  }

  public function labels($statuses) {
    $out = [];
    foreach ($statuses as $key => $label) {
      $out[$key] = $label;
      if ($key === 'wc-processing') {
        $out['wc-ffp-preparing']        = _x('Preparing', 'Order status', 'fastfood-pro');
        $out['wc-ffp-ready']            = _x('Ready', 'Order status', 'fastfood-pro');
        $out['wc-ffp-out-for-delivery'] = _x('Out for delivery', 'Order status', 'fastfood-pro');
      }
    }
    return $out;
  }

  // Woo forventer slug uten "wc-" i rapportfilteret
  public function reports($st) {
    $st[] = 'ffp-preparing';
    $st[] = 'ffp-ready';
    $st[] = 'ffp-out-for-delivery';
    return $st;
  }
}
