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
            'openai'   => 'PluginsAlpha_OpenAI',
            'gemini'   => 'PluginsAlpha_Gemini',
            'perplexity' => 'PluginsAlpha_Perplexity',
            'pollinations' => 'PluginsAlpha_Pollinations',
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
    public static function get_text_provider(): string
    {
        $provider = 'openai';

        if (class_exists('PluginsAlpha_Settings')) {
            $opts   = PluginsAlpha_Settings::get();
            $orion  = $opts['orion_posts'] ?? [];

            if (!empty($orion['text_provider'])) {
                $candidate = (string) $orion['text_provider'];
                if (in_array($candidate, ['openai', 'gemini'], true)) {
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
        /** 
         * Diz ao Intelephense quais classes podem aparecer aqui.
         * Todas elas têm is_configured().
         *
         * @var class-string<
         *     PluginsAlpha_OpenAI |
         *     PluginsAlpha_Gemini
         * > $class
         */
        $class = self::resolve_provider($provider);

        if (is_wp_error($class)) {
            return $class;
        }

        // Agora o Intelephense para de reclamar
        if (! $class::is_configured()) {
            return new WP_Error(
                'pga_provider_no_key',
                "Nenhuma credencial encontrada para o provedor '{$provider}'."
            );
        }

        return true;
    }

    // ------------------------------------------------------------
    // PONTO ÚNICO: gerar TEXTO genérico (Orion, Stories etc.)
    // ------------------------------------------------------------

    public static function titles(string $prompt, array $args = [])
    {
        // 1) Resolve provider
        $provider = isset($args['provider'])
            ? (string) $args['provider']
            : self::get_text_provider();

        // 2) Garante que está configurado
        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        // 3) Despacha para o provedor certo,
        //    que *já* retorna array de strings (títulos)
        switch ($provider) {
            case 'gemini':
                if (!class_exists('PluginsAlpha_Gemini')) {
                    return new WP_Error(
                        'pga_gemini_missing',
                        'Classe PluginsAlpha_Gemini não encontrada.'
                    );
                }
                return PluginsAlpha_Gemini::titles($prompt);

            case 'openai':
            default:
                if (!class_exists('PluginsAlpha_OpenAI')) {
                    return new WP_Error(
                        'pga_openai_missing',
                        'Classe PluginsAlpha_OpenAI não encontrada.'
                    );
                }
                return PluginsAlpha_OpenAI::titles($prompt);
        }
    }

    // AI::outline
    public static function outline(string $prompt, array $args = [])
    {
        // 1) Descobre o provider (args > settings)
        $provider = isset($args['provider'])
            ? (string) $args['provider']
            : self::get_text_provider();

        // 2) Valida credenciais
        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

    // 3) Resolve a classe do provider (OpenAI / Gemini / etc.)
        /** 
         * @var class-string<
         *    PluginsAlpha_OpenAI |
         *    PluginsAlpha_Gemini
         * > $class 
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 4) Chama o método outline() do próprio provedor
        if (!method_exists($class, 'outline')) {
            return new WP_Error(
                'pga_outline_not_implemented',
                "O provedor '{$provider}' não implementa outline()."
            );
        }

        return $class::outline($prompt, $args);
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
         * Aqui avisamos pro Intelephense:
         * $class vai ser SEMPRE uma dessas classes
         * (todas elas tem generate_story_pages()).
         *
         * @var class-string<PluginsAlpha_OpenAI|PluginsAlpha_Gemini> $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        if (!method_exists($class, 'generate_story_pages')) {
            return new WP_Error(
                'pga_story_missing_method',
                "O provedor '{$provider}' não implementa generate_story_pages()."
            );
        }

        return $class::generate_story_pages($prompt, $args);
    }

    public static function meta_description(string $prompt, array $args = [])
    {
        // 1) Resolve provider (args > settings)
        $provider = isset($args['provider'])
            ? (string) $args['provider']
            : self::get_text_provider();

        // 2) Garante credenciais
        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

        /** 
         * $class será sempre uma dessas (ambas têm complete()).
         * Ajusta se você tiver mais providers no futuro.
         *
         * @var class-string<
         *   PluginsAlpha_OpenAI |
         *   PluginsAlpha_Gemini
         * > $class 
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 3) Esquema mínimo só pra orientar o modelo
        $schema = [
            'meta_description' => 'string',
        ];

        // Chama o complete do provider (OpenAI / Gemini)
        // Não passo $args aqui pra manter compat com assinatura do OpenAI::complete
        $result = $class::complete($prompt, $schema);

        if (is_wp_error($result)) {
            return $result;
        }

        // Se algum provider resolver devolver string crua, aceitamos também
        if (is_string($result)) {
            return $result;
        }

        if (is_array($result) && isset($result['meta_description'])) {
            return (string) $result['meta_description'];
        }

        return new WP_Error('pga_meta_desc_format', 'Formato inesperado para a meta description.');
    }

    // ------------------------------------------------------------
    // PROMPT DE IMAGEM (usa provider de TEXTO pra montar o prompt)
    // ------------------------------------------------------------
    public static function image_prompt(string $prompt, array $args = [])
    {
        // 1) Descobre o provider (args > settings)
        $provider = isset($args['provider'])
            ? (string) $args['provider']
            : self::get_text_provider();

        // 2) Garante que esse provider está configurado
        $ok = self::ensure_text_provider($provider);
        if (is_wp_error($ok)) {
            return $ok;
        }

    // 3) Resolve a classe do provider (ex.: PluginsAlpha_OpenAI, PluginsAlpha_Gemini)
        /**
         * @var class-string<
         *   PluginsAlpha_OpenAI |
         *   PluginsAlpha_Gemini
         * > $class
         */
        $class = self::resolve_provider($provider);
        if (is_wp_error($class)) {
            return $class;
        }

        // 4) Garante que o provider implementa image_prompt()
        if (!method_exists($class, 'image_prompt')) {
            return new WP_Error(
                'pga_image_prompt_missing_method',
                "O provedor '{$provider}' não implementa image_prompt()."
            );
        }

        // 5) Despacha para o provedor
        // (mantemos só 1 parâmetro porque hoje OpenAI/Gemini declaram image_prompt(string $prompt))
        return $class::image_prompt($prompt);
    }
}
