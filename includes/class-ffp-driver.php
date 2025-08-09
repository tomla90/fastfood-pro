<?php
if (!defined('ABSPATH')) exit;

/**
 * Driver-modul: auto-registrering (valgfritt), claim ordre, oppdater status.
 */
class FFP_Driver {
    public function __construct() {
        // Valgfri auto-registrering via skjult endpoint kunne lages – her holder vi oss til admin som oppretter brukere med rolle "driver".
        add_filter('user_has_cap', [$this,'grant_driver_caps'], 10, 3);
    }

    public function grant_driver_caps($allcaps, $caps, $args) {
        $user = get_user_by('id', $args[1] ?? 0);
        if (!$user) return $allcaps;
        if (in_array('driver', (array)$user->roles, true)) {
            $allcaps['ffp_driver'] = true;
            $allcaps['read'] = true;
        }
        return $allcaps;
    }
}
