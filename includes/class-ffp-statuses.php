<?php
if (!defined('ABSPATH')) exit;

class FFP_Statuses {

    public function __construct() {
        add_action('init', [$this, 'register_statuses']);
        add_filter('wc_order_statuses', [$this, 'add_to_list']);
    }

    public function register_statuses() {

        // Preparing
        register_post_status('wc-ffp-preparing', [
            'label'                     => _x('Preparing', 'Order status', 'fastfood-pro'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Preparing <span class="count">(%s)</span>', 'Preparing <span class="count">(%s)</span>')
        ]);

        // Ready
        register_post_status('wc-ffp-ready', [
            'label'                     => _x('Ready', 'Order status', 'fastfood-pro'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Ready <span class="count">(%s)</span>', 'Ready <span class="count">(%s)</span>')
        ]);

        // Delivery (renamet – tidligere Out for delivery)
        register_post_status('wc-ffp-delivery', [
            'label'                     => _x('Out for Delivery', 'Order status', 'fastfood-pro'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Out for Delivery <span class="count">(%s)</span>', 'Out for Delivery <span class="count">(%s)</span>')
        ]);
    }

    public function add_to_list($statuses) {
        $statuses['wc-ffp-preparing'] = _x('Preparing', 'Order status', 'fastfood-pro');
        $statuses['wc-ffp-ready']     = _x('Ready', 'Order status', 'fastfood-pro');
        $statuses['wc-ffp-delivery']  = _x('Out for Delivery', 'Order status', 'fastfood-pro');
        return $statuses;
    }
}
