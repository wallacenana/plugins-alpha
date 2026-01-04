<?php
// includes/Prompts.php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Prompts
{
    public static function register_ajax(): void
    {
        add_action('wp_ajax_pga_orion_prompts_export', [__CLASS__, 'ajax_export']);
        add_action('wp_ajax_pga_orion_prompts_import_prepare', [__CLASS__, 'ajax_import_prepare']);
        add_action('wp_ajax_pga_orion_prompts_import_apply', [__CLASS__, 'ajax_import_apply']);
        add_action('wp_ajax_pga_orion_template_delete', ['PluginsAlpha_Prompts', 'ajax_delete_template']);
    }

    public static function ajax_delete_template(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.'], 403);
        }

        $okNonce = check_ajax_referer('pga_orion_prompts_ie', '_ajax_nonce', false);
        if (!$okNonce) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }

        $slug = sanitize_key((string)($_POST['slug'] ?? ''));
        if ($slug === '' || in_array($slug, ['article', 'modelar_youtube', 'global'], true)) {
            wp_send_json_error(['message' => 'Modelo inválido.'], 400);
        }

        // remove do templates
        $templates = get_option('pga_orion_templates', []);
        if (!is_array($templates)) $templates = [];
        unset($templates[$slug]);
        update_option('pga_orion_templates', $templates, false);

        // remove prompts órfãos
        $prompts = get_option(self::OPTION, []);
        if (!is_array($prompts)) $prompts = [];
        unset($prompts[$slug]);
        update_option(self::OPTION, $prompts, false);

        wp_send_json_success(['message' => "Modelo '{$slug}' removido do banco."]);
    }

    private static function ie_nonce_action(): string
    {
        return 'pga_orion_prompts_ie';
    }

    private static function export_filename(): string
    {
        // YYYY-MM-DD_HH-mm-ss
        return 'orion-prompts-' . gmdate('Y-m-d_H-i-s') . '.json';
    }

    const OPTION = 'pga_orion_prompts';
    public static function date()
    {
        return date("d/m/Y");
    }

    /* =============================
    * STAGES (etapas)
    * ============================= */

    public static function stages(): array
    {
        return [
            'title'                => __('Título', 'plugins-alpha'),
            'outline'              => __('Esboço', 'plugins-alpha'),
            'section'              => __('Seções', 'plugins-alpha'),
            'meta_description'     => __('Meta descrição', 'plugins-alpha'),
            'keywords'             => __('Gerar keywords', 'plugins-alpha'),
            'slug'                 => __('Slug', 'plugins-alpha'),
        ];
    }

    /* =============================
   * OPTION RAW
   * ============================= */
    public static function get_all_raw(): array
    {
        $opt = get_option(self::OPTION, []);
        return is_array($opt) ? $opt : [];
    }

    /* =============================
   * GET PROMPT (template + stage)
   * ============================= */
    public static function get_prompt_for(string $template, string $stage): string
    {
        $template = $template !== '' ? sanitize_key($template) : 'article';
        $stage    = $stage !== '' ? sanitize_key($stage) : 'content';

        $raw = self::get_all_raw();

        // 1) salvo do template
        if (isset($raw[$template][$stage]) && is_string($raw[$template][$stage])) {
            $v = trim($raw[$template][$stage]);
            if ($v !== '') return $v;
        }

        // 2) fallback pro article
        if ($template !== 'article') {
            if (isset($raw['article'][$stage]) && is_string($raw['article'][$stage])) {
                $v = trim($raw['article'][$stage]);
                if ($v !== '') return $v;
            }
        }

        // 3) default interno
        return self::default_prompt_for($template, $stage);
    }

    /* =============================
   * DEFAULTS INTERNOS (CORE)
   * ============================= */
    public static function default_prompt_for(string $template, string $stage): string
    {
        $template = sanitize_key($template);
        $stage    = sanitize_key($stage);


        if ($template === 'modelar_youtube') {
            if ($stage === 'title') {
                return self::default_title_modelar_youtube_prompt();
            }
            if ($stage === 'outline') {
                return self::default_outline_modelar_youtube_prompt();
            }
            if ($stage === 'section') {
                return self::default_section_modelar_youtube_prompt();
            }
        }

        // core 1: article
        switch ($stage) {
            case 'title':
                return self::default_title_prompt();
            case 'outline':
                return self::default_outline_prompt();
            case 'section':
                return self::default_section_base_prompt();
            case 'slug':
                return self::default_slug_prompt();
            case 'image':
                return self::default_image_prompt();
            case 'meta_description':
                return self::default_meta_description_prompt();
            case 'post_thumbnail_regen':
                return self::default_post_thumbnail_regen_prompt();
            case 'story':
                return self::story_default_template();
            case 'keywords':
                return self::default_keywords_prompt();
            default:
                return self::default_outline_prompt();
        }
    }

    /* =============================
   * SUFFIX JSON (NÃO EDITÁVEL)
   * ============================= */
    private static function title_json_suffix(): string
    {
        return "Responda APENAS em JSON UTF-8 válido no formato:\n"
            . "{ \"titles\": [\"Título 1\", \"Título 2\", \"Título 3\"] }\n";
    }

    private static function outline_json_suffix(array $limits = []): string
    {
        $minSections = isset($limits['min_sections']) ? (int)$limits['min_sections'] : 4;
        $maxSections = isset($limits['max_sections']) ? (int)$limits['max_sections'] : 6;

        $locale = isset($limits['locale']) ? (string)$limits['locale'] : 'pt_BR';

        return "Responda SOMENTE em JSON UTF-8 válido, sem markdown.\n"
            . "Regras técnicas (não discuta, apenas cumpra):\n"
            . "- O outline deve estar no idioma {$locale}.\n"
            . "- Crie entre {$minSections} e {$maxSections} seções H2.\n"
            . "Formato exato:\n"
            . "{\n"
            . "  \"sections\": [\n"
            . "    {\n"
            . "      \"id\": \"1\",\n"
            . "      \"level\": \"h2\",\n"
            . "      \"heading\": \"Título H2...\",\n"
            . "      \"bullets\": [\"...\", \"...\"],\n"
            . "      \"children\": [\n"
            . "        {\"id\":\"1\",\"level\":\"h3\",\"heading\":\"Subtítulo H3...\",\"bullets\":[\"...\",\"...\"]}\n"
            . "      ]\n"
            . "    }\n"
            . "  ]\n"
            . "}\n";
    }

    private static function meta_description_json_suffix(): string
    {
        return "Responda APENAS em JSON UTF-8 válido, no formato {\"content\": \"...\"}.\n";
    }

    private static function extract_youtube_chapters(string $desc): array
    {
        $desc = str_replace("\r\n", "\n", $desc);
        $lines = array_map('trim', explode("\n", $desc));

        $chapters = [];
        foreach ($lines as $line) {
            // aceita 0:00, 00:00, 0:00:00, 00:00:00
            if (preg_match('/^(?:\d{1,2}:)?\d{1,2}:\d{2}\s+(.+)$/', $line, $m)) {
                $title = trim($m[1]);
                if ($title !== '') $chapters[] = $title;
            }
        }

        // remove duplicados e lixo
        $chapters = array_values(array_unique(array_filter($chapters, fn($t) => mb_strlen($t) >= 3)));

        return array_slice($chapters, 0, 30);
    }


    public static function build_outline_prompt_modelar_youtube(
        string $url,
        array  $video,
        string $articleTitle,
        string $length,
        string $locale
    ): string {

        $tpl    = self::get_prompt_for('modelar_youtube', 'outline');
        $locale = $locale ?: 'pt_BR';

        [$minWords, $maxWords] = self::length_to_range($length);
        $cfg = self::outline_config($length);

        $videoTitle       = trim((string)($video['title'] ?? ''));
        $videoDescription = trim((string)($video['description'] ?? ''));
        $tagsArr          = (array)($video['tags'] ?? []);

        // limpa e corta descrição
        if ($videoDescription !== '') {
            $videoDescription = wp_strip_all_tags($videoDescription);
            $videoDescription = html_entity_decode($videoDescription, ENT_QUOTES, 'UTF-8');
        }

        // chapters extraídos da descrição (timestamps)
        $chapters = self::extract_youtube_chapters($videoDescription);

        // corta descrição (evita token gigante)
        if ($videoDescription !== '') {
            $videoDescription = function_exists('mb_substr')
                ? mb_substr($videoDescription, 0, 900)
                : substr($videoDescription, 0, 900);
        }

        $tags = '';
        if (!empty($tagsArr)) {
            $tags = implode(', ', array_slice(array_map('trim', $tagsArr), 0, 25));
        }

        // prompt base editável
        $base = self::replace_vars($tpl, [
            'locale'       => $locale,
            'articleTitle' => $articleTitle,
            'url'          => trim((string)$url),
        ]);

        // CONTEXTO INTERNO: o que deixa fiel
        $ctx  = "\n\nCONTEXTO INTERNO (não cite vídeo/canal/URL):\n";
        if ($videoTitle !== '') {
            $ctx .= "- Título do material base: {$videoTitle}\n";
        }
        $ctx .= "- Título do artigo: {$articleTitle}\n";
        $ctx .= "- Hoje é: " . SELF::date();

        if (!empty($chapters)) {
            $ctx .= "- Capítulos (use como esqueleto principal do outline):\n";
            foreach ($chapters as $c) {
                $ctx .= "  - {$c}\n";
            }
            $ctx .= "- Regra: use os capítulos como base do outline.\n";
            $ctx .= "- Se houver MAIS capítulos do que o máximo de seções permitido, AGRUPE capítulos relacionados em uma única seção H2.\n";
            $ctx .= "- Você deve respeitar o limite de seções H2 definido nas regras técnicas.\n";
        } else {
            $ctx .= "- Não há capítulos claros. Use o trecho da descrição para inferir a progressão.\n";
        }

        if ($videoDescription !== '') {
            $ctx .= "- Descrição (trecho):\n{$videoDescription}\n";
        }

        if ($tags !== '') {
            $ctx .= "- Tags (APENAS como apoio, não como base principal): {$tags}\n";
        }

        $ctx .= "- Regra: inclua uma introdução curta (primeira seção H2) contextualizando o tema.\n";
        $ctx .= "- Não use markdown; use somente HTML.\n";

        $suffix = self::outline_json_suffix([
            'min_sections' => (int)$cfg['min_sections'],
            'max_sections' => (int)$cfg['max_sections'],
            'min_words'    => (int)$minWords,
            'max_words'    => (int)$maxWords,
            'locale'       => (string)$locale,
        ]);

        return $base . $ctx . "\n\n" . $suffix;
    }


    /* =============================
   * VAR REPLACE
   * ============================= */
    private static function replace_vars(string $tpl, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{{' . $k . '}}'] = (string)$v;
        }
        return strtr($tpl, $map);
    }

    /* =============================
   * BUILDERS (API pública)
   * ============================= */
    public static function build_title_prompt(
        string $template,
        string $keyword,
        int $min = 3,
        int $max = 5,
        string $locale = 'pt_BR',
        string $url = ''
    ): string {
        $tpl = self::get_prompt_for($template, 'title');

        $base = self::replace_vars($tpl, [
            'keyword' => $keyword,
            'url'     => $url,
        ]);

        return
            "CONTEXTO DO TEMA:\n"
            . "Assunto principal: \"{$keyword}\"\n\n"
            . "Quantidade de títulos a gerar: entre {$min} e {$max}.\n\n"
            . "Escreva em {$locale}.\n\n"
            . "- Hoje é: " . SELF::date() . " (data automatica puxando por função no WordPress, pode usar o ano quando necessário). \n\n"
            . $base
            . "\n\n"
            . self::title_json_suffix();
    }


    public static function build_outline_prompt(
        string $template,
        string $keyword,
        string $articleTitle,
        string $length,
        string $locale,
        string $url = ''
    ): string {
        $tpl = self::get_prompt_for($template, 'outline');

        [$minWords, $maxWords] = self::length_to_range($length);
        $cfg = self::outline_config($length);

        $url = trim((string)$url);

        $base = self::replace_vars($tpl, [
            'keyword'      => $keyword,
            'articleTitle' => $articleTitle,
            'url'          => $url,
        ]);

        $suffix = self::outline_json_suffix([
            'min_sections' => (int)$cfg['min_sections'],
            'max_sections' => (int)$cfg['max_sections'],
            'min_words'    => (int)$minWords,
            'max_words'    => (int)$maxWords,
            'locale'       => (string)$locale,
        ]);

        // CONTEXTO INTERNO: o que deixa fiel
        $ctx  = "\n\nCONTEXTO INTERNO:\n";
        if ($articleTitle !== '') {
            $ctx .= "- Título já definido: {$articleTitle}\n";
        }
        $ctx .= "- Título do artigo: {$articleTitle}\n";
        $ctx .= "- Hoje é: " . SELF::date() . ". ";
        $ctx .= "- Regra: inclua uma introdução curta (primeira seção H2) contextualizando o tema.\n";
        $ctx .= "- Não se esqueça, você é um jornalista sênior especializado em Google Discover, notícias e títulos de alto CTR.\n";
        $ctx .= "- Não use markdown; use somente HTML.\n\n";
        $ctx .= "ESTRUTURA DE KEYWORDS:\n";
        $ctx .= "- o esboço deve ser contextualizado com base na frase chave de foco também: \"{$keyword}\".\n";

        $ctx .= "REGRAS CRÍTICAS SOBRE O TÍTULO:\n";
        $ctx .= "- O conteúdo deste esboço DEVE ser coerente com o título do artigo.\n";
        $ctx .= "- Se o título promete um certo número de passos, dicas, motivos etc,\n";
        $ctx .= "  respeite essa estrutura no conjunto das seções (não crie um número diferente).\n";
        $ctx .= "- Não mude o foco do artigo. Não contradiga o que o título promete.\n\n";
        $ctx .= "- Não insira numeração se o título não for especifico sobre quantidades, exemplo 'x motivos para [...]', 'x itens sobre [...] etc'.\n\n";

        return $base . "\n\n" . $ctx . "\n\n" . $suffix;
    }

    public static function build_meta_description_prompt(string $template, string $keyword, string $articleTitle, string $locale = 'pt_BR', string $content = ''): string
    {
        $tpl = self::get_prompt_for($template, 'meta_description');

        $plain = '';
        if ($content !== '') {
            $plain = wp_strip_all_tags($content);
            $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
            if (function_exists('mb_strlen') && mb_strlen($plain) > 1200) {
                $plain = mb_substr($plain, 0, 1200) . '...';
            } elseif (strlen($plain) > 1200) {
                $plain = substr($plain, 0, 1200) . '...';
            }
        }

        $base = self::replace_vars($tpl, [
            'keyword'      => $keyword,
            'articleTitle' => $articleTitle,
            'locale'       => $locale,
            'content'      => $plain,
        ]);

        $default = "Você é um especialista em SEO e Copywriting em {$locale}.\n"
            . "Sua tarefa é criar uma meta descrição altamente clicável para o Google.\n"
            . "Título: \"{$articleTitle}\"\n"
            . "Palavra-chave principal: \"{$keyword}\"\n";

        return $default . "\n\n" . $base . "\n\n" . self::meta_description_json_suffix();
    }

    public static function build_slug_prompt(string $template, string $keyword, string $articleTitle, string $locale = 'pt_br'): string
    {
        $tpl = self::get_prompt_for($template, 'slug');

        $base = self::replace_vars($tpl, [
            'keyword'      => $keyword,
            'articleTitle' => $articleTitle,
            'locale'       => $locale,
        ]);

        $default = "Palavra-chave principal: \"{$keyword}\"\n"
            . "Gere um slug de URL para o título: \"{$articleTitle}\"\n";

        return $default . "\n\n" . $base . "\n\n" . self::meta_description_json_suffix();
    }

    public static function build_image_prompt(string $template, string $keyword, string $title, string $locale, string $imageProvider = ''): string
    {
        $imageProvider = trim((string)$imageProvider);

        $stage = ($imageProvider === 'pexels' || $imageProvider === 'unsplash')
            ? 'image_stock'
            : 'image';

        $tpl = self::get_prompt_for($template, $stage);

        return self::replace_vars($tpl, [
            'keyword'  => $keyword,
            'locale'   => $locale,
            'template' => $template,
            'title'    => $title,
        ]);
    }

    /* =============================
   * PROMPT: regen thumbnail por post
   * ============================= */
    public static function build_post_thumbnail_regen_prompt(string $title, string $content, string $locale = 'pt_BR', string $imageProvider = ''): string
    {
        // esse stage é "core", não depende do template selecionado no gerador
        $tpl = self::get_prompt_for('article', 'post_thumbnail_regen');

        // Regra dinâmica por provider
        $imageProvider = trim((string)$imageProvider);
        if ($imageProvider === 'pexels' || $imageProvider === 'unsplash') {
            $tpl .= "\n\n";
            $tpl .= "- O resultado final deve ser APENAS uma frase curta (no máximo 4 PALAVRAS SIMPLES), como TAGS de busca.\n";
            $tpl .= "- Ex.: \"cachorro no sofá\", \"mulher sorrindo notebook\".\n";
            $tpl .= "- Não use prefixos como \"Imagem de\".\n";
        } else {
            $tpl .= "\n\n";
            $tpl .= "Regras específicas para IA:\n";
            $tpl .= "- FOTO REALISTA HORIZONTAL 16:9, luz natural, estilo cinematográfico.\n";
            $tpl .= "- Sem texto, sem logos, sem marca d'água.\n";
        }

        $plain = wp_strip_all_tags($content);
        $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
        if (function_exists('mb_strlen') && mb_strlen($plain) > 1200) {
            $plain = mb_substr($plain, 0, 1200) . '...';
        } elseif (strlen($plain) > 1200) {
            $plain = substr($plain, 0, 1200) . '...';
        }

        return self::replace_vars($tpl, [
            'title'   => $title,
            'content' => $plain,
            'locale'  => $locale,
        ]);
    }

    /* =============================
   * STORIES: prompt por post (JSON fixo)
   * ============================= */
    public static function build_story_prompt_for_post(WP_Post $post, string $raw_html, string $brief = '', string $imageProvider = 'pollinations'): string
    {
        $tpl = self::get_prompt_for('article', 'story');

        $title   = get_the_title($post);
        $content = wp_strip_all_tags($raw_html);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        $locale  = get_locale() ?: 'pt_BR';

        // regra dinâmica do campo prompt
        if ($imageProvider === 'pexels' || $imageProvider === 'unsplash') {
            $image_rule =
                'No campo "prompt" gere apenas TAGS curtas (até 4 palavras simples) para banco de imagens.';
        } else {
            $image_rule =
                'No campo "prompt" gere um prompt de FOTO REALISTA VERTICAL, cinematográfica, luz natural, sem texto, sem logos.';
        }

        $base = self::replace_vars($tpl, [
            'title'             => $title,
            'content'           => $content,
            'brief'             => $brief,
            'locale'            => $locale,
            'image_prompt_rule' => $image_rule,
        ]);

        return $base . "\n\n" . self::story_json_format_block();
    }

    public static function ajax_export(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.'], 403);
        }

        $ok = check_ajax_referer(self::ie_nonce_action(), '_ajax_nonce', false);
        if (!$ok) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }

        $data = self::export_data();
        $data['_meta'] = [
            'exported_at_gmt' => gmdate('c'),
            'filename'        => self::export_filename(),
            'version'         => '1',
        ];

        wp_send_json_success($data);
    }


    /* =============================
   * UI: render_page (PROMPTS)
   * ============================= */
    public static function render_page(): void
    {
        $nonce_ie = wp_create_nonce(self::ie_nonce_action());
?>
        <script>
            window.PGA_PROMPTS_EXPORT = {
                ajaxurl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
                nonce: <?php echo wp_json_encode($nonce_ie); ?>
            };
        </script>
        <?php

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão.', 'plugins-alpha'));
        }

        self::handle_save();

        $raw = self::get_all_raw();
        $stages = self::stages();

        $tpls = class_exists('PluginsAlpha_Orion_Templates')
            ? PluginsAlpha_Orion_Templates::get_all()
            : [
                'article' => ['label' => 'Artigo (padrão)', 'builtin' => 1, 'enabled' => 1],
                'modelar_youtube' => ['label' => 'Modelar YouTube', 'builtin' => 1, 'enabled' => 1],
            ];

        // Garante que os 2 core sempre apareçam
        if (!isset($tpls['article'])) {
            $tpls['article'] = ['label' => 'Artigo (padrão)', 'builtin' => 1, 'enabled' => 1];
        }
        if (!isset($tpls['modelar_youtube'])) {
            $tpls['modelar_youtube'] = ['label' => 'Modelar YouTube', 'builtin' => 1, 'enabled' => 1];
        }

        // Ordena: core primeiro
        uksort($tpls, function ($a, $b) {
            $prio = ['article' => 0, 'modelar_youtube' => 1, 'global' => 2];
            $pa = $prio[$a] ?? (!empty($tpls_all[$a]['builtin']) ? 10 : 20);
            $pb = $prio[$b] ?? (!empty($tpls_all[$b]['builtin']) ? 10 : 20);

            if ($pa !== $pb) return $pa <=> $pb;
            return strcmp($a, $b);
        });

        $core_templates = ['article', 'modelar_youtube'];

        $core_defaults = [];
        foreach ($core_templates as $ct) {
            foreach (array_keys($stages) as $sk) {
                $core_defaults[$ct][$sk] = self::default_prompt_for($ct, $sk);
            }
        }

        // Templates salvos
        $tpls_all = class_exists('PluginsAlpha_Orion_Templates')
            ? PluginsAlpha_Orion_Templates::get_all()
            : [];

        // Garante os 2 nativos (se por algum motivo não vierem)
        if (empty($tpls_all['article'])) {
            $tpls_all['article'] = ['label' => 'Artigo (padrão)', 'enabled' => 1, 'builtin' => 1];
        }
        if (empty($tpls_all['modelar_youtube'])) {
            $tpls_all['modelar_youtube'] = ['label' => 'Modelar YouTube', 'enabled' => 1, 'builtin' => 1];
        }

        if (empty($tpls_all['global'])) {
            $tpls_all['global'] = ['label' => 'Global', 'enabled' => 1, 'builtin' => 1];
        }

        // Se não existir nenhum custom ainda, adiciona 1 exemplo (desativado) pra guiar
        $has_custom = false;
        foreach ($tpls_all as $k => $v) {
            if (empty($v['builtin'])) {
                $has_custom = true;
                break;
            }
        }
        if (!$has_custom) {
            $tpls_all['receitas'] = ['label' => 'Receitas (exemplo)', 'enabled' => 0, 'builtin' => 0];
        }

        // Só pra organizar: nativos primeiro
        uksort($tpls_all, function ($a, $b) use ($tpls_all) {
            $ab = !empty($tpls_all[$a]['builtin']) ? 0 : 1;
            $bb = !empty($tpls_all[$b]['builtin']) ? 0 : 1;
            if ($ab !== $bb) return $ab <=> $bb;
            return strcmp($a, $b);
        });

        settings_errors('plugins-alpha-orion-prompts');
        ?>
        <style>
            .pga-card {
                display: block;
            }
        </style>
        <div class="wrap">
            <div class="pga-topbar">
                <div class="pga-title-row">
                    <div>
                        <h1 class="pga-h1"><?php esc_html_e('Prompts do Órion', 'plugins-alpha'); ?></h1>
                        <p class="pga-sub">
                            <?php esc_html_e('Configure o comportamento da IA por modelo e etapa. Campos vazios herdam automaticamente o padrão interno.', 'plugins-alpha'); ?>
                        </p>
                    </div>
                    <div class="pga-import-export">
                        <button type="button" class="pga-btn" id="pga-prompts-export">
                            <?php esc_html_e('Exportar prompts', 'plugins-alpha'); ?>
                        </button>

                        <button type="button" class="pga-btn" id="pga-prompts-import">
                            <?php esc_html_e('Importar prompts', 'plugins-alpha'); ?>
                        </button>

                        <!-- input hidden pra abrir file picker -->
                        <input type="file" id="pga-prompts-import-file" accept="application/json" style="display:none" />
                    </div>

                </div>
            </div>

            <!-- ✅ FORM ENVOLVENDO TUDO (prompts + templates + footer) -->
            <form method="post" action="">
                <?php wp_nonce_field('pga_orion_prompts_save', 'pga_orion_prompts_nonce'); ?>
                <input type="hidden" name="pga_action" value="save">
                <div class="" id="pga-prompts-app">

                    <!-- TABS (Templates) -->
                    <div class="pga-tabs" role="tablist" aria-label="Modelos">
                        <?php
                        $tplIndex = 0;
                        foreach ($tpls as $tpl_slug => $tpl_meta):
                            $tpl_slug = sanitize_key((string)$tpl_slug);
                            $label    = (string)($tpl_meta['label'] ?? $tpl_slug);
                            $isActive = ($tplIndex === 0);
                            $tplIndex++;
                        ?>
                            <button
                                type="button"
                                class="pga-tab"
                                role="tab"
                                aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
                                data-pga-tab="tpl"
                                data-tpl="<?php echo esc_attr($tpl_slug); ?>">
                                <span><?php echo esc_html($label); ?></span>
                            </button>
                        <?php endforeach; ?>

                        <!-- Global tab -->
                        <button
                            type="button"
                            class="pga-tab"
                            role="tab"
                            aria-selected="false"
                            data-pga-tab="tpl"
                            data-tpl="global">
                            <span><?php esc_html_e('Global', 'plugins-alpha'); ?></span>
                        </button>
                    </div>

                    <?php
                    // ===== PANELS por template =====
                    $panelIndex = 0;
                    foreach ($tpls as $tpl_slug => $tpl_meta):
                        $tpl_slug = sanitize_key((string)$tpl_slug);
                        $label    = (string)($tpl_meta['label'] ?? $tpl_slug);
                        $builtin  = !empty($tpl_meta['builtin']);
                        $isActive = ($panelIndex === 0);
                        $panelIndex++;

                        // lista de stages (keys)
                        $stageKeys = array_keys($stages);
                        $firstStage = $stageKeys[0] ?? '';
                    ?>
                        <section class="pga-panel <?php echo $isActive ? 'is-active' : ''; ?>"
                            data-pga-panel="tpl"
                            data-tpl="<?php echo esc_attr($tpl_slug); ?>"
                            aria-label="<?php echo esc_attr($label); ?>">

                            <!-- stage tabs -->
                            <div class="pga-stage-tabs" role="tablist" aria-label="Etapas">
                                <?php foreach ($stages as $stage_key => $stage_label): ?>
                                    <span class="pga-barra">
                                        <button
                                            type="button"
                                            class="pga-stage-tab <?php echo ($stage_key === $firstStage) ? 'is-active' : ''; ?>"
                                            data-pga-tab="stage"
                                            data-stage="<?php echo esc_attr($stage_key); ?>">
                                            <?php echo esc_html($stage_label); ?>
                                        </button>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <!-- stage panels (cards) -->
                            <?php foreach ($stages as $stage_key => $stage_label):

                                // valor salvo ou default efetivo
                                if (isset($raw[$tpl_slug]) && array_key_exists($stage_key, $raw[$tpl_slug])) {
                                    $val = is_string($raw[$tpl_slug][$stage_key]) ? $raw[$tpl_slug][$stage_key] : '';
                                } else {
                                    $val = self::default_prompt_for($tpl_slug, $stage_key);
                                }

                                $default = self::default_prompt_for($tpl_slug, $stage_key);
                                $canRestore = in_array($tpl_slug, ['article', 'modelar_youtube'], true);
                            ?>
                                <div
                                    class="pga-stage-card"
                                    data-pga-panel="stage"
                                    data-stage="<?php echo esc_attr($stage_key); ?>"
                                    style="<?php echo ($stage_key === $firstStage) ? '' : 'display:none'; ?>">

                                    <div class="pga-stage-head">
                                        <!-- <h3>
                                            <?php echo esc_html($stage_label); ?>
                                            <?php if ($stage_key === 'titles'): ?>
                                                <span class="pga-stage-chip">Google Discover</span>
                                            <?php endif; ?>
                                        </h3> -->
                                    </div>

                                    <textarea
                                        class="pga-textarea"
                                        rows="25"
                                        name="pga_orion_prompts[<?php echo esc_attr($tpl_slug); ?>][<?php echo esc_attr($stage_key); ?>]"
                                        data-default-b64="<?php echo esc_attr(base64_encode((string)$default)); ?>"><?php echo esc_textarea($val); ?></textarea>
                                    <?php if ($canRestore): ?>
                                        <button type="button"
                                            class="pga-restore"
                                            data-pga-restore="1">
                                            <span class="dashicons dashicons-update"></span>
                                            <?php esc_html_e('Restaurar padrão', 'plugins-alpha'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    <?php endforeach; ?>


                    <!-- ===== GLOBAL PANEL ===== -->
                    <section class="pga-panel"
                        data-pga-panel="tpl"
                        data-tpl="global"
                        aria-label="Global">

                        <?php
                        $globalStages = [
                            'post_thumbnail_regen' => __('Regenerar thumbnail', 'plugins-alpha'),
                            'image_stock'          => __('Imagem (Pexels / Unsplash)', 'plugins-alpha'),
                            'story'                => __('Web Stories', 'plugins-alpha'),
                        ];
                        $globalKeys = array_keys($globalStages);
                        $firstGlobal = $globalKeys[0] ?? '';
                        ?>

                        <div class="pga-stage-tabs" role="tablist" aria-label="Etapas globais">
                            <?php foreach ($globalStages as $stage_key => $stage_label): ?>
                                <span class="pga-barra">
                                    <button
                                        type="button"
                                        class="pga-stage-tab <?php echo ($stage_key === $firstGlobal) ? 'is-active' : ''; ?>"
                                        data-pga-tab="stage"
                                        data-stage="<?php echo esc_attr($stage_key); ?>">
                                        <?php echo esc_html($stage_label); ?>
                                    </button>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($globalStages as $stage_key => $stage_label):

                            // valor salvo global ou default interno global
                            if (isset($raw['global'][$stage_key]) && is_string($raw['global'][$stage_key])) {
                                $val = $raw['global'][$stage_key];
                            } else {
                                $val = self::get_prompt_for('global', $stage_key);
                            }

                            $default = self::get_prompt_for('global', $stage_key);
                        ?>
                            <div
                                class="pga-stage-card"
                                data-pga-panel="stage"
                                data-stage="<?php echo esc_attr($stage_key); ?>"
                                style="<?php echo ($stage_key === $firstGlobal) ? '' : 'display:none'; ?>">
                                <div class="pga-card">
                                    <div class="pga-stage-head">
                                        <!-- <h3><?php echo esc_html($stage_label); ?></h3> -->

                                        <!-- ✅ nos globais: restaurar sempre -->
                                        <button type="button"
                                            class="pga-restore"
                                            data-pga-restore="1">
                                            <span class="dashicons dashicons-update"></span>
                                            <?php esc_html_e('Restaurar padrão', 'plugins-alpha'); ?>
                                        </button>
                                    </div>

                                    <textarea
                                        class="pga-textarea"
                                        rows="25"
                                        name="pga_orion_prompts[global][<?php echo esc_attr($stage_key); ?>]"
                                        data-default-b64="<?php echo esc_attr(base64_encode((string)$default)); ?>"><?php echo esc_textarea($val); ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>


                    <!-- ✅ MODAL: Modelos de conteúdo (DENTRO DO FORM) -->
                    <div class="pga-modal" id="pga-templates-modal" aria-hidden="true">
                        <div class="pga-modal__backdrop" data-pga-modal-close></div>

                        <div class="pga-modal__panel" role="dialog" aria-modal="true" aria-labelledby="pga-templates-title">
                            <div class="pga-modal__head">
                                <h2 id="pga-templates-title"><?php esc_html_e('Modelos de conteúdo', 'plugins-alpha'); ?></h2>
                                <button type="button" class="pga-btn" data-pga-modal-close><?php esc_html_e('Fechar', 'plugins-alpha'); ?></button>
                            </div>

                            <p class="description" style="margin-top:0;">
                                <?php esc_html_e('Aqui você escolhe quais modelos aparecem no gerador do Órion. O plugin mantém 2 nativos: Artigo e Modelar YouTube.', 'plugins-alpha'); ?>
                            </p>

                            <table class="pga-table" id="pga-orion-templates-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Modelo', 'plugins-alpha'); ?></th>
                                        <th style="width:240px;"><?php esc_html_e('Ativo', 'plugins-alpha'); ?></th>
                                        <th style="width:160px;text-align:right;"><?php esc_html_e('Ações', 'plugins-alpha'); ?></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($tpls_all as $slug => $row):
                                        $slug = sanitize_key((string)$slug);
                                        $is_builtin = !empty($row['builtin']) || in_array($slug, ['article', 'modelar_youtube', 'global'], true);
                                        $label = (string)($row['label'] ?? $slug);
                                        $enabled = !empty($row['enabled']) ? 1 : 0;
                                    ?>
                                        <tr data-slug="<?php echo esc_attr($slug); ?>" data-builtin="<?php echo $is_builtin ? '1' : '0'; ?>">
                                            <td>
                                                <input
                                                    class="pga-input"
                                                    name="pga_orion_templates[<?php echo esc_attr($slug); ?>][label]"
                                                    value="<?php echo esc_attr($label); ?>"
                                                    <?php echo $is_builtin ? 'disabled' : ''; ?>>
                                            </td>

                                            <td>
                                                <div class="pga-switch">
                                                    <label>
                                                        <input type="checkbox"
                                                            name="pga_orion_templates[<?php echo esc_attr($slug); ?>][enabled]"
                                                            value="1"
                                                            <?php checked($enabled === 1); ?>
                                                            <?php echo $is_builtin ? 'disabled' : ''; ?>>
                                                        <strong><?php echo $enabled ? esc_html__('Ativo', 'plugins-alpha') : esc_html__('Inativo', 'plugins-alpha'); ?></strong>
                                                    </label>

                                                    <?php if ($is_builtin): ?>
                                                        <input type="hidden"
                                                            name="pga_orion_templates[<?php echo esc_attr($slug); ?>][enabled]"
                                                            value="1">
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td style="text-align:right;">
                                                <?php if (!$is_builtin): ?>
                                                    <button type="button" class="pga-btn pga-remove-tpl-row"><?php esc_html_e('Remover', 'plugins-alpha'); ?></button>
                                                <?php else: ?>
                                                    <span class="pga-mini">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>

                                <tfoot>
                                    <tr>
                                        <td colspan="3">
                                            <button type="button" class="pga-btn" id="pga-add-tpl-row">+ <?php esc_html_e('Adicionar modelo custom', 'plugins-alpha'); ?></button>
                                            <span class="pga-mini" style="margin-left:10px;"><?php esc_html_e('Ex.: receitas, review, modelar_url', 'plugins-alpha'); ?></span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Loading overlay -->
                    <div class="pga-loading" id="pga-loading" aria-hidden="true">
                        <div class="pga-loading-card"><?php esc_html_e('Carregando…', 'plugins-alpha'); ?></div>
                    </div>

                    <!-- ✅ BARRA FIXA (DENTRO DO FORM) -->
                    <div class="pga-bottom-bar">
                        <div class="pga-bottom-left">
                            <button type="submit" class="pga-btn pga-btn--primary">
                                <?php esc_html_e('Salvar prompts', 'plugins-alpha'); ?>
                            </button>

                            <button type="button" class="pga-btn" id="pga-open-templates">
                                <?php esc_html_e('Modelos', 'plugins-alpha'); ?>
                            </button>
                            <button type="button" class="pga-btn" id="pga-vars-btn">
                                <?php esc_html_e('Variáveis Disponíveis', 'plugins-alpha'); ?>
                            </button>
                            <div id="pga-vars-panel" class="pga-vars-panel">
                                <div class="pga-vars-pop" id="pga-vars-pop" aria-hidden="true">
                                    <div class="pga-vars-pop__body">
                                        <div class="pga-vars-grid">
                                            <h3>Título (titles)</h3>
                                            <code>{{keyword}}</code>
                                            <code>{{locale}}</code>
                                            <code>{{url}}</code>

                                            <h3>Outline</h3>
                                            <code>{{keyword}}</code>
                                            <code>{{articleTitle}}</code>
                                            <code>{{locale}}</code>
                                            <code>{{url}}</code>

                                            <h3>Sessão (section)</h3>
                                            <code>{{keyword}}</code>
                                            <code>{{articleTitle}}</code>
                                            <code>{{locale}}</code>
                                            <code>{{url}}</code>
                                            <code>{{section_number}}</code>
                                            <code>{{section_level}}</code>
                                            <code>{{section_bullets}}</code>
                                            <code>{{section_children}}</code>
                                            <code>{{sections_count}}</code>

                                            <h3>Thumb e re-geração (image / post_thumbnail_regen / image_stock)</h3>
                                            <code>{{keyword}}</code>
                                            <code>{{title}}</code>
                                            <code>{{locale}}</code>

                                            <h3>Stories</h3>
                                            <code>{{keyword}}</code>
                                            <code>{{title}}</code>
                                            <code>{{locale}}</code>
                                            <code>{{image_prompt_rule}}</code>

                                            <h3>Keywords</h3>
                                            <code>{{locale}}</code>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /app -->
            </form>
        </div>

        <script>
            (function() {
                // =========================
                // Helpers
                // =========================
                function decodeB64Unicode(b64) {
                    try {
                        const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
                        return new TextDecoder('utf-8').decode(bytes);
                    } catch (e) {
                        try {
                            return atob(b64);
                        } catch (e2) {
                            return '';
                        }
                    }
                }

                function showLoading(on) {
                    const el = document.getElementById('pga-loading');
                    if (!el) return;
                    el.classList.toggle('is-on', !!on);
                    el.setAttribute('aria-hidden', on ? 'false' : 'true');
                }

                // =========================
                // Template Tabs
                // =========================
                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('[data-pga-tab="tpl"]');
                    if (!btn) return;

                    const tpl = btn.getAttribute('data-tpl');
                    if (!tpl) return;

                    // set active tab
                    document.querySelectorAll('[data-pga-tab="tpl"]').forEach(t => {
                        t.setAttribute('aria-selected', (t === btn) ? 'true' : 'false');
                    });

                    // show panel
                    document.querySelectorAll('[data-pga-panel="tpl"]').forEach(p => {
                        const is = p.getAttribute('data-tpl') === tpl;
                        p.classList.toggle('is-active', is);
                    });
                });

                // =========================
                // Stage Tabs (inside active template panel)
                // =========================
                document.addEventListener('click', function(e) {
                    const btn = e.target.closest('[data-pga-tab="stage"]');
                    if (!btn) return;

                    // find current template panel
                    const panel = btn.closest('[data-pga-panel="tpl"]');
                    if (!panel) return;

                    const stage = btn.getAttribute('data-stage');
                    if (!stage) return;

                    // set active stage tab inside this panel
                    panel.querySelectorAll('[data-pga-tab="stage"]').forEach(t => {
                        t.classList.toggle('is-active', (t === btn));
                    });

                    // show stage card inside this panel
                    panel.querySelectorAll('[data-pga-panel="stage"]').forEach(c => {
                        const is = c.getAttribute('data-stage') === stage;
                        c.style.display = is ? '' : 'none';
                    });
                });

                document.getElementById('pga-vars-btn').addEventListener('click', function() {
                    var panel = document.getElementById('pga-vars-panel');
                    panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
                });

                (function() {
                    function decodeB64Unicode(b64) {
                        try {
                            const bytes = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
                            return new TextDecoder('utf-8').decode(bytes);
                        } catch (e) {
                            try {
                                return atob(b64);
                            } catch (e2) {
                                return '';
                            }
                        }
                    }

                    document.addEventListener('click', function(e) {
                        const btn = e.target.closest('.pga-restore,[data-pga-restore="1"]');
                        if (!btn) return;

                        const stagePanel = btn.closest('[data-pga-panel="stage"]') || btn.closest('.pga-stage');
                        if (!stagePanel) return;

                        const ta = stagePanel.querySelector('textarea[data-default-b64]');
                        if (!ta) return;

                        const val = decodeB64Unicode(ta.getAttribute('data-default-b64') || '');

                        const apply = () => {
                            ta.value = val;
                            ta.dispatchEvent(new Event('input', {
                                bubbles: true
                            }));
                        };

                        if (window.Swal) {
                            Swal.fire({
                                title: 'Restaurar padrão?',
                                text: 'Vamos substituir o conteúdo atual deste campo pelo padrão do sistema.',
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Restaurar',
                                cancelButtonText: 'Cancelar'
                            }).then(r => {
                                if (r.isConfirmed) apply();
                            });
                        } else {
                            if (confirm('Restaurar padrão deste campo?')) apply();
                        }
                    });
                })();

                // =========================
                // Modal open/close
                // =========================
                function openModal() {
                    const m = document.getElementById('pga-templates-modal');
                    if (!m) return;
                    m.classList.add('is-open');
                    m.setAttribute('aria-hidden', 'false');
                }

                function closeModal() {
                    const m = document.getElementById('pga-templates-modal');
                    if (!m) return;
                    m.classList.remove('is-open');
                    m.setAttribute('aria-hidden', 'true');
                }

                document.addEventListener('click', function(e) {
                    if (e.target.closest('#pga-open-templates')) {
                        e.preventDefault();
                        openModal();
                    }
                    if (e.target.closest('[data-pga-modal-close]')) {
                        e.preventDefault();
                        closeModal();
                    }
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        const m = document.getElementById('pga-templates-modal');
                        if (m && m.classList.contains('is-open')) closeModal();
                    }
                });

                // =========================
                // Templates: add/remove row (names kept!)
                // =========================
                function slugify(s) {
                    s = (s || '').toString().trim().toLowerCase();
                    s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    s = s.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
                    if (!s) s = 'modelo_' + Math.floor(Math.random() * 9999);
                    return s;
                }

                // ✅ ADD TEMPLATE (SweetAlert) — substitui o window.prompt
                document.addEventListener('click', async function(e) {
                    const addBtn = e.target.closest('#pga-add-tpl-row');
                    if (!addBtn) return;

                    const table = document.getElementById('pga-orion-templates-table');
                    if (!table) return;

                    const tbody = table.querySelector('tbody');
                    if (!tbody) return;

                    // helper: slugify (mantém o seu padrão)
                    function slugify(str) {
                        return String(str || '')
                            .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // remove acentos
                            .toLowerCase()
                            .trim()
                            .replace(/[^a-z0-9]+/g, '_')
                            .replace(/^_+|_+$/g, '');
                    }

                    // ✅ se não tiver Swal, cai pro prompt antigo
                    if (!window.Swal) {
                        const label = window.prompt('Nome do modelo (ex.: Receitas, Review, Modelar URL):', '');
                        if (!label) return;

                        const slug = slugify(label);
                        if (!slug) return;

                        if (tbody.querySelector(`tr[data-slug="${CSS.escape(slug)}"]`)) {
                            alert('Já existe um modelo com esse slug: ' + slug);
                            return;
                        }

                        const tr = document.createElement('tr');
                        tr.setAttribute('data-slug', slug);
                        tr.setAttribute('data-builtin', '0');
                        tr.innerHTML = `
      <td>
        <input class="pga-input"
          name="pga_orion_templates[${slug}][label]"
          value="${String(label).replace(/"/g,'&quot;')}">
      </td>
      <td>
        <div class="pga-switch">
          <label>
            <input type="checkbox"
              name="pga_orion_templates[${slug}][enabled]"
              value="1" checked>
            <strong>Ativo</strong>
          </label>
        </div>
      </td>
      <td style="text-align:right;">
        <button type="button" class="pga-btn pga-remove-tpl-row">Remover</button>
      </td>
    `;
                        tbody.appendChild(tr);
                        return;
                    }

                    // ✅ SweetAlert modal input
                    const res = await Swal.fire({
                        title: 'Adicionar modelo',
                        html: `<div style="text-align:left">
        <div style="font-size:13px;color:#666;margin:0 0 10px">
          Digite um nome (ex.: <b>Receitas</b>, <b>Review</b>, <b>Modelar URL</b>).
        </div>
        <input id="pga_tpl_label" class="swal2-input" placeholder="Nome do modelo" autocomplete="off">
        <div style="display:flex;gap:10px;margin-top:10px;align-items:center">
          <label style="display:flex;gap:8px;align-items:center;font-size:13px;color:#444;margin:0">
            <input id="pga_tpl_enabled" type="checkbox" checked style="transform:scale(1.05)">
            Criar como <b>Ativo</b>
          </label>
        </div>
      </div>`,
                        focusConfirm: false,
                        showCancelButton: true,
                        confirmButtonText: 'Adicionar',
                        cancelButtonText: 'Cancelar',
                        preConfirm: () => {
                            const label = (document.getElementById('pga_tpl_label')?.value || '').trim();
                            const enabled = !!document.getElementById('pga_tpl_enabled')?.checked;

                            if (label.length < 2) {
                                Swal.showValidationMessage('Digite um nome com ao menos 2 caracteres.');
                                return false;
                            }

                            const slug = slugify(label);
                            if (!slug) {
                                Swal.showValidationMessage('Não consegui gerar o slug. Tente outro nome.');
                                return false;
                            }

                            // evita duplicar slug
                            if (tbody.querySelector(`tr[data-slug="${CSS.escape(slug)}"]`)) {
                                Swal.showValidationMessage('Já existe um modelo com esse slug: ' + slug);
                                return false;
                            }

                            return {
                                label,
                                slug,
                                enabled
                            };
                        },
                    });

                    if (!res.isConfirmed || !res.value) return;

                    const {
                        label,
                        slug,
                        enabled
                    } = res.value;

                    const tr = document.createElement('tr');
                    tr.setAttribute('data-slug', slug);
                    tr.setAttribute('data-builtin', '0');

                    tr.innerHTML = `
    <td>
      <input class="pga-input"
        name="pga_orion_templates[${slug}][label]"
        value="${String(label).replace(/"/g, '&quot;')}">
    </td>
    <td>
      <div class="pga-switch">
        <label>
          <input type="checkbox"
            name="pga_orion_templates[${slug}][enabled]"
            value="1" ${enabled ? 'checked' : ''}>
          <strong>${enabled ? 'Ativo' : 'Inativo'}</strong>
        </label>
      </div>
    </td>
    <td style="text-align:right;">
      <button type="button" class="pga-btn pga-remove-tpl-row">Remover</button>
    </td>
  `;

                    tbody.appendChild(tr);

                    try {
                        document.body.classList.add('pga-has-unsaved');
                    } catch (e) {}

                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Modelo adicionado',
                            html: `
                                <div style="text-align:left">
                                    <div><b>${label}</b></div>
                                    <div style="margin-top:6px;color:#666;font-size:13px">
                                    Ele já aparece na lista, mas <b>ainda não foi salvo</b>.<br>
                                    Clique em <b>Salvar prompts</b> na barra inferior para gravar.
                                    </div>
                                </div>
                                `,
                            confirmButtonText: 'OK',
                            allowOutsideClick: true,
                        });
                    }

                });


                document.addEventListener('click', function(e) {
                    const rm = e.target.closest('.pga-remove-tpl-row');
                    if (!rm) return;

                    const tr = rm.closest('tr');
                    if (!tr) return;

                    // remove row
                    tr.parentNode.removeChild(tr);
                });

            })();
        </script>
