<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_OpenAI
{

    // ---- Lê config do plugin ----
    private static function cfg(): array
    {
        if (!class_exists('PluginsAlpha_Settings')) {
            return [
                'key'          => trim(get_option('alpha_orion_posts_openai_key', '')),
                'model_text'   => get_option('alpha_orion_posts_model_text', 'gpt-4o-mini'),
                'model_image'  => get_option('alpha_orion_posts_model_image', 'gpt-image-1'),
                'temperature'  => floatval(get_option('alpha_orion_posts_temperature', 0.6)),
                'max_tokens'   => intval(get_option('alpha_orion_posts_max_tokens', 6000)),
                'timeout'      => 60,
            ];
        }
        $opt = PluginsAlpha_Settings::get();
        return [
            'key'          => trim($opt['apis']['openai']['key'] ?? ''),
            'model_text'   => $opt['apis']['openai']['model_text']  ?? 'gpt-4o-mini',
            'model_image'  => $opt['apis']['openai']['model_image'] ?? 'gpt-image-1',
            'temperature'  => floatval($opt['apis']['openai']['temperature'] ?? 0.6),
            'max_tokens'   => intval($opt['apis']['openai']['max_tokens'] ?? 6000),
            'timeout'      => 60,
        ];
    }

    public static function outline(string $prompt)
    {
        $c = self::cfg();
        if (!$c['key']) {
            return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');
        }

        $system = "Você é um planejador de conteúdo SEO. " .
            "Responda SEMPRE SOMENTE em JSON UTF-8 válido, no formato {\"sections\":[...]}.";

        $body = [
            'model'       => $c['model_text'],
            'temperature' => $c['temperature'],
            'max_tokens'  => min(3000, $c['max_tokens']),
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $c['timeout'],
        ];

        $res = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);

        if ($code !== 200) {
            $msg = 'HTTP ' . $code;
            $j   = json_decode($raw, true);
            if (!empty($j['error']['message'])) {
                $msg = $j['error']['message'];
            }
            return new WP_Error('pga_openai_http_outline', $msg, ['http_code' => $code]);
        }

        $json = json_decode($raw, true);
        $txt  = (string)($json['choices'][0]['message']['content'] ?? '');

        $parsed = self::extract_json($txt); // já existe nessa classe
        if (!is_array($parsed) || empty($parsed['sections']) || !is_array($parsed['sections'])) {
            return new WP_Error('pga_outline_parse', 'Falha ao decodificar ESBOÇO.');
        }

        return $parsed['sections'];
    }

    // ---- Completar texto (retorna array padronizado) ----
    public static function complete(string $prompt, array $schema = [])
    {
        $c = self::cfg();
        if (!$c['key']) return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');

        $defaultSchema = [
            'title'             => 'string',
            'titles_suggestions' => ['string'],
            'content'           => 'string',
            'meta_title'        => 'string',
            'meta_description'  => 'string',
            'image_alt'         => 'string',
            'links'             => ['internal' => ['string'], 'external' => ['string']],
        ];
        $schema = $schema ?: $defaultSchema;

        $system = "Você é um gerador de artigos SEO. Responda SOMENTE em JSON UTF-8 válido, sem markdown. " .
            "O campo 'content' deve ser HTML SEM <h1>. Schema: " . json_encode($schema, JSON_UNESCAPED_UNICODE);

        $body = [
            'model'       => $c['model_text'],
            'temperature' => $c['temperature'],
            'max_tokens'  => $c['max_tokens'],
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $c['timeout'],
        ];

        $res  = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        if ($code !== 200) {
            $msg = 'HTTP ' . $code;
            $j = json_decode($raw, true);
            if (isset($j['error']['message'])) $msg = $j['error']['message'];
            return new WP_Error('pga_openai_http', $msg, ['http_code' => $code]);
        }

        $json = json_decode($raw, true);
        $txt  = (string)($json['choices'][0]['message']['content'] ?? '');

        $parsed = self::extract_json($txt);
        if (!$parsed) return new WP_Error('pga_parse', 'Falha ao decodificar JSON do modelo.');

        return [
            'title'              => trim((string)($parsed['title'] ?? '')),
            'titles_suggestions' => array_values(array_filter((array)($parsed['titles_suggestions'] ?? []))),
            'content'            => trim((string)($parsed['content'] ?? '')),
            'meta_title'         => trim((string)($parsed['meta_title'] ?? '')),
            'meta_description'   => trim((string)($parsed['meta_description'] ?? '')),
            'image_alt'          => trim((string)($parsed['image_alt'] ?? '')),
            'links'              => [
                'internal' => array_values(array_filter((array)($parsed['links']['internal'] ?? []))),
                'external' => array_values(array_filter((array)($parsed['links']['external'] ?? []))),
            ],
        ];
    }

    // dentro da classe PluginsAlpha_OpenAI:

    public static function titles(string $prompt)
    {
        $c = self::cfg();
        if (!$c['key']) return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');

        $system = "Você é um gerador de TÍTULOS. Responda SOMENTE em JSON UTF-8 válido, sem markdown, no formato {\"titles\":[\"...\"]}.";

        $body = [
            'model'       => $c['model_text'],
            'temperature' => $c['temperature'],
            'max_tokens'  => min(1200, $c['max_tokens']),
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $c['timeout'],
        ];

        $res  = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        if ($code !== 200) {
            $msg = 'HTTP ' . $code;
            $j = json_decode($raw, true);
            if (!empty($j['error']['message'])) $msg = $j['error']['message'];
            return new WP_Error('pga_openai_titles_http', $msg, ['http_code' => $code]);
        }

        $json = json_decode($raw, true);
        $txt  = (string)($json['choices'][0]['message']['content'] ?? '');
        $parsed = self::extract_json($txt);
        if (!is_array($parsed) || empty($parsed['titles']) || !is_array($parsed['titles'])) {
            return new WP_Error('pga_titles_parse', 'Falha ao decodificar títulos.');
        }
        $titles = array_values(array_filter(array_map('trim', $parsed['titles'])));
        if (!$titles) return new WP_Error('pga_no_titles', 'Sem títulos retornados.');
        return $titles;
    }


    // ---- Gera imagem e devolve base64 ----
    public static function image_base64(string $prompt, string $size = '1200x675')
    {
        $c = self::cfg();
        if (!$c['key']) return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');

        $payload = [
            'model'            => $c['model_image'], // gpt-image-1 ou dall-e-3 (se sua conta ainda usar)
            'prompt'           => $prompt,
            'size'             => $size,
            'n'                => 1,
            'response_format'  => 'b64_json',
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'timeout' => $c['timeout'],
        ];

        $res  = wp_remote_post('https://api.openai.com/v1/images/generations', $args);
        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        if ($code !== 200) {
            $msg = 'HTTP ' . $code;
            $j = json_decode($raw, true);
            if (isset($j['error']['message'])) $msg = $j['error']['message'];
            return new WP_Error('pga_openai_img', $msg, ['http_code' => $code]);
        }

        $j = json_decode($raw, true);
        return $j['data'][0]['b64_json'] ?? new WP_Error('pga_openai_img_empty', 'Resposta de imagem vazia.');
    }

    // ---- helper para extrair JSON de respostas em texto/markdown ----
    private static function extract_json(string $txt)
    {
        if (preg_match('/```json\s*(.+?)```/is', $txt, $m)) {
            $a = json_decode(trim($m[1]), true);
            if (is_array($a)) return $a;
        }
        $a = json_decode(trim($txt), true);
        if (is_array($a)) return $a;
        $start = strpos($txt, '{');
        $end = strrpos($txt, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $chunk = substr($txt, $start, $end - $start + 1);
            $a = json_decode($chunk, true);
            if (is_array($a)) return $a;
        }
        return null;
    }
}
