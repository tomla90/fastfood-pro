<?php
if (!defined('ABSPATH')) exit;

class FFP_PWA {
    public function __construct() {
        add_action('wp_head', [$this,'link_tags']);
        add_action('init', [$this,'rewrite_rules']);
        add_action('template_redirect', [$this,'serve_files']);
    }

    public function link_tags() {
        $name = esc_attr(get_option('blogname'));
        echo '<link rel="manifest" href="'.esc_url(home_url('/ffp-manifest.json')).'">'."\n";
        echo '<meta name="theme-color" content="#111827">'."\n";
        echo '<link rel="apple-touch-icon" href="'.esc_url(FFP_URL.'assets/pwa/icon-192.png').'">'."\n";
    }

    public function rewrite_rules() {
        add_rewrite_rule('^ffp-manifest\.json$', 'index.php?ffp_manifest=1', 'top');
        add_rewrite_rule('^ffp-sw\.js$', 'index.php?ffp_sw=1', 'top');
        add_filter('query_vars', function($qv){ $qv[]='ffp_manifest'; $qv[]='ffp_sw'; return $qv; });
    }

    public function serve_files() {
        if (get_query_var('ffp_manifest')) {
            $manifest = [
                'name' => get_option('blogname'),
                'short_name' => 'FastFood',
                'start_url' => esc_url_raw( home_url( add_query_arg([], get_permalink()) ) ),
                'display' => 'standalone',
                'background_color' => '#111827',
                'theme_color' => '#111827',
                'icons' => [
                    ['src'=>FFP_URL.'assets/pwa/icon-192.png','sizes'=>'192x192','type'=>'image/png'],
                    ['src'=>FFP_URL.'assets/pwa/icon-512.png','sizes'=>'512x512','type'=>'image/png'],
                ]
            ];
            nocache_headers();
            header('Content-Type: application/json; charset=utf-8');
            echo wp_json_encode($manifest);
            exit;
        }
        if (get_query_var('ffp_sw')) {
            nocache_headers();
            header('Content-Type: application/javascript; charset=utf-8');
            readfile(FFP_DIR.'assets/pwa/sw.js'); // enkel statisk SW
            exit;
        }
    }
}
