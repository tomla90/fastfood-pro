<?php
/**
 * FastFood Pro – Settings screen
 */
if (!defined('ABSPATH')) exit;

class FFP_Settings {
    private $option = 'ffp_settings';

    // Definer alle felt ett sted (id, label, type, choices)
    private function fields() {
        $fields = [
            ['store_mode',       'Butikkmodus',                        'select',   ['takeaway'=>'Takeaway','delivery'=>'Delivery','both'=>'Begge']],
            ['order_sound',      'Lyd ved ny ordre (admin)',           'checkbox'],
            ['onesignal_app_id', 'OneSignal App ID (valgfritt)',       'text'],
            ['onesignal_rest_key','OneSignal REST API Key (valgfritt)','text'],
            ['license_server',   'Lisensserver URL',                   'text'],
            ['update_endpoint',  'Oppdaterings-endpoint (relativ)',    'text'],
            ['license_key',      'Lisensnøkkel',                       'text'],
            ['store_address',    'Butikkadresse (for distanse)',       'text'],
            ['map_provider',     'Kartleverandør',                     'select',   ['mapbox'=>'Mapbox','google'=>'Google']],
            ['mapbox_token',     'Mapbox Access Token',                'text'],
            ['google_api_key',   'Google API Key (Geocoding)',         'text'],
            ['enable_sse',       'Live via SSE (anbefalt)',            'checkbox'],
        ];

        // Ekstra felter
        $fields = array_merge($fields, [
            ['pricing_formula','Prisformel (JSON)'],
            ['zones_json','Soner (JSON)'],
            ['email_to','Varsel e-post til (kommaseparert)'],
            ['email_subject_new','Emne: ny ordre'],
            ['email_body_new','E-postmal: ny ordre'],
            ['email_subject_status','Emne: statusendring'],
            ['email_body_status','E-postmal: statusendring'],
            ['webhook_url','Webhook URL (POST JSON)'],
            ['enable_pwa','Aktiver PWA','checkbox'],
        ]);

        return $fields;
    }

    public function __construct() {
        add_action('admin_menu',  [$this,'menu']);
        add_action('admin_init',  [$this,'register']);
    }

    /** Legg inn meny under WooCommerce (faller tilbake til Innstillinger hvis Woo mangler) */
    public function menu() {
        if (class_exists('WooCommerce')) {
            add_submenu_page(
                'woocommerce',
                'FastFood Pro – Innstillinger',
                'FastFood Pro',
                'manage_options',
                'ffp_settings',
                [$this,'render_page']
            );
        } else {
            add_options_page(
                'FastFood Pro – Innstillinger',
                'FastFood Pro',
                'manage_options',
                'ffp_settings',
                [$this,'render_page']
            );
        }
    }

    /** Registrer setting + seksjon + alle feltene */
    public function register() {
        register_setting('ffp_settings_group', $this->option, [$this,'sanitize']);
        add_settings_section('ffp_main', 'FastFood Pro – Innstillinger', '__return_false', 'ffp_settings');

        foreach ($this->fields() as $f) {
            add_settings_field(
                $f[0],
                esc_html($f[1]),
                [$this,'render_field'],
                'ffp_settings',
                'ffp_main',
                ['id'=>$f[0], 'type'=>$f[2] ?? 'text', 'choices'=>$f[3] ?? []]
            );
        }
    }

