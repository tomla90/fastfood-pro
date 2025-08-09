<?php
if (!defined('ABSPATH')) exit;

class FFP_Settings {
    private $option = 'ffp_settings';

    public function __construct() {
        add_action('admin_init', [$this,'register']);
    }

    public function register() {
        register_setting('ffp_settings_group', $this->option, [$this,'sanitize']);

        add_settings_section('ffp_main', 'FastFood Pro – Innstillinger', '__return_false', 'ffp_settings');

        $fields = [
            ['store_mode','Butikkmodus (takeaway/delivery/both)'],
            ['order_sound','Lyd ved ny ordre (admin)'],
            ['onesignal_app_id','OneSignal App ID (valgfritt)'],
            ['onesignal_rest_key','OneSignal REST API Key (valgfritt)'],
            ['license_server','Lisensserver URL'],
            ['update_endpoint','Oppdaterings‑endpoint (relativ)'],
            ['license_key','Lisensnøkkel'],
        ];
        foreach ($fields as $f) {
            add_settings_field($f[0], $f[1], [$this,'render_field'], 'ffp_settings', 'ffp_main', ['id'=>$f[0]]);
        }
    }

    public function sanitize($input) {
        $out = get_option($this->option, []);
        foreach ($input as $k=>$v) $out[$k] = is_string($v) ? sanitize_text_field($v) : $v;
        $out['order_sound'] = !empty($input['order_sound']);
        return $out;
    }

    public function render_field($args) {
        $opts = get_option($this->option, []);
        $id = esc_attr($args['id']);
        $val = $opts[$id] ?? '';
        if ($id === 'order_sound') {
            echo '<label><input type="checkbox" name="ffp_settings[order_sound]" value="1" '.checked($val, true, false).'> Aktiv</label>';
        } else {
            echo '<input type="text" class="regular-text" name="ffp_settings['.$id.']" value="'.esc_attr($val).'">';
        }
    }
}