<?php
    }

    private static function handle_save(): void
    {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper((string) sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : '';

        if ($method !== 'POST') return;

        if (!current_user_can('manage_options')) return;

        $action = isset($_POST['pga_action'])
            ? sanitize_key((string) wp_unslash($_POST['pga_action']))
            : '';

        // =========================================================
        // 1) IMPORT (form separado)
        // =========================================================
        if ($action === 'import') {

            if (
                empty($_POST['pga_orion_prompts_import_nonce']) ||
                !wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_POST['pga_orion_prompts_import_nonce'])),
                    'pga_orion_prompts_import'
                )
            ) {
                add_settings_error(
                    'plugins-alpha-orion-prompts',
                    'pga_import_nonce',
                    __('Nonce inválido no import.', 'plugins-alpha'),
                    'error'
                );
                return;
            }

            if (empty($_FILES['pga_orion_import_file']['tmp_name'])) {
                add_settings_error(
                    'plugins-alpha-orion-prompts',
                    'pga_import_file',
                    __('Envie um arquivo JSON para importar.', 'plugins-alpha'),
                    'error'
                );
                return;
            }

            $raw = file_get_contents($_FILES['pga_orion_import_file']['tmp_name']);
            $data = json_decode((string) $raw, true);

            if (!is_array($data)) {
                add_settings_error(
                    'plugins-alpha-orion-prompts',
                    'pga_import_json',
                    __('JSON inválido.', 'plugins-alpha'),
                    'error'
                );
                return;
            }

            return;
        }

        // =========================================================
        // 2) SAVE PROMPTS (form principal)
        // =========================================================
        if (
            empty($_POST['pga_orion_prompts_nonce']) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['pga_orion_prompts_nonce'])),
                'pga_orion_prompts_save'
            )
        ) {
            return;
        }

        $raw = [];
        if (isset($_POST['pga_orion_prompts'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw = wp_unslash($_POST['pga_orion_prompts']);
        }

        $out = [];

        if (is_array($raw)) {
            foreach ($raw as $tpl => $st) {
                $tpl = sanitize_key((string)$tpl);
                if (!is_array($st)) continue;

                foreach ($st as $stage => $val) {
                    $stage = sanitize_key((string)$stage);
                    if (!is_string($val)) continue;

                    $out[$tpl][$stage] = wp_kses_post($val);
                }
            }
        }

        // templates (do modal)
        // ... você já tem $templates e $out prontos

        $templates = (array) ($_POST['pga_orion_templates'] ?? []);

        // ✅ normaliza slugs válidos que ficaram na tabela (inclui nativos)
        $keep = [];
        foreach ($templates as $slug => $row) {
            $slug = sanitize_key((string)$slug);
            if ($slug === '') continue;
            if ($slug === 'global') continue;
            $keep[$slug] = true;
        }
        $keep['article'] = true;
        $keep['modelar_youtube'] = true;

        // ✅ remove prompts de templates que não existem mais
        if (is_array($out)) {
            foreach (array_keys($out) as $tpl_slug) {
                $tpl_slug = sanitize_key((string)$tpl_slug);
                if ($tpl_slug === 'global') continue;
                if (empty($keep[$tpl_slug])) {
                    unset($out[$tpl_slug]);
                }
            }
        }

        // ✅ também limpa templates removidos (opcional, mas coerente)
        $clean_templates = [];
        foreach ($templates as $slug => $row) {
            $slug = sanitize_key((string)$slug);
            if ($slug === '' || $slug === 'global') continue;

            $label = sanitize_text_field((string)($row['label'] ?? $slug));
            $enabled = !empty($row['enabled']) ? 1 : 0;

            // força nativos
            if (in_array($slug, ['article', 'modelar_youtube'], true)) {
                $clean_templates[$slug] = ['label' => $label ?: $slug, 'enabled' => 1, 'builtin' => 1];
            } else {
                $clean_templates[$slug] = ['label' => $label ?: $slug, 'enabled' => $enabled, 'builtin' => 0];
            }
        }

        update_option('pga_orion_templates', $clean_templates, false);
        update_option(self::OPTION, $out, false);


        add_settings_error(
            'plugins-alpha-orion-prompts',
            'pga_orion_prompts_updated',
            __('Prompts salvos com sucesso.', 'plugins-alpha'),
            'updated'
        );
    }

    private static function export_data(): array
    {
        $templates = get_option('pga_orion_templates', []);
        if (!is_array($templates)) $templates = [];

        $keep = ['article' => true, 'modelar_youtube' => true];
        foreach ($templates as $slug => $_) {
            $slug = sanitize_key((string)$slug);
            if ($slug && $slug !== 'global') $keep[$slug] = true;
        }

        $prompts = get_option(self::OPTION, []);
        if (!is_array($prompts)) $prompts = [];

        $filtered_prompts = [];
        foreach ($prompts as $tpl_slug => $stages) {
            $tpl_slug = sanitize_key((string)$tpl_slug);
            if (empty($keep[$tpl_slug])) continue;
            if (!is_array($stages)) continue;
            $filtered_prompts[$tpl_slug] = $stages;
        }

        if (!is_array($templates)) $templates = [];
        if (!is_array($prompts))   $prompts   = [];

        // remove "global" de templates se aparecer
        if (isset($templates['global'])) unset($templates['global']);

        return [
            'templates' => $templates,
            'prompts'   => $prompts,
        ];
    }

    public static function ajax_import_prepare(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.'], 403);
        }

        $ok = check_ajax_referer(self::ie_nonce_action(), '_ajax_nonce', false);
        if (!$ok) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }

        // IMPORTANT: o JS envia como "file"
        if (empty($_FILES['file']) || !isset($_FILES['file']['tmp_name'])) {
            wp_send_json_error(['message' => 'Arquivo não recebido (campo "file").'], 400);
        }

        $f = $_FILES['file'];

        if (!empty($f['error'])) {
            wp_send_json_error(['message' => 'Erro no upload: ' . (int)$f['error']], 400);
        }

        $raw = @file_get_contents($f['tmp_name']);
        if (!$raw) {
            wp_send_json_error(['message' => 'Não consegui ler o arquivo enviado.'], 400);
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            wp_send_json_error(['message' => 'JSON inválido.'], 400);
        }

        // valida estrutura mínima
        $templates = isset($json['templates']) && is_array($json['templates']) ? $json['templates'] : [];
        $prompts   = isset($json['prompts'])   && is_array($json['prompts'])   ? $json['prompts']   : [];

        if (!$templates && !$prompts) {
            wp_send_json_error(['message' => 'Arquivo não contém "templates" nem "prompts".'], 400);
        }

        // monta lista importável
        $items = self::build_import_items($templates, $prompts);

        // token + transient com payload (15 min)
        $token = wp_generate_password(20, false, false);
        $uid   = get_current_user_id();
        $tkey  = "pga_orion_ie_{$uid}_{$token}";

        set_transient($tkey, [
            'uid'       => $uid,
            'templates' => $templates,
            'prompts'   => $prompts,
            'items'     => $items,
            'created'   => time(),
        ], 15 * MINUTE_IN_SECONDS);

        wp_send_json_success([
            'token' => $token,
            'items' => $items,
        ]);
    }

    public static function ajax_import_apply(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Sem permissão.'], 403);
        }

        $ok = check_ajax_referer(self::ie_nonce_action(), '_ajax_nonce', false);
        if (!$ok) {
            wp_send_json_error(['message' => 'Nonce inválido.'], 403);
        }

        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        if ($token === '') {
            wp_send_json_error(['message' => 'Token ausente.'], 400);
        }

        $uid  = get_current_user_id();
        $tkey = "pga_orion_ie_{$uid}_{$token}";
        $pack = get_transient($tkey);

        if (!is_array($pack) || (int)($pack['uid'] ?? 0) !== (int)$uid) {
            wp_send_json_error(['message' => 'Token inválido/expirado.'], 400);
        }

        $overwrite = !empty($_POST['overwrite']) && (string)$_POST['overwrite'] === '1';

        $keys_json = isset($_POST['keys']) ? (string) wp_unslash($_POST['keys']) : '[]';
        $keys = json_decode($keys_json, true);
        if (!is_array($keys) || empty($keys)) {
            wp_send_json_error(['message' => 'Nenhum item selecionado.'], 400);
        }

        $templates = is_array($pack['templates'] ?? null) ? $pack['templates'] : [];
        $prompts   = is_array($pack['prompts'] ?? null) ? $pack['prompts'] : [];

        $result = self::apply_import_selected($templates, $prompts, $keys, $overwrite);

        // mata o token
        delete_transient($tkey);

        wp_send_json_success([
            'message' => sprintf('Importado: %d item(ns).', (int)$result['imported']),
            'details' => $result,
        ]);
    }

    private static function build_import_items(array $templates, array $prompts): array
    {
        $allowed_stages = array_keys(self::stages());
        $allowed_stages = array_merge($allowed_stages, ['image', 'image_stock', 'post_thumbnail_regen', 'story']);

        // existentes
        $current_templates = get_option('pga_orion_templates', []);
        $current_prompts   = get_option(self::OPTION, []);

        if (!is_array($current_templates)) $current_templates = [];
        if (!is_array($current_prompts))   $current_prompts   = [];

        $items = [];

        // templates
        foreach ($templates as $slug => $meta) {
            $slug = sanitize_key((string)$slug);
            if ($slug === '' || $slug === 'global') continue; // não importar "global" como template

            $label = is_array($meta) && isset($meta['label']) ? (string)$meta['label'] : $slug;

            $items[] = [
                'key'         => 'tpl:' . $slug,
                'type'        => 'template',
                'tpl'         => $slug,
                'stage'       => '',
                'label'       => $label,
                'hasExisting' => !empty($current_templates[$slug]),
                'size'        => (int) strlen($label),
            ];
        }

        // prompts
        foreach ($prompts as $tpl_slug => $stages) {
            $tpl_slug = sanitize_key((string)$tpl_slug);
            if ($tpl_slug === '' || $tpl_slug === 'global') continue; // global é tratado separado (se você quiser)
            if (!is_array($stages)) continue;

            foreach ($stages as $stage => $val) {
                $stage = sanitize_key((string)$stage);
                if (!in_array($stage, $allowed_stages, true)) continue;
                if (!is_string($val)) continue;

                $hasExisting = !empty($current_prompts[$tpl_slug]) && array_key_exists($stage, (array)$current_prompts[$tpl_slug]);

                $items[] = [
                    'key'         => 'pr:' . $tpl_slug . ':' . $stage,
                    'type'        => 'prompt',
                    'tpl'         => $tpl_slug,
                    'stage'       => $stage,
                    'label'       => '',
                    'hasExisting' => $hasExisting,
                    'size'        => (int) strlen($val),
                ];
            }
        }

        return $items;
    }

    private static function apply_import_selected(array $import_templates, array $import_prompts, array $keys, bool $overwrite): array
    {
        $allowed_stages = array_keys(self::stages());
        $allowed_stages = array_merge($allowed_stages, ['image', 'image_stock', 'post_thumbnail_regen', 'story']);

        $current_templates = get_option('pga_orion_templates', []);
        $current_prompts   = get_option(self::OPTION, []);

        if (!is_array($current_templates)) $current_templates = [];
        if (!is_array($current_prompts))   $current_prompts   = [];

        $imported = 0;

        // normaliza keys
        if (!is_array($keys)) $keys = [];
        $keys = array_values(array_unique(array_filter(array_map(function ($k) {
            $k = is_string($k) ? $k : '';
            $k = trim($k);
            return $k !== '' ? $k : null;
        }, $keys))));

        // index rápido do que foi selecionado
        $want = array_fill_keys(array_map('strval', $keys), true);

        // se veio sem nenhum prefixo reconhecido, NUNCA importa tudo
        $has_prefixed = false;
        foreach ($want as $k => $_) {
            $k = (string)$k;
            if (strpos($k, 'tpl:') === 0 || strpos($k, 'pr:') === 0 || strpos($k, 'template:') === 0 || strpos($k, 'prompt:') === 0) {
                $has_prefixed = true;
                break;
            }
        }

        // compat: aceita também "template:" e "prompt:" (converte para tpl:/pr:)
        if ($has_prefixed) {
            $want2 = $want;
            foreach ($want as $k => $_) {
                $k = (string)$k;

                if (strpos($k, 'template:') === 0) {
                    $slug = sanitize_key(substr($k, 9));
                    if ($slug !== '') $want2['tpl:' . $slug] = true;
                    continue;
                }

                if (strpos($k, 'prompt:') === 0) {
                    $rest = trim(substr($k, 7));
                    if ($rest !== '') {
                        // espera prompt:<tpl_slug>:<stage>
                        $parts = explode(':', $rest, 3);
                        $tpl   = sanitize_key($parts[0] ?? '');
                        $stage = sanitize_key($parts[1] ?? '');
                        if ($tpl !== '' && $stage !== '') {
                            $want2['pr:' . $tpl . ':' . $stage] = true;
                        }
                    }
                    continue;
                }
            }
            $want = $want2;
        }

        // 1) templates selecionados
        foreach ($import_templates as $slug => $meta) {
            $slug = sanitize_key((string)$slug);
            if ($slug === '' || $slug === 'global') continue;

            $k = 'tpl:' . $slug;
            if (empty($want[$k])) continue;

            $exists = array_key_exists($slug, $current_templates);
            if ($exists && !$overwrite) {
                continue;
            }

            $label   = is_array($meta) && isset($meta['label']) ? sanitize_text_field((string)$meta['label']) : $slug;
            $enabled = is_array($meta) && array_key_exists('enabled', $meta) ? (int)!empty($meta['enabled']) : 1;

            if (in_array($slug, ['article', 'modelar_youtube'], true)) {
                $current_templates[$slug] = ['label' => $label ?: $slug, 'enabled' => 1, 'builtin' => 1];
            } else {
                $current_templates[$slug] = ['label' => $label ?: $slug, 'enabled' => $enabled, 'builtin' => 0];
            }

            $imported++;
        }

        // 2) prompts selecionados
        foreach ($import_prompts as $tpl_slug => $stages) {
            $tpl_slug = sanitize_key((string)$tpl_slug);
            if ($tpl_slug === '' || $tpl_slug === 'global') continue;
            if (!is_array($stages)) continue;

            foreach ($stages as $stage => $val) {
                $stage = sanitize_key((string)$stage);
                if (!in_array($stage, $allowed_stages, true)) continue;
                if (!is_string($val)) continue;

                $k = 'pr:' . $tpl_slug . ':' . $stage;
                if (empty($want[$k])) continue;

                if (empty($current_templates[$tpl_slug])) {
                    $current_templates[$tpl_slug] = [
                        'label'   => $tpl_slug,
                        'enabled' => 1,
                        'builtin' => in_array($tpl_slug, ['article', 'modelar_youtube'], true) ? 1 : 0,
                    ];
                }

                $exists = isset($current_prompts[$tpl_slug]) && array_key_exists($stage, (array)$current_prompts[$tpl_slug]);
                if ($exists && !$overwrite) {
                    continue;
                }

                $current_prompts[$tpl_slug][$stage] = wp_kses_post($val);
                $imported++;
            }
        }

        update_option('pga_orion_templates', $current_templates, false);
        update_option(self::OPTION, $current_prompts, false);

        return [
            'imported'  => $imported,
            'overwrite' => $overwrite ? 1 : 0,
        ];
    }


    /* =============================
   * HELPERS: length/outline config
   * ============================= */
    public static function length_to_range(string $length): array
    {
        switch ($length) {
            case 'short':
                return [400, 800];
            case 'medium':
                return [800, 1500];
            case 'long':
                return [1500, 2500];
            case 'extra-long':
            case 'extra_long':
            case 'extra':
                return [2500, 5000];
            default:
                return [400, 800];
        }
    }

    public static function outline_config(string $length): array
    {
        switch ($length) {
            case 'short':
                return ['min_sections' => 4, 'max_sections' => 8];
            case 'medium':
                return ['min_sections' => 8, 'max_sections' => 15];
            case 'long':
                return ['min_sections' => 15, 'max_sections' => 20];
            case 'extra-long':
            case 'extra_long':
            case 'extra':
                return ['min_sections' => 20, 'max_sections' => 30];
            default:
                return ['min_sections' => 4, 'max_sections' => 8];
        }
    }

    public static function build_section_prompt(
        string $template,
        string $keyword,
        string $articleTitle,
        array  $section,
        string $length,
        string $locale,
        int    $sectionsCount,
        int    $section_number,
        string $url = '',
    ): string {
        $tpl = self::get_prompt_for($template, 'section');

        $heading = trim((string)($section['heading'] ?? ''));
        $level   = strtolower(trim((string)($section['level'] ?? 'h2')));
        if ($level !== 'h2' && $level !== 'h3') $level = 'h2';

        // bullets (da própria seção)
        $bullets = '';
        if (!empty($section['bullets']) && is_array($section['bullets'])) {
            $list = [];
            foreach ($section['bullets'] as $b) {
                $b = trim((string)$b);
                if ($b !== '') $list[] = '- ' . $b;
            }
            if ($list) $bullets = implode("\n", $list);
        }

        // children headings (H3 sugeridos)
        $children = '';
        if (!empty($section['children']) && is_array($section['children'])) {
            $list = [];
            foreach ($section['children'] as $c) {
                $h = trim((string)($c['heading'] ?? ''));
                if ($h !== '') $list[] = '- ' . $h;
            }
            if ($list) $children = implode("\n", $list);
        }

        // word goal (se vier do outline)
        $goalMin = 0;
        $goalMax = 0;
        if (!empty($section['word_goal']) && is_array($section['word_goal'])) {
            $goalMin = (int)($section['word_goal']['min'] ?? 0);
            $goalMax = (int)($section['word_goal']['max'] ?? 0);
        }

        [$minWords, $maxWords] = self::length_to_range($length);

        // fallback: se outline não trouxe word_goal, usa uma meta por seção
        if ($goalMin <= 0 || $goalMax <= 0) {
            // mais curto: por seção
            $per = max(90, (int) floor($maxWords / max(1, $sectionsCount)));

            // metas menores (curto de verdade)
            $goalMin = (int) max(60, floor($per * 0.55));
            $goalMax = (int) max($goalMin + 30, floor($per * 0.75));
        }

        $childrenCount = 0;
        if (!empty($section['children']) && is_array($section['children'])) {
            $childrenCount = count($section['children']);
        }

        // se tem H3 sugerido, divide o budget entre H2 + H3s
        if ($childrenCount > 0) {
            $parts = 1 + $childrenCount; // H2 + cada H3
            $goalMax = (int) max(100, floor($goalMax / $parts));
            $goalMin = (int) max(50, floor($goalMin / $parts));
        }

        $url = trim((string)$url);

        $base = self::replace_vars($tpl, [
            'keyword'          => $keyword,
            'articleTitle'     => $articleTitle,
            'locale'           => $locale,
            'section_heading'  => $heading,
            'section_level'    => $level,
            'section_children' => $children,
            'section_bullets'  => $bullets,
            'sections_count'   => (string)$sectionsCount,
            'section_number'       => $section_number,
            'url'              => $url,
        ]);

        $ctx = '';
        $idx = max(1, (int)$section_number);
        $total = max(1, (int)$sectionsCount);
        $remaining = max(0, $total - $idx);

        $state = "ESTADO DE GERAÇÃO (obrigatório):\n"
            . "- Esta seção é a {$idx} de {$total} (restam {$remaining}).\n"
            . "- Não repita tópicos já cobertos em outras seções.\n"
            . "- Esta seção deve avançar o artigo (informação nova).\n"
            . "do artigo com título exato:\n"
            . "\"{$articleTitle}\"\n\n"

            . "REGRAS CRÍTICAS SOBRE O CONTEUDO:\n"
            . "- Cada parágrafo deve ter no máximo 2 frases.\n"
            . "- Quebre parágrafos com frequência (leitura mobile).\n"
            . "REGRAS CRÍTICAS SOBRE O TÍTULO:\n"
            . "- O conteúdo desta seção DEVE ser coerente com o título do artigo.\n"
            . "- Se o título promete um certo número de passos, dicas, motivos etc,\n"
            . "  respeite essa estrutura no conjunto das seções (não crie um número diferente).\n"
            . "- Não mude o foco do artigo. Não contradiga o que o título promete.\n"
            . "- A primeira palavra deve sempre ser capitalizada nos títulos.\n\n"
            . "- Não insira numeração se o título não for especifico sobre quantidades, exemplo 'x motivos para [...]', 'x itens sobre [...] etc'.\n\n";

        if ($template != 'modelar_youtube') {
            $state .= "ESTRUTURA DE KEYWORDS:\n"
                . "Distribua de maneira fluida a frase chave de foco, mas sem exagerar, só use quando de fato fizer sentido e use sempre de forma fluida, no máximo uma vez por sessão. A frase é: \"{$keyword}\".\n";
        }

        // sufixo técnico: tamanho + estrutura + formato de saída
        $tech = "Regras técnicas (não discuta, apenas cumpra):\n"
            . "- Hoje é: " . SELF::date() . ". "
            . "- Escreva SOMENTE esta seção (não escreva o artigo inteiro).\n"
            . "- Comece EXATAMENTE com <{$level}>{$heading}</{$level}>.\n"
            . "- NÃO escreva outros H2 fora desta seção.\n"
            . "- NÃO use <h1>.\n"
            . "- Use HTML limpo: <p>, <strong>, <ul>, <ol>, <li>.\n"
            . "- Esta seção DEVE ter entre {$goalMin} e {$goalMax}. Se passar de {$goalMax}, encurte antes de finalizar.\n"
            . "- Só use lista se realmente ajudar.\n"
            . "- Não use markdown; use somente HTML.\n"
            . "- - Se fizer sentido, inclua H3 dentro desta seção usando os subtítulos sugeridos.\n\n"
            . "- Esta seção (incluindo QUALQUER H3 e TODO o texto abaixo deles) DEVE ter entre {$goalMin} e {$goalMax} palavras NO TOTAL.\n"
            . "- Se houver H3, divida o orçamento de palavras entre os blocos (H2 + H3s) para não ultrapassar {$goalMax}.\n";


        if ($template === 'modelar_youtube') {
            $tech .= "- Regra obrigatória: NÃO mencione vídeo, canal, link ou URL.\n";
        }

        if ($bullets !== '') {
            $tech .= "- Cubra os pontos sugeridos nos bullets (sem listar literalmente; use como guia).\n";
        }

        return $state . "\n" . $tech . "\n" . $base . "\n" . $ctx . "\n\n";
    }

    public static function build_title_prompt_modelar_youtube(
        array $video,
        string $keyword,
        int $min = 1,
        int $max = 3,
        string $locale = 'pt_BR'
    ): string {

        $tpl    = self::get_prompt_for('modelar_youtube', 'title');
        $locale = $locale ?: 'pt_BR';

        $videoTitle       = trim((string)($video['title'] ?? ''));
        $videoDescription = trim((string)($video['description'] ?? ''));

        // corta descrição (evita token gigante)
        if ($videoDescription !== '') {
            $videoDescription = wp_strip_all_tags($videoDescription);
            $videoDescription = html_entity_decode($videoDescription, ENT_QUOTES, 'UTF-8');
            $videoDescription = function_exists('mb_substr')
                ? mb_substr($videoDescription, 0, 700)
                : substr($videoDescription, 0, 700);
        }

        // 1) prompt base editável (limpo)
        $base = self::replace_vars($tpl, [
            'keyword' => $keyword,
            'locale'  => $locale,
        ]);

        // 2) contexto interno (backend) — obrigatório, invisível pro user
        $ctx  = "\n\n";
        $ctx .= "CONTEXTO INTERNO:\n";
        $ctx .= "- Gere apenas títulos ";
        $ctx .= "- Hoje é: " . SELF::date();
        $ctx .= "- Gere um título com base no original: ";
        if ($videoTitle !== '')   $ctx .= $videoTitle . "\n";

        $ctx .= "- Lembre-se de contextualizar com o que está na descrição: ";
        if ($videoDescription !== '') {
            $ctx .= "Descrição: \n";
            $ctx .= $videoDescription . "\n";
        }

        // 3) regras fixas + suffix JSON fixo
        $fixed =
            "\n\n"
            . "Quantidade de títulos a gerar: entre {$min} e {$max}.\n"
            . "Escreva em {$locale}.\n";

        return $fixed . $base . $ctx . "\n\n" . self::title_json_suffix();
    }

    public static function build_keywords_prompt(
        string $template,
        string $command,
        string $locale,
        int $count,
        string $category,
        array $existing_list = []
    ): string {
        $tpl = self::get_prompt_for($template, 'keywords');

        $command  = trim((string)$command);
        $locale   = $locale ?: 'pt_BR';
        $category = trim((string)$category);
        $count    = max(1, min(100, (int)$count));

        $base = self::replace_vars($tpl, [
            'command'  => $command,
            'locale'   => $locale,
            'category' => $category
        ]);

        $ban = '';
        if (!empty($existing_list)) {
            $existing_list = array_slice($existing_list, 0, 200);
            $ban = "\n\nPROIBIDO repetir qualquer uma destas keywords (nem variações mínimas):\n- "
                . implode("\n- ", array_map('trim', $existing_list))
                . "\n";
        }

        $suffix =
            "Responda SOMENTE em JSON UTF-8 válido, sem markdown.\n"
            . "Regras técnicas (não discuta, apenas cumpra):\n"
            . "- Gere {$count} keywords NOVAS e DIFERENTES.\n"
            . "- Gere em {$locale}.\n"
            . "- Categoria: {$category}.\n"
            . "- Use o comando como direção: {$command}.\n"
            . "- Uma keyword por linha dentro do campo \"content\".\n"
            . "- Não use bullets, não use numeração, não use aspas etras fora do JSON.\n"
            . "- Não adicione explicações.\n\n"
            . "Responda APENAS em JSON UTF-8 válido, no seguinte formato:\n\n"
            . "{\n"
            . "  \"content\": \"keyword 1\n keyword 2\n keyword 3\"\n"
            . "}\n";

        return $base . $ban . "\n\n" . $suffix;
    }

    /* =============================
   * DEFAULTS (CORE)
   * ============================= */

    private static function default_keywords_prompt(): string
    {

        return "Nós somos a Plugins Alpha, vendemos plugins para WordPress, nosso produto foco atualmente "
            . "é o Alpha Suite, que contém os módulos Alpha Órion e o Alpha Stories, o Orion é um plugin que "
            . "gera conteúdos com IA e o Stories gera Web Stories do Google com apenas 1 clique. \n"
            . "Siga as especificações abaixo para gerar keywords: ";
    }

    private static function default_title_prompt(): string
    {
        return
            "Você é um jornalista sênior especializado em Google Discover, notícias e títulos de alto CTR.\n\n"

            . "Seu objetivo é criar TÍTULOS CURTOS, CLAROS e IMPACTANTES, com estilo jornalístico profissional, "
            . "capazes de gerar curiosidade real e cliques orgânicos no Google Discover.\n\n"

            . "DIRETRIZES EDITORIAIS OBRIGATÓRIAS:\n"
            . "- Trate o conteúdo como NOTÍCIA ou atualização relevante.\n"
            . "- Priorize acontecimentos, mudanças, revelações, tendências ou fatos recentes.\n"
            . "- Use linguagem clara, direta e natural, sem exageros artificiais.\n\n"
            . "- Inclua a palavra-chave obrigatoriamente, mas envolva-a em um benefício emocional "
            . "ou uma promessa de solução (Ex: [Palavra-chave] + [Benefício/Curiosidade]).\n\n"

            . "CARACTERÍSTICAS DE UM BOM TÍTULO PARA DISCOVER:\n"
            . "- Especificidade: use números, dados, nomes próprios ou fatos concretos sempre que fizer sentido.\n"
            . "- Emoção e curiosidade: desperte interesse real sem apelar para clickbait vazio.\n"
            . "- Autoridade: o título deve parecer confiável e informativo.\n"
            . "- Clareza: o leitor precisa entender imediatamente sobre o que é a matéria.\n"
            . "- Urgência: sempre que aplicável, traga senso de novidade ou relevância imediata.\n\n"

            . "ESTILO:\n"
            . "- Jornalístico, direto e profissional.\n"
            . "- Frases bem construídas, com vocabulário rico, mas acessível.\n"
            . "- Evite palavras genéricas, promessas vagas ou termos sensacionalistas.\n\n"

            . "REGRAS FINAIS:\n"
            . "- O título deve parecer escrito por um jornalista humano experiente.\n"
            . "- Nunca use emojis, aspas desnecessárias ou símbolos estranhos.\n"
            . "- Não explique o processo. Apenas entregue os títulos.\n";
    }

    private static function default_title_modelar_youtube_prompt(): string
    {
        return "Você é um redator sênior especializado em SEO e Google Discover.\n\n"
            . "Sua tarefa é gerar títulos fortes e naturais para um artigo que será inspirado por um tema.\n\n"
            . "Regras obrigatórias:\n"
            . "- NÃO mencione fonte, vídeo, canal ou URL no título.\n"
            . "- Não use aspas.\n"
            . "- Evite clickbait mentiroso; seja curioso e específico.\n"
            . "- Foque em clareza + curiosidade + benefício.\n\n"
            . "Gere algo diferente do que já gerou.\n"
            . "Gere variações diferentes (ângulos diferentes: guia, lista, erros, passo a passo, explicação simples, comparativo, etc.).\n";
    }

    private static function default_outline_modelar_youtube_prompt(): string
    {
        return "OBJETIVO E-E-A-T:\n"
            . "- Transformar o conhecimento empírico do vídeo em tópicos que demonstrem expertise técnica.\n"
            . "- Eliminar vícios de linguagem de vídeo ('deixe o like', 'se inscreva') e focar em entregar a solução prometida.\n\n"
            . "DIRETRIZES DO OUTLINE:\n"
            . "- H2s DE VALOR: Os títulos das seções devem responder diretamente às dores do usuário ou curiosidade do Google Discover.\n"
            . "- SEÇÃO DE 'INSIGHTS CHAVE': Crie um H2 inicial que resuma os pontos fundamentais discutidos no vídeo para oferecer valor imediato.\n"
            . "- PROFUNDIDADE (H3): Alguns H2 devem ter subtópicos (children) que detalham o 'como fazer' ou 'por que isso funciona'.\n"
            . "- LISTAS TÉCNICAS: Use bullets para organizar dados, passos ou requisitos que no vídeo estão dispersos.\n\n"
            . "REGRAS RÍGIDAS:\n"
            . "- Deve ser evitado capitalização de título, só capitalize quando realmente for necessário, naqueles momentos especificos.\n"
            . "- PROIBIDO citar que o conteúdo veio de um vídeo, canal, link ou YouTube.\n"
            . "- NÃO utilize termos de encerramento como 'Conclusão'. Termine com um tópico de 'Aplicação Prática' ou 'Próximos Passos'.\n"
            . "- O texto deve parecer um artigo nativo escrito por um especialista humano, não uma transcrição.";
    }

    private static function default_outline_prompt(): string
    {
        return "OBJETIVO E-E-A-T:\n"
            . "Tarefa: criar um ESBOÇO (outline) do artigo.\n\n"
            . "DIRETRIZES DO OUTLINE:\n"
            . "- H2s DE VALOR: Os títulos das seções devem responder diretamente às dores do usuário ou curiosidade do Google Discover.\n"
            . "- SEÇÃO DE 'INSIGHTS CHAVE': Crie um H2 inicial que resuma os pontos fundamentais discutidos no vídeo para oferecer valor imediato.\n"
            . "- PROFUNDIDADE (H3): Alguns H2 devem ter subtópicos (children) que detalham o 'como fazer' ou 'por que isso funciona'.\n"
            . "- LISTAS TÉCNICAS: Use bullets para organizar dados, passos ou requisitos que no vídeo estão dispersos.\n\n"
            . "REGRAS RÍGIDAS:\n"
            . "- Deve ser evitado capitalização de título, só capitalize quando realmente for necessário, naqueles momentos especificos.\n"
            . "- NÃO utilize termos de encerramento como 'Conclusão'. Termine com um tópico de 'Aplicação Prática' ou 'Próximos Passos'.\n"
            . "- O texto deve parecer um artigo nativo escrito por um especialista humano."
            . "- Use H2 com tópicos bem diferentes entre si (sem repetição).\n"
            . "- Inclua H3 apenas quando fizer sentido aprofundar um H2.\n"
            . "- Em cada seção, sugira bullets com pontos concretos (quem/o quê/quando/onde/como/por quê, exemplos, dados, passos, cuidados).\n"
            . "- Evite clichês e títulos genéricos (ex.: “Guia completo”).\n";
    }

    private static function default_section_base_prompt(): string
    {
        return "Você é um Especialista de Conteúdo Sênior (E-E-A-T).\n"
            . "Sua tarefa é redigir UMA seção específica de um artigo de autoridade, a partir dos pontos fornecidos.\n\n"
            . "Diretrizes:\n"
            . "- Vá direto ao ponto: explique o 'como' e o 'porque' com clareza.\n"
            . "- Evite conteúdo AI Fluff, traga dados e do porquê você escolheria aquela opção, sempre com base nas suas experiências. "
            . "Evite coisas como \"um x (necessidade) confiável pode fazer toda a diferença\", use termos técnicos para mostrar a melhor solução.\n"
            . "- Quero um conteúdo sempre em primeira pessoa, falando de experiências e recomendações suas com base em testes, usabilidade e dando a sua opinião sobre qual o que é melhor.\n"
            . "- Faça de tudo para melhorar a autoridade do conteúdo, mencione ferramentas onlines quando necessário, ferramentas que você usa que poucas pessoas conhecem, se destaque sempre como autoridade no assunto e demonstre isso com comparativos, mencionando sempre o seu ponto de vista pessoal sobre cada um.\n"
            . "- Parágrafos curtos (2–4 linhas) e escaneáveis.\n"
            . "- Use <strong> apenas para destacar conceitos essenciais (sem exagero).\n"
            . "- Não invente estatísticas; quando não houver dado, use linguagem prudente.\n\n"
            . "Faça CTA fluido com links externos sobre cada ferramenta mencionada com a tag \"a\" com target _blank.\n\n"
            . "Pontos-chave para transformar em conteúdo denso, com profundidade:\n"
            . "{{section_bullets}}\n\n"
            . "Subtítulos sugeridos (se aplicável):\n"
            . "{{section_children}}\n";
    }

    private static function default_section_modelar_youtube_prompt(): string
    {
        return "Você é um Especialista de Conteúdo Sênior (E-E-A-T).\n"
            . "Sua tarefa é redigir UMA seção específica de um artigo de autoridade, a partir dos pontos fornecidos.\n\default_meta_description_promptn"
            . "Diretrizes:\n"
            . "- Vá direto ao ponto: explique o 'como' e o 'porque' com clareza.\n"
            . "- Evite conteúdo AI Fluff, traga dados e do porquê você escolheria aquela opção, sempre com base nas suas experiências. "
            . "Evite coisas como \"um x (necessidade) confiável pode fazer toda a diferença\", use termos técnicos para mostrar a melhor solução.\n"
            . "- Quero um conteúdo sempre em primeira pessoa, falando de experiências e recomendações suas com base em testes, usabilidade e dando a sua opinião sobre qual o que é melhor.\n"
            . "- Faça de tudo para melhorar a autoridade do conteúdo, mencione ferramentas onlines quando necessário, ferramentas que você usa que poucas pessoas conhecem, se destaque sempre como autoridade no assunto e demonstre isso com comparativos, mencionando sempre o seu ponto de vista pessoal sobre cada um.\n"
            . "- Texto 100% nativo para leitura (não mencione vídeo, canal, assistir).\n"
            . "- Parágrafos curtos (2–4 linhas) e escaneáveis.\n"
            . "- Use <strong> apenas para destacar conceitos essenciais (sem exagero).\n"
            . "- Não invente estatísticas; quando não houver dado, use linguagem prudente.\n\n"
            . "Faça CTA fluido com links externos sobre cada ferramenta mencionada com a tag \"a\" com target _blank.\n\n"
            . "Pontos-chave para transformar em conteúdo denso, com profundidade:\n"
            . "{{section_bullets}}\n\n"
            . "Subtítulos sugeridos (se aplicável):\n"
            . "{{section_children}}\n";
    }


    private static function default_meta_description_prompt(): string
    {
        return "Instruções específicas:\n"
            . "- Use entre 130 e 150 caracteres.\n"
            . "- A palavra-chave deve aparecer de forma natural, preferencialmente no início.\n"
            . "- Inclua uma chamada para ação (CTA) discreta ou prometa a solução de uma dor.\n"
            . "- Escreva em uma única frase fluida.\n"
            . "- Proibido: emojis, aspas, Markdown, hashtags ou linguagem robótica.\n"
            . "- Cria urgência e identifique  um problema. \n"
            . "- Prometa facilidade, para atrai quem está sem tempo (sem mentira, sem exagero).\n"
            . "- Gera uma dúvida no leitor, quando cabível. \n"
            . "Resultado: apenas o texto da meta descrição.";
    }

    private static function default_slug_prompt(): string
    {
        return "Instruções específicas:\n"
            . "Use apenas letras minúsculas hifens para separar as palavras e remova stop words "
            . "(como 'o', 'a', 'com', 'para') para mantê-lo curto e focado na palavra-chave principal.\n"
            . "Está slug é para meio de funil";
    }

    private static function default_image_prompt(): string
    {
        return 'Você é um gerador de prompts para imagens realistas.' . "\n"
            . 'Crie um prompt em {{locale}} para gerar uma thumbnail fotográfica relacionada a "{{keyword}}".' . "\n"
            . 'Considere o título: "{{title}}" e o tipo de conteúdo: "{{template}}".' . "\n\n"
            . "Regras:\n"
            . "- Cena única marcante, proporção 16:9.\n"
            . "- Sem texto, sem marcas d’água, sem logos.\n";
    }

    private static function default_post_thumbnail_regen_prompt(): string
    {
        return "Ultra-realistic natural photo, 16:9 aspect ratio.\n"
            . "title (pt-BR): \"{{title}}\".\n"
            . "context: {{content}}.\n"
            . "Avoid text, watermarks, clutter.\n";
    }

    public static function story_default_template(): string
    {
        $s  = "Você é uma especialista em transformar posts de blog em Web Stories AMP.\n\n";
        $s .= "Você receberá:\n";
        $s .= "- Título: {{title}}\n";
        $s .= "- Conteúdo: {{content}}\n";
        $s .= "- Brief: {{brief}}\n";
        $s .= "- Locale: {{locale}}\n\n";
        $s .= "Tarefa:\n";
        $s .= "- Gere 7 a 10 páginas curtas.\n";
        $s .= "- Linguagem simples e envolvente.\n";
        $s .= "- No campo \"prompt\": {{image_prompt_rule}}\n";
        $s .= "- Responda somente em JSON.\n";
        return $s;
    }

    public static function story_json_format_block(): string
    {
        return "Responda APENAS em JSON UTF-8 válido, no seguinte formato:\n\n"
            . "{\n"
            . "  \"pages\": [\n"
            . "    {\n"
            . "      \"heading\": \"Título\",\n"
            . "      \"body\": \"Texto curto.\",\n"
            . "      \"cta_text\": \"\",\n"
            . "      \"cta_url\": \"\",\n"
            . "      \"prompt\": \"\"\n"
            . "    }\n"
            . "  ]\n"
            . "}\n";
    }
}
