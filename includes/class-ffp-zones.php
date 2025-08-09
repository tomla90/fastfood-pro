<?php
if (!defined('ABSPATH')) exit;

/**
 * Sonepris og global prisformel.
 * Lagres i option "ffp_settings":
 *  - pricing_formula: ['base'=>35,'per_km'=>8,'min'=>39,'max'=>199]
 *  - zones_json: JSON array [{"name":"Sentrum","postcode_regex":"^40(\\d{2})$","base":29,"per_km":6,"min":39,"max":149}]
 */
class FFP_Zones {
    public static function match_zone_by_postcode($postcode) {
        $s = get_option('ffp_settings', []);
        $zones = !empty($s['zones_json']) ? json_decode($s['zones_json'], true) : [];
        if (!is_array($zones)) return null;
        foreach ($zones as $z) {
            $re = $z['postcode_regex'] ?? '';
            if (!$re) continue;
            if (@preg_match('/'.$re.'/', (string)$postcode)) return $z;
        }
        return null;
    }

    public static function calc_fee($km, $postcode = '') {
        $s = get_option('ffp_settings', []);
        $formula = $s['pricing_formula'] ?? ['base'=>35,'per_km'=>8,'min'=>39,'max'=>199];

        // Hvis sone matcher, bruk sone-verdier
        $zone = $postcode ? self::match_zone_by_postcode($postcode) : null;
        if ($zone) $formula = [
            'base'=>floatval($zone['base'] ?? $formula['base']),
            'per_km'=>floatval($zone['per_km'] ?? $formula['per_km']),
            'min'=>floatval($zone['min'] ?? $formula['min']),
            'max'=>floatval($zone['max'] ?? $formula['max']),
        ];

        $fee = floatval($formula['base']) + (floatval($formula['per_km']) * ceil(max(0,$km)));
        $fee = max(floatval($formula['min']), min(floatval($formula['max']), $fee));
        return round($fee, 2);
    }
}
