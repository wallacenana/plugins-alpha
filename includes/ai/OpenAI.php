<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_OpenAI
{

    // ---- Lê config do plugin ----
    private static function cfg(): array
    {
        $opt = AlphaSuite_Settings::get();
        $oa  = $opt['apis']['openai'] ?? [];

        return [
            'key'          => trim((string) ($oa['key'] ?? '')),
            'model_text'   => (string) ($oa['model_text']  ?? 'gpt-4o-mini'),
            'model_image'  => (string) ($oa['model_image'] ?? 'gpt-image-1'),
            'temperature'  => (float)  ($oa['temperature'] ?? 0.6),
            'max_tokens'   => (int)    ($oa['max_tokens']  ?? 6000),
            'max_output_tokens'   => (int)    ($oa['max_tokens']  ?? 6000),
            'timeout'      => 500,
        ];
    }

    public static function is_configured(): bool
    {
        $c = self::cfg();
        return $c['key'] !== '';
    }

    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $c = self::cfg();

        if (empty($c['key'])) {
            return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');
        }

        $isStructured = !empty($schema);

        $system = $isStructured
            ? "Responda SOMENTE com JSON válido UTF-8. Sem markdown. Sem explicações."
            : "Você é um gerador de conteúdo SEO.";

        $body = [
            'model'       => $c['model_text'],
            'temperature' => $args['temperature'] ?? 0.5,
            'max_tokens'  => $args['max_tokens'] ?? 2000,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];

        $res = wp_remote_post(
            'https://api.openai.com/v1/chat/completions',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $c['key'],
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => $c['timeout'] ?? 60,
                'body'    => wp_json_encode($body),
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);

        if ($code !== 200) {
            return new WP_Error(
                'pga_openai_http',
                'Erro HTTP ' . $code,
                ['raw' => $raw]
            );
        }

        $json = json_decode($raw, true);
        $txt  = trim((string)($json['choices'][0]['message']['content'] ?? ''));

        if ($txt === '') {
            return new WP_Error(
                'pga_openai_empty',
                'Nenhum texto retornado.',
                ['raw' => $raw]
            );
        }

        // 🔹 MODO TEXTO (antigo comportamento)
        if (!$isStructured) {
            return $txt;
        }

        // 🔹 MODO JSON
        $parsed = json_decode($txt, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'pga_json_invalid',
                'JSON inválido retornado.',
                ['raw' => $txt]
            );
        }

        if (isset($parsed['content']) && is_string($parsed['content'])) {

            $inner = json_decode($parsed['content'], true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($inner)) {
                $parsed = $inner;
            }
        }

        return $parsed;
    }


    public static function outline(string $prompt, array $args = [])
    {
        $c = self::cfg();
        $useSearch = !empty($args['use_search']);

        if (!$c['key']) {
            return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');
        }

        $body = [
            "model" => $c['model_text'],
            "input" => [
                [
                    "role" => "system",
                    "content" => [
                        [
                            "type" => "input_text",
                            "text" => "Responda SOMENTE em JSON UTF-8 válido no formato {\"sections\":[...]} sem qualquer texto antes ou depois. FORMATO VALIDO JSON COM NO MÁXIMO 20 BULLETS, MÁXIMO"
                        ]
                    ]
                ],
                [
                    "role" => "user",
                    "content" => [
                        [
                            "type" => "input_text",
                            "text" => $prompt
                        ]
                    ]
                ]
            ],
            "temperature" => 0.4,
            "max_output_tokens" => 4600
        ];

        if ($useSearch) {
            $body["tools"] = [
                ["type" => "web_search"]
            ];
            $body["tool_choice"] = "auto";
        }

        $request = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $c['timeout'],
        ];

        $res = wp_remote_post('https://api.openai.com/v1/responses', $request);

        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);

        if ($code !== 200) {
            $j = json_decode($raw, true);
            $msg = $j['error']['message'] ?? ('HTTP ' . $code);
            return new WP_Error('pga_openai_http_outline', $msg, ['http_code' => $code]);
        }

        $json = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'pga_openai_invalid_json',
                'Resposta bruta inválida da OpenAI.',
                [
                    'json_error' => json_last_error_msg(),
                    'raw_tail'   => substr($raw, -500)
                ]
            );
        }

        $txt = self::extract_output_text($json);

        if (!$txt) {
            return new WP_Error('pga_no_output', 'Nenhum texto retornado pela API.');
        }

        $txt = trim($txt);

        /*
        |--------------------------------------------------------------------------
        | DECODE FINAL
        |--------------------------------------------------------------------------
        */

        $parsed = json_decode($txt, true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            // tentativa de extrair JSON bruto
            if (preg_match('/\{.*\}/s', $txt, $match)) {
                $parsed = json_decode($match[0], true);
            }
        }

        if (
            is_array($parsed) &&
            !empty($parsed['sections']) &&
            is_array($parsed['sections'])
        ) {
            return $parsed['sections'];
        }

        return new WP_Error(
            'pga_outline_parse',
            'Falha ao decodificar ESBOÇO.',
            [
                'json_error' => json_last_error_msg(),
                'snippet'    => mb_substr($txt, 0, 1000)
            ]
        );
    }

    private static function extract_output_text(array $json): string
    {
        $txt = '';

        if (!empty($json['output'])) {
            foreach ($json['output'] as $item) {
                if ($item['type'] === 'message' && !empty($item['content'])) {
                    foreach ($item['content'] as $content) {
                        if ($content['type'] === 'output_text') {
                            $txt .= $content['text'] ?? '';
                        }
                    }
                }
            }
        }

        return trim($txt);
    }
}
