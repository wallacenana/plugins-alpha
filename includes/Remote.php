<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Remote {
  public static function catalog() : array {
    $api_url = get_option('pa_admin_api_url', '');
    $api_key = get_option('pa_admin_api_key', '');
    $cache   = get_transient('pa_catalog_cache');
    if ($cache && is_array($cache)) return $cache;

    if ($api_url) {
      $res = wp_remote_get(rtrim($api_url, '/').'/alpha/catalog', [
        'timeout' => 12,
        'headers' => ['Accept'=>'application/json','Authorization'=>'Bearer '.$api_key],
      ]);
      if (!is_wp_error($res)) {
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (is_array($body)) {
          set_transient('pa_catalog_cache', $body, 30 * MINUTE_IN_SECONDS);
          return $body;
        }
      }
    }

    // fallback mock
    $mock = [
      [
        'slug' => 'orion-posts',
        'name' => 'Órion Posts',
        'desc' => 'Gere calendários e artigos com IA.',
        'logo' => PGA_URL.'assets/images/orion-posts.png',
        'price' => 297.00,
        'promo_price' => 197.00,
        'buy_url' => '#',
      ],
      [
        'slug' => 'alpha-stories',
        'name' => 'Alpha Stories',
        'desc' => 'Crie Web Stories otimizadas.',
        'logo' => PGA_URL.'assets/images/alpha-stories.png',
        'price' => 297.00,
        'promo_price' => null,
        'buy_url' => '#',
      ],
    ];
    set_transient('pa_catalog_cache', $mock, 30 * MINUTE_IN_SECONDS);
    return $mock;
  }
}
