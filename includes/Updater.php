<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Updater {

    public static function init(){
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__,'check']);
        add_filter('plugins_api', [__CLASS__,'info'], 10, 3);
    }

    public static function check($transient){
        if (empty($transient->checked)) return $transient;

        $slug   = 'alpha-gpt-posts/alpha-gpt-posts.php';
        $curVer = $transient->checked[$slug] ?? null;
        if (!$curVer) return $transient;

        $lic = PluginsAlpha_License::get();
        $url = PluginsAlpha_Server::updates_endpoint() . '?' . http_build_query([
            'action'      => 'check',
            'slug'        => 'alpha-gpt-posts',
            'ver'         => $curVer,
            'email'       => $lic['email'],
            'purchase_id' => $lic['purchase_id'],
            'key'         => $lic['key'],
            'site'        => home_url('/'),
        ], '', '&');

        $resp = wp_remote_get($url, ['timeout'=>8]);
        if (is_wp_error($resp)) return $transient;
        if (wp_remote_retrieve_response_code($resp) !== 200) return $transient;

        $b = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($b) || empty($b['new_version'])) return $transient;

        $obj = (object)[
            'slug'        => 'alpha-gpt-posts',
            'plugin'      => $slug,
            'new_version' => $b['new_version'],
            'url'         => PluginsAlpha_Server::base(),
            'package'     => $b['download_url'] ?? '',
            'tested'      => $b['tested'] ?? '',
            'requires'    => $b['requires'] ?? '',
        ];
        if (version_compare($obj->new_version, $curVer, '>')) {
            $transient->response[$slug] = $obj;
        }
        return $transient;
    }

    public static function info($result, $action, $args){
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== 'alpha-gpt-posts') return $result;

        $lic = PluginsAlpha_License::get();
        $url = PluginsAlpha_Server::updates_endpoint() . '?' . http_build_query([
            'action'      => 'info',
            'slug'        => 'alpha-gpt-posts',
            'email'       => $lic['email'],
            'purchase_id' => $lic['purchase_id'],
            'key'         => $lic['key'],
            'site'        => home_url('/'),
        ], '', '&');

        $resp = wp_remote_get($url, ['timeout'=>8]);
        if (is_wp_error($resp)) return $result;
        if (wp_remote_retrieve_response_code($resp) !== 200) return $result;

        $b = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($b)) return $result;

        return (object)[
            'name'         => 'Alpha GPT Posts',
            'slug'         => 'alpha-gpt-posts',
            'version'      => $b['new_version'] ?? '',
            'tested'       => $b['tested'] ?? '',
            'requires'     => $b['requires'] ?? '',
            'last_updated' => $b['last_updated'] ?? '',
            'sections'     => $b['sections'] ?? ['description'=>'', 'changelog'=>''],
            'download_link'=> $b['download_url'] ?? '',
        ];
    }
}
