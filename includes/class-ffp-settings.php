<?php
if (!defined('ABSPATH')) exit;

class FFP_Settings {
    private $option = 'ffp_settings';

    private function fields() {
        return [
            ['store_mode',       'Butikkmodus', 'select', ['takeaway'=>'Takeaway','delivery'=>'Delivery','both'=>'Begge']],
            ['order_sound',      'Lyd ved ny ordre (admin)', 'checkbox'],
            ['order_sound_src',  'Lydfil for ny ordre', 'media'], // NY
            ['onesignal_app_id', 'OneSignal App ID (valgfritt)', 'text'],
            ['onesignal_rest_key','OneSignal REST API Key (valgfritt)', 'text'],
            ['license_server',   'Lisensserver URL', 'text'],
            ['update_endpoint',  'Oppdaterings-endpoint (relativ)', 'text'],
            ['license_key',      'Lisensnøkkel', 'text'],
            ['store_address',    'Butikkadresse (for distanse)', 'text'],
            ['map_provider',     'Kartleverandør', 'select', ['mapbox'=>'Mapbox','google'=>'Google']],
            ['mapbox_token',     'Mapbox Access Token', 'text'],
            ['google_api_key',   'Google API Key (Geocoding)', 'text'],
            ['enable_sse',       'Live via SSE (anbefalt)', 'checkbox'],
            ['enable_pwa',       'Aktiver PWA', 'checkbox'],
            ['pricing_formula',  'Prisformel', 'custom_price'],
            ['zones',            'Soner', 'custom_zones'],
            ['email_to',         'Varsel e-post til (kommaseparert)', 'text'],
            ['email_subject_new','Emne: ny ordre', 'text'],
            ['email_body_new',   'E-postmal: ny ordre', 'textarea'],
            ['email_subject_status','Emne: statusendring', 'text'],
            ['email_body_status','E-postmal: statusendring', 'textarea'],
            ['webhook_url',      'Webhook URL (POST JSON)', 'text'],
        ];
    }

    public function __construct() {
        add_action('admin_menu',  [$this,'menu']);
        add_action('admin_init',  [$this,'register']);
        add_action('admin_enqueue_scripts', [$this,'enqueue_assets']);
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'woocommerce_page_ffp_settings' && $hook !== 'settings_page_ffp_settings') return;

        wp_enqueue_script('ffp-settings-js', FFP_URL.'assets/js/settings.js', ['jquery'], FFP_VERSION, true);
        wp_enqueue_style('ffp-settings-css', FFP_URL.'assets/css/settings.css', [], FFP_VERSION);

