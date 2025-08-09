<?php
if (!defined('ABSPATH')) exit;

/**
 * Enkel lisensklient + custom updater mot din backend.
 * Forhindrer deaktivering ved oppdatering: vi endrer ikke plugin-slug.
 */
class FFP_Licensing_Client {
    private $settings;

    public function __construct() {
        $this->settings = get_option('ffp_settings', []);
        add_filter('pre_set_site_transient_update_plugins', [$this,'check_for_updates']);
        add_filter('plugins_api', [$this,'plugins_api'], 10, 3);
    }

    private function server_url($path='') {
        $base = rtrim($this->settings['license_server'] ?? '', '/');
        return $base . $path;
    }

    public function check_for_updates($transient) {
        // === DEV BYPASS START (SLETT denne blokken når lisens skal være på) ===
        // Hvis du vil tvinge bypass uansett, sett konstanten i fastfood-pro.php:
        // define('FFP_DEV_LICENSE_BYPASS', true);
        if (
            (defined('FFP_DEV_LICENSE_BYPASS') && FFP_DEV_LICENSE_BYPASS)
            || empty($this->settings['license_server'])
            || empty($this->settings['license_key'])
        ) {
            return $transient; // hopper over lisens/oppdateringssjekk i DEV
        }
        // === DEV BYPASS END ===

        if (empty($transient->checked)) return $transient;
        $license  = $this->settings['license_key'] ?? '';
        $endpoint = $this->settings['update_endpoint'] ?? '/wp-json/ffpls/v1/update';

        $url = $this->server_url($endpoint);
        if (!$url) return $transient;

        $body = wp_remote_post($url, [
            'timeout' => 12,
            'body' => [
                'slug'        => 'fastfood-pro/fastfood-pro.php',
                'version'     => FFP_VERSION,
                'license_key' => $license,
                'site_url'    => home_url(),
            ]
        ]);

        if (is_wp_error($body)) return $transient;
        $data = json_decode(wp_remote_retrieve_body($body), true);
        if (!is_array($data) || empty($data['new_version'])) return $transient;

        if (version_compare(FFP_VERSION, $data['new_version'], '<')) {
            $obj = (object)[
                'slug'       => 'fastfood-pro',
                'plugin'     => 'fastfood-pro/fastfood-pro.php',
                'new_version'=> $data['new_version'],
                'url'        => $data['homepage'] ?? '',
                'package'    => $data['package'] ?? '', // zip URL (server validerer lisens)
                'tested'     => $data['tested'] ?? '',
                'requires'   => $data['requires'] ?? '',
            ];
            $transient->response['fastfood-pro/fastfood-pro.php'] = $obj;
        }
        return $transient;
    }

    public function plugins_api($result, $action, $args) {
        // === DEV BYPASS START (SLETT denne blokken når lisens skal være på) ===
        if (
            (defined('FFP_DEV_LICENSE_BYPASS') && FFP_DEV_LICENSE_BYPASS)
            || empty($this->settings['license_server'])
            || empty($this->settings['license_key'])
        ) {
            return $result; // hopper over plugin_information i DEV
        }
        // === DEV BYPASS END ===

        if ($action !== 'plugin_information' || ($args->slug ?? '') !== 'fastfood-pro') return $result;

        $endpoint = $this->settings['update_endpoint'] ?? '/wp-json/ffpls/v1/update';
        $url      = $this->server_url($endpoint);
        $license  = $this->settings['license_key'] ?? '';

        $res = wp_remote_post($url, [
            'timeout'=>12,
            'body'=>[
                'slug'        => 'fastfood-pro/fastfood-pro.php',
                'info'        => 1,
                'license_key' => $license,
                'site_url'    => home_url(),
            ]
        ]);
        if (is_wp_error($res)) return $result;

        $data = json_decode(wp_remote_retrieve_body($res), true);
        if (!$data) return $result;

        $obj = new stdClass();
        $obj->name     = 'FastFood Pro';
        $obj->slug     = 'fastfood-pro';
        $obj->version  = $data['new_version'] ?? FFP_VERSION;
        $obj->author   = '<a href="https://granbergdigital.no">Granberg Digital</a>';
        $obj->homepage = $data['homepage'] ?? '';
        $obj->sections = [
            'description' => $data['description'] ?? 'FastFood Pro beskrivelser.',
            'changelog'   => $data['changelog'] ?? 'Ingen changelog.'
        ];
        $obj->download_link = $data['package'] ?? '';
        return $obj;
    }
}
