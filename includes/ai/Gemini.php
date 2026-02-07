<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Gemini
{

    // ---- Lê config do plugin (GEMINI) ----
    private static function cfg(): array
    {
        // Fallback legado (sem PluginsAlpha_Settings)
        if (!class_exists('PluginsAlpha_Settings')) {
            return [
                'key'         => trim(get_option('alpha_orion_posts_gemini_key', '')),
                'model_text'  => get_option('alpha_orion_posts_gemini_model_text', 'gemini-1.5-pro'),
                'temperature' => (float) get_option('alpha_orion_posts_gemini_temperature', 0.6),
                'max_tokens'  => (int) get_option('alpha_orion_posts_gemini_max_tokens', 6000),
                'timeout'     => 60,
            ];
        }

        $opt = PluginsAlpha_Settings::get();
        $ge  = $opt['apis']['gemini'] ?? [];

        return [
            'key'         => trim((string) ($ge['key']         ?? '')),
            'model_text'  => (string)      ($ge['model_text']  ?? 'gemini-1.5-pro'),
            'temperature' => (float)       ($ge['temperature'] ?? 0.6),
            'max_tokens'  => (int)         ($ge['max_tokens']  ?? 6000),
            'timeout'     => 60,
        ];
    }

    public static function is_configured(): bool
    {
        $c = self::cfg();
        return $c['key'] !== '';
    }

    /**
     * Helper único para chamar o Gemini.
     *
     * @param string $system     Prompt de sistema (regras, formato, etc).
     * @param string $userPrompt Prompt do usuário.
     * @param array  $args       ['model' => '...', 'temperature' => 0.7, 'max_tokens' => 4000, ...]
     *
     * @return string|WP_Error   Texto bruto retornado pelo modelo (concatenado) OU erro.
     */
    private static function call_gemini(string $prompt, array $args)
    {
        $body = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => $args['temperature'],
                'maxOutputTokens' => $args['max_tokens'],
            ],
        ];

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($args['model']),
            rawurlencode($args['key'])
        );

        $res = wp_remote_post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => $args['timeout'],
        ]);

        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);

        if ($code < 200 || $code >= 300) {
            return new WP_Error('pga_gemini_http', 'Erro Gemini', [
                'http_code' => $code,
                'body'      => substr($raw, 0, 800),
            ]);
        }

        $json = json_decode($raw, true);

        $txt = '';
        if (!empty($json['candidates'][0]['content']['parts'])) {
            foreach ($json['candidates'][0]['content']['parts'] as $part) {
                if (!empty($part['text'])) {
                    $txt .= $part['text'];
                }
            }
        }

        return $txt;
    }

    public static function complete(
        string $prompt,
        array $schema = [],
        array $args = []
    ) {
        // ---- SETTINGS (DB) ----
        $gemini = self::cfg();

        $key        = trim((string)($gemini['key'] ?? ''));
        $model      = (string)($args['model'] ?? $gemini['model_text'] ?? 'gemini-1.5-pro');
        $temperature = $args['temperature'] ?? $gemini['temperature'] ?? 0.6;
        $maxTokens  = (int)($args['max_tokens'] ?? $gemini['max_tokens'] ?? 6000);
        $timeout    = (int)($args['timeout'] ?? 60);

        if ($key === '') {
            return new WP_Error('pga_no_key', 'Gemini API Key ausente.');
        }

        // ---- CHAMADA ----
        $txt = self::call_gemini(
            $prompt,
            [
                'key'         => $key,
                'model'       => $model,
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
                'timeout'     => $timeout,
            ]
        );

        if (is_wp_error($txt)) {
            return $txt;
        }

        $txt = trim((string)$txt);
        
        if (empty($schema)) {
            return ['content' => $txt];
        }

        $parsed = self::extract_json($txt);
        if (!$parsed) {
            return new WP_Error(
                'pga_parse',
                'Falha ao decodificar JSON do modelo.',
                ['snippet' => mb_substr($txt, 0, 800)]
            );
        }

        // 🔹 SE o JSON tiver "content" string, unwrap
        if (isset($parsed['content']) && is_string($parsed['content'])) {
            return [
                'content' => trim($parsed['content']),
            ];
        }

        // 🔹 caso contrário, retorna o JSON estruturado normal
        foreach ($schema as $keyName => $_) {
            if (!array_key_exists($keyName, $parsed)) {
                return new WP_Error(
                    'pga_schema_missing',
                    "Campo obrigatório ausente no JSON: {$keyName}",
                    ['response' => $parsed]
                );
            }
        }

        return $parsed;
    }

    private static function extract_json(string $raw): ?array
    {
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
