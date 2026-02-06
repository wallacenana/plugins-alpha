<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_RESTRSS
{
    public static function register_routes()
    {
        register_rest_route('pga/v1', '/rss/get', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'get_rss'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public static function get_rss(WP_REST_Request $req)
    {
        $params = $req->get_json_params();
        $url    = trim($params['url'] ?? '');
        $limit  = min(20, intval($params['limit'] ?? 10));

        if (!$url) {
            return new WP_Error('no_url', 'URL is required', ['status' => 400]);
        }

        // 🔹 1) Converte Google News search → RSS
        if (str_contains($url, 'news.google.com')) {
            $parsed = wp_parse_url($url);

            if (empty($parsed['query'])) {
                return new WP_Error('invalid_url', 'Invalid Google News URL', ['status' => 400]);
            }

            $rssUrl = 'https://news.google.com/rss/search?' . $parsed['query'];
        } else {
            // fallback: assume que já é RSS
            $rssUrl = $url;
        }

        // 🔹 2) Fetch RSS
        $response = wp_remote_get($rssUrl, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (AlphaOrionRSS)'
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('fetch_error', 'Failed to fetch RSS', ['status' => 500]);
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            return new WP_Error('empty_body', 'Empty RSS body', ['status' => 500]);
        }

        // 🔹 3) Parse RSS
        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($rss === false || empty($rss->channel->item)) {
            return new WP_Error('invalid_rss', 'Invalid RSS feed', ['status' => 500]);
        }

        // 🔹 4) Extrai itens
        $items = [];

        foreach ($rss->channel->item as $item) {
            $items[] = [
                'title'   => trim((string) $item->title),
                'link'    => trim((string) $item->link),
                'source'  => (string) ($item->source ?? ''),
                'pubDate' => (string) ($item->pubDate ?? ''),
            ];

            if (count($items) >= $limit) break;
        }

        return rest_ensure_response([
            'rss_url' => $rssUrl,
            'count'   => count($items),
            'items'   => $items
        ]);
    }
}
