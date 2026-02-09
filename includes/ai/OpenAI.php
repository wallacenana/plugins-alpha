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

        $presencePenalty = $args['presence_penalty']
            ?? ($isStructured ? 0.0 : 0.7);

        $frequencyPenalty = $args['frequency_penalty']
            ?? ($isStructured ? 0.0 : 0.9);

        // 🔴 MODO JSON HARD
        $systemPrompt = $isStructured
            ? "Você deve responder APENAS com JSON válido UTF-8.
Não use markdown.
Não use aspas tipográficas.
Não quebre linhas dentro de strings.
Não inclua texto fora do JSON.
Não explique nada."
            : "Você é um gerador de artigos focado em SEO GEO e E-E-A-T.";

        $body = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'top_p' => $topP,
            'presence_penalty' => $presencePenalty,
            'frequency_penalty' => $frequencyPenalty,
            'messages' => [
                ['role' => 'system', 'content' => trim($systemPrompt)],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];

        // 🧯 STOP defensivo (evita vazamento de texto fora do JSON)
        if ($isStructured) {
            $body['stop'] = ["\n\n", "\n```"];
        }

        $argsReq = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'timeout' => $c['timeout'] ?? 60,
            'body'    => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        $res = wp_remote_post('https://api.openai.com/v1/chat/completions', $argsReq);
        if (is_wp_error($res)) {
            return $res;
        }

        $raw = wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);

        if (!isset($json['choices'][0]['message']['content'])) {
            return new WP_Error(
                'pga_openai_invalid',
                'Resposta inválida da IA.',
                ['raw' => $raw]
            );
        }

        $txt = trim((string)$json['choices'][0]['message']['content']);

        // 🔥 PARSE CONTROLADO
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