        // For media-feltet
        wp_enqueue_media();
    }

    public function menu() {
        if (class_exists('WooCommerce')) {
            add_submenu_page('woocommerce', 'FastFood Pro – Innstillinger', 'FastFood Pro', 'manage_options', 'ffp_settings', [$this,'render_page']);
        } else {
            add_options_page('FastFood Pro – Innstillinger', 'FastFood Pro', 'manage_options', 'ffp_settings', [$this,'render_page']);
        }
    }

    public function register() {
        register_setting('ffp_settings_group', $this->option, [$this,'sanitize']);
        add_settings_section('ffp_main', '', '__return_false', 'ffp_settings');

        foreach ($this->fields() as $f) {
            add_settings_field($f[0], esc_html($f[1]), [$this,'render_field'], 'ffp_settings', 'ffp_main', [
                'id'=>$f[0],
                'type'=>$f[2] ?? 'text',
                'choices'=>$f[3] ?? []
            ]);
        }
    }

    public function sanitize($input) {
        $out = get_option($this->option, []);

        foreach ($this->fields() as $f) {
            $id = $f[0];
            $type = $f[2] ?? 'text';
            switch ($type) {
                case 'checkbox':
                    $out[$id] = !empty($input[$id]);
                    break;
                case 'select':
                    $choices = array_keys($f[3]);
                    $out[$id] = in_array($input[$id] ?? '', $choices, true) ? $input[$id] : ($out[$id] ?? '');
                    break;
                case 'custom_price':
                    $out['pricing_formula'] = [
                        'base'   => floatval($input['price_base'] ?? 0),
                        'per_km' => floatval($input['price_per_km'] ?? 0),
                        'min'    => floatval($input['price_min'] ?? 0),
                        'max'    => floatval($input['price_max'] ?? 0),
                    ];
                    break;
                case 'custom_zones':
                    $zones = [];
                    if (!empty($input['zone_name']) && is_array($input['zone_name'])) {
                        foreach ($input['zone_name'] as $i => $name) {
                            if (trim($name) === '') continue;
                            $zones[] = [
                                'name'           => sanitize_text_field($name),
                                'postcode_regex' => sanitize_text_field($input['zone_regex'][$i] ?? ''),
                                'base'           => floatval($input['zone_base'][$i] ?? 0),
                                'per_km'         => floatval($input['zone_per_km'][$i] ?? 0),
                                'min'            => floatval($input['zone_min'][$i] ?? 0),
                                'max'            => floatval($input['zone_max'][$i] ?? 0),
                            ];
                        }
                    }
                    $out['zones'] = $zones;
                    break;
                case 'media':
                    $out[$id] = esc_url_raw($input[$id] ?? '');
                    break;
                default:
                    $out[$id] = sanitize_text_field($input[$id] ?? '');
                    break;
            }
        }
        return $out;
    }

    public function render_field($args) {
        $opts = get_option($this->option, []);
        $id = $args['id'];
        $type = $args['type'];
        $choices = $args['choices'] ?? [];
        $val = $opts[$id] ?? '';

        switch ($type) {
            case 'checkbox':
                echo '<label><input type="checkbox" name="'.$this->option.'['.$id.']" value="1" '.checked($val, true, false).'> Aktiv</label>';
                break;
            case 'select':
                echo '<select name="'.$this->option.'['.$id.']">';
                foreach ($choices as $k => $label) {
                    echo '<option value="'.esc_attr($k).'" '.selected($val, $k, false).'>'.esc_html($label).'</option>';
                }
                echo '</select>';
                break;
            case 'textarea':
                echo '<textarea class="large-text" rows="4" name="'.$this->option.'['.$id.']">'.esc_textarea($val).'</textarea>';
                break;
            case 'custom_price':
                $pf = $opts['pricing_formula'] ?? ['base'=>35,'per_km'=>8,'min'=>39,'max'=>199];
                echo '<label>Basepris: <input type="number" step="0.01" name="'.$this->option.'[price_base]" value="'.esc_attr($pf['base']).'"></label> ';
                echo '<label>Pris/km: <input type="number" step="0.01" name="'.$this->option.'[price_per_km]" value="'.esc_attr($pf['per_km']).'"></label> ';
                echo '<label>Min: <input type="number" step="0.01" name="'.$this->option.'[price_min]" value="'.esc_attr($pf['min']).'"></label> ';
                echo '<label>Maks: <input type="number" step="0.01" name="'.$this->option.'[price_max]" value="'.esc_attr($pf['max']).'"></label>';
                break;
            case 'custom_zones':
                $zones = $opts['zones'] ?? [];
                echo '<table class="widefat ffp-zones-table"><thead><tr><th>Navn</th><th>Postnummer Regex</th><th>Base</th><th>Pris/km</th><th>Min</th><th>Maks</th><th></th></tr></thead><tbody>';
                if (!empty($zones)) {
                    foreach ($zones as $z) {
                        echo '<tr>
                            <td><input type="text" name="'.$this->option.'[zone_name][]" value="'.esc_attr($z['name']).'"></td>
                            <td><input type="text" name="'.$this->option.'[zone_regex][]" value="'.esc_attr($z['postcode_regex']).'"></td>
                            <td><input type="number" step="0.01" name="'.$this->option.'[zone_base][]" value="'.esc_attr($z['base']).'"></td>
                            <td><input type="number" step="0.01" name="'.$this->option.'[zone_per_km][]" value="'.esc_attr($z['per_km']).'"></td>
                            <td><input type="number" step="0.01" name="'.$this->option.'[zone_min][]" value="'.esc_attr($z['min']).'"></td>
                            <td><input type="number" step="0.01" name="'.$this->option.'[zone_max][]" value="'.esc_attr($z['max']).'"></td>
                            <td><button type="button" class="button ffp-remove-zone">X</button></td>
                        </tr>';
                    }
                }
                echo '</tbody></table>';
                echo '<p><button type="button" class="button" id="ffp-add-zone">+ Legg til sone</button></p>';
                break;
            case 'media':
                $url = esc_url($val ?: '');
                $fid = $this->option.'_'.$id;
                echo '<div class="ffp-media-field" id="'.esc_attr($fid).'">';
                echo '  <input type="text" class="regular-text ffp-media-url" name="'.$this->option.'['.$id.']" value="'.$url.'" placeholder="https://...">';
                echo '  <button type="button" class="button ffp-media-select">Velg fra media</button>';
                echo '</div>';
                break;
            default:
                echo '<input type="text" class="regular-text" name="'.$this->option.'['.$id.']" value="'.esc_attr($val).'">';
                break;
        }
    }

    public function render_page() {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>FastFood Pro – Innstillinger</h1><form method="post" action="options.php">';
        settings_fields('ffp_settings_group');
        do_settings_sections('ffp_settings');
        submit_button();
        echo '</form></div>';
    }
}
