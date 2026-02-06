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

    public static function outline(string $prompt)
    {
        $system = 'Você é um planejador de conteúdo SEO. ' .
            'Responda SOMENTE em JSON válido no formato {"sections":[...]}';

        $res = self::complete(
            $system . "\n\n" . $prompt,
            [],
        );

        if (is_wp_error($res)) {
            return $res;
        }

        if (empty($res['sections']) || !is_array($res['sections'])) {
            return new WP_Error(
                'pga_outline_parse',
                'Falha ao decodificar ESBOÇO.',
                ['response' => $res]
            );
        }

        return $res['sections'];
    }

    // ---- Completar texto (retorna array padronizado) ----
    public static function complete(string $prompt, array $schema = [], array $opts = [])
    {
        $c = self::cfg();

        $model = $c['model'] ?? $c['model_text'];
        $isResponses = preg_match('/^(gpt-5|o\d)/i', $model);

        $maxTokens = isset($opts['max_tokens'])
            ? (int) $opts['max_tokens']
            : (int) $c['max_tokens'];

        $temperature = $opts['temperature'] ?? $c['temperature'];


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
                'max_output_tokens' => 6000,
            ];
        } else {
            $endpoint = 'https://api.openai.com/v1/chat/completions';

            $body = [
                'model'       => $model,
                'temperature' => $opts['temperature'] ?? $c['temperature'],
                'max_tokens'  => $maxTokens,
                'top_p'             => $opts['top_p'] ?? null,
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
        $parsed = self::extract_json($txt);

        if (!$parsed) {
            if (stripos($txt, '<h2') !== false || stripos($txt, '<p') !== false) {
                return ['content' => $txt];
            }

            return new WP_Error(
                'pga_parse',
                'Falha ao decodificar JSON do modelo.',
                ['snippet' => mb_substr($txt, 0, 800)]
            );
        }

        return $parsed;
    }


    /**
     * Gera páginas de Web Stories usando a OpenAI (Responses API).
     *
     * @param string $prompt Prompt final (já montado pelo PluginsAlpha_Prompts)
     * @param array  $args   ['model' => '...', 'temperature' => 0.4, 'max_tokens' => 6000, ...]
     *
     * @return array|WP_Error
     *   Sucesso: ['pages' => [...], 'raw_json' => '...']
     */
    public static function generate_story_pages(string $prompt, array $args = [])
    {
        $c = self::cfg();
        if (!$c['key']) {
            return new WP_Error('alpha_ai_key', 'Configure sua OpenAI API Key nas Configurações.');
        }

        $model       = isset($args['model']) ? (string) $args['model'] : (string) $c['model_text'];
        $temperature = isset($args['temperature'])
            ? (float) $args['temperature']
            : (float) $c['temperature'];
        $max_tokens  = isset($args['max_tokens'])
            ? (int) $args['max_tokens']
            : 6000;

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
                'timeout' => $c['timeout'],
                'headers' => [
                    'Authorization' => 'Bearer ' . $c['key'],
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
                'OpenAI retornou ' . $code . ': ' .  $body
            );
        }

        $obj = json_decode((string) $body, true);
        if (!is_array($obj)) {
            return new WP_Error('alpha_ai_json', 'Resposta da OpenAI não é um JSON válido no topo.');
        }

        $status = $obj['status'] ?? ($obj['output'][0]['status'] ?? '');
        if ($status && $status !== 'completed') {
            return new WP_Error(
                'alpha_ai_incomplete',
                'OpenAI não conseguiu concluir o JSON (status: ' . $status . ').'
            );
        }

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

        if (!$data && preg_match('/\{.*\}/s', (string) $json_text, $m)) {
            $data = json_decode($m[0], true);
        }

        if (!$data || empty($data['pages']) || !is_array($data['pages'])) {
            return new WP_Error('alpha_ai_parse', 'Não consegui interpretar o JSON de páginas.');
        }

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

    // dentro da classe PluginsAlpha_OpenAI:

    public static function titles(string $prompt)
    {
        $system = 'Você é um gerador de TÍTULOS. ' .
            'Responda SOMENTE em JSON UTF-8 válido, sem markdown, ' .
            'no formato {"titles":["..."]}.';

        $res = self::complete(
            $system . "\n\n" . $prompt,
            [],
            [
                // limite baixo, títulos não precisam de muito
                'max_tokens'  => 6000,
                'temperature' => 0.6,
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        if (
            !is_array($res) ||
            empty($res['titles']) ||
            !is_array($res['titles'])
        ) {
            return new WP_Error(
                'pga_titles_parse',
                'Falha ao decodificar títulos.',
                ['response' => $res]
            );
        }

        $titles = array_values(
            array_filter(
                array_map('trim', $res['titles'])
            )
        );

        if (!$titles) {
            return new WP_Error('pga_no_titles', 'Sem títulos retornados.');
        }

        return $titles;
    }

    public static function meta_description(string $prompt)
    {
        $c = self::cfg();
        if (!$c['key']) {
            return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');
        }

        $system = "Você é um gerador de META DESCRIÇÕES para SEO. "
            . "Responda SOMENTE em JSON UTF-8 válido, sem markdown, "
            . "no formato {\"description\":\"...\"}.";

        $body = [
            'model'       => $c['model_text'],
            'temperature' => $c['temperature'],
            'max_tokens'  => min(600, $c['max_tokens']),
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $c['timeout'],
        ];

        $res  = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        if ($code !== 200) {
            $msg = 'HTTP ' . $code;
            $j = json_decode($raw, true);
            if (!empty($j['error']['message'])) {
                $msg = $j['error']['message'];
            }
            return new WP_Error('pga_openai_meta_http', $msg, ['http_code' => $code]);
        }

        $json = json_decode($raw, true);
        $txt  = (string)($json['choices'][0]['message']['content'] ?? '');

        $parsed = self::extract_json($txt);
        if (!is_array($parsed) || empty($parsed['description'])) {
            return new WP_Error('pga_meta_parse', 'Falha ao decodificar meta description.');
        }

        $desc = trim((string)$parsed['description']);
        if ($desc === '') {
            return new WP_Error('pga_meta_empty', 'Meta description vazia.');
        }

        return $desc;
    }

    public static function slug(string $prompt)
    {
        $c = self::cfg();
        if (!$c['key']) {
            return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');
        }

        $system = "Você é um gerador de SLUG para SEO. "
            . "Responda SOMENTE em JSON UTF-8 válido, sem markdown, "
            . "no formato {\"content\":\"...\"}.";

        $body = [
            'model'       => $c['model_text'],
            'temperature' => $c['temperature'],
            'max_tokens'  => min(600, $c['max_tokens']),
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $c['timeout'],
        ];

        $res  = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        if ($code !== 200) {
            $msg = 'HTTP ' . $code;
            $j = json_decode($raw, true);
            if (!empty($j['error']['message'])) {
                $msg = $j['error']['message'];
            }
            return new WP_Error('pga_openai_meta_http', $msg, ['http_code' => $code]);
        }

        $json = json_decode($raw, true);
        $txt  = (string)($json['choices'][0]['message']['content'] ?? '');

        $parsed = self::extract_json($txt);
        if (!is_array($parsed) || empty($parsed['content'])) {
            return new WP_Error('pga_slug', 'Falha ao decodificar Slug.');
        }

        $desc = trim((string)$parsed['slug']);
        if ($desc === '') {
            return new WP_Error('pga_slug_empty', 'Slug vazia.');
        }

        return $desc;
    }

    /**
     * Gera um PROMPT FINAL de imagem (não o meta-prompt),
     * no estilo do titles(): retorna só a string com o prompt.
     */
    public static function image_prompt(string $prompt)
    {
        $c = self::cfg();
        if (!$c['key']) {
            return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');
        }

        $system = "Você é um gerador de PROMPTS DE IMAGEM realistas. "
            . "Sua tarefa é transformar instruções em um ÚNICO prompt final "
            . "para gerar uma imagem, em uma linha, sem explicações. "
            . "Responda SOMENTE em JSON UTF-8 válido, sem markdown, "
            . "no formato {\"prompt\":\"...\"}.";

        $body = [
            'model'       => $c['model_text'],
            'temperature' => $c['temperature'],
            'max_tokens'  => min(600, $c['max_tokens']),
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $c['timeout'],
        ];

        $res  = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        if ($code !== 200) {
            $msg = 'HTTP ' . $code;
            $j = json_decode($raw, true);
            if (!empty($j['error']['message'])) {
                $msg = $j['error']['message'];
            }
            return new WP_Error('pga_openai_image_http', $msg, ['http_code' => $code]);
        }

        $json = json_decode($raw, true);
        $txt  = (string)($json['choices'][0]['message']['content'] ?? '');

        $parsed = self::extract_json($txt);
        if (!is_array($parsed) || empty($parsed['prompt'])) {
            return new WP_Error('pga_image_prompt_parse', 'Falha ao decodificar prompt de imagem.');
        }

        $imgPrompt = trim((string)$parsed['prompt']);
        if ($imgPrompt === '') {
            return new WP_Error('pga_image_prompt_empty', 'Prompt de imagem vazio.');
        }

        return $imgPrompt;
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
