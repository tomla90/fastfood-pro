<?php
if (!defined('ABSPATH')) exit;

/**
 * Geokoding + distanseberegning via Mapbox eller Google.
 * Returnerer distanse i kilometer (float) eller null ved feil.
 */
class FFP_Geo {
    public static function geocode($address) {
        $settings = get_option('ffp_settings', []);
        $provider = $settings['map_provider'] ?? 'mapbox'; // mapbox|google
        $address_q = rawurlencode($address);

        if ($provider === 'google') {
            $key = $settings['google_api_key'] ?? '';
            if (!$key) return null;
            $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address_q}&key={$key}";
            $res = wp_remote_get($url, ['timeout'=>12]);
            if (is_wp_error($res)) return null;
            $data = json_decode(wp_remote_retrieve_body($res), true);
            if (!$data || empty($data['results'][0]['geometry']['location'])) return null;
            $loc = $data['results'][0]['geometry']['location'];
            return ['lat'=>$loc['lat'],'lng'=>$loc['lng']];
        } else {
            $token = $settings['mapbox_token'] ?? '';
            if (!$token) return null;
            $url = "https://api.mapbox.com/geocoding/v5/mapbox.places/{$address_q}.json?limit=1&access_token={$token}";
            $res = wp_remote_get($url, ['timeout'=>12]);
            if (is_wp_error($res)) return null;
            $data = json_decode(wp_remote_retrieve_body($res), true);
            if (!$data || empty($data['features'][0]['center'])) return null;
            $c = $data['features'][0]['center']; // [lng,lat]
            return ['lat'=>floatval($c[1]), 'lng'=>floatval($c[0])];
        }
    }

    public static function distance_km($from_addr, $to_addr) {
        $a = self::geocode($from_addr);
        $b = self::geocode($to_addr);
        if (!$a || !$b) return null;
        return self::haversine($a['lat'],$a['lng'],$b['lat'],$b['lng']);
    }

    private static function haversine($lat1,$lon1,$lat2,$lon2) {
        $R = 6371; // km
        $dLat = deg2rad($lat2-$lat1);
        $dLon = deg2rad($lon2-$lon1);
        $x = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
        return 2*$R*asin(min(1, sqrt($x)));
    }
}
