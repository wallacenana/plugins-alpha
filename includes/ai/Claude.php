<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Claude
{
    // ---- Lê config do plugin ----
    private static function cfg(): array
    {
        $opt = PluginsAlpha_Settings::get();
        $cl  = $opt['apis']['claude'] ?? [];

        return [
            'key'         => trim((string)($cl['key'] ?? '')),
            'model_text'  => (string)($cl['model_text'] ?? 'claude-3-5-sonnet-20240620'),
            'temperature' => (float) ($cl['temperature'] ?? 0.6),
            'max_tokens'  => (int)   ($cl['max_tokens'] ?? 4096),
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
            return new WP_Error('pga_claude_no_key', 'Chave de API do Claude não configurada.');
        }

        $body = [
            'model' => $c['model_text'],
            'max_tokens' => $c['max_tokens'],
            'temperature' => $c['temperature'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        $res = wp_remote_post(
            'https://api.anthropic.com/v1/messages',
            [
                'headers' => [
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $c['key'],
                    'anthropic-version' => '2023-06-01',
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
                'pga_claude_http',
                $json['error']['message'] ?? 'Erro Claude',
                ['http_code' => $code]
            );
        }

        // ---- EXTRAÇÃO ----
        $txt = '';
        if (!empty($json['content'][0]['text'])) {
            $txt = (string)$json['content'][0]['text'];
        }

        $txt = trim($txt);

        // ---- PARSE ----
        if (empty($schema)) {
            return ['content' => $txt];
        }

        $parsed = self::extract_json($txt);
        if (!$parsed) {
            return new WP_Error(
                'pga_parse',
                'Falha ao decodificar JSON do Claude.',
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
