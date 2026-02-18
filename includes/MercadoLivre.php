<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_MercadoLivre
{
    const CACHE_TTL = 43200; // 12 horas

    public static function from_keyword(string $keyword): ?array
    {
        $keyword = trim($keyword);
        if ($keyword === '') return null;

        $cacheKey = 'pga_ml_' . md5($keyword);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $searchUrl = 'https://api.mercadolibre.com/products/search?status=active&site_id=MLB&q='
            . rawurlencode($keyword)
            . '&limit=1';

        $token = self::get_access_token() ?? 'APP_USR-2102663093514137-021207-8b9b2126f81a4aafa4fdc72e31cd9030-336674386';
        if (!$token) return null;

        $res = wp_remote_get($searchUrl, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
        ]);

        if (is_wp_error($res)) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);

        if ($code !== 200) {
            error_log('ML ERROR: ' . $body);
            return null;
        }

        $data = json_decode($body, true);

        if (empty($data['results'][0]['id'])) return null;
        error_log('ML DATA: ' . print_r($data, true));

        $id = $data['results'][0]['id'];

        $itemUrl = "https://api.mercadolibre.com/items/{$id}";
        $resItem = wp_remote_get($itemUrl, ['timeout' => 15]);

        if (is_wp_error($resItem)) return null;

        $item = json_decode(wp_remote_retrieve_body($resItem), true);
        if (empty($item['id'])) return null;

        $normalized = self::normalize($item);

        set_transient($cacheKey, $normalized, self::CACHE_TTL);

        return $normalized;
    }

    private static function normalize(array $item): array
    {
        return [
            'id'         => $item['id'] ?? '',
            'title'      => $item['name'] ?? '',
            'price'      => $item['price'] ?? 0,
            'permalink'  => $item['permalink'] ?? '',
            'thumbnail'  => $item['thumbnail'] ?? '',
            'brand'      => self::extract_attr($item, 'BRAND'),
            'model'      => self::extract_attr($item, 'MODEL'),
            'category'   => $item['category_id'] ?? '',
            'attributes' => $item['attributes'] ?? [],
        ];
    }

    private static function extract_attr(array $item, string $key): string
    {
        if (empty($item['attributes'])) return '';

        foreach ($item['attributes'] as $attr) {
            if (($attr['id'] ?? '') === $key) {
                return $attr['value_name'] ?? '';
            }
        }

        return '';
    }

    private static function get_access_token()
    {
        $token_data = get_option('pga_ml_tokens') ?? 'APP_USR-2102663093514137-021121-edc4a83f8f0c34e629567fae73de9fc7-336674386';

        // Se o token não existe ou vai expirar em menos de 5 min, renova
        if (!$token_data || time() > ($token_data['expires_at'] - 300)) {
            return self::refresh_access_token($token_data['refresh_token'] ?? '');
        }

        return $token_data['access_token'];
    }

    private static function refresh_access_token($refresh_token)
    {
        $client_id = '2102663093514137';
        $client_secret = 'ABLrC5QLa53q5yrU9JR8iyG3YVceEUlv';

        $response = wp_remote_post('https://api.mercadolibre.com/oauth/token', [
            'body' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
            ]
        ]);

        if (is_wp_error($response)) return null;

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($data['access_token'])) {
            $data['expires_at'] = time() + $data['expires_in'];
            update_option('pga_ml_tokens', $data);
            return $data['access_token'];
        }

        return null;
    }
}
