<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_OpenAI
{

    // ---- Lê config do plugin ----
    private static function cfg(): array
    {
        $opt = PluginsAlpha_Settings::get();
        $oa  = $opt['apis']['openai'] ?? [];

        return [
            'key'          => trim((string) ($oa['key'] ?? '')),
            'model_text'   => (string) ($oa['model_text']  ?? 'gpt-4o-mini'),
            'model_image'  => (string) ($oa['model_image'] ?? 'gpt-image-1'),
            'temperature'  => (float)  ($oa['temperature'] ?? 0.6),
            'max_tokens'   => (int)    ($oa['max_tokens']  ?? 6000),
            'max_output_tokens'   => (int)    ($oa['max_tokens']  ?? 6000),
            'timeout'      => 120,
        ];
    }

    public static function is_configured(): bool
    {
        $c = self::cfg();
        return $c['key'] !== '';
    }

    // ---- Completar texto (retorna array padronizado) ----
    public static function complete(string $prompt, array $schema = [], array $opts = [])
    {
        $c = self::cfg();

        $model = $c['model'] ?? $c['model_text'];
        $isResponses = preg_match('/^(gpt-5|o\d)/i', $model);

        $maxTokens = 8000;

        // ---------- REQUEST ----------
        if ($isResponses) {
            $endpoint = 'https://api.openai.com/v1/responses';

            $body = [
                'model' => $model,
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [
                            ['type' => 'input_text', 'text' => 'Você é um gerador de artigos, focado em SEO GEO e E-E-A-T']
                        ]
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'input_text', 'text' => $prompt]
                        ]
                    ],
                ],
                'max_output_tokens' => 12000,
            ];
        } else {
            $endpoint = 'https://api.openai.com/v1/chat/completions';

            $body = [
                'model'       => $model,
                'temperature' => 1,
                'max_tokens'  => $maxTokens,
                'top_p'             => 0.95,
                'presence_penalty'  => $opts['presence_penalty'] ?? null,
                'frequency_penalty' => $opts['frequency_penalty'] ?? null,
                'messages'    => [
                    ['role' => 'system', 'content' => 'Você é um gerador de artigos, focado em SEO GEO e E-E-A-T'],
                    ['role' => 'user',   'content' => $prompt],
                ],
            ];

            $body = array_filter($body, fn($v) => $v !== null);
        }

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'timeout' => $c['timeout'],
            'body'    => wp_json_encode($body),
        ];

        $res = wp_remote_post($endpoint, $args);
        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);

        if ($code !== 200) {
            return new WP_Error(
                'pga_openai_http',
                $json['error']['message'] ?? 'Erro OpenAI',
                ['http_code' => $code]
            );
        }
        // ---------- EXTRAÇÃO UNIFICADA ----------
        $txt = '';

        if ($isResponses && !empty($json['output'])) {
            foreach ($json['output'] as $out) {
                foreach ($out['content'] ?? [] as $c) {
                    if (($c['type'] ?? '') === 'output_text') {
                        $txt .= (string)($c['text'] ?? '');
                    }
                }
            }
        } else {
            $txt = (string)($json['choices'][0]['message']['content'] ?? '');
        }

        $txt = trim($txt);
        // ---------- PARSE ----------

        // 🔴 REGRA DE OURO:
        // schema vazio = TEXTO LIVRE (HTML, texto editorial, etc)
        if (empty($schema)) {
            return [
                'content' => $txt,
            ];
        }

        // ⬇️ DAQUI PRA BAIXO: APENAS PARA JSON ESTRUTURADO
        $parsed = self::extract_json($txt);

        if (!$parsed) {
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

    // ---- helper para extrair JSON de respostas em texto/markdown ----
    private static function extract_json(string $raw): ?array
    {
        // remove lixo antes/depois do JSON
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $raw = $m[0];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
