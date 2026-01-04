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
    private static function call_gemini(string $system, string $userPrompt, array $args = [])
    {
        $c = self::cfg();
        if (empty($c['key'])) {
            return new WP_Error('pga_no_key', 'Chave Gemini não configurada.');
        }

        $model       = isset($args['model']) ? (string) $args['model'] : $c['model_text'];
        $temperature = isset($args['temperature']) ? (float) $args['temperature'] : $c['temperature'];
        $maxTokens   = isset($args['max_tokens']) ? (int) $args['max_tokens'] : $c['max_tokens'];

        $body = [
            'systemInstruction' => [
                'role'  => 'system',
                'parts' => [
                    ['text' => $system],
                ],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $userPrompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => $temperature,
                'maxOutputTokens' => $maxTokens,
                // se quiser forçar JSON:
                // 'responseMimeType' => 'application/json',
            ],
        ];

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($model),
            rawurlencode($c['key'])
        );

        $args_http = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => $c['timeout'],
        ];

        $res = wp_remote_post($endpoint, $args_http);
        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);

        if ($code < 200 || $code >= 300) {
            $msg = 'HTTP ' . $code;
            $j   = json_decode($raw, true);
            if (!empty($j['error']['message'])) {
                $msg = $j['error']['message'];
            }

            $err = new WP_Error(
                'pga_gemini_http',
                $msg,
                [
                    'http_code'    => $code,
                    'body_snippet' => substr((string) $raw, 0, 800),
                ]
            );

            return $err;
        }

        $json = json_decode($raw, true);

        // Junta todos os "parts" de texto do primeiro candidato
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

    public static function outline(string $prompt, array $args = [])
    {
        $system = "Você é um planejador de conteúdo SEO. "
            . "Responda SEMPRE SOMENTE em JSON UTF-8 válido, no formato {\"sections\":[...]}.";

        $txt = self::call_gemini($system, $prompt, [
            // você pode deixar o helper usar defaults,
            // ou passar overrides vindos de $args:
            'model'       => $args['model']       ?? null,
            'temperature' => $args['temperature'] ?? null,
            'max_tokens'  => min(3000, $args['max_tokens'] ?? 3000),
        ]);

        if (is_wp_error($txt)) {
            return $txt;
        }

        $parsed = self::extract_json($txt);
        if (!is_array($parsed) || empty($parsed['sections']) || !is_array($parsed['sections'])) {
            return new WP_Error(
                'pga_outline_parse',
                'Falha ao decodificar ESBOÇO.',
                [
                    'snippet' => mb_substr($txt, 0, 800),
                ]
            );
        }

        return $parsed['sections'];
    }
    // -----------------------------------------------------------------
    // complete() USANDO O HELPER (compatível com AI::complete)
    // -----------------------------------------------------------------
    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $defaultSchema = [
            'content'            => 'string',
        ];
        $schema = $schema ?: $defaultSchema;

        $system = 'Você é um gerador de artigos, focado em SEO GEO e E-E-A-T';

        $txt = self::call_gemini($system, $prompt, [
            'model'       => $args['model']       ?? null,
            'temperature' => $args['temperature'] ?? null,
            'max_tokens'  => $args['max_tokens']  ?? null,
        ]);

        if (is_wp_error($txt)) {
            return $txt;
        }

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
     * Gera páginas de Web Stories usando o Gemini (Responses API).
     *
     * @param string $prompt Prompt final (já montado pelo PluginsAlpha_Prompts)
     * @param array  $args   ['model' => '...', 'temperature' => 0.4, 'max_tokens' => 6000, ...]
     *
     * @return array|WP_Error
     *   Sucesso: ['pages' => [...], 'raw_json' => '...']
     */
    public static function generate_story_pages(string $prompt, array $args = [])
    {
        // Regras para o modelo (formato JSON com pages[])
        $system = "Você é um gerador de páginas para Web Stories. "
            . "Responda SEMPRE SOMENTE em JSON UTF-8 válido, sem markdown, "
            . "no formato {\"pages\":[{\"heading\":\"...\",\"body\":\"...\",\"cta_text\":\"...\",\"cta_url\":\"...\",\"prompt\":\"...\"}, ...]}.";

        // Chama o helper genérico do Gemini
        $txt = self::call_gemini($system, $prompt, [
            // overrides opcionais vindos de $args
            'model'       => $args['model']       ?? null,
            'temperature' => $args['temperature'] ?? null,
            'max_tokens'  => $args['max_tokens']  ?? 6000,
        ]);

        if (is_wp_error($txt)) {
            return $txt;
        }

        // Aqui, diferentemente do Gemini /v1/responses, o helper já devolve
        // todo o texto concatenado em $txt
        $json_text = (string) $txt;

        // Tenta decodificar direto
        $data = json_decode($json_text, true);

        // Fallback: tenta extrair só o objeto JSON de dentro do texto
        if (!$data && preg_match('/\{.*\}/s', $json_text, $m)) {
            $data = json_decode($m[0], true);
        }

        if (!$data || empty($data['pages']) || !is_array($data['pages'])) {
            return new WP_Error(
                'alpha_ai_parse',
                'Não consegui interpretar o JSON de páginas.',
                ['snippet' => mb_substr($json_text, 0, 800)]
            );
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


    // -----------------------------------------------------------------
    // TÍTULOS (Gemini) usando o helper call_gemini()
    // -----------------------------------------------------------------
    public static function titles(string $prompt)
    {
        $c = self::cfg();
        if (empty($c['key'])) {
            return new WP_Error('pga_no_key', 'Chave Gemini não configurada.');
        }

        $system = "Você é um gerador de TÍTULOS. "
            . "Responda SOMENTE em JSON UTF-8 válido, sem markdown, "
            . "no formato {\"titles\":[\"...\"]}.";

        // limita tokens (similar ao Gemini: min(1200, max_tokens))
        $maxTokens = min(1200, (int) ($c['max_tokens'] ?? 1200));

        $txt = self::call_gemini($system, $prompt, [
            'max_tokens' => $maxTokens,
        ]);

        if (is_wp_error($txt)) {
            return $txt;
        }

        // mesmo helper que você usa no Gemini; copie pra cá se ainda não tiver
        $parsed = self::extract_json($txt);

        if (!is_array($parsed) || empty($parsed['titles']) || !is_array($parsed['titles'])) {
            return new WP_Error('pga_titles_parse', 'Falha ao decodificar títulos.');
        }

        $titles = array_values(array_filter(array_map('trim', $parsed['titles'])));
        if (!$titles) {
            return new WP_Error('pga_no_titles', 'Sem títulos retornados.');
        }

        return $titles;
    }

    // -----------------------------------------------------------------
    // META DESCRIPTION (Gemini) usando o helper call_gemini()
    // -----------------------------------------------------------------
    public static function meta_description(string $prompt)
    {
        $c = self::cfg();
        if (empty($c['key'])) {
            return new WP_Error('pga_no_key', 'Chave Gemini não configurada.');
        }

        $system = "Você é um gerador de META DESCRIÇÕES para SEO. "
            . "Responda SOMENTE em JSON UTF-8 válido, sem markdown, "
            . "no formato {\"description\":\"...\"}.";

        $maxTokens = min(600, (int) ($c['max_tokens'] ?? 600));

        $txt = self::call_gemini($system, $prompt, [
            'max_tokens' => $maxTokens,
        ]);

        if (is_wp_error($txt)) {
            return $txt;
        }

        $parsed = self::extract_json($txt);
        if (!is_array($parsed) || empty($parsed['description'])) {
            return new WP_Error('pga_meta_parse', 'Falha ao decodificar meta description.');
        }

        $desc = trim((string) $parsed['description']);
        if ($desc === '') {
            return new WP_Error('pga_meta_empty', 'Meta description vazia.');
        }

        return $desc;
    }

    public static function slug(string $prompt)
    {
        $c = self::cfg();
        if (empty($c['key'])) {
            return new WP_Error('pga_no_key', 'Chave Gemini não configurada.');
        }

        $system = "Você é um gerador de META DESCRIÇÕES para SEO. "
            . "Responda SOMENTE em JSON UTF-8 válido, sem markdown, "
            . "no formato {\"content\":\"...\"}.";

        $maxTokens = min(600, (int) ($c['max_tokens'] ?? 600));

        $txt = self::call_gemini($system, $prompt, [
            'max_tokens' => $maxTokens,
        ]);

        if (is_wp_error($txt)) {
            return $txt;
        }

        $parsed = self::extract_json($txt);
        if (!is_array($parsed) || empty($parsed['content'])) {
            return new WP_Error('pga_slug', 'Falha ao decodificar slug.');
        }

        $desc = trim((string) $parsed['content']);
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
        if (empty($c['key'])) {
            return new WP_Error('pga_no_key', 'Chave Gemini não configurada.');
        }

        $system = "Você é um gerador de PROMPTS DE IMAGEM realistas. "
            . "Sua tarefa é transformar instruções em um ÚNICO prompt final "
            . "para gerar uma imagem, em uma linha, sem explicações. "
            . "Responda SOMENTE em JSON UTF-8 válido, sem markdown, "
            . "no formato {\"prompt\":\"...\"}.";

        $maxTokens = min(600, (int) ($c['max_tokens'] ?? 600));

        $txt = self::call_gemini($system, $prompt, [
            'max_tokens' => $maxTokens,
        ]);

        if (is_wp_error($txt)) {
            return $txt;
        }

        $parsed = self::extract_json($txt);
        if (!is_array($parsed) || empty($parsed['prompt'])) {
            return new WP_Error('pga_image_prompt_parse', 'Falha ao decodificar prompt de imagem.');
        }

        $imgPrompt = trim((string) $parsed['prompt']);
        if ($imgPrompt === '') {
            return new WP_Error('pga_image_prompt_empty', 'Prompt de imagem vazio.');
        }

        return $imgPrompt;
    }


    // ---- helper para extrair JSON de respostas em texto/markdown ----
    private static function extract_json(string $txt)
    {
        if (preg_match('/```json\s*(.+?)```/is', $txt, $m)) {
            $a = json_decode(trim($m[1]), true);
            if (is_array($a)) return $a;
        }
        $a = json_decode(trim($txt), true);
        if (is_array($a)) return $a;
        $start = strpos($txt, '{');
        $end = strrpos($txt, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $chunk = substr($txt, $start, $end - $start + 1);
            $a = json_decode($chunk, true);
            if (is_array($a)) return $a;
        }
        return null;
    }
}
