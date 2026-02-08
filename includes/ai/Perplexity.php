<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Perplexity
{
    // ---- Lê config do plugin ----
    private static function cfg(): array
    {
        $opt = PluginsAlpha_Settings::get();
        $px  = $opt['apis']['perplexity'] ?? [];

        return [
            'key'         => trim((string)($px['key'] ?? '')),
            'model_text'  => (string)($px['model_text'] ?? 'llama-3.1-sonar-large-128k-online'),
            'temperature' => (float) ($px['temperature'] ?? 0.6),
            'max_tokens'  => (int)   ($px['max_tokens'] ?? 8000),
            'timeout'     => 120,
        ];
    }

    public static function is_configured(): bool
    {
        return self::cfg()['key'] !== '';
    }

    // ---- Completar texto ----
    public static function complete(string $prompt, array $schema = [], array $opts = [])
    {
        $c = self::cfg();

        if (!$c['key']) {
            return new WP_Error('pga_perplexity_no_key', 'Chave de API do Perplexity não configurada.');
        }
        
        $body = [
            'model' => $c['model_text'],
            'temperature' => $c['temperature'],
            'max_tokens' => $c['max_tokens'],
            'messages' => [
                ['role' => 'system', 'content' => 'Você é um gerador de artigos, focado em SEO GEO e E-E-A-T'],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];

        $res = wp_remote_post(
            'https://api.perplexity.ai/chat/completions',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $c['key'],
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => $c['timeout'],
                'body'    => wp_json_encode($body),
            ]
        );

        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);

        if ($code !== 200) {
            return new WP_Error(
                'pga_perplexity_http',
                $json['error']['message'] ?? 'Erro Perplexity',
                ['http_code' => $code]
            );
        }

        $txt = (string)($json['choices'][0]['message']['content'] ?? '');
        $txt = trim($txt);

        if (empty($schema)) {
            return ['content' => $txt];
        }

        $parsed = self::extract_json($txt);
        if (!$parsed) {
            return new WP_Error(
                'pga_parse',
                'Falha ao decodificar JSON do Perplexity.',
                ['snippet' => mb_substr($txt, 0, 800)]
            );
        }

        foreach ($schema as $key => $_) {
            if (!array_key_exists($key, $parsed)) {
                return new WP_Error(
                    'pga_schema_missing',
                    "Campo obrigatório ausente no JSON: {$key}"
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
