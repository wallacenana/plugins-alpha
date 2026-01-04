<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_OpenAI
{

    // ---- Lê config do plugin ----
    private static function cfg(): array
    {
        if (!class_exists('PluginsAlpha_Settings')) {
            return [
                'key'          => trim(get_option('alpha_orion_posts_openai_key', '')),
                'model_text'   => get_option('alpha_orion_posts_model_text', 'gpt-4o-mini'),
                'model_image'  => get_option('alpha_orion_posts_model_image', 'gpt-image-1'),
                'temperature'  => (float) get_option('alpha_orion_posts_temperature', 0.6),
                'max_tokens'   => (int) get_option('alpha_orion_posts_max_tokens', 6000),
                'timeout'      => 60,
            ];
        }

        $opt = PluginsAlpha_Settings::get();
        $oa  = $opt['apis']['openai'] ?? [];

        return [
            'key'          => trim((string) ($oa['key'] ?? '')),
            'model_text'   => (string) ($oa['model_text']  ?? 'gpt-4o-mini'),
            'model_image'  => (string) ($oa['model_image'] ?? 'gpt-image-1'),
            'temperature'  => (float)  ($oa['temperature'] ?? 0.6),
            'max_tokens'   => (int)    ($oa['max_tokens']  ?? 6000),
            'timeout'      => 60,
        ];
    }

    public static function is_configured(): bool
    {
        $c = self::cfg();
        return $c['key'] !== '';
    }

    public static function outline(string $prompt)
    {
        $c = self::cfg();
        if (!$c['key']) {
            return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');
        }

        $system = "Você é um planejador de conteúdo SEO. " .
            "Responda SEMPRE SOMENTE em JSON UTF-8 válido, no formato {\"sections\":[...]}.";

        $body = [
            'model'       => $c['model_text'],
            'temperature' => $c['temperature'],
            'max_tokens'  => min(3000, $c['max_tokens']),
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

        $res = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);

        if ($code !== 200) {
            $msg = 'HTTP ' . $code;
            $j   = json_decode($raw, true);
            if (!empty($j['error']['message'])) {
                $msg = $j['error']['message'];
            }
            return new WP_Error('pga_openai_http_outline', $msg, ['http_code' => $code]);
        }

        $json = json_decode($raw, true);
        $txt  = (string)($json['choices'][0]['message']['content'] ?? '');

        $parsed = self::extract_json($txt);
        if (!is_array($parsed) || empty($parsed['sections']) || !is_array($parsed['sections'])) {

            // manda um pedacinho da resposta pro data, igual fizemos nas seções
            return new WP_Error(
                'pga_outline_parse',
                'Falha ao decodificar ESBOÇO.',
                [
                    'snippet' => mb_substr($txt, 0, 800), // primeiros 800 chars
                ]
            );
        }

        return $parsed['sections'];
    }

    // ---- Completar texto (retorna array padronizado) ----
    public static function complete(string $prompt, array $schema = [], array $opts = [])
    {
        $c = self::cfg();

        $defaultSchema = ['content' => 'string'];
        $schema = $schema ?: $defaultSchema;

        $body = [
            'model'       => $c['model_text'],
            'temperature' => $opts['temperature'] ?? $c['temperature'],
            'max_tokens'  => $opts['max_tokens'] ?? $c['max_tokens'],
            // ✅ overrides úteis pra diversidade
            'top_p'              => $opts['top_p'] ?? null,
            'presence_penalty'   => $opts['presence_penalty'] ?? null,
            'frequency_penalty'  => $opts['frequency_penalty'] ?? null,

            'messages'    => [
                ['role' => 'system', 'content' => 'Você é um gerador de artigos, focado em SEO GEO e E-E-A-T'],
                ['role' => 'user',   'content' => $prompt],
            ],
        ];

        // remove nulls (OpenAI não curte null em alguns campos)
        $body = array_filter($body, fn($v) => $v !== null);

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $c['key'],
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $c['timeout'],
        ];

        $res  = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        if ($code !== 200) {
            $msg = 'HTTP ' . $code;
            $j   = json_decode($raw, true);
            if (isset($j['error']['message'])) {
                $msg = $j['error']['message'];
            }

            $err = new WP_Error(
                'pga_openai_http',
                $msg,
                [
                    'http_code'    => $code,
                    'body_snippet' => substr((string)$raw, 0, 800),
                ]
            );

            return $err;
        }

        $json = json_decode($raw, true);
        $txt  = (string)($json['choices'][0]['message']['content'] ?? '');

        $parsed = self::extract_json($txt);
        if (!$parsed) {
            // fallback: se vier HTML direto, aceita como content
            $html = trim($txt);

            if ($html !== '' && (stripos($html, '<p') !== false || stripos($html, '<h2') !== false || stripos($html, '<h3') !== false)) {
                return ['content' => $html];
            }

            return new WP_Error(
                'pga_parse',
                'Falha ao decodificar JSON do modelo.',
                ['snippet' => substr($txt, 0, 800)]
            );
        }

        return [
            'content'            => trim((string)($parsed['content'] ?? '')),
        ];
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
        $c = self::cfg();
        if (!$c['key']) return new WP_Error('pga_no_key', 'Chave OpenAI não configurada.');

        $system = "Você é um gerador de TÍTULOS. Responda SOMENTE em JSON UTF-8 válido, sem markdown, no formato {\"titles\":[\"...\"]}.";

        $body = [
            'model'       => $c['model_text'],
            'temperature' => $c['temperature'],
            'max_tokens'  => min(1200, $c['max_tokens']),
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
        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        if ($code !== 200) {
            $msg = 'HTTP ' . $code;
            $j = json_decode($raw, true);
            if (!empty($j['error']['message'])) $msg = $j['error']['message'];
            return new WP_Error('pga_openai_titles_http', $msg, ['http_code' => $code]);
        }

        $json = json_decode($raw, true);
        $txt  = (string)($json['choices'][0]['message']['content'] ?? '');
        $parsed = self::extract_json($txt);
        if (!is_array($parsed) || empty($parsed['titles']) || !is_array($parsed['titles'])) {
            return new WP_Error('pga_titles_parse', 'Falha ao decodificar títulos.');
        }
        $titles = array_values(array_filter(array_map('trim', $parsed['titles'])));
        if (!$titles) return new WP_Error('pga_no_titles', 'Sem títulos retornados.');
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
    private static function extract_json(string $txt)
    {
        $txt = trim($txt);

        // 1) se vier em ```json ... ```
        if (preg_match('/```json\s*(.+?)```/is', $txt, $m)) {
            $inner = trim($m[1]);
            $a = json_decode($inner, true);
            if (is_array($a)) return self::decode_escaped_unicode_recursive($a);

            // tenta sanitizar escapes inválidos e decodificar
            $inner2 = preg_replace('/\\\\(?!["\\\\\/bfnrtu]|u[0-9a-fA-F]{4})/', '\\\\\\\\', $inner);
            $a = json_decode($inner2, true);
            if (is_array($a)) return self::decode_escaped_unicode_recursive($a);

            // fallback: extrai "content" na marra
            if (preg_match('/"content"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s', $inner2, $mm)) {
                $content = stripcslashes($mm[1]);
                return ['content' => $content];
            }
        }

        // 2) tenta JSON direto
        $a = json_decode($txt, true);
        if (is_array($a)) return self::decode_escaped_unicode_recursive($a);

        // 3) tenta com escapes inválidos corrigidos (ex.: \d -> \\d)
        $txt2 = preg_replace('/\\\\(?!["\\\\\/bfnrtu]|u[0-9a-fA-F]{4})/', '\\\\\\\\', $txt);
        $a = json_decode($txt2, true);
        if (is_array($a)) return self::decode_escaped_unicode_recursive($a);

        // 4) tenta recortar o primeiro {...} e repetir as tentativas
        $start = strpos($txt, '{');
        $end   = strrpos($txt, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $chunk = substr($txt, $start, $end - $start + 1);

            $a = json_decode($chunk, true);
            if (is_array($a)) return self::decode_escaped_unicode_recursive($a);

            $chunk2 = preg_replace('/\\\\(?!["\\\\\/bfnrtu]|u[0-9a-fA-F]{4})/', '\\\\\\\\', $chunk);
            $a = json_decode($chunk2, true);
            if (is_array($a)) return self::decode_escaped_unicode_recursive($a);

            if (preg_match('/"content"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s', $chunk2, $mm)) {
                $content = stripcslashes($mm[1]);
                return ['content' => $content];
            }
        }

        // 5) último fallback: se tiver "content": "..." em qualquer lugar
        if (preg_match('/"content"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s', $txt2, $mm)) {
            $content = stripcslashes($mm[1]);
            return ['content' => $content];
        }

        return null;
    }


    /**
     * Recursively decode any JSON-style unicode escapes (e.g. "\\u00e7")
     * found inside string values of an array/object produced from model output.
     */
    private static function decode_escaped_unicode_recursive($val)
    {
        if (is_array($val)) {
            foreach ($val as $k => $v) {
                $val[$k] = self::decode_escaped_unicode_recursive($v);
            }
            return $val;
        }

        if (!is_string($val)) return $val;

        // Quick check for common pattern like "\u00e7"
        if (preg_match('/\\\\u[0-9a-fA-F]{4}/', $val)) {
            // Attempt to decode via json_decode on a quoted string
            $try = json_decode('"' . addcslashes($val, '"') . '"');
            if (is_string($try)) return $try;
        }

        return $val;
    }
}