    /** Saniter og valider alle felter før lagring */
    public function sanitize($input) {
        $out = get_option($this->option, []);

        // Defaults
        $defs = [
            'store_mode'       => 'both',
            'order_sound'      => false,
            'onesignal_app_id' => '',
            'onesignal_rest_key' => '',
            'license_server'   => '',
            'update_endpoint'  => '',
            'license_key'      => '',
            'store_address'    => '',
            'map_provider'     => 'mapbox',
            'mapbox_token'     => '',
            'google_api_key'   => '',
            'enable_sse'       => true,
            'enable_pwa'       => false,
            'pricing_formula'  => ['base'=>35,'per_km'=>8,'min'=>39,'max'=>199],
            'zones_json'       => '',
            'email_to'         => '',
            'email_subject_new'=> '',
            'email_body_new'   => '',
            'email_subject_status' => '',
            'email_body_status'=> '',
            'webhook_url'      => '',
        ];
        foreach ($defs as $k => $def) {
            if (!isset($out[$k])) $out[$k] = $def;
        }

        // Feltdefinisjoner
        $field_map = [];
        foreach ($this->fields() as $f) {
            $field_map[$f[0]] = ['type'=>$f[2] ?? 'text', 'choices'=>$f[3] ?? []];
        }

        foreach ((array)$input as $k => $v) {
            if (!isset($field_map[$k])) continue;
            $type = $field_map[$k]['type'];

            switch ($type) {
                case 'checkbox':
                    $out[$k] = !empty($v) ? true : false;
                    break;

                case 'select':
                    $choices = array_keys($field_map[$k]['choices']);
                    $v = is_string($v) ? sanitize_text_field($v) : '';
                    $out[$k] = in_array($v, $choices, true) ? $v : $out[$k];
                    break;

                case 'text':
                default:
                    if ($k === 'license_server' || $k === 'webhook_url') {
                        $out[$k] = esc_url_raw($v);
                    } else {
                        $out[$k] = is_string($v) ? sanitize_text_field($v) : $out[$k];
                    }
                    break;
            }
        }

        // Spesial: enable_sse & enable_pwa
        $out['enable_sse'] = !empty($input['enable_sse']);
        $out['enable_pwa'] = !empty($input['enable_pwa']);

        // Prisformel JSON eller array
        if (empty($input['pricing_formula'])) {
            $out['pricing_formula'] = ['base'=>35,'per_km'=>8,'min'=>39,'max'=>199];
        } else {
            $pf = $input['pricing_formula'];
            if (is_string($pf)) { $pf = json_decode(wp_unslash($pf), true); }
            if (is_array($pf)) $out['pricing_formula'] = $pf;
        }

        // Valider JSON soner
        if (!empty($input['zones_json'])) {
            $z = json_decode(wp_unslash($input['zones_json']), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $out['zones_json'] = wp_unslash($input['zones_json']);
            }
        }

        // Autogeokod butikkadresse hvis endret
        $old = get_option($this->option, []);
        if (($old['store_address'] ?? '') !== ($input['store_address'] ?? '')) {
            $geo = FFP_Geo::geocode(sanitize_text_field($input['store_address']));
            if ($geo) {
                $out['store_lat'] = $geo['lat'];
                $out['store_lng'] = $geo['lng'];
            }
        }

        return $out;
    }

    /** Render enkeltfelt basert på type */
    public function render_field($args) {
        $opts    = get_option($this->option, []);
        $id      = esc_attr($args['id']);
        $type    = $args['type'] ?? 'text';
        $choices = $args['choices'] ?? [];
        $val     = $opts[$id] ?? '';

        if (in_array($id, ['email_body_new','email_body_status'])) {
            echo '<textarea class="large-text" rows="6" name="ffp_settings['.$id.']">'.esc_textarea($val).'</textarea>
            <p class="description">Placeholders: {order_id} {status} {total} {customer} {address} {driver}</p>';

        } elseif ($id === 'pricing_formula' || $id === 'zones_json') {
            $pretty = is_string($val) ? $val : wp_json_encode($val, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            echo '<textarea class="large-text code" rows="8" name="ffp_settings['.$id.']">'.esc_textarea($pretty).'</textarea>';
            if ($id === 'pricing_formula') {
                echo '<p class="description">Eksempel: {"base":35,"per_km":8,"min":39,"max":199}</p>';
            } else {
                echo '<p class="description">Eksempel sone: [{"name":"Sentrum","postcode_regex":"^40(\\d{2})$","base":29,"per_km":6,"min":39,"max":149}]</p>';
            }

        } elseif (in_array($id, ['enable_pwa','enable_sse','order_sound'])) {
            echo '<label><input type="checkbox" name="ffp_settings['.$id.']" value="1" '.checked($val, true, false).'> Aktiv</label>';

        } elseif ($type === 'select') {
            echo '<select name="ffp_settings['.$id.']">';
            foreach ($choices as $key => $label) {
                echo '<option value="'.esc_attr($key).'" '.selected($val, $key, false).'>'.esc_html($label).'</option>';
            }
            echo '</select>';

        } else {
            echo '<input type="text" class="regular-text" name="ffp_settings['.$id.']" value="'.esc_attr(is_array($val) ? wp_json_encode($val) : $val).'">';
        }
    }

    /** Selve innstillingssiden */
    public function render_page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1>FastFood Pro – Innstillinger</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('ffp_settings_group');
                do_settings_sections('ffp_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
