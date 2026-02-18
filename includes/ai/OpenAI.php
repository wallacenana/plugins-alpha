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
            'timeout'      => 500,
        ];
    }

    public static function is_configured(): bool
    {
        $c = self::cfg();
        return $c['key'] !== '';
    }

    // ---- Completar texto (retorna array padronizado) ----
    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $c = self::cfg();

        $model = $c['model'] ?? $c['model_text'];
        $isStructured = !empty($schema);
        $useSearch = !empty($args['use_search']);

        // 🔒 defaults seguros
        $maxTokens = $args['max_tokens']
            ?? ($isStructured ? 1800 : 8000);

        $temperature = $args['temperature']
            ?? ($isStructured ? 0.45 : 0.6);

        $topP = $args['top_p']
            ?? ($isStructured ? 0.7 : 0.95);

        $presencePenalty = $args['presence_penalty']
            ?? ($isStructured ? 0 : 0.9);

        $frequencyPenalty = $args['frequency_penalty']
            ?? ($isStructured ? 0 : 0.9);

        // 🔴 SYSTEM PROMPT
        $systemPrompt = $isStructured
            ? "Você deve responder APENAS com JSON válido UTF-8.\n"
            . "Não use markdown.\n"
            . "Não use aspas tipográficas.\n"
            . "Não quebre linhas dentro de strings.\n"
            . "Não inclua texto fora do JSON.\n"
            . "Não explique nada."
            : "Você é um gerador de artigos focado em SEO GEO e E-E-A-T.";

        // 🧠 BODY (Responses API)
        $body = [
            "model" => $model,
            "max_output_tokens" => $maxTokens,
            "temperature" => $temperature,
            "top_p" => $topP,
            "presence_penalty" => $presencePenalty,
            "frequency_penalty" => $frequencyPenalty,
            "input" => [
                [
                    "role" => "system",
                    "content" => trim($systemPrompt),
                ],
                [
                    "role" => "user",
                    "content" => $prompt,
                ]
            ],
        ];
        $body["tools"] = [
            ["type" => "web_search"]
        ];
        $body["tool_choice"] = "auto";


        $argsReq = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'timeout' => $c['timeout'] ?? 60,
            'body'    => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $res = wp_remote_post('https://api.openai.com/v1/responses', $argsReq);

        if (is_wp_error($res)) {
            return $res;
        }

        $raw = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);

        // 🔍 Extração robusta do texto
        $txt = '';

        // 1️⃣ caminho fácil (às vezes vem pronto)
        if (!empty($json['output_text'])) {
            $txt = $json['output_text'];
        }

        // 2️⃣ caminho robusto
        if (!$txt && !empty($json['output']) && is_array($json['output'])) {
            foreach ($json['output'] as $item) {
                if (
                    isset($item['type']) &&
                    $item['type'] === 'message' &&
                    !empty($item['content'][0]['text'])
                ) {
                    $txt = $item['content'][0]['text'];
                    break;
                }
            }
        }

        error_log(print_r($txt, true));

        if (!$txt) {
            return new WP_Error(
                'pga_openai_invalid',
                'Resposta inválida da IA.',
                ['raw' => $raw]
            );
        }

        $txt = trim((string)$txt);

        // 🔥 MODO TEXTO NORMAL
        if (!$isStructured) {
            return ['content' => $txt];
        }

        // 🔥 PARSE CONTROLADO JSON
        if (preg_match('/\{.*\}/s', $txt, $m)) {
            $txt = $m[0];
        }

        $parsed = json_decode($txt, true);

        if (!is_array($parsed))
            return $parsed = $txt;

        // 🔒 valida contrato mínimo
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
