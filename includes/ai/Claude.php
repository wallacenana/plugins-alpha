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
            'temperature' => (float) ($cl['temperature'] ?? 0.95),
            'max_tokens'  => (int)   ($cl['max_tokens'] ?? 4096),
            'timeout'     => 120,
        ];
    }

    public static function is_configured(): bool
    {
        return self::cfg()['key'] !== '';
    }

    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $c = self::cfg();

        if (empty($c['key'])) {
            return new WP_Error('pga_claude_no_key', 'Chave de API do Claude não configurada.');
        }

        $model = $c['model'] ?? $c['model_text'];

        $isStructured = !empty($schema);

        // 🔒 limites seguros do Claude (hard limit ~4096)
        $maxTokens = $args['max_tokens']
            ?? ($isStructured ? 1200 : 3500);

        $temperature = $args['temperature']
            ?? ($isStructured ? 0.15 : 0.9);

        $topP = $args['top_p']
            ?? ($isStructured ? 0.7 : 0.95);

        // 🔴 SYSTEM PROMPT
        $systemPrompt = $isStructured
            ? "Você deve responder APENAS com JSON válido UTF-8.
Não use markdown.
Não use aspas tipográficas.
Não quebre linhas dentro de strings.
Não inclua texto fora do JSON.
Não explique nada."
            : "Você é um gerador de artigos focado em SEO GEO e E-E-A-T.";

        /**
         * ⚠️ Claude NÃO usa role=system no messages
         * system é campo separado
         */
        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'top_p' => $topP,
            'system' => trim($systemPrompt),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        $argsReq = [
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $c['key'],
                'anthropic-version' => '2023-06-01',
            ],
            'timeout' => $c['timeout'] ?? 60,
            'body'    => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $res = wp_remote_post('https://api.anthropic.com/v1/messages', $argsReq);
        if (is_wp_error($res)) {
            return $res;
        }

        $raw  = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);

        if (
            empty($json['content'][0]['text'])
            || !is_string($json['content'][0]['text'])
        ) {
            return new WP_Error(
                'pga_claude_invalid',
                'Resposta inválida do Claude.',
                ['raw' => $raw]
            );
        }

        $txt = trim($json['content'][0]['text']);

        // 🔥 TEXTO LIVRE
        if (!$isStructured) {
            return ['content' => $txt];
        }

        // 🔥 JSON HARD PARSE
        if (preg_match('/\{.*\}/s', $txt, $m)) {
            $txt = $m[0];
        }

        $parsed = json_decode($txt, true);

        if (!is_array($parsed)) {
            return new WP_Error(
                'pga_parse',
                'Falha ao decodificar JSON do modelo.',
                ['snippet' => mb_substr($txt, 0, 800)]
            );
        }

        // 🔒 contrato mínimo
        foreach ($schema as $key => $_) {
            if (!array_key_exists($key, $parsed)) {
                return new WP_Error(
                    'pga_schema_missing',
                    "Campo obrigatório ausente no JSON: {$key}",
                    ['response' => $parsed]
                );
            }
        }

        return $parsed;
    }
}
