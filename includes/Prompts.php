<?php

/**
 * Central de Prompts do Órion / Plugins Alpha.
 *
 * - Todos os prompts (conteúdo, títulos, imagens, stories) ficam concentrados aqui.
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
        $raw = array();
        if (isset($_POST['pga_orion_prompts'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw = wp_unslash($_POST['pga_orion_prompts']);
        }

        $in  = is_array($raw)
            ? array_map('wp_kses_post', $raw)
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

        // helper simples pra pegar o valor do textarea:
        // se não existir opção salva, mostra o default
        // phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        function pga_orion_prompt_value(array $raw, string $key): string
        {
            if (isset($raw[$key]) && is_string($raw[$key]) && trim($raw[$key]) !== '') {
                return $raw[$key];
            }
            return PluginsAlpha_Prompts::default_prompt_for($key);
        }
        // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

        settings_errors('plugins-alpha-orion-prompts');
?>
        <div class="wrap">
            <style>
                #pga-vars-btn {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    z-index: 99999;
                    padding: 10px 16px;
                    border-radius: 6px;
                    background: #2271b1;
                    color: #fff;
                    cursor: pointer;
                    font-weight: 600;
                    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
                }

                #pga-vars-panel {
                    position: fixed;
                    bottom: 70px;
                    right: 20px;
                    width: 340px;
                    max-height: 70vh;
                    overflow-y: auto;
                    padding: 15px;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3);
                    display: none;
                    z-index: 99999;
                }

                #pga-vars-panel h3 {
                    margin-top: 0;
                    margin-bottom: 10px;
                    font-size: 16px;
                    font-weight: 600;
                }

                #pga-vars-panel code {
                    background: #f1f1f1;
                    padding: 2px 4px;
                    border-radius: 3px;
                }

                #pga-vars-panel ul {
                    margin: 0;
                    padding-left: 18px;
                }
            </style>
            <h1><?php esc_html_e('Prompts Gerais', 'plugins-alpha'); ?></h1>
            <p><?php esc_html_e('Edite abaixo os prompts usados pelo Órion. Se um campo ficar vazio, o plugin usará o prompt padrão interno.', 'plugins-alpha'); ?></p>

            <div id="pga-vars-btn">📌 Variáveis Disponíveis</div>

            <div id="pga-vars-panel">
                <h3>Variáveis para usar nos prompts</h3>
                <p>Copie e cole os placeholders nos prompts. Sempre entre <code>{{ }}</code>.</p>

                <ul>
                    <li><code>{{keyword}}</code> – frase chave escolhida</li>
                    <li><code>{{title}}</code> – título atual do artigo</li>
                    <li><code>{{forced_title}}</code> – título forçado pelo usuário</li>
                    <li><code>{{locale}}</code> – idioma (ex: pt_BR)</li>
                    <li><code>{{template}}</code> – template atual (article, news, howto…)</li>
                    <li><code>{{content}}</code> – conteúdo resumido do post</li>
                    <li><code>{{url}}</code> – URL usada em reviews ou modelagem</li>
                    <li><code>{{min_words}}</code> – mínimo de palavras</li>
                    <li><code>{{max_words}}</code> – máximo de palavras</li>
                    <li><code>{{min_sections}}</code> – mínimo de seções</li>
                    <li><code>{{max_sections}}</code> – máximo de seções</li>
                    <li><code>{{articleTitle}}</code> – título final do artigo</li>
                    <li><code>{{extra}}</code> – campo adicional futuro</li>
                </ul>

                <p><strong>Dica:</strong> mantenha os nomes padronizados exatamente assim.</p>
            </div>

            <script>
                document.getElementById('pga-vars-btn').addEventListener('click', function() {
                    var panel = document.getElementById('pga-vars-panel');
                    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
                });
            </script>

            <form method="post" action="">
                <?php wp_nonce_field('pga_orion_prompts_save', 'pga_orion_prompts_nonce'); ?>

                <table class="form-table" role="presentation">

                    <!-- STORIES / WEB STORIES -->
                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_story">
                                <?php esc_html_e('Web Stories (estrutura das páginas)', 'plugins-alpha'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea
                                id="pga_orion_prompt_story"
                                name="pga_orion_prompts[story]"
                                rows="12"
                                class="large-text code"><?php
                                                        echo esc_textarea(pga_orion_prompt_value($raw, 'story'));
                                                        ?></textarea>
                            <p class="description">
                                <?php
                                esc_html_e(
                                    'Prompt base usado para gerar as páginas (slides) do Web Story. Pode usar {{title}} (título do post), {{content}} (conteúdo já processado) e {{brief}} (brief padrão), além de {{locale}}.',
                                    'plugins-alpha'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>

                    <!-- ARTIGO PADRÃO -->
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
                                <?php esc_html_e('Usado quando o template for o padrão (article). Saída em JSON com title, content, meta, etc. Pode usar {{keyword}}, {{title}}, {{locale}}, {{min_words}}, {{max_words}}.', 'plugins-alpha'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- REVIEW ROUNDUP -->
                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_review_roundup"><?php esc_html_e('Review Roundup (vários produtos)', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_review_roundup"
                                name="pga_orion_prompts[review_roundup]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'review_roundup')); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Pode usar {{keyword}}, {{title}}, {{locale}} e {{url}} (quando houver página de referência).', 'plugins-alpha'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- REVIEW SINGLE -->
                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_review_single"><?php esc_html_e('Review Single (1 produto)', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_review_single"
                                name="pga_orion_prompts[review_single]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'review_single')); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Pode usar {{keyword}}, {{title}}, {{locale}} e {{url}} como fonte principal.', 'plugins-alpha'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- NEWS -->
                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_news"><?php esc_html_e('Notícia / News', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_news"
                                name="pga_orion_prompts[news]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'news')); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Pode usar {{keyword}}, {{title}}, {{locale}} e {{url}}.', 'plugins-alpha'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- HOW-TO -->
                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_howto"><?php esc_html_e('Guia / How-to', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_howto"
                                name="pga_orion_prompts[howto]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'howto')); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Pode usar {{keyword}}, {{title}}, {{locale}}.', 'plugins-alpha'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- FAQ -->
                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_faq"><?php esc_html_e('FAQ', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_faq"
                                name="pga_orion_prompts[faq]"
                                rows="10"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'faq')); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Pode usar {{keyword}}, {{title}}, {{locale}}.', 'plugins-alpha'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- TÍTULOS -->
                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_title"><?php esc_html_e('Títulos', 'plugins-alpha'); ?></label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_title"
                                name="pga_orion_prompts[title]"
                                rows="8"
                                class="large-text code"><?php echo esc_textarea(pga_orion_prompt_value($raw, 'title')); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Pode usar {{keyword}}, {{min}}, {{max}}, {{locale}} e {{url}}.', 'plugins-alpha'); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- IMAGEM / THUMBNAIL -->
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

                    <!-- REGEN THUMB POR POST -->
                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_post_thumbnail_regen">
                                <?php esc_html_e('Regenerar thumbnail por post', 'plugins-alpha'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea
                                id="pga_orion_prompt_post_thumbnail_regen"
                                name="pga_orion_prompts[post_thumbnail_regen]"
                                rows="8"
                                class="large-text code"><?php
                                                        echo esc_textarea(pga_orion_prompt_value($raw, 'post_thumbnail_regen'));
                                                        ?></textarea>
                            <p class="description">
                                <?php esc_html_e(
                                    'Usado quando você clica para gerar uma nova thumbnail diretamente no post. Pode usar {{title}}, {{content}} (resumo), {{locale}}.',
                                    'plugins-alpha'
                                ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- OUTLINE NORMAL -->
                    <tr>
                        <th scope="row">
                            <label for="pga_orion_prompt_outline">
                                <?php esc_html_e('Esboço', 'plugins-alpha'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="pga_orion_prompt_outline"
                                name="pga_orion_prompts[outline]"
                                rows="10"
                                class="large-text code"><?php
                                                        echo esc_textarea(pga_orion_prompt_value($raw, 'outline'));
                                                        ?></textarea>
                            <p class="description">
                                <?php esc_html_e(
                                    'Usado para gerar o esboço em JSON ("sections") dos artigos longos / extra longos. Pode usar {{keyword}}, {{articleTitle}}, {{locale}}, {{min_words}}, {{max_words}}, {{min_sections}}, {{max_sections}}.',
                                    'plugins-alpha'
                                ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- OUTLINE MODELAR URL -->
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
                                    'Usado quando o modelo for "Modelar URL". Pode usar {{url}}, {{articleTitle}}, {{locale}}, {{min_words}}, {{max_words}}, {{min_sections}}, {{max_sections}}.',
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

            case 'post_thumbnail_regen':
                return self::default_post_thumbnail_regen_prompt();

            case 'story':
                return self::story_default_template();

            case 'discover_article':
                return self::default_article();

            case 'article':
            default:
                return self::default_article();
        }
    }

    /**
     * Faz o replace dos placeholders padrão.
     * Exemplos suportados: {{keyword}}, {{locale}}, {{title}}, {{forced_title}},
     * {{url}}, {{template}}, {{min}}, {{max}}, {{min_words}}, {{max_words}},
     * {{min_sections}}, {{max_sections}}, {{articleTitle}}, {{content}}, {{brief}}.
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
     * @param array  $opts      Ex: ['locale' => 'pt_BR', 'title' => '...', ...]
     * @param string $url       Opcional, mais usado em review_single ou news.
     */
    public static function build_content_prompt(string $template, string $length, string $keyword, array $opts = array(), string $url = ''): string
    {
        $template = $template !== '' ? $template : 'discover_article';

        $locale = isset($opts['locale']) ? (string) $opts['locale'] : 'pt_BR';

        // NOVO PADRÃO: title (mantendo compat com forced_title)
        $title = '';
        if (isset($opts['title']) && is_string($opts['title'])) {
            $title = trim($opts['title']);
        } elseif (isset($opts['forced_title']) && is_string($opts['forced_title'])) {
            // compat legado
            $title = trim($opts['forced_title']);
        }

        $key_map = array(
            'review_roundup' => 'review_roundup',
            'review_single'  => 'review_single',
            'news'           => 'news',
            'howto'          => 'howto',
            'faq'            => 'faq',
            'article'        => 'article',
        );

        $key = isset($key_map[$template]) ? $key_map[$template] : 'article';

        $tpl = self::get_prompt_for($key);

        // usa range numérico, não mais a string "short/medium"
        [$minWords, $maxWords] = self::length_to_range($length);

        $vars = array(
            'keyword'       => $keyword,
            'locale'        => $locale,
            'title'         => $title,
            // compat: ainda preenche forced_title, mas padrão agora é title
            'forced_title'  => $title,
            'url'           => $url,
            'template'      => $template,
            'min_words'     => (string)$minWords,
            'max_words'     => (string)$maxWords,
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

        // formato de JSON controlado no back
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

        return $base . "\n\n" . self::outline_json_suffix();
    }

    /* ---------------------------------------------------------------------
     *  DEFAULTS – aqui você pode ir refinando com calma depois
     * ------------------------------------------------------------------ */

    /**
     * Template padrão para Web Stories.
     * Agora suporta placeholders: {{title}}, {{content}}, {{brief}}, {{locale}}.
     */
    public static function story_default_template(): string
    {
        $s = "";
        $s .= "Você é uma especialista em transformar posts de blog em Web Stories AMP envolventes, otimizadas para leitura rápida em dispositivos móveis.\n\n";

        $s .= "Você receberá:\n";
        $s .= "- Título do post: {{title}}.\n";
        $s .= "- Conteúdo já processado (sem tags HTML): {{content}}.\n";
        $s .= "- Brief adicional (se existir): {{brief}}.\n";
        $s .= "- Locale/idioma: {{locale}}.\n\n";

        $s .= "Sua tarefa é:\n";
        $s .= "- Ler o título e o conteúdo do post.\n";
        $s .= "- Extrair as ideias principais.\n";
        $s .= "- Quebrar em PÁGINAS (slides) curtas e diretas.\n";
        $s .= "- A quebra de páginas em slides deve ser feito entre 7 a 10 slides.\n";
        $s .= "- Criar um fluxo lógico de início, meio e fim.\n";
        $s .= "- Criar uma página final com CTA para ler o conteúdo completo no artigo (chamada para ação), mas crie também o prompt da imagem.\n\n";

        $s .= "Regras importantes gerais:\n";
        $s .= "- Linguagem simples, envolvente e natural, no mesmo idioma de {{locale}}.\n";
        $s .= "- Evite parágrafos muito longos.\n";
        $s .= "- Não invente informações; use apenas o que estiver no conteúdo e no brief.\n\n";

        $s .= "Regras adicionais IMPORTANTES:\n";
        $s .= "- O campo \"body\" de cada página deve ter entre 160 e 240 caracteres.\n";
        $s .= "- Intercale o CTA dos slides: o primeiro não deve ter CTA; do segundo em diante, alterne um slide sem CTA e o próximo com CTA.\n";
        $s .= "- O CTA deve ser um texto simples (\"Saiba mais\", \"Veja mais\", \"Descubra como...\"), sempre em {{locale}}.\n";
        $s .= "- O último slide SEMPRE deve ter CTA para o artigo em questão.\n";
        $s .= "- Evite títulos genéricos como \"Introdução\"; o primeiro título precisa ser o mais chamativo de todos.\n";
        $s .= "- Não inclua comentários ou explicações fora do JSON.\n";
        $s .= "- Não inclua markdown, HTML, bullet points ou explicações fora do JSON.\n";
        $s .= "- No campo \"prompt\" de cada página, crie SEMPRE um prompt de FOTO REALISTA VERTICAL, estilo cinematográfico, cores naturais, sem qualquer texto, sem letras, sem legendas, sem molduras, sem desenho, sem ilustração.\n";

        return $s;
    }

    /**
     * Bloco fixo explicando o FORMATO JSON obrigatório (stories).
     */
    public static function story_json_format_block(): string
    {
        $s  = "Responda APENAS em JSON UTF-8 válido, no seguinte formato exato:\n\n";
        $s .= "{\n";
        $s .= "  \"pages\": [\n";
        $s .= "    {\n";
        $s .= "      \"heading\": \"Título da página\",\n";
        $s .= "      \"body\": \"Texto curto da página.\",\n";
        $s .= "      \"cta_text\": \"Texto do botão ou chamada final (intercalados).\",\n";
        $s .= "      \"cta_url\": \"\",\n";
        $s .= "      \"prompt\": \"Crie um prompt de imagem em português sobre o conteúdo do slide, levando em conta título e conteúdo para gerar uma FOTO REALISTA VERTICAL do tema deste slide, estilo cinematográfico, luz natural, sem molduras, sem desenho, sem ilustração, sem texto, sem legendas, sem letras, sem logos.\"\n";
        $s .= "    }\n";
        $s .= "  ]\n";
        $s .= "}\n\n";

        $s .= "Regras adicionais sobre CTA:\n";
        $s .= "- Deixe SEMPRE o campo \"cta_url\" como string vazia \"\". O sistema preencherá automaticamente com o link do artigo.\n";
        $s .= "- No campo \"prompt\", crie sempre um prompt de FOTO REALISTA VERTICAL, estilo cinematográfico, cores naturais, sem qualquer texto, sem letras, sem legendas, sem molduras, sem desenho, sem ilustração.\n";

        return $s;
    }

    /**
     * Cabeçalho que reforça para a IA que a resposta deve ser só JSON (stories).
     */
    public static function json_header_for_responses_api(): string
    {
        $s  = "IMPORTANTE: a resposta deve ser APENAS um JSON válido.\n";
        $s .= "A palavra \"json\" aparece aqui apenas para atender requisitos internos da API.\n\n";
        $s .= "- No campo \"prompt\", crie sempre um prompt de FOTO REALISTA VERTICAL, estilo cinematográfico, cores naturais, sem qualquer texto, sem letras, sem legendas, sem molduras, sem desenho, sem ilustração.\n";

        return $s;
    }

    /**
     * Monta o prompt completo para gerar Web Stories
     * a partir de um post.
     *
     * @param WP_Post $post
     * @param string  $raw_html Conteúdo HTML do post
     * @param string  $brief    Brief padrão (se existir)
     * @return string           Texto final que será enviado à IA
     */
    public static function build_story_prompt_for_post(WP_Post $post, string $raw_html, string $brief = ''): string
    {
        // 1) Template vindo da central de prompts (chave: story)
        $system_pt = self::get_prompt_for('story'); // já cai no default se vazio

        // 2) Prepara variáveis para replace
        $title   = get_the_title($post);
        $content = wp_strip_all_tags($raw_html);
        $locale  = get_locale() ?: 'pt_BR';

        $vars = [
            'title'   => $title,
            'content' => $content,
            'brief'   => $brief,
            'locale'  => $locale,
        ];

        $system_pt = self::replace_vars($system_pt, $vars);

        // 3) Blocos fixos (formato + header de JSON)
        $format_block = self::story_json_format_block();
        $json_header  = self::json_header_for_responses_api();

        // 4) Monta tudo
        $input_text  = $json_header . "\n";
        $input_text .= $system_pt . "\n\n";
        $input_text .= $format_block . "\n";

        return $input_text;
    }

    public static function build_outline_prompt_modelar(
        string $url,
        string $articleTitle,
        string $length,
        string $locale
    ): string {
        $tpl    = self::get_prompt_for('outline_modelar');
        $locale = $locale ?: 'pt_BR';

        [$minWords, $maxWords] = self::length_to_range($length);
        $cfg         = self::outline_config($length);
        $minSections = $cfg['min_sections'];
        $maxSections = $cfg['max_sections'];

        $vars = [
            'url'          => $url,
            'articleTitle' => $articleTitle,
            'locale'       => $locale,
            'min_words'    => (string)$minWords,
            'max_words'    => (string)$maxWords,
            'min_sections' => (string)$minSections,
            'max_sections' => (string)$maxSections,
        ];

        $base = self::replace_vars($tpl, $vars);

        // INSTRUÇÃO DE JSON fora do prompt editável
        $base .= "\n\n";
        $base .= "Responda APENAS com um JSON UTF-8 válido no formato:\n";
        $base .= "{\n";
        $base .= '  "sections": [' . "\n";
        $base .= '    {' . "\n";
        $base .= '      "id": "1",' . "\n";
        $base .= '      "heading": "Título da seção H2",' . "\n";
        $base .= '      "level": "h2",' . "\n";
        $base .= '      "children": [' . "\n";
        $base .= '        {"id": "1.1", "heading": "Subtópico H3", "level": "h3"}' . "\n";
        $base .= '      ]' . "\n";
        $base .= '    }' . "\n";
        $base .= '  ]' . "\n";
        $base .= "}\n";

        return $base;
    }

    public static function build_section_prompt(
        string $keyword,
        string $articleTitle,
        array $section,
        string $length = 'short',
        string $locale = 'pt_BR',
        int $sectionsCount = 1,
        string $url = ''
    ): string {
        [$globalMin, $globalMax] = self::length_to_range($length);

        $totalMax = $globalMax > 0 ? $globalMax : 800;
        $sectionsCount = max(1, $sectionsCount);
        $approxMax = (int) floor($totalMax / $sectionsCount);
        $approxMin = max(80, (int) floor($approxMax * 0.5));

        $children = (array)($section['children'] ?? []);
        $bullets  = (array)($section['bullets']  ?? []);

        $heading = '';
        if (!empty($section['heading'])) {
            $heading = (string)$section['heading'];
        } elseif (!empty($section['title'])) {
            $heading = (string)$section['title'];
        } elseif (!empty($section['text'])) {
            $heading = (string)$section['text'];
        }

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

        $locale = $locale ?: 'pt_BR';
        $txt  = '';
        $txt .= "Atue como um especialista em SEO escrevendo em {$locale}.\n";
        $txt .= "O foco deste artigo é Google Discover, então o conteúdo deve ser fluido e despertar cada vez mais interesse em ler.\n\n";

        $txt .= "Você deve escrever APENAS o conteúdo (HTML) da seção \"{$heading}\" ({$level})\n";
        $txt .= "do artigo com título exato:\n\n";
        $txt .= "\"{$articleTitle}\"\n\n";

        $txt .= "REGRAS CRÍTICAS SOBRE O TÍTULO:\n";
        $txt .= "- O conteúdo desta seção DEVE ser coerente com o título do artigo.\n";
        $txt .= "- Se o título promete um certo número de passos, dicas, motivos etc,\n";
        $txt .= "  respeite essa estrutura no conjunto das seções (não crie um número diferente).\n";
        $txt .= "- Não mude o foco do artigo. Não contradiga o que o título promete.\n\n";
        $txt .= "- Não insira numeração se o título não for especifico sobre quantidades, exemplo 'x motivos para [...]', 'x itens sobre [...] etc'.\n\n";

        if ($url !== '') {
            $txt .= "Contexto de modelagem:\n";
            $txt .= "- Use como base principal o conteúdo da página em: {$url}\n";
            $txt .= "- Caso não consiga modelar acessar esse site ou for algo relacionado a bot, então deve ser retornado imediatamente um formato incompativel para o formato pedido abaixo, para terminar em erro\n";
            $txt .= "- Leia e entenda o conteúdo dessa página e então reescreva a seção com suas próprias palavras.\n";
            $txt .= "- NUNCA copie frases inteiras ou parágrafos do texto original.\n";
            $txt .= "- NUNCA mencione o nome do site, domínio, marca ou autores da página original.\n";
            $txt .= "- NUNCA use placeholders como \"[Nome do Produto]\"; escreva o texto final completo.\n";
            $txt .= "- Se a página original listar produtos com nomes específicos, use esses nomes reais no texto.\n";
            $txt .= "- NUNCA invente nomes genéricos como \"Rastreador A\", \"Rastreador B\" ou similares.\n";
            $txt .= "- Se não conseguir ler a URL, passe informações de produtos reais, pois o foco deste artigo é ser apresentado como material principal.\n";
            $txt .= "- Evite frases que pareçam slogans ou trechos de marketing do site original (por exemplo, \"testamos todos eles\" ou frases muito similares).\n\n";

            if (trim($keyword) !== '') {
                $txt .= "- Considere também a frase de foco interna \"{$keyword}\" apenas como guia semântico, mas sem tratá-la como referência externa.\n";
            }

            $txt .= "\n";
        } else {
            $txt .= "Frase chave de foco ou comando: \"{$keyword}\".\n";
            $txt .= "- Entenda se este item é uma frase chave ou um comando;\n";
            $txt .= "  se for um comando, siga o sentido do que ele quer dizer.\n\n";
        }

        $txt .= "Regras de tamanho:\n";
        $txt .= "- O texto desta seção deve ter aproximadamente entre {$approxMin} e {$approxMax} palavras.\n";
        $txt .= "- É OBRIGATÓRIO não ultrapassar {$approxMax} palavras.\n";
        $txt .= "- Se estiver chegando perto de {$approxMax} palavras, termine a ideia e finalize a seção.\n";
        $txt .= "- Desenvolva bem as ideias, com explicações e exemplos práticos, mas evite enrolação.\n";
        $txt .= "- Cada parágrafo deve ser curto, para leitura fácil em telas de celular.\n\n";

        $txt .= "Regras de HTML:\n";
        $txt .= "- Não inclua <h1>.\n";
        $txt .= "- Comece o conteúdo já com a tag {$level} principal desta seção.\n";
        $txt .= "- Use parágrafos (<p>) claros e escaneáveis.\n";
        $txt .= "- Use <strong> para negrito, nunca ** **.\n";
        $txt .= "- Use listas não ordenadas (<ul><li>) quando fizer sentido (passo a passo, checklist, dicas etc).\n";
        $txt .= "- Use <p>, <ul>, <li>, <strong> etc. em HTML puro.\n";
        $txt .= "- Trechos importantes do texto devem estar em negrito.\n";
        $txt .= "- No mínimo 40% do conteúdo deve ter palavras de transição, como: mas, por isso, entretanto, isso, quando, em resumo e outras similares, sem perder a voz ativa.\n\n";

        $txt .= "Regras críticas sobre a frase chave (quando existir):\n";
        $txt .= "- Esta seção deve conter ao menos uma vez a frase chave de foco.\n";
        $txt .= "- Se esta for a seção de introdução ou conclusão, então a frase chave deve estar na primeira frase de maneira fluida.\n\n";

        $txt .= "- Nunca copie frases de abertura ou slogans do site de origem (por exemplo: \"testamos todos eles\" ou frases similares).\n";
        $txt .= "- Nunca mencione o nome do site, domínio ou marca da página original.\n";
        $txt .= "- Não faça referências ao fato de estar modelando outro texto; escreva como um artigo original.\n\n";

        $txt .= "Contexto do esboço desta seção:\n";
        $txt .= $bulletsText . "\n";

        $txt .= "IMPORTANTE:\n";
        $txt .= "- Responda APENAS com o HTML desta seção.\n";
        $txt .= "- Não explique o que está fazendo, não inclua comentários fora do HTML.\n";
        $txt .= "- Dê uma solução real para a questão, não crie coisas como 'marca a', 'produto a', coisas ficticias assim. Use seus dados para trazer uma solução real em resposta a keyword.\n";

        return $txt . "\n\n" . self::section_json_suffix($level);
    }

    private static function section_json_suffix(string $level): string
    {
        $s  = '';
        $s .= "IMPORTANTE:\n";
        $s .= "- Responda APENAS em JSON UTF-8 válido, seguindo exatamente o formato abaixo.\n";
        $s .= "- O campo \"content\" deve conter SOMENTE o HTML desta seção, começando pela tag {$level}.\n\n";
        $s .= "{\n";
        $s .= "  \"title\": \"\",\n";
        $s .= "  \"titles_suggestions\": [],\n";
        $s .= "  \"content\": \"<{$level}>...</{$level}>...\",\n";
        $s .= "  \"meta_title\": \"\",\n";
        $s .= "  \"meta_description\": \"\",\n";
        $s .= "  \"image_alt\": \"\",\n";
        $s .= "  \"links\": {\"internal\": [], \"external\": []}\n";
        $s .= "}\n";

        return $s;
    }


    /**
     * Prompt padrão para gerar um prompt de IMAGEM
     * baseado no título + conteúdo de um post.
     */
    private static function default_post_thumbnail_regen_prompt(): string
    {
        $s  = "";
        $s .= "Ultra-realistic natural photo, 16:9 aspect ratio, smartphone camera style.\n";
        $s .= "Soft daylight from a window, simple and authentic visual.\n";
        $s .= "Main subject based on the title and context.\n\n";

        $s .= "title (pt-BR): \"{{title}}\".\n";
        $s .= "context: {{content}}.\n\n";

        $s .= "Show only ONE clear subject. Avoid text, hands, people, watermarks, filters, or clutter.\n";
        $s .= "Background must be real and lightly blurred (kitchen, living room, bedroom, bathroom, or generic indoor environment).\n";
        $s .= "Make it look natural, casual and photographic, not artistic.\n";

        return $s;
    }

    /**
     * Monta o prompt de thumbnail para um post específico,
     * usando título + conteúdo como base.
     */
    public static function build_post_thumbnail_regen_prompt(
        string $title,
        string $content,
        string $locale = 'pt_BR'
    ): string {
        $tpl = self::get_prompt_for('post_thumbnail_regen');

        if (!$tpl) {
            $tpl = self::default_post_thumbnail_regen_prompt();
        }

        $vars = [
            'title'   => $title,
            'content' => $content,
            'locale'  => $locale,
        ];

        return self::replace_vars($tpl, $vars);
    }


    private static function default_outline_modelar_prompt(): string
    {
        $s  = '';
        $s .= 'Você está em 2025 e é um redator sênior especializado em SEO e Google Discover em {{locale}}.' . "\n\n";
        $s .= 'Sua tarefa é criar o ESBOÇO COMPLETO de um artigo com o título:' . "\n";
        $s .= '"{{articleTitle}}".' . "\n\n";

        $s .= "Contexto e referência:\n";
        $s .= "- Acesse e analise o conteúdo da seguinte URL:\n";
        $s .= "  {{url}}\n";
        $s .= "- Use essa página apenas como referência de ideias, estrutura e principais pontos do tema.\n";
        $s .= "- Reescreva tudo com suas próprias palavras, sem copiar trechos literalmente.\n\n";

        $s .= "Regras importantes:\n";
        $s .= "- Nunca mencione a URL, o site ou que está modelando outro conteúdo.\n";
        $s .= "- Não escreva nada sobre \"fonte\", \"referência\" ou créditos.\n";
        $s .= "- O leitor não deve perceber que existe uma página de origem.\n\n";
        $s .= "- Nunca copie frases de abertura ou slogans do site de origem (por exemplo: \"testamos todos eles\" ou similares).\n";
        $s .= "- Nunca mencione o nome do site, domínio ou marca da página original.\n";

        $s .= "Especificações do esboço:\n";
        $s .= "- O artigo final deve ter entre {{min_words}} e {{max_words}} palavras.\n";
        $s .= "- Crie entre {{min_sections}} e {{max_sections}} seções principais de nível H2.\n";
        $s .= "- Quando fizer sentido, crie subtópicos H3 dentro das seções principais.\n";
        $s .= "- Cada título de seção deve ser claro, direto e alinhado com o tema central do artigo.\n\n";

        $s .= "O que entregar agora:\n";
        $s .= "- Apenas o esboço hierárquico (H2 e H3).\n";
        $s .= "- Não escreva o conteúdo completo das seções, apenas os títulos.\n";

        return $s;
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
        $s .= "- Só deve ser inserido numerações se o título tiver alguma quantidade de algo.\n\n";

        $s .= "Regras de tamanho:\n";
        $s .= "- O artigo final terá entre {{min_words}} e {{max_words}} palavras.\n";
        $s .= "- Crie entre {{min_sections}} e {{max_sections}} seções principais (H2).\n";
        $s .= "- Cada H2 pode ter 1 a 3 subseções (H3).\n\n";

        $s .= "Estrutura:\n";
        $s .= "- \"sections\" é um array de seções de nível H2.\n";
        $s .= "- Cada H2 pode conter um array \"children\" com H3 relacionados.\n";
        $s .= "- Com exceção da introdução e finalização, ao menos 1 H2 deve ter subseções.\n";
        $s .= "- Inclua \"bullets\" com ideias que serão desenvolvidas em cada seção.\n\n";

        $s .= "Finalização:\n";
        $s .= "- Finalize sempre com a conclusão, mas jamais coloque o título como 'conclusão'.\n\n";

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

        if ($content !== '') {
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

        $s .= "Responda APENAS em JSON UTF-8 válido, no formato {\"description\": \"...\"}.\n";

        return $s;
    }



    private static function default_article(): string
    {
        $s  = '';
        $s .= 'Estamos em 2025. Você é um redator sênior especializado em SEO, GEO e conteúdo de blog em {{locale}}.' . "\n\n";
        $s .= 'O título do post é: "{{title}}".' . "\n\n";
        $s .= 'Escreva um ARTIGO completo, natural e humanizado sobre: "{{keyword}}".' . "\n\n";
        $s .= 'Use o título exatamente como está em {{title}} quando ele não estiver vazio; caso contrário, escolha o melhor título possível e preencha no JSON.' . "\n\n";
        $s .= "Regras editoriais (não cite estas regras no texto):\n";
        $s .= "- Introdução com a frase-chave {{keyword}} na primeira frase, de forma fluida.\n";
        $s .= "- Corpo em HTML SEM <h1>, organizado em <h2>/<h3> com parágrafos curtos.\n";
        $s .= "- Linguagem clara, prática, com exemplos quando fizer sentido.\n";
        $s .= "- Conclusão retomando os principais pontos e um CTA leve.\n";
        $s .= "- Gere internamente meta_title, meta_description e image_alt coerentes com a keyword.\n";
        $s .= "- O artigo final deve ter entre {{min_words}} e {{max_words}} palavras.\n";

        return $s;
    }


    private static function default_review_roundup(): string
    {
        $s  = '';
        $s .= 'Você é um redator especializado em reviews comparativos, escrevendo em {{locale}}.' . "\n";
        $s .= 'Crie um ARTIGO REVIEW do tipo "roundup" (vários produtos) sobre: "{{keyword}}".' . "\n";
        $s .= 'Se {{title}} não estiver vazio, use como título principal no JSON.' . "\n\n";
        $s .= "Regras principais:\n";
        $s .= "- Estruture em seções por produto e seções comparativas (prós, contras, para quem é indicado).\n";
        $s .= "- Nunca afirme que existe um \"melhor absoluto\"; mostre cenários.\n";
        $s .= "- Use HTML no campo \"content\" (sem <h1>), focando em <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>.\n";
        $s .= "- Inclua CTAs leves para o leitor visitar a página oficial ou site de compra.\n\n";
        $s .= "- O conteúdo deve ser real e buscar de fato produtos que resolvam o problema em questão.\n";
        $s .= "- Gere internamente meta_title, meta_description e image_alt coerentes com a keyword.\n";

        return $s;
    }

    private static function default_review_single(): string
    {
        $s  = '';
        $s .= 'Você é um redator especializado em reviews detalhados de um único produto, em {{locale}}.' . "\n";
        $s .= 'Crie um REVIEW completo sobre o produto relacionado à palavra-chave: "{{keyword}}". Se {{url}} não estiver vazio, use a página como principal fonte de informações (sem copiar trechos literalmente).' . "\n";
        $s .= 'Se {{title}} não estiver vazio, use como título principal no JSON.' . "\n\n";
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
        $s .= 'Se {{title}} não estiver vazio, use como título principal no JSON.' . "\n\n";
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
        $s .= 'Escreva um GUIA / HOW-TO sobre: "{{keyword}}".' . "\n";
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
        $s .= "Se aqui tiver uma url entre aspas, então você deve acessar o conteudo e modelar o titulo: '{{url}}':\n";
        $s .= "- Títulos curtos e específicos, evitando clickbait vazio.\n";
        $s .= "- Use emoção, curiosidade, autoridade e relevância de notícia quando fizer sentido.\n";
        $s .= "- Considere o contexto de Discover: urgência, novidade e interesse atual do público.\n";
        $s .= "- No máximo 60 caracteres.\n";
        $s .= "- Deve ser sempre diferente de anteriores que você já escreveu a pedido meu.\n";
        $s .= "- Nunca gere números absurdos como mais de 20, ou seja, nunca gere nomes como '30 dicas para xxxx'.\n";

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
        $s .= "- Não escreva palavras como 'Descubra', 'veja como' ou palavras desse tipo; prefira inserir uma dor com uma solução dessa dor, falando de possíveis benefícios.\n";
        $s .= "- Peça para não ter textos ou marca d'água.\n";

        return $s;
    }
}
