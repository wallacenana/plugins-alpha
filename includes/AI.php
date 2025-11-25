<?php
if (!defined('ABSPATH')) exit;

/**
 * Cliente de IA genérico do Plugins Alpha
 * - Aqui ficam TODAS as chamadas de IA (OpenAI, Anthropic, etc.)
 * - Cada "coisa" (stories, títulos, calendário, etc) vira um método
 */
class PluginsAlpha_AI
{
    /**
     * Provider padrão (pode virar opção no futuro)
     */
    public static function get_provider(): string
    {
        // Exemplo: poderia vir de uma opção global do Plugins Alpha
        // $opts = PluginsAlpha_Settings::get();
        // return $opts['apis']['provider'] ?? 'openai';
        return 'openai';
    }

    public static function get_api_key(): string
    {
        // Reusa a lógica existente no Helpers
        return PluginsAlpha_Helpers::alpha_ai_get_api_key();
    }

    public static function get_model(): string
    {
        return PluginsAlpha_Helpers::alpha_ai_get_model();
    }

    public static function get_temperature(): float
    {
        return PluginsAlpha_Helpers::alpha_ai_get_temperature();
    }

    /**
     * Ponto de entrada genérico para gerar páginas de Web Stories
     * - Aqui fazemos o switch de provider
     *
     * @param string $prompt Texto final já montado (via PluginsAlpha_Prompts)
     * @param array  $args   ['model' => '...', 'temperature' => 0.4, 'max_tokens' => 6000, ...]
     * @return array|WP_Error
     *   Sucesso: ['pages' => [...], 'raw_json' => '...']
     */
    public static function generate_story_pages(string $prompt, array $args = [])
    {
        $provider = $args['provider'] ?? self::get_provider();

        switch ($provider) {
            case 'openai':
            default:
                return self::generate_story_pages_openai($prompt, $args);
        }
    }

    /**
     * Implementação específica para OpenAI (Responses API)
     */
    protected static function generate_story_pages_openai(string $prompt, array $args = [])
    {
        $key  = self::get_api_key();
        if (!$key) {
            return new WP_Error('alpha_ai_key', 'Configure sua OpenAI API Key nas Configurações.');
        }

        $model       = $args['model'] ?? self::get_model();
        $temperature = isset($args['temperature'])
            ? (float) $args['temperature']
            : self::get_temperature();
        $max_tokens  = isset($args['max_tokens']) ? (int) $args['max_tokens'] : 6000;

        $payload = [
            'model' => $model,
            'input' => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_object',
                ],
            ],
            'temperature'       => $temperature,
            'max_output_tokens' => $max_tokens,
        ];

        $res = wp_remote_post(
            'https://api.openai.com/v1/responses',
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($payload),
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);

        if (200 !== $code) {
            return new WP_Error(
                'alpha_ai_http',
                'OpenAI retornou ' . $code . ': ' . substr((string) $body, 0, 300)
            );
        }

        $obj = json_decode((string) $body, true);
        if (!is_array($obj)) {
            return new WP_Error('alpha_ai_json', 'Resposta da OpenAI não é um JSON válido no topo.');
        }

        // status pode vir no topo ou dentro de output[0]
        $status = $obj['status'] ?? ($obj['output'][0]['status'] ?? '');
        if ($status && $status !== 'completed') {
            return new WP_Error(
                'alpha_ai_incomplete',
                'OpenAI não conseguiu concluir o JSON (status: ' . $status . ').'
            );
        }

        // Extrai texto do output (Responses API)
        $json_text = '';
        if (!empty($obj['output'][0]['content'])) {
            foreach ($obj['output'][0]['content'] as $chunk) {
                if (!empty($chunk['text'])) {
                    $json_text .= $chunk['text'];
                }
                if (!empty($chunk['raw'])) {
                    $json_text .= $chunk['raw'];
                }
            }
        } elseif (!empty($obj['output_text'])) {
            $json_text = $obj['output_text'];
        }

        $data = json_decode($json_text, true);

        // Fallback: tenta pegar só o primeiro {...} se vier embrulhado
        if (!$data && preg_match('/\{.*\}/s', (string) $json_text, $m)) {
            $data = json_decode($m[0], true);
        }

        if (!$data || empty($data['pages']) || !is_array($data['pages'])) {
            return new WP_Error('alpha_ai_parse', 'Não consegui interpretar o JSON de páginas.');
        }

        // Normaliza páginas (sem sanitizar ainda; isso fica no Helpers)
        $pages = [];
        foreach ($data['pages'] as $p) {
            $pages[] = [
                'heading'  => isset($p['heading']) ? (string) $p['heading'] : '',
                'body'     => isset($p['body']) ? (string) $p['body'] : '',
                'cta_text' => isset($p['cta_text']) ? (string) $p['cta_text'] : '',
                'cta_url'  => isset($p['cta_url']) ? (string) $p['cta_url'] : '',
                'prompt'   => isset($p['prompt']) ? (string) $p['prompt'] : '',
            ];
        }

        return [
            'pages'    => $pages,
            'raw_json' => $json_text,
        ];
    }

    /**
     * EXEMPLO de como você pode criar outras funções depois:
     *
     * public static function generate_blog_titles(string $prompt, array $args = []) { ... }
     * public static function generate_calendar(string $prompt, array $args = []) { ... }
     *
     * Sempre com o mesmo esquema: switch de provider dentro.
     */
}
