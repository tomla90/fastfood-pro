<?php
if ( ! defined('ABSPATH') ) exit;

$settings = get_option('ffp_settings', [
    'zone_name'   => [],
    'zone_regex'  => [],
    'zone_base'   => [],
    'zone_per_km' => [],
    'zone_min'    => [],
    'zone_max'    => [],
]);

?>
<div class="wrap">
    <h1><?php esc_html_e('FastFood Pro – Innstillinger', 'fastfood-pro'); ?></h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('ffp_settings_group');
        do_settings_sections('ffp_settings_group');
        ?>

        <h2><?php esc_html_e('Leveringssoner', 'fastfood-pro'); ?></h2>
        <p><?php esc_html_e('Definer prisregler per sone. Regex brukes for å matche postnummer.', 'fastfood-pro'); ?></p>

        <table class="widefat fixed striped ffp-zones-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Navn', 'fastfood-pro'); ?></th>
                    <th><?php esc_html_e('Regex (Postnr)', 'fastfood-pro'); ?></th>
                    <th><?php esc_html_e('Basepris', 'fastfood-pro'); ?></th>
                    <th><?php esc_html_e('Pris/km', 'fastfood-pro'); ?></th>
                    <th><?php esc_html_e('Min', 'fastfood-pro'); ?></th>
                    <th><?php esc_html_e('Maks', 'fastfood-pro'); ?></th>
                    <th><?php esc_html_e('Handlinger', 'fastfood-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ( ! empty($settings['zone_name']) && is_array($settings['zone_name']) ) {
                    foreach ( $settings['zone_name'] as $i => $name ) {
                        $name   = esc_attr($name);
                        $regex  = esc_attr($settings['zone_regex'][$i] ?? '');
                        $base   = esc_attr($settings['zone_base'][$i] ?? '');
                        $per_km = esc_attr($settings['zone_per_km'][$i] ?? '');
                        $min    = esc_attr($settings['zone_min'][$i] ?? '');
                        $max    = esc_attr($settings['zone_max'][$i] ?? '');
                        ?>
                        <tr class="ffp-zone-row">
                            <td><input type="text" name="ffp_settings[zone_name][]" value="<?php echo $name; ?>" placeholder="Sentrum"></td>
                            <td>
                                <div class="ffp-zone-regex">
                                    <input type="text" name="ffp_settings[zone_regex][]" value="<?php echo $regex; ?>" placeholder="^40(\d{2})$">
                                    <input type="text" class="ffp-regex-test" placeholder="Test: 4020" title="Skriv postnummer for å teste regex">
                                    <span class="ffp-test-result" aria-live="polite"></span>
                                </div>
                            </td>
                            <td><input type="number" step="0.01" min="0" name="ffp_settings[zone_base][]" value="<?php echo $base; ?>" placeholder="29"></td>
                            <td><input type="number" step="0.01" min="0" name="ffp_settings[zone_per_km][]" value="<?php echo $per_km; ?>" placeholder="6"></td>
                            <td><input type="number" step="0.01" min="0" name="ffp_settings[zone_min][]" value="<?php echo $min; ?>" placeholder="39"></td>
                            <td><input type="number" step="0.01" min="0" name="ffp_settings[zone_max][]" value="<?php echo $max; ?>" placeholder="149"></td>
                            <td class="ffp-zone-actions">
                                <button type="button" class="button ffp-move-up" title="Flytt opp">↑</button>
                                <button type="button" class="button ffp-move-down" title="Flytt ned">↓</button>
                                <button type="button" class="button ffp-dup-zone" title="Dupliser">⎘</button>
                                <button type="button" class="button ffp-remove-zone" title="Fjern">✕</button>
                            </td>
                        </tr>
                        <?php
                    }
                }
                ?>
            </tbody>
        </table>

        <p>
            <button type="button" id="ffp-add-zone" class="button button-primary">
                <?php esc_html_e('Legg til ny sone', 'fastfood-pro'); ?>
            </button>
        </p>

        <?php submit_button(); ?>
    </form>
</div>
