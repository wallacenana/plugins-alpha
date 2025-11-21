<?php

/**
 * Central de Prompts do Órion / Plugins Alpha.
 *
 * - Todos os prompts (conteúdo, títulos, imagens) ficam concentrados aqui.
 * - Admin pode editar cada prompt em uma tela única.
 * - Regra: se o prompt salvo estiver vazio, o plugin usa o padrão interno.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PluginsAlpha_Prompts
{

    const OPTION = 'pga_orion_prompts';

    /**
     * Lê option crua.
     */
    public static function get_all_raw(): array
    {
        $opt = get_option(self::OPTION, array());
        return is_array($opt) ? $opt : array();
    }

    /**
     * Salva os prompts enviados pelo form.
     */
    private static function handle_save(): void
    {
        // METHOD
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
            : '';

        if ('POST' !== $method) {
            return;
        }

        // NONCE
        if (
            empty($_POST['pga_orion_prompts_nonce']) ||
            ! wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['pga_orion_prompts_nonce'])),
                'pga_orion_prompts_save'
            )
        ) {
            return;
        }

        if (! current_user_can('manage_options')) {
            return;
        }

        // DADOS
        // pega o array bruto e remove as barras
        $raw = array();

        if (isset($_POST['pga_orion_prompts'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw = wp_unslash($_POST['pga_orion_prompts']);
        }

        // sanitiza tudo que veio
        $in = array();

        if (is_array($raw)) {
            $in = array_map('wp_kses_post', $raw); // ou sanitize_textarea_field, se preferir
        }


        $in  = is_array($raw)
            ? array_map('wp_kses_post', $raw) // ou outra sanitização que você preferir
            : array();
        $out = array();

        if (is_array($in)) {
            foreach ($in as $key => $val) {
                if (! is_string($val)) {
                    continue;
                }
                $k        = sanitize_key($key);
                // deixa HTML básico porque são prompts, não front-end
                $out[$k] = wp_kses_post(wp_unslash($val));
            }
        }

        update_option(self::OPTION, $out, false);

        add_settings_error(
            'plugins-alpha-orion-prompts',
            'pga_orion_prompts_updated',
            __('Prompts salvos com sucesso.', 'plugins-alpha'),
            'updated'
        );
    }

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão.', 'plugins-alpha'));
        }

        self::handle_save();

        $raw = self::get_all_raw();

        // função helper bem simples (sem closure) só para montar valor do textarea:
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        function pga_orion_prompt_value(array $raw, string $key): string
        {
            if (isset($raw[$key]) && is_string($raw[$key]) && trim($raw[$key]) !== '') {
                return $raw[$key];
            }
            // se não tiver salvo, mostra o default para o admin saber o que está sendo usado
            return PluginsAlpha_Prompts::default_prompt_for($key);
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        settings_errors('plugins-alpha-orion-prompts');
?>
        <div class="wrap">
            <h1><?php esc_html_e('Prompts do Órion Posts', 'plugins-alpha'); ?></h1>
            <p><?php esc_html_e('Edite abaixo os prompts usados pelo Órion. Se um campo ficar vazio, o plugin usará o prompt padrão interno.', 'plugins-alpha'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('pga_orion_prompts_save', 'pga_orion_prompts_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_article"><?php esc_html_e('Artigo padrão', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_article"
                                name="pga_orion_prompts[article]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'article')); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Usado quando o template for o padrão (article). Saída em JSON com title, content, meta, etc.', 'plugins-alpha'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_review_roundup"><?php esc_html_e('Review Roundup (vários produtos)', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_review_roundup"
                                name="pga_orion_prompts[review_roundup]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'review_roundup')); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_review_single"><?php esc_html_e('Review Single (1 produto)', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_review_single"
                                name="pga_orion_prompts[review_single]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'review_single')); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_news"><?php esc_html_e('Notícia / News', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_news"
                                name="pga_orion_prompts[news]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'news')); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_howto"><?php esc_html_e('Guia / How-to', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_howto"
                                name="pga_orion_prompts[howto]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'howto')); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_faq"><?php esc_html_e('FAQ', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_faq"
                                name="pga_orion_prompts[faq]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'faq')); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_title"><?php esc_html_e('Títulos (CTR / Discover)', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_title"
                                name="pga_orion_prompts[title]"
                                rows="8"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'title')); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_image"><?php esc_html_e('Imagem / Thumbnail', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_image"
                                name="pga_orion_prompts[image]"
                                rows="6"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'image')); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Prompt base para thumbnails (Pollinations / OpenAI). Pode usar {{keyword}}, {{title}}, {{locale}}, {{template}}.', 'plugins-alpha'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_outline">
                                <?php esc_html_e('Esboço (outline para posts longos)', 'plugins-alpha'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_outline"
                                name="pga_orion_prompts[outline]"
                                rows="10"
                                class="large-text code"><?php
                                                        echo esc_textarea(pga_orion_prompt_value($raw, 'outline'));
                                                        ?></textarea>
                            <?php esc_html_e(
                                'Usado para gerar o esboço em JSON ("sections") dos artigos longos / extra longos.Se ficar vazio, o plugin usa o prompt padrão interno.',
                                'plugins-alpha'
                            ); ?>

                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_outline_modelar">
                                <?php esc_html_e('Esboço (modelar URL)', 'plugins-alpha'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_outline_modelar"
                                name="pga_orion_prompts[outline_modelar]"
                                rows="10"
                                class="large-text code"><?php
                                                        echo esc_textarea(pga_orion_prompt_value($raw, 'outline_modelar'));
                                                        ?></textarea>
                            <p class="description">
                                <?php esc_html_e(
                                    'Usado quando o modelo for "Modelar URL". Gera o esboço em JSON ("sections") a partir do conteúdo da URL informada.',
                                    'plugins-alpha'
                                ); ?>
                            </p>
                        </td>
                    </tr>



                </table>

                <?php submit_button(__('Salvar prompts', 'plugins-alpha')); ?>
            </form>
        </div>
<?php
    }

    /* ---------------------------------------------------------------------
     *  ACESSO AOS PROMPTS
     * ------------------------------------------------------------------ */

    /**
     * Retorna o prompt efetivo de uma chave (article, review_single, etc.),
     * já aplicando fallback para default se o salvo estiver vazio.
     */
    public static function get_prompt_for(string $key): string
    {
        $raw = self::get_all_raw();

        if (isset($raw[$key]) && is_string($raw[$key])) {
            $val = trim($raw[$key]);
            if ($val !== '') {
                return $val;
            }
        }

        return self::default_prompt_for($key);
    }

    private static function content_json_suffix(): string
    {
        $s  = '';
        $s .= "Saída OBRIGATÓRIA: JSON UTF-8 válido no formato:\n";
        $s .= "{\n";
        $s .= "  \"title\": \"...\",\n";
        $s .= "  \"titles_suggestions\": [],\n";
        $s .= "  \"content\": \"<h2>...</h2>...\",\n";
        $s .= "  \"meta_title\": \"\",\n";
        $s .= "  \"meta_description\": \"\",\n";
        $s .= "  \"image_alt\": \"\",\n";
        $s .= "  \"links\": {\"internal\": [], \"external\": []}\n";
        $s .= "}\n";
        $s .= "Não use markdown. Não inclua <h1> em hipótese alguma. Não use percentuais soltos no corpo do texto.\n";
        return $s;
    }

    private static function title_json_suffix(): string
    {
        $s  = '';
        $s .= "Responda APENAS em JSON UTF-8 válido no formato:\n";
        $s .= "{ \"titles\": [\"Título 1\", \"Título 2\", \"Título 3\"] }\n";
        return $s;
    }

    /**
     * Retorna o prompt padrão interno para cada tipo.
     * Aqui você pode ir refinando os textos à vontade.
     */
    public static function default_prompt_for(string $key): string
    {
        switch ($key) {
            case 'review_roundup':
                return self::default_review_roundup();
            case 'review_single':
                return self::default_review_single();
            case 'news':
                return self::default_news();
            case 'howto':
                return self::default_howto();
            case 'faq':
                return self::default_faq();
            case 'title':
                return self::default_title_prompt();
            case 'image':
                return self::default_image_prompt();
            case 'outline':
                return self::default_outline_prompt();
            case 'outline_modelar':
                return self::default_outline_modelar_prompt();
            case 'article':
            default:
                return self::default_article();
        }
    }

    /**
     * Faz o replace dos placeholders padrão.
     * Suporta: {{keyword}}, {{locale}}, {{forced_title}}, {{url}}, {{template}}, {{extra}}, {{min}}, {{max}}, {{title}}
     */
    private static function replace_vars(string $tpl, array $vars): string
    {
        $map = array();

        foreach ($vars as $k => $v) {
            $map['{{' . $k . '}}'] = $v;
        }

        return strtr($tpl, $map);
    }

    /* ---------------------------------------------------------------------
     *  API PÚBLICA PARA O RESTO DO PLUGIN
     * ------------------------------------------------------------------ */

    /**
     * Prompt de CONTEÚDO (article, review_roundup, review_single, news, howto, faq).
     *
     * @param string $template  article|review_roundup|review_single|news|howto|faq
     * @param string $keyword
     * @param array  $opts      Ex: ['locale' => 'pt_BR', 'forced_title' => '...', ...]
     * @param string $url       Opcional, mais usado em review_single ou news.
     */
    public static function build_content_prompt(string $template, string $length, string $keyword, array $opts = array(), string $url = ''): string
    {
        $template = $template !== '' ? $template : 'discover_article';

        $locale = isset($opts['locale']) ? (string) $opts['locale'] : 'pt_BR';
        $forced = isset($opts['forced_title']) ? trim((string) $opts['forced_title']) : '';

        $key_map = array(
            'review_roundup' => 'review_roundup',
            'review_single'  => 'review_single',
            'news'           => 'news',
            'howto'          => 'howto',
            'faq'            => 'faq',
            'article'        => 'discover_article',
        );

        $key = isset($key_map[$template]) ? $key_map[$template] : 'discover_article';

        $tpl = self::get_prompt_for($key);

        // usa range numérico, não mais a string "short/medium"
        [$minWords, $maxWords] = self::length_to_range($length);

        $vars = array(
            'keyword'      => $keyword,
            'locale'       => $locale,
            'forced_title' => $forced,
            'url'          => $url,
            'template'     => $template,
            'min_words'    => (string)$minWords,
            'max_words'    => (string)$maxWords,
        );

        $base = self::replace_vars($tpl, $vars);

        return $base . "\n\n" . self::content_json_suffix();
    }


    /**
     * Prompt para geração de TÍTULOS (lista de títulos em JSON).
     */
    public static function build_title_prompt(
        string $keyword,
        int $min       = 3,
        int $max       = 5,
        string $locale = 'pt_BR'
    ): string {
        $tpl = self::get_prompt_for('title');

        $vars = array(
            'keyword' => $keyword,
            'min'     => (string) $min,
            'max'     => (string) $max,
            'locale'  => $locale,
        );

        $base = self::replace_vars($tpl, $vars);

        // aqui entra o formato de JSON, sempre controlado pelo back
        return $base . "\n\n" . self::title_json_suffix();
    }


    /**
     * Prompt para geração de IMAGENS (thumbnail).
     */
    public static function build_image_prompt(
        string $keyword,
        string $title,
        string $locale,
        string $template
    ): string {
        $tpl = self::get_prompt_for('image');

        $vars = array(
            'keyword'  => $keyword,
            'locale'   => $locale,
            'template' => $template,
            'title'    => $title,
        );

        return self::replace_vars($tpl, $vars);
    }

    public static function length_to_range(string $length): array
    {
        switch ($length) {
            case 'short':
                return [600, 800];

            case 'medium':
                return [800, 1500];

            case 'long':
                return [1500, 2500];

            case 'extra-long':
            case 'extra_long':
            case 'extra':
                return [2500, 5000];

            default:
                return [800, 1500];
        }
    }

    public static function outline_config(string $length): array
    {
        switch ($length) {
            case 'short':
                return ['min_sections' => 4, 'max_sections' => 6];

            case 'medium':
                return ['min_sections' => 6, 'max_sections' => 10];

            case 'long':
                return ['min_sections' => 10, 'max_sections' => 15];

            case 'extra-long':
            case 'extra_long':
            case 'extra':
                return ['min_sections' => 15, 'max_sections' => 20];

            default:
                return ['min_sections' => 4, 'max_sections' => 6];
        }
    }


    public static function build_outline_prompt(
        string $keyword,
        string $articleTitle,
        string $length,
        string $locale
    ): string {
        $tpl = self::get_prompt_for('outline');

        [$minWords, $maxWords]     = self::length_to_range($length);
        $cfg                       = self::outline_config($length);
        $minSections               = $cfg['min_sections'];
        $maxSections               = $cfg['max_sections'];
        $vars = [
            'keyword'      => $keyword,
            'articleTitle' => $articleTitle,
            'locale'       => $locale,
            'min_words'    => (string)$minWords,
            'max_words'    => (string)$maxWords,
            'min_sections' => (string)$minSections,
            'max_sections' => (string)$maxSections,
        ];

        $base = self::replace_vars($tpl, $vars);

        return $base; // template já descreve o JSON
    }

    /* ---------------------------------------------------------------------
     *  DEFAULTS – aqui você pode ir refinando com calma depois
     * ------------------------------------------------------------------ */

    protected static function default_outline_modelar_prompt(): string
    {
        $s  = '';
        $s .= 'Atue como um especialista em SEO escrevendo em {{locale}}.' . "\n\n";

        $s .= 'Você deve criar APENAS UM ESBOÇO (outline) COMPLETO para um artigo de blog ' .
            'MODELADO a partir do conteúdo da seguinte URL, sem copiar trechos literalmente:' . "\n\n";

        $s .= 'URL base para modelagem:' . "\n";
        $s .= '{{url}}' . "\n\n";

        $s .= 'Frase chave ou comando principal:' . "\n";
        $s .= '"{{keyword}}"' . "\n\n";

        $s .= 'Itens adicionais (produtos, variações, termos complementares), se existirem:' . "\n";
        $s .= '{{extra}}' . "\n\n";

        $s .= 'O título do artigo já está definido e NÃO PODE ser alterado:' . "\n";
        $s .= '"{{articleTitle}}"' . "\n\n";

        $s .= 'Regras de estrutura:' . "\n";
        $s .= '- O artigo final terá entre {{min_words}} e {{max_words}} palavras.' . "\n";
        $s .= '- Crie entre {{min_sections}} e {{max_sections}} seções principais (H2).' . "\n";
        $s .= '- Cada H2 pode ter 1 a 3 subseções (H3).' . "\n";
        $s .= '- A estrutura deve refletir a lógica da página de origem, porém com melhorias: ' .
            'mais clareza, organização superior e foco em Discover.' . "\n";
        $s .= '- Se {{extra}} listar vários produtos, pensar como review comparativo / roundup.' . "\n\n";

        $s .= 'Para cada seção (H2):' . "\n";
        $s .= '- Defina "heading".' . "\n";
        $s .= '- Defina "word_goal" com min/max de palavras.' . "\n";
        $s .= '- Liste "bullets" com ideias que serão desenvolvidas.' . "\n";
        $s .= '- Em "children", inclua eventuais H3 com seus próprios bullets.' . "\n\n";

        $s .= 'FORMATO DA RESPOSTA (OBRIGATÓRIO) — JSON UTF-8 válido, sem markdown:' . "\n\n";

        $s .= '{' . "\n";
        $s .= '  "sections": [' . "\n";
        $s .= '    {' . "\n";
        $s .= '      "id": "1",' . "\n";
        $s .= '      "level": "h2",' . "\n";
        $s .= '      "heading": "Título da seção...",' . "\n";
        $s .= '      "word_goal": { "min": 300, "max": 500 },' . "\n";
        $s .= '      "bullets": ["...", "..."],' . "\n";
        $s .= '      "children": [' . "\n";
        $s .= '        {' . "\n";
        $s .= '          "id": "1.1",' . "\n";
        $s .= '          "level": "h3",' . "\n";
        $s .= '          "heading": "Subtítulo...",' . "\n";
        $s .= '          "bullets": ["...", "..."]' . "\n";
        $s .= '        }' . "\n";
        $s .= '      ]' . "\n";
        $s .= '    }' . "\n";
        $s .= '  ]' . "\n";
        $s .= '}' . "\n\n";

        $s .= 'Não escreva nada fora desse JSON.' . "\n";

        return $s;
    }


    public static function build_outline_prompt_modelar(
        string $keyword,
        string $articleTitle,
        string $length,
        string $locale,
        string $url,
        array $allKeywords = []
    ): string {
        $tpl       = self::get_prompt_for('outline_modelar');
        $locale    = $locale ?: 'pt_BR';
        $url       = trim($url);

        // range de palavras e nº de seções igual ao outline normal
        [$minWords, $maxWords] = self::length_to_range($length);
        $cfg                   = self::outline_config($length);
        $minSections           = $cfg['min_sections'];
        $maxSections           = $cfg['max_sections'];

        // monta string com keywords extras (se existirem)
        $extra = '';
        if (!empty($allKeywords)) {
            $clean = array_values(array_filter(array_map('trim', $allKeywords)));
            if ($clean) {
                $extra = implode("\n", array_map(function ($k) {
                    return '- ' . $k;
                }, $clean));
            }
        }

        $vars = [
            'keyword'      => $keyword,
            'articleTitle' => $articleTitle,
            'locale'       => $locale,
            'min_words'    => (string)$minWords,
            'max_words'    => (string)$maxWords,
            'min_sections' => (string)$minSections,
            'max_sections' => (string)$maxSections,
            'url'          => $url,
            'extra'        => $extra,
        ];

        $base = self::replace_vars($tpl, $vars);

        // o template já descreve o JSON; não precisa sufixo extra
        return $base;
    }


    public static function build_section_prompt(
        string $keyword,
        string $articleTitle,
        array $section,
        string $length = 'short',
        string $locale = 'pt_BR',
        int $sectionsCount = 1
    ): string {
        [$globalMin, $globalMax] = self::length_to_range($length);

        $totalMax = $globalMax > 0 ? $globalMax : 800;
        $sectionsCount = max(1, $sectionsCount);
        $approxMax = (int) floor($totalMax / $sectionsCount);
        $approxMin = max(80, (int) floor($approxMax * 0.5));

        $children = (array)($section['children'] ?? []);
        $bullets  = (array)($section['bullets']  ?? []);

        // 🔧 heading robusto: aceita vários formatos e faz fallback
        $heading = '';
        if (!empty($section['heading'])) {
            $heading = (string)$section['heading'];
        } elseif (!empty($section['title'])) {
            $heading = (string)$section['title'];
        } elseif (!empty($section['text'])) {
            $heading = (string)$section['text'];
        }

        // se ainda assim vier vazio, tenta pegar do primeiro filho
        if ($heading === '' && !empty($children)) {
            foreach ($children as $c) {
                if (!empty($c['heading'])) {
                    $heading = (string)$c['heading'];
                    break;
                }
                if (!empty($c['title'])) {
                    $heading = (string)$c['title'];
                    break;
                }
            }
        }

        // fallback hardcore: pelo menos identifica a seção
        if ($heading === '') {
            $heading = 'Seção do artigo relacionada a ' . $keyword;
        }

        $level = (string)($section['level'] ?? 'h2');

        $bulletsText = '';
        if ($bullets) {
            $bulletsText .= "Pontos principais desta seção:\n";
            foreach ($bullets as $b) {
                $bulletsText .= "- " . $b . "\n";
            }
        }

        if ($children) {
            $bulletsText .= "\nSubseções (H3) que devem ser contempladas:\n";
            for ($i = 0; $i < count($children); $i++) {
                $h = (string)($children[$i]['heading'] ?? $children[$i]['title'] ?? '');
                if ($h !== '') {
                    $bulletsText .= "- " . $h . "\n";
                }
            }
        }


        $txt  = '';
        $txt .= "Atue como um especialista em SEO e GEO escrevendo em {$locale}.\n";
        $txt .= "O foco deste artigo é Google Discover, então o conteúdo deve ser fluido e despertar cada vez mais interesse em ler.\n\n";

        $txt .= "Você deve escrever APENAS o conteúdo (HTML) da seção \"{$heading}\" ({$level})\n";
        $txt .= "do artigo com título exato:\n\n";
        $txt .= "\"{$articleTitle}\"\n\n";

        $txt .= "REGRAS CRÍTICAS SOBRE O TÍTULO:\n";
        $txt .= "- O conteúdo desta seção DEVE ser coerente com o título do artigo.\n";
        $txt .= "- Se o título promete um certo número de passos, dicas, motivos etc,\n";
        $txt .= "  respeite essa estrutura no conjunto das seções (não crie um número diferente).\n";
        $txt .= "- Não mude o foco do artigo. Não contradiga o que o título promete.\n\n";

        $txt .= "Frase chave de foco ou comando: \"{$keyword}\". Entenda se este item é uma frase chave ou um comando; se for um comando, siga o sentido do que o conteúdo quer dizer e, se tiver uma URL, acesse para modelar o conteúdo, mas não insira um link como referência.\n\n";

        $txt .= "Regras de tamanho:\n";
        $txt .= "- O texto desta seção deve ter aproximadamente entre {$approxMin} e {$approxMax} palavras.\n";
        $txt .= "- Desenvolva bem as ideias, com explicações e exemplos práticos, mas evite enrolação.\n";
        $txt .= "- Cada parágrafo deve ter no máximo 4 linhas, ou seja, abaixo de 300 palavras. Cada tópico também deve conter no máximo 300 palavras; entre títulos e subtítulos, respeite esse limite.\n\n";

        $txt .= "Regras de HTML:\n";
        $txt .= "- Não inclua <h1>.\n";
        $txt .= "- Comece o conteúdo já com a tag {$level} principal desta seção.\n";
        $txt .= "- A frase chave de foco deve ser distribuída pelo conteúdo levando em conta a performance de SEO.\n";
        $txt .= "- A frase chave de foco deve estar principalmente na primeira frase de maneira fluida.\n";
        $txt .= "- A frase chave de foco deve estar presente no último parágrafo.\n";
        $txt .= "- Use parágrafos (<p>) claros e escaneáveis.\n";
        $txt .= "- Use <strong> para negrito, nunca ** **.\n";
        $txt .= "- Use listas não ordenadas (<ul><li>) quando fizer sentido (passo a passo, checklist, dicas etc).\n";
        $txt .= "- Use <p>, <ul>, <li>, <strong> etc. em HTML puro.\n";
        $txt .= "- Trechos importantes do texto devem estar em negrito.\n";
        $txt .= "- No mínimo 40% do conteúdo deve ter palavras de transição, como: mas, por isso, entretanto, isso, quando, em resumo e outras similares, sem perder a voz ativa.\n\n";

        $txt .= "Regras críticas sobre a frase chave:\n";
        $txt .= "- Esta seção deve conter ao menos uma vez a frase chave de foco.\n";
        $txt .= "- Se esta for a seção de introdução ou conclusão, então a frase chave deve estar na primeira frase de maneira fluida.\n\n";

        $txt .= "Contexto do esboço:\n";
        $txt .= $bulletsText . "\n";


        return $txt;
    }


    protected static function default_outline_prompt(): string
    {
        $s  = '';
        $s .= "Atue como um especialista em SEO escrevendo em {{locale}}.\n\n";

        $s .= "Você deve criar APENAS UM ESBOÇO (outline) completo para um artigo de blog\n";
        $s .= "com a frase chave de foco: \"{{keyword}}\".\n";
        $s .= "Se o que estiver no campo acima for um texto ou link, então deve ser acessado o link e modelado os tópicos ou seguido as diretrizes.\n";
        $s .= "Se atente também caso o campo acima tenha vários nomes de produtos; nesse caso, provavelmente o usuário está fazendo um review comparativo ou até mesmo um post review de algum produto.\n\n";

        $s .= "O título do artigo já está definido e NÃO PODE ser traído pelo conteúdo:\n";
        $s .= "\"{{articleTitle}}\"\n\n";

        $s .= "Regras IMPORTANTES sobre consistência com o título:\n";
        $s .= "- Se o título menciona um número específico de passos, dicas, motivos, estratégias etc\n";
        $s .= "  (por exemplo: \"5 passos para ...\", \"7 motivos para ...\"),\n";
        $s .= "  o esboço DEVE refletir exatamente esse número de itens principais.\n";
        $s .= "- Não invente mais nem menos passos do que o prometido no título.\n";
        $s .= "- A intenção do título (promessa principal) deve ser claramente atendida nas seções.\n\n";

        $s .= "Regras de tamanho:\n";
        $s .= "- O artigo final terá entre {{min_words}} e {{max_words}} palavras.\n";
        $s .= "- Crie entre {{min_sections}} e {{max_sections}} seções principais (H2).\n";
        $s .= "- Cada H2 pode ter 1 a 3 subseções (H3).\n\n";

        $s .= "Estrutura:\n";
        $s .= "- \"sections\" é um array de seções de nível H2.\n";
        $s .= "- Cada H2 pode conter um array \"children\" com H3 relacionados.\n";
        $s .= "- Com exceção da introdução, ao menos 1 H2 deve ter subseções.\n";
        $s .= "- Inclua \"bullets\" com ideias que serão desenvolvidas em cada seção.\n\n";

        $s .= "Finalização:\n";
        $s .= "- Finalize sempre com a conclusão.\n\n";

        $s .= "A frase chave \"{{keyword}}\" deve ser considerada em toda a estrutura.\n";

        return $s;
    }


    protected static function outline_json_suffix(): string
    {
        $s  = '';
        $s .= "Responda SOMENTE em JSON UTF-8 válido, sem markdown,\n";
        $s .= "seguindo exatamente o formato:\n\n";

        $s .= "{\n";
        $s .= "  \"sections\": [\n";
        $s .= "    {\n";
        $s .= "      \"id\": \"1\",\n";
        $s .= "      \"level\": \"h2\",\n";
        $s .= "      \"heading\": \"Título H2...\",\n";
        $s .= "      \"word_goal\": { \"min\": 300, \"max\": 500 },\n";
        $s .= "      \"bullets\": [\"...\", \"...\"],\n";
        $s .= "      \"children\": [\n";
        $s .= "        {\n";
        $s .= "          \"id\": \"1.1\",\n";
        $s .= "          \"level\": \"h3\",\n";
        $s .= "          \"heading\": \"Subtítulo H3...\",\n";
        $s .= "          \"bullets\": [\"...\", \"...\"]\n";
        $s .= "        }\n";
        $s .= "      ]\n";
        $s .= "    }\n";
        $s .= "  ]\n";
        $s .= "}\n";

        return $s;
    }

    public static function build_meta_description_prompt(
        string $keyword,
        string $articleTitle,
        string $locale = 'pt_BR',
        string $content = ''
    ): string {
        $keyword      = trim($keyword);
        $articleTitle = trim($articleTitle);
        $locale       = $locale ?: 'pt_BR';

        // dá uma resumida no conteúdo pra não mandar 10km de texto
        if ($content !== '') {
            // remove tags e limita o contexto pra ~1500 caracteres
            $plain = wp_strip_all_tags($content);
            $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
            if (mb_strlen($plain) > 1500) {
                $plain = mb_substr($plain, 0, 1500) . '...';
            }
        } else {
            $plain = '';
        }

        $ctx = $plain ? "Trecho do artigo para contexto:\n\"{$plain}\"\n\n" : '';

        $s  = '';
        $s .= "Você é um especialista em SEO, escrevendo em {$locale} para artigos focados em Google Discover.\n\n";

        $s .= "Gere APENAS uma meta descrição em texto simples (sem HTML, sem markdown, sem aspas ao redor, sem emojis).\n\n";

        $s .= "Regras:\n";
        $s .= "- Idioma: {$locale}.\n";
        $s .= "- Tamanho: entre 130 e 150 caracteres (ideal ~150).\n";
        $s .= "- Deve ser uma frase única, fluida, que desperte curiosidade sem ser clickbait barato.\n";
        $s .= "- Incluir a frase chave de foco de forma natural: \"{$keyword}\", mas caso essa keyword seja um comando, siga o que o comando diz e crie algo que faça sentido.\n";
        $s .= "- Não use \"clique aqui\", \"não perca\", \"leia agora\", \"descubra\" e similares.\n";
        $s .= "- Não use aspas, não use markdown, não use tags HTML.\n";
        $s .= "- Fale diretamente com o leitor, mas sem prometer coisas impossíveis.\n\n";

        $s .= "Título exato do artigo:\n";
        $s .= "\"{$articleTitle}\"\n\n";

        $s .= "{$ctx}\n";

        $s .= "Retorne APENAS a meta descrição final, nada mais.\n";

        return $s;
    }



    private static function default_article(): string
    {
        $s  = '';
        $s .= 'Estamos em 2025. Você é um redator sênior especializado em SEO, GEO e conteúdo de blog em {{locale}}.' . "\n\n";
        $s .= 'O titulo do post é: "{{forced_title}}".' . "\n\n";
        $s .= 'Escreva um ARTIGO completo, natural e humanizado, com no mínimo {{lenght}} palavras, sobre: "{{keyword}}".' . "\n\n";
        $s .= 'Se {{forced_title}} não estiver vazio, use exatamente esse texto como título principal. Caso contrário, escolha o melhor título possível.' . "\n\n";
        $s .= "Regras editoriais (não cite estas regras no texto):\n";
        $s .= "- Introdução com a frase-chave {{keyword}} na primeira frase, de forma fluida.\n";
        $s .= "- Corpo em HTML SEM <h1>, organizado em <h2>/<h3> com parágrafos curtos.\n";
        $s .= "- Linguagem clara, prática, com exemplos quando fizer sentido.\n";
        $s .= "- Conclusão retomando os principais pontos e um CTA leve.\n";
        $s .= "- Gere internamente meta_title, meta_description e image_alt coerentes com a keyword.\n";

        return $s;
    }


    private static function default_review_roundup(): string
    {
        $s  = '';
        $s .= 'Você é um redator especializado em reviews comparativos, escrevendo em {{locale}}.' . "\n";
        $s .= 'Crie um ARTIGO REVIEW do tipo "roundup" (vários produtos) sobre: "{{keyword}}".' . "\n";
        $s .= 'Se {{forced_title}} não estiver vazio, use como título principal no JSON.' . "\n\n";
        $s .= "Regras principais:\n";
        $s .= "- Estruture em seções por produto e seções comparativas (prós, contras, para quem é indicado).\n";
        $s .= "- Nunca afirme que existe um \"melhor absoluto\"; mostre cenários.\n";
        $s .= "- Use HTML no campo \"content\" (sem <h1>), focando em <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>.\n";
        $s .= "- Inclua CTAs leves para o leitor visitar a página oficial ou site de compra.\n\n";
        $s .= "- O conteudo deve ser real e buscar de fato produtos que resolvam o problema em questão.\n\n";

        return $s;
    }

    private static function default_review_single(): string
    {
        $s  = '';
        $s .= 'Você é um redator especializado em reviews detalhados de um único produto, em {{locale}}.' . "\n";
        $s .= 'Crie um REVIEW completo sobre o produto relacionado à palavra-chave: "{{keyword}}". Se {{url}} não estiver vazio, use a página como principal fonte de informações (sem copiar trechos literalmente).' . "\n";
        $s .= 'Se {{forced_title}} não estiver vazio, use como título principal no JSON.' . "\n\n";
        $s .= "Estrutura sugerida:\n";
        $s .= "- Introdução com contexto e promessa.\n";
        $s .= "- Seções: o que é, como funciona, benefícios, pontos de atenção, para quem é indicado, como comprar.\n";
        $s .= "- Use provas sociais quando fizer sentido (\"usuários relatam\", \"depoimentos indicam\"), sem inventar dados específicos.\n";
        $s .= "- Conclusão respondendo se \"vale a pena\" e com CTA claro.\n\n";
        $s .= "- Gere internamente meta_title, meta_description e image_alt coerentes com a keyword.\n";

        return $s;
    }

    private static function default_news(): string
    {
        $s  = '';
        $s .= 'Você é um jornalista escrevendo notícias em {{locale}}.' . "\n";
        $s .= 'Escreva uma NOTÍCIA factual sobre: "{{keyword}}", com lide claro e objetivo.' . "\n";
        $s .= 'Se {{forced_title}} não estiver vazio, use como título principal no JSON.' . "\n\n";
        $s .= "Regras:\n";
        $s .= "- Estrutura jornalística básica: lide (quem, o quê, quando, onde, por quê), desenvolvimento, contexto, próximos passos.\n";
        $s .= "- Linguagem neutra, informativa, sem sensacionalismo barato.\n";
        $s .= "- Corpo em HTML (sem <h1>), usando <h2>/<h3> para seções.\n\n";
        $s .= "- Gere internamente meta_title, meta_description e image_alt coerentes com a keyword.\n";

        return $s;
    }

    private static function default_howto(): string
    {
        $s  = '';
        $s .= 'Você é um redator instrucional escrevendo em {{locale}}.' . "\n";
        $s .= 'Escreva um GUIA / HOW-TO com no mínimo 600 palavras sobre: "{{keyword}}".' . "\n";
        $s .= 'O conteúdo deve ser passo a passo, escaneável, com listas e dicas práticas.' . "\n";
        $s .= 'A PRIMEIRA frase da introdução deve conter a palavra-chave principal.' . "\n\n";
        $s .= "Regras:\n";
        $s .= "- Corpo em HTML sem <h1>, usando <h2>, <h3>, <p>, <ul>, <ol>, <li>.\n";
        $s .= "- Inclua checklists e passos claros.\n";
        $s .= "- Conclusão com resumo e CTA leve.\n\n";
        $s .= "- Gere internamente meta_title, meta_description e image_alt coerentes com a keyword.\n";

        return $s;
    }

    private static function default_faq(): string
    {
        $s  = '';
        $s .= 'Você é um redator criando FAQ em {{locale}}.' . "\n";
        $s .= 'Crie um conteúdo do tipo PERGUNTAS FREQUENTES sobre: "{{keyword}}".' . "\n";
        $s .= 'A primeira frase de introdução deve conter a palavra-chave exatamente como está.' . "\n\n";
        $s .= "Regras:\n";
        $s .= "- Tamanho mínimo: 600 palavras.\n";
        $s .= "- Estrutura SEM <h1>; use apenas <h2>, <h3>, <p>, <ul>, <ol>, <li>.\n";
        $s .= "- Comece com uma breve introdução em <p>.\n";
        $s .= "- Crie entre 8 e 12 perguntas; cada pergunta em <h2> com a resposta logo abaixo em <p>.\n";
        $s .= "- Conclusão curta com síntese prática.\n";
        $s .= "- Gere meta_title, meta_description e image_alt como nos demais modelos.\n\n";
        $s .= "- Gere internamente meta_title, meta_description e image_alt coerentes com a keyword.\n";

        return $s;
    }

    private static function default_title_prompt(): string
    {
        $s  = '';
        $s .= 'Estamos em 2025 e você é um redator sênior especializado em SEO, Google Discover e títulos de alto CTR em {{locale}}.' . "\n\n";
        $s .= 'Gere entre {{min}} e {{max}} TÍTULOS criativos, naturais e de alto clique para um conteúdo com a frase chave: "{{keyword}}".' . "\n\n";
        $s .= "Diretrizes:\n";
        $s .= "- Títulos curtos e específicos, evitando clickbait vazio.\n";
        $s .= "- Use emoção, curiosidade, autoridade e relevância de notícia quando fizer sentido.\n";
        $s .= "- Considere o contexto de Discover: urgência, novidade e interesse atual do público.\n";
        $s .= "- No máximo 60 caracteres.\n";
        $s .= "- Deve ser sempre diferente de anteriores que você já escreveu a pedido meu.\n";
        $s .= "- Nunca gere numeros absurdos como mais de 20, ou seja, nunca gere nomes como '30 dicas para xxxx'.\n";

        return $s;
    }

    private static function default_image_prompt(): string
    {
        $s  = '';
        $s .= 'Você é um gerador de prompts para imagens realistas.' . "\n";
        $s .= 'Crie um prompt em {{locale}} para gerar uma thumbnail em estilo fotográfico/realista (não ilustrativo) relacionada ao conteúdo: "{{keyword}}".' . "\n";
        $s .= 'Considere também o título do post: "{{title}}" e o tipo de conteúdo: "{{template}}".' . "\n\n";
        $s .= "Regras:\n";
        $s .= "- Descreva a cena com detalhes visuais claros (ambiente, iluminação, enquadramento, estilo).\n";
        $s .= "- Evite texto na imagem, logotipos e elementos de interface.\n";
        $s .= "- Não cite palavras como \"thumbnail\", \"blog\", \"post\", \"imagem\" no prompt.\n";
        $s .= "- Foque em uma única cena marcante, em proporção 16:9.\n";
        $s .= "- Tamanho do prompt: pelo menos 200 caracteres.\n";
        $s .= "- não escreva palavras como 'Descubra', 'veja como' ou palavras desse tipo, de preferencia por inserir uma dor com uma solução dessa dor, então fale de possiveis beneficios'.\n";
        $s .= "- peça para não ter textos ou marca d'agua";

        return $s;
    }
}
