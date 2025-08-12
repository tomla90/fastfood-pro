<?php
if (!defined('ABSPATH')) exit;

class FFP_Statuses {
  public function __construct() {
    add_action('init', [$this, 'register']); // WP-level
    add_filter('woocommerce_register_shop_order_post_statuses', [$this, 'wc_register']); // WC-level
    add_filter('wc_order_statuses', [$this, 'labels']); // dropdowns/filtre
    add_filter('woocommerce_reports_order_statuses', [$this, 'reports']); // rapporter (valgfritt)
  }

  public function register() {
    // WP post_status - nødvendig for at de skal eksistere
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

  // Woo ber eksplisitt om denne strukturen for shop_order
  public function wc_register($statuses) {
    $statuses['wc-ffp-preparing'] = [
      'label'                     => _x('Preparing', 'Order status', 'fastfood-pro'),
      'public'                    => true,
      'exclude_from_search'       => false,
      'show_in_admin_all_list'    => true,
      'show_in_admin_status_list' => true,
      'label_count'               => _n_noop('Preparing <span class="count">(%s)</span>', 'Preparing <span class="count">(%s)</span>', 'fastfood-pro'),
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

  // Legg statusene inn i lister/filtre (viser pene etiketter)
  public function labels($statuses) {
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

  // Ta dem med i rapporter (valgfritt)
  public function reports($st) {
    $st[] = 'ffp-preparing';
    $st[] = 'ffp-ready';
    $st[] = 'ffp-out-for-delivery';
    return $st;
  }
}
