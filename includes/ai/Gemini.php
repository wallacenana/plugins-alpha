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

    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $c = self::cfg();

        $model = $c['model'] ?? $c['model_text'];

        $isStructured = !empty($schema);

        // 🔒 defaults seguros
        $maxTokens = $args['max_tokens']
            ?? ($isStructured ? 1800 : 5000);

        $temperature = $args['temperature']
            ?? ($isStructured ? 0.15 : 0.95);

        $topP = $args['top_p']
            ?? ($isStructured ? 0.7 : 0.95);

        // 🔴 MODO JSON HARD
        $systemPrompt = $isStructured
            ? "Você deve responder APENAS com JSON válido UTF-8.
Não use markdown.
Não use aspas tipográficas.
Não quebre linhas dentro de strings.
Não inclua texto fora do JSON.
Não explique nada."
            : "Você é um gerador de artigos focado em SEO GEO e E-E-A-T.";

        /**
         * 📌 Gemini NÃO usa roles system/user separados.
         * Tudo precisa ir no texto do prompt.
         */
        $finalPrompt = trim($systemPrompt) . "\n\n" . $prompt;

        $body = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $finalPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'topP'        => $topP,
                'maxOutputTokens' => $maxTokens,
            ]
        ];

        $argsReq = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => $c['timeout'] ?? 60,
            'body'    => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            $model,
            $c['key']
        );

        $res = wp_remote_post($endpoint, $argsReq);
        if (is_wp_error($res)) {
            return $res;
        }

        $raw  = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);

        if (
            !isset($json['candidates'][0]['content']['parts'][0]['text'])
        ) {
            return new WP_Error(
                'pga_gemini_invalid',
                'Resposta inválida do Gemini.',
                ['raw' => $raw]
            );
        }

        $txt = trim((string)$json['candidates'][0]['content']['parts'][0]['text']);

        // 🔥 PARSE CONTROLADO (IGUAL AO OPENAI)
        if (!$isStructured) {
            return ['content' => $txt];
        }

        // remove lixo antes/depois
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

        // valida contrato mínimo
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
