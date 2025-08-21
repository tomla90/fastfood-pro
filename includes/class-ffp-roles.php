<?php
if (!defined('ABSPATH')) exit;

class FFP_Roles {

    const OPT_ROLES_VERSION = 'ffp_roles_version';
    const VERSION = 2; // øk ved endringer for å tvinge ensure() til å kjøre

    public function __construct() {
        add_action('init', [$this, 'ensure'], 5);
    }

    /** Opprett/oppdater roller og caps */
    public function ensure() {
        $stored = (int) get_option(self::OPT_ROLES_VERSION, 0);
        if ($stored >= self::VERSION) return;

        // Våre egendefinerte caps
        $caps_driver   = ['read' => true, 'ffp_view_orders' => true];
        $caps_kitchen  = ['read' => true, 'ffp_view_orders' => true, 'ffp_update_orders' => true];
        $caps_staff    = ['read' => true, 'ffp_view_orders' => true, 'ffp_update_orders' => true];
        $caps_manager  = [
            'read'                => true,
            'ffp_view_orders'     => true,
            'ffp_update_orders'   => true,
            'ffp_assign_driver'   => true,
            'ffp_manage_settings' => true,
        ];

        // Opprett (eller oppdater) roller
        $this->upsert_role('driver',      __('Sjåfør', 'fastfood-pro'),          $caps_driver);
        $this->upsert_role('kitchen',     __('Kjøkken', 'fastfood-pro'),         $caps_kitchen);
        $this->upsert_role('ffp_staff',   __('Ansatt', 'fastfood-pro'),          $caps_staff);
        $this->upsert_role('ffp_manager', __('Fastfood Manager', 'fastfood-pro'),$caps_manager);

        // Gi Shop manager våre viktige caps automatisk
        if ($r = get_role('shop_manager')) {
            $r->add_cap('ffp_view_orders');
            $r->add_cap('ffp_update_orders');
        }

        // Administrator skal ALLTID ha alle våre caps
        if ($r = get_role('administrator')) {
            foreach (['ffp_view_orders','ffp_update_orders','ffp_assign_driver','ffp_manage_settings'] as $cap) {
                $r->add_cap($cap);
            }
            // sikre 'read' også (burde være der uansett)
            $r->add_cap('read');
        }

        update_option(self::OPT_ROLES_VERSION, self::VERSION);
    }

    /** Opprett rolle hvis den ikke finnes, og sikre caps hvis den finnes */
    private function upsert_role(string $slug, string $label, array $caps) {
        // Sørg for at 'read' alltid er med
        if (!isset($caps['read'])) $caps['read'] = true;

        if (!get_role($slug)) {
            add_role($slug, $label, $caps);
            return;
        }

        // Rolle finnes – sørg for at caps er på plass
        $role = get_role($slug);
        if ($role) {
            $role->add_cap('read');
            foreach ($caps as $cap => $grant) {
                if ($grant) $role->add_cap($cap);
                else        $role->remove_cap($cap);
            }
        }
    }
}
