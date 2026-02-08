<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_AI
{
    // ------------------------------------------------------------
    // BASE: leitura de settings (1 lugar só)
    // ------------------------------------------------------------
    private static function settings(): array
    {
        // Fallback legado (antes do PluginsAlpha_Settings existir)
        if (!class_exists('PluginsAlpha_Settings')) {
            return [
                'apis' => [
                    'openai' => [
                        'key'         => trim(get_option('alpha_orion_posts_openai_key', '')),
                        'model_text'  => get_option('alpha_orion_posts_model_text', 'gpt-4o-mini'),
                        'model_image' => get_option('alpha_orion_posts_model_image', 'gpt-image-1'),
                        'temperature' => (float) get_option('alpha_orion_posts_temperature', 0.6),
                        'max_tokens'  => (int) get_option('alpha_orion_posts_max_tokens', 6000),
                    ],
                    // Gemini não existia no legado → vazio por padrão
                    'gemini' => [
                        'key'         => '',
                        'model_text'  => 'gemini-1.5-pro',
                        'temperature' => 0.6,
                        'max_tokens'  => 6000,
                    ],
                ],
                'orion_posts' => [
                    'text_provider'   => 'openai',
                    'images_provider' => 'pollinations',
                ],
            ];
        }

        return PluginsAlpha_Settings::get();
    }

    public static function resolve_provider(string $provider)
    {
        // Normaliza
        $provider = strtolower(trim($provider));

        $map = [
            'openai'     => 'PluginsAlpha_OpenAI',
            'gemini'     => 'PluginsAlpha_Gemini',
            'perplexity' => 'PluginsAlpha_Perplexity',
            'claude'     => 'PluginsAlpha_Claude',
            'mistral'    => 'PluginsAlpha_Mistral',
            'cohere'     => 'PluginsAlpha_Cohere',
        ];


        if (!isset($map[$provider])) {
            return new WP_Error('pga_invalid_provider', "Provider desconhecido: $provider");
        }

        $class = $map[$provider];

        if (!class_exists($class)) {
            return new WP_Error('pga_missing_class', "Classe do provider não encontrada: $class");
        }

        return $class;
    }


    /**
     * Provider de TEXTO (OpenAI, Gemini...)
     * - Lê de: pga_settings[orion_posts][text_provider]
     * - Default: openai
     */
    public static function get_text_provider($format = 'orion_posts'): string
    {
        $provider = 'openai';

        if (class_exists('PluginsAlpha_Settings')) {
            $opts   = PluginsAlpha_Settings::get();
            $bucket = $opts[$format] ?? [];

            if (!empty($bucket['text_provider'])) {
                $candidate = (string)$bucket['text_provider'];
                if (in_array($candidate, ['openai', 'gemini', 'claude', 'mistral', 'cohere', 'perplexity'], true)) {
                    $provider = $candidate;
                }
            }
        }

        return $provider;
    }

    /**
     * @param string $prompt
     * @param array  $schema
     * @param array  $args
     * @return array|WP_Error
     *
     * @psalm-suppress UndefinedMethod
     * @phpstan-ignore-next-line
     */
    public static function complete(string $prompt, array $schema = [], array $args = [])
    {
        $format = (string)($args['format'] ?? '');

        $provider = $args['provider']
            ?? self::get_text_provider($format ?: 'orion_posts');

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<PluginsAlpha_OpenAI|PluginsAlpha_Gemini>
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 args vão crus, sem mexer
        return $class::complete($prompt, $schema, $args);
    }


    /**
     * Provider de IMAGEM (Pollinations, OpenAI, Pexels, Unsplash...)
     * - Lê de: pga_settings[orion_posts][images_provider]
     * - Default: pollinations
     */
    public static function get_image_provider(array $overrideArgs = []): string
    {
        if (!empty($overrideArgs['image_provider'])) {
            return (string) $overrideArgs['image_provider'];
        }

        $opts  = self::settings();
        $orion = $opts['orion_posts'] ?? [];

        $prov = isset($orion['images_provider']) ? (string) $orion['images_provider'] : 'pollinations';

        if (!in_array($prov, ['pollinations', 'openai', 'pexels', 'unsplash', 'none'], true)) {
            $prov = 'pollinations';
        }

        return $prov;
    }

    /**
     * Garante que o provider de TEXTO está configurado.
     * Se não tiver chave → WP_Error.
     *
     * @param string $provider
     * @return true|WP_Error
     */
    private static function ensure_text_provider(string $provider)
    {
        $class = self::resolve_provider($provider);

        if (is_wp_error($class)) {
            return $class;
        }

        return true;
    }

    public static function faq(array $args)
    {
        $provider = $args['provider'] ?? self::get_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) return $ok;

        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) return $class;

        $keyword = trim((string)($args['keyword'] ?? ''));
        if ($keyword === '') {
            return new WP_Error('pga_faq_kw', 'Keyword inválida para FAQ.');
        }

        $qty    = min(5, max(1, (int)($args['qty'] ?? 3)));
        $locale = $args['locale'] ?? 'pt_BR';

        // PROMPT enxuto e determinístico
        $prompt = PluginsAlpha_Prompts::build_faq_prompt([
            'keyword' => $keyword,
            'qty'     => $qty,
            'locale'  => $locale,
        ]);

        // SCHEMA força JSON-LD válido
        $schema = [
            '@context'   => 'string',
            '@type'      => 'string',
            'mainEntity' => [
                [
                    '@type' => 'string',
                    'name'  => 'string',
                    'acceptedAnswer' => [
                        '@type' => 'string',
                        'text'  => 'string',
                    ],
                ],
            ],
        ];

        $result = $class::complete($prompt, $schema);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result; // JSON-LD PRONTO
    }

    // ------------------------------------------------------------
    // PONTO ÚNICO: gerar TEXTO genérico (Orion, Stories etc.)
    // ------------------------------------------------------------

    public static function titles(string $prompt, array $args = [])
    {
        $provider = $args['provider'] ?? self::get_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<PluginsAlpha_OpenAI|PluginsAlpha_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 contrato
        $schema = [
            'titles' => 'array',
        ];

        // 🔹 opções centralizadas
        $opts = [
            'template'    => 'titles',
            'temperature' => $args['temperature'] ?? 0.6,
            'max_tokens'  => $args['max_tokens'] ?? 600,
            'provider'    => $provider,
        ];

        // 🔥 chamada única
        $resp = $class::complete($prompt, $schema, $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        if (
            !is_array($resp) ||
            empty($resp['titles']) ||
            !is_array($resp['titles'])
        ) {
            return new WP_Error(
                'pga_titles_invalid',
                'Resposta inválida para títulos.',
                ['response' => $resp]
            );
        }

        // normalização mínima
        $titles = array_values(
            array_filter(
                array_map(
                    fn($t) => trim((string) $t),
                    $resp['titles']
                )
            )
        );

        if (!$titles) {
            return new WP_Error('pga_no_titles', 'Nenhum título retornado.');
        }

        return $titles;
    }


    public static function outline(string $prompt, array $args = [])
    {
        // 1) Resolve provider
        $provider = $args['provider'] ?? self::get_text_provider();

        // 2) Valida provider
        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<PluginsAlpha_OpenAI|PluginsAlpha_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 3) Schema do outline
        $schema = [
            'sections' => 'array',
        ];

        // 4) Opções centralizadas
        $opts = [
            'template'    => 'outline',
            'temperature' => $args['temperature'] ?? 0.6,
            'max_tokens'  => $args['max_tokens'] ?? 1200,
            'provider'    => $provider,
        ];

        // 🔥 chamada ÚNICA ao provider
        $resp = $class::complete($prompt, $schema, $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        if (
            !is_array($resp) ||
            empty($resp['sections']) ||
            !is_array($resp['sections'])
        ) {
            return new WP_Error(
                'pga_outline_invalid',
                'Resposta inválida para outline.',
                ['response' => $resp]
            );
        }

        return $resp['sections'];
    }

    /**
     * Provider padrão para STORIES (pode virar opção separada no futuro)
     */
    public static function get_story_text_provider(): string
    {
        $provider = 'openai';
        if (class_exists('PluginsAlpha_Settings')) {
            $opts    = PluginsAlpha_Settings::get();
            $stories = $opts['stories'] ?? [];

            if (!empty($stories['text_provider'])) {
                $candidate = (string) $stories['text_provider'];
                if (in_array($candidate, ['openai', 'gemini'], true)) {
                    $provider = $candidate;
                }
            }
        }

        return $provider;
    }

    /**
     * Gera páginas de Web Stories, despachando para o provedor correto.
     *
     * @param string $prompt Prompt final já montado (Prompts::build_story_prompt_for_post)
     * @param array  $args   ['provider' => 'gemini', 'model' => '...', 'temperature' => 0.4, ...]
     */
    public static function generate_story_pages(string $prompt, array $args = [])
    {
        $provider = isset($args['provider'])
            ? (string) $args['provider']
            : self::get_story_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<PluginsAlpha_OpenAI|PluginsAlpha_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 schema do story
        $schema = [
            'pages' => 'array'
        ];

        // 🔹 opções de geração (único lugar!)
        $opts = [
            'temperature' => $args['temperature'] ?? 0.4,
            'max_tokens'  => $args['max_tokens'] ?? 6000,
            'template'    => 'story_pages',
            'provider'    => $provider,
        ];

        // 🔥 AQUI é o ponto-chave
        $resp = $class::complete($prompt, $schema, $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        if (!isset($resp['pages']) || !is_array($resp['pages'])) {
            return new WP_Error(
                'pga_story_invalid',
                'Resposta inválida: pages ausente ou inválido.'
            );
        }

        // normalização mínima
        $pages = [];
        foreach ($resp['pages'] as $p) {
            $pages[] = [
                'heading'  => (string)($p['heading'] ?? ''),
                'body'     => (string)($p['body'] ?? ''),
                'cta_text' => (string)($p['cta_text'] ?? ''),
                'cta_url'  => (string)($p['cta_url'] ?? ''),
                'prompt'   => (string)($p['prompt'] ?? ''),
            ];
        }

        return [
            'pages' => $pages,
        ];
    }

    public static function meta_description(string $prompt, array $args = [])
    {
        $provider = $args['provider'] ?? self::get_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<PluginsAlpha_OpenAI|PluginsAlpha_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 contrato da resposta
        $schema = [
            'description' => 'string',
        ];

        // 🔹 opções centralizadas
        $opts = [
            'template'    => 'meta_description',
            'temperature' => $args['temperature'] ?? 0.6,
            'max_tokens'  => $args['max_tokens'] ?? 1200,
            'provider'    => $provider,
        ];

        // 🔥 chamada correta (igual ao story)
        $resp = $class::complete($prompt, $schema, $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        // 🔒 contrato mínimo
        if (!is_array($resp) || empty($resp['description'])) {
            return new WP_Error(
                'pga_meta_desc_invalid',
                'Resposta inválida para meta description.'
            );
        }

        $desc = trim((string) $resp['description']);

        if ($desc === '') {
            return new WP_Error(
                'pga_meta_desc_empty',
                'Meta description vazia.'
            );
        }

        return $desc;
    }


    public static function slug(string $prompt, array $args = [])
    {
        $provider = $args['provider'] ?? self::get_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<PluginsAlpha_OpenAI|PluginsAlpha_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 🔹 contrato
        $schema = [
            'slug' => 'string',
        ];

        // 🔹 opções
        $opts = [
            'template'    => 'slug',
            'temperature' => $args['temperature'] ?? 0.3,
            'max_tokens'  => $args['max_tokens'] ?? 120,
            'provider'    => $provider,
        ];

        $resp = $class::complete($prompt, $schema, $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        if (!is_array($resp) || empty($resp['slug'])) {
            return new WP_Error(
                'pga_slug_invalid',
                'Resposta inválida para slug.',
                ['response' => $resp]
            );
        }

        $slug = trim((string) $resp['slug']);

        if ($slug === '') {
            return new WP_Error('pga_slug_empty', 'Slug vazia.');
        }

        // 🔒 normalização final de segurança
        $slug = sanitize_title($slug);

        if ($slug === '') {
            return new WP_Error('pga_slug_invalid_final', 'Slug inválida após sanitização.');
        }

        return $slug;
    }


    public static function image_prompt(string $prompt, array $args = [])
    {
        $provider = $args['provider'] ?? self::get_text_provider();

        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /**
         * @var class-string<PluginsAlpha_OpenAI|PluginsAlpha_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        $schema = [
            'prompt' => 'string',
        ];

        $opts = [
            'template'    => 'image_prompt',
            'temperature' => $args['temperature'] ?? 0.6,
            'max_tokens'  => $args['max_tokens'] ?? 300,
            'provider'    => $provider,
        ];

        $resp = $class::complete($prompt, $schema, $opts);
        if (is_wp_error($resp)) {
            return $resp;
        }

        if (!is_array($resp) || empty($resp['prompt'])) {
            return new WP_Error(
                'pga_image_prompt_invalid',
                'Resposta inválida para image prompt.',
                ['response' => $resp]
            );
        }

        $imgPrompt = trim((string) $resp['prompt']);
        if ($imgPrompt === '') {
            return new WP_Error(
                'pga_image_prompt_empty',
                'Prompt de imagem vazio.'
            );
        }

        return $imgPrompt;
    }
}
