<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Prompts
{
    public static function init(): void {}
    public static function register_ajax(): void
    {
        add_action('wp_ajax_pga_orion_prompts_export', [__CLASS__, 'ajax_export']);
        add_action('wp_ajax_pga_orion_prompts_import_prepare', [__CLASS__, 'ajax_import_prepare']);
        add_action('wp_ajax_pga_orion_prompts_import_apply', [__CLASS__, 'ajax_import_apply']);
        add_action('wp_ajax_pga_orion_template_delete', [__CLASS__, 'ajax_delete_template']);
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
        if ($slug === '' || in_array($slug, ['article', 'modelar_youtube', 'rss', 'global'], true)) {
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
        return wp_date('d/m/Y');
    }

    /* =============================
    * STAGES (etapas)
    * ============================= */

    public static function stages(): array
    {
        return [
            'title'                => __('Título', 'alpha-suite'),
            'outline'              => __('Esboço', 'alpha-suite'),
            'section'              => __('Seções', 'alpha-suite'),
            'meta_description'     => __('Meta descrição', 'alpha-suite'),
            'keywords'             => __('Gerar keywords', 'alpha-suite'),
            'slug'                 => __('Slug', 'alpha-suite'),
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

        if ($template === 'rss') {
            if ($stage === 'title') {
                return self::default_title_rss_prompt();
            }
            if ($stage === 'outline') {
                return self::default_outline_rss_prompt();
            }
            if ($stage === 'section') {
                return self::default_section_rss_prompt();
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
    private static function replace_vars(string $tpl, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{{' . $k . '}}'] = (string)$v;
        }
        return strtr($tpl, $map);
    }

    /* =============================
   * SUFFIX JSON (NÃO EDITÁVEL)
   * ============================= */
    private static function title_json_suffix(): string
    {
        return "Responda APENAS em JSON UTF-8 válido no formato:\n"
            . "{ \"title\": [\"Título 1\", \"Título 2\", \"Título 3\"] }\n";
    }

    private static function outline_json_suffix(): string
    {
        return
            "Você está tendo muitos problemas para gerar um json válido POR FAVOR, PRESTE MUITA ATENÇÃO NA ABERTURA E FECHAMENTO DAS TAGS, ESTOU GASTANDO MUITOS CRÉDITOS NESSE MODELO QUE SÓ ESTÁ RETORNANDO UM JSON VÁLIDO, SE ATENTE EM ENVIAR UM JSON VALIDO, COM NO MÁXIMO 1 CHILDREN\n"
            . "Responda SOMENTE em JSON UTF-8 válido, sem markdown, não se esqueça de fechar o json, crie um json 100% válido, TENTE NÃO COLOCAR EM ASPAS OS DETALHES IMPORTANTES, POIS ISSO VAI ATRAPALHAR NA CRIAÇÃO DE UM JSON VÁLIDO, FOQUE APENAS NO JSON VÁLIDO\n"
            . "Formato exato (MÁXIMO DE 20 BULLETS INDEPENDENTE DA OCASIÃO, PROIBIDO GERAR BULLETS DUPLICADOS E INFINITOS):\n"
            . "{"
            . "\"sections\": [\n"
            . "  {\n"
            . "   \"id\": 1,\n"
            . "   \"level\": \"h2\",\n"
            . "   \"paragraph\": \"contexto sobre o tema apresentado\",\n"
            . "   \"heading\": \"Título H2...\",\n"
            . "   \"bullets\": [\n"
            . "     \"...\",\n"
            . "     \"...\"\n"
            . "  ],\n"
            . "  \"children\": [\n (se houver necessidade de h3 na sessão)"
            . "    {\n"
            . "      \"id\": 1,\n"
            . "      \"level\": \"h3\",\n"
            . "      \"heading\": \"Subtítulo H3...\",\n"
            . "      \"paragraph\": \"paragrafo sobre o h3...\",\n"
            . "      \"bullets\": [\n"
            . "        \"...\",\n"
            . "        \"...\"\n"
            . "      ]\n"
            . "    }\n"
            . "   ]\n"
            . "  }\n"
            . " ]\n"
            . "}"
            . "Responda SOMENTE em JSON UTF-8 válido no formato {\"sections\":[...]} sem qualquer texto antes ou depois. FORMATO VALIDO JSON COM NO MÁXIMO 20 BULLETS, MÁXIMO.\n\n";
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
            'videoTitle'   => $videoTitle,
            'chapters'     => $chapters,
            'videoDescription' => $videoDescription,
            'tags'         => $tags,
        ]);

        // CONTEXTO INTERNO: o que deixa fiel
        $ctx  = "\n\nCONTEXTO INTERNO (não cite vídeo/canal/URL):\n";
        if ($videoTitle !== '') {
            $ctx .= "- Título do material base: {$videoTitle}\n";
        }
        $ctx .= "- Título do artigo: {$articleTitle}\n";
        $ctx .= "- Hoje é: " . SELF::date();
        $ctx .= "O esboço deve ser gerada no idioma, pode traduzir incluse a KW: {$locale}\n\n";

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

        $suffix = self::outline_json_suffix();

        return $base . $ctx . "\n\n" . $suffix;
    }

    /* =============================
   * BUILDERS (API pública)
   * ============================= */
    public static function build_title_prompt(
        string $template,
        string $keyword,
        int $min = 3,
        int $max = 5,
        string $locale = 'pt_BR'
    ): string {
        $tpl = self::get_prompt_for($template, 'title');

        $base = self::replace_vars($tpl, [
            'keyword' => $keyword,
            'locale'  => $locale,
            'template' => $template,
        ]);

        return
            "CONTEXTO DO TEMA:\n"
            . "Assunto principal: \"{$keyword}\"\n"
            . "Quantidade de títulos a gerar: entre {$min} e {$max}\n"
            . "O titulo deve ser gerado em, pode traduzir incluse a KW: {$locale}\n"
            . "Data atual: " . SELF::date() . " (use o ano quando relevante)\n\n"
            . $base
            . "\n\n"
            . "FORMATO DE SAÍDA:\n"
            . "- Retorne apenas JSON válido, sem markdown, sem comentários\n"
            . "- Siga exatamente a estrutura especificada abaixo\n"
            . self::title_json_suffix();
    }

    public static function build_title_rss_prompt(
        string $seed_title,
        string $locale = 'pt_BR',
        string $url = ''
    ): string {

        $tpl = self::get_prompt_for('rss', 'title');

        $base = self::replace_vars($tpl, [
            'tituloRef'  => $seed_title,
            'locale'     => $locale,
            'url'        => $url,
        ]);

        $sourceContext = $url
            ? "Fonte original da notícia: {$url}\n"
            : '';

        return
            "CONTEXTO DA NOTÍCIA:\n"
            . "Título base: \"{$seed_title}\"\n"
            . $sourceContext
            . "O titulo deve ser gerado em, pode traduzir incluse a KW: {$locale}\n"
            . "Data atual: " . self::date() . "\n\n"
            . "INSTRUÇÕES:\n"
            . "- Reescreva o título\n"
            . "- Não invente informações\n"
            . "- Evite clickbait exagerado\n\n"
            . $base
            . "\n\nFORMATO DE SAÍDA:\n"
            . "Responda APENAS em JSON UTF-8 válido no formato:\n"
            . "{ \"title\": \"Título final aqui\" }\n";
    }

    public static function build_outline_rss_prompt(
        string $chosenTitle,
        string $seedTitle,
        string $length,
        string $locale,
        string $url = '',
        string $font = '',
        string $sourceContent = '',
    ): string {

        $tpl = self::get_prompt_for('rss', 'outline');

        $base = self::replace_vars($tpl, [
            'tituloRef'  => $seedTitle,
            'locale'     => $locale,
            'url'        => $url,
        ]);

        [$minWords, $maxWords] = self::length_to_range($length);
        $cfg = self::outline_config($length);

        return
            "Você é um jornalista espealista criar esboço com base em uma noticia.\n"
            . "Crie apenas o esboço e passe as infomações conforme listado abaixo.\n\n"

            . "É PROIBIDO GERAR ALGO SEM TER INFORMAÇÕES PEDIDAS ABAIXO.\n\n"
            . "Responda SOMENTE em JSON UTF-8 válido no formato {\"sections\":[...]} sem qualquer texto antes ou depois. FORMATO VALIDO JSON COM NO MÁXIMO 20 BULLETS, MÁXIMO.\n\n"

            . "CONTEXTO DA NOTÍCIA:\n"
            . "Título original: {$seedTitle}\n"
            . "Título reescrito: {$chosenTitle}\n"
            . "Url do artigo RSS Fonte original (se houver): \"{$url}\"\n"
            . "O esboço deve ser gerado em, pode traduzir incluse a KW: {$locale}\n\n"

            . "Se a URL não puder ser acessada e estiver vazia, pesquise pelo título \"{$seedTitle}\" no site \"{$font}\".\n"
            . "Se não encontrar conteúdo confiável na fonte indicada, retorne:\n"
            . "Não crie mais niveis, o máximo que deve ser criado é o h3, mas isso se for pedido mais abaixo pelo usuário:\n"
            . "{\"sections\":[]}\n\n"

            . "ESTRUTURA:\n"
            . "- Gere entre {$cfg['min_sections']} e {$cfg['max_sections']} seções H2\n"
            . "- Conteúdo estimado entre {$minWords} e {$maxWords} palavras\n"
            . "- Cada seção deve conter no máximo 20 bullets (vinte, VINTEEEEE, MÁXIMO 20, MÁXIMO DE 20 BULLETS, MAXIMO, MAXIMO MAXIMOOOOOOO, NUNCA GERAR MAIS DO QUE ISSOOOOO)\n"
            . "- Se não houver fatos suficientes, gere apenas os existentes\n"
            . "- Cada bullet deve ter no máximo 100 caracteres e não ter caracteres especiais, aspas ou formatação de texto, apenas texto corrido.\n"
            . "- Cada paragraph deve ter no máximo 100 caracteres e não ter caracteres especiais, aspas ou formatação de texto, apenas texto corrido.\n"
            . "CORRETO:\n"
            . "- \"No dia [x] de [mês x] de [ano x] (se for o caso), [Pessoa 1] acusou [pessoa 2]\"\n"
            . "- \"[pessoa 1] se reuniu com [pessoa 2] em [quando e onde]\"\n"
            . "- \"[empresa x] propõe janela de [x] dias para exibição teatral\"\n"
            . "- \"[empresa x] deu previsão para o [semestre x] de 2026\"\n\n"

            . "ERRADO:\n"
            . "- \"A situação atual levanta questões sobre ética\"\n"
            . "- \"A forma como as empresas gerenciam isso pode afetar...\"\n"
            . "- \"A resolução terá implicações para...\"\n"
            . "- \"[empresa x] divulgou [o que] de [obra]... (se não tiver o [onde], não serve, precisa complementar com o onde, o dado tem que ser verificavel)\"\n"
            . "- \"[empresa x] deu previsão para 2026, sem data definida\"\n\n"


            . $base . "\n\n"

            . "CONTEÚDO BASE DA NOTÍCIA:\n"
            . "-----inicio----\n"
            . $sourceContent . "\n"
            . "-----fim----\n\n"

            . self::outline_json_suffix();
    }

    public static function build_section_rss_prompt(
        string $articleTitle,
        array  $section,
        string $length,
        string $locale,
        int    $sectionsCount,
        string $section_number,
        string $url = '',
        string $font = '',
    ): string {
        $tpl    = self::get_prompt_for('rss', 'section');

        $heading = trim((string)($section['heading'] ?? ''));
        $level   = strtolower(trim((string)($section['level'] ?? 'h2')));
        if ($level !== 'h2' && $level !== 'h3') $level = 'h2';

        $sectionParagraph = trim((string)($section['paragraph'] ?? ''));

        // children detalhado (H3 sugeridos com paragraph)
        $childrenDetailed = '';
        if (!empty($section['children']) && is_array($section['children'])) {
            $list = [];
            $n = 1;
            foreach ($section['children'] as $c) {
                $h = trim((string)($c['heading'] ?? ''));
                $p = trim((string)($c['paragraph'] ?? ''));
                if ($h === '') continue;

                $line = "H3 {$n}: {$h}";
                if ($p !== '') $line .= " — Brief: {$p}";
                $list[] = $line;
                $n++;
            }
            if ($list) $childrenDetailed = implode("\n", $list);
        }

        $bullets = '';
        if (!empty($section['bullets']) && is_array($section['bullets'])) {
            $list = [];

            foreach ($section['bullets'] as $b) {

                if (is_array($b)) {
                    // tenta pegar campo comum
                    $b = $b['text'] ?? reset($b);
                }

                if (!is_string($b)) {
                    continue;
                }

                $b = trim($b);

                if ($b !== '') {
                    $list[] = '- ' . $b;
                }
            }

            if ($list) {
                $bullets = implode("\n", $list);
            }
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

        // word goal
        $goalMin = 0;
        $goalMax = 0;
        if (!empty($section['word_goal']) && is_array($section['word_goal'])) {
            $goalMin = (int)($section['word_goal']['min'] ?? 0);
            $goalMax = (int)($section['word_goal']['max'] ?? 0);
        }

        [$minWords, $maxWords] = self::length_to_range($length);

        $sectionsCount = max(1, (int)$sectionsCount);

        // Se só existe 1 seção → usa o range inteiro
        if ($sectionsCount === 1) {
            $goalMin = $minWords;
            $goalMax = $maxWords;
        } else {

            // Distribuição proporcional simples
            $goalMin = (int) floor($minWords / $sectionsCount);
            $goalMax = (int) floor($maxWords / $sectionsCount);

            // Ajuste leve para dar margem editorial
            $goalMax = (int) floor($goalMax * 1.10); // +10% de flexibilidade
        }

        // Garantia mínima apenas para evitar micro seção
        $goalMin = max(40, $goalMin);
        $goalMax = max($goalMin + 30, $goalMax);

        $idx = max(1, (int)$section_number);
        $total = max(1, (int)$sectionsCount);
        $remaining = max(0, $total - $idx);

        $base = self::replace_vars($tpl, [
            'articleTitle'              => $articleTitle,
            'locale'                    => $locale,
            'section_heading'           => $heading,
            'section_level'             => $level,
            'section_paragraph'         => $sectionParagraph,
            'section_children'          => $children,
            'section_children_detailed' => $childrenDetailed,
            'section_bullets'           => $bullets,
            'sections_count'            => (string)$sectionsCount,
            'section_number'            => (string)$section_number,
            'url'                       => $url,
            'font'                      => $font,
        ]);

        $state = "CONTEXTO DA SEÇÃO:\n"
            . "Título do artigo: \"{$articleTitle}\"\n"
            . "Título da seção: \"{$heading}\"\n"
            . "Fonte original: {$url} ou {$font}\n"
            . "Idioma final para escrever, pode traduzir incluse a KW: {$locale}\n"
            . "Você está gerando APENAS a seção {$idx} de {$total} (restam {$remaining}) (Cada seção é gerada ISOLADAMENTE - você NÃO tem acesso ao conteúdo das outras seções)\n\n"

            . "REGRAS CRITICAS OBRIGATÓRIAS:\n"
            . "Cada sessão deve ser de h2 até h2, ou seja, quando tiver h3, ele também deve ter no máximo {$goalMax} palavras, ou seja, h2+h3+[quantos outros h3 tiverem] a soma máxima tem qser de {$goalMax} palavras\n"

            . "Tente acessar a URL para extrair informações, mas se não conseguir, use o conteúdo do site \"{$font}\" para se informar sobre a notícia. Não invente informações que não estejam presentes na URL ou no site de referência.\n\n";

        $brief = "BRIEF DA SEÇÃO (siga fielmente):\n"
            . "Heading ({$level}): {$heading}\n";

        if ($sectionParagraph !== '') {
            $brief .= "Parágrafo-guia: {$sectionParagraph}\n"
                . "REGRA: EXPANDA com profundidade, contexto e exemplos. Não apenas reescreva, mas acima de tudo, modele com base no conteúdo existente na noticia real.\n\n";
        }

        if ($childrenDetailed !== '') {
            $brief .= "Subtítulos H3 com briefs:\n{$childrenDetailed}\n"
                . "REGRA: Crie cada H3 e desenvolva seguindo o brief.\n\n";
        } else if ($children !== '') {
            $brief .= "Subtítulos H3 sugeridos:\n{$children}\n\n";
        }

        if ($bullets !== '') {
            $brief .= "Os bullets são guias para o conteúdo, sao dados importantes extraidos para ajudar a cada sessão, isso não quer dizer que tenha que colocar bullets no conteúdo, você receberá mais abaixo informações sobre isso:\n{$bullets}\n\n";
        }

        $tech = "REGRAS TÉCNICAS (obrigatório):\n"
            . "- Comece EXATAMENTE com: <{$level}>{$heading}</{$level}>\n"
            . "- NÃO escreva outros H2\n"
            . "- Esta seção inteira deve ter entre {$goalMin} e {$goalMax} palavras\n\n";

        return $state . $brief . $tech . $base . "Não use markdown";
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
            'locale'       => $locale,
            'template'     => $template,
        ]);

        $suffix = self::outline_json_suffix();

        // CONTEXTO INTERNO
        $ctx  = "\n\nCONTEXTO INTERNO:\n";
        $ctx .= "Título do artigo: {$articleTitle}\n";
        $ctx .= "Palavra-chave de foco (GEO): {$keyword}\n";
        $ctx .= "Data atual: " . SELF::date() . "\n";
        $ctx .= "Idioma de saída (idioma que deve ser), pode traduzir incluse a KW: {$locale}\n\n";

        $ctx .= "ESTRUTURA E TAMANHO:\n";
        $ctx .= "- Esboço deve ter entre {$cfg['min_sections']} e {$cfg['max_sections']} seções H2\n";
        $ctx .= "- Conteúdo final terá entre {$minWords} e {$maxWords} palavras\n";

        $ctx .= "ANÁLISE DO TÍTULO:\n";
        $ctx .= "- Se o título promete QUANTIDADE ESPECÍFICA (ex: '5 motivos', '7 dicas', '3 erros'), você DEVE criar EXATAMENTE esse número de H2s\n";
        $ctx .= "- Exemplos de estrutura:\n";
        $ctx .= "  • Título: '5 motivos para usar WordPress' → H2s: 'Motivo 1: Flexibilidade total', 'Motivo 2: Comunidade ativa', etc.\n";
        $ctx .= "  • Título: '7 erros comuns em SEO' → H2s: 'Erro 1: Ignorar pesquisa de palavras-chave', 'Erro 2: Conteúdo duplicado', etc.\n";
        $ctx .= "  • Título: '3 passos para criar um blog' → H2s: 'Passo 1: Escolher plataforma', 'Passo 2: Configurar hospedagem', etc.\n";
        $ctx .= "- Se o título NÃO especifica quantidade, NÃO numere os H2s\n";
        $ctx .= "- NUNCA mude o foco ou contradiga o que o título promete\n\n";

        $ctx .= "CONTEXTUALIZAÇÃO PARA SEÇÕES FUTURAS:\n";
        $ctx .= "- CRÍTICO: Cada seção será escrita ISOLADAMENTE por outro chat sem contexto das outras seções\n";
        $ctx .= "- Você DEVE ser ESPECÍFICO e EXPLÍCITO nas instruções de cada seção\n";
        $ctx .= "- Crie um paragraph para criar um breve contexto sobre o assunto da seção\n";
        $ctx .= "- NUNCA use referências vagas como:\n";
        $ctx .= "  ❌ 'use os critérios dos itens selecionados'\n";
        $ctx .= "  ❌ 'conforme mencionado anteriormente'\n";
        $ctx .= "  ❌ 'baseado na lista acima'\n";
        $ctx .= "- SEMPRE especifique COMPLETAMENTE o que deve ser feito:\n";
        $ctx .= "  ✅ 'crie tabela comparando: preço, facilidade de uso, recursos, suporte. Os itens serão: xx, xxx, xxx'\n";
        $ctx .= "  ✅ 'explique os 3 tipos: WordPress.com, WordPress.org, Managed WordPress'\n";
        $ctx .= "  ✅ 'detalhe cada passo: 1) escolher domínio, 2) contratar hospedagem, 3) instalar WordPress'\n";
        $ctx .= "- Se pedir tabela, especifique TODOS os itens/colunas/critérios que devem estar nela\n";
        $ctx .= "- Se pedir lista, especifique TODOS os elementos que devem compor a lista\n\n";

        $ctx .= "CAPITALIZAÇÃO (OBRIGATÓRIO):\n";
        $ctx .= "- Use APENAS primeira palavra maiúscula + nomes próprios nos H2 e H3\n";
        $ctx .= "- ❌ ERRADO: 'Como Instalar WordPress No Seu Servidor'\n";
        $ctx .= "- ❌ ERRADO: 'como instalar wordpress no seu servidor'\n";
        $ctx .= "- ✅ CORRETO: 'Como instalar WordPress no seu servidor'\n";
        $ctx .= "- ✅ CORRETO: 'O que é SEO'\n";
        $ctx .= "- ✅ CORRETO: 'Quem foi Moana'\n";
        $ctx .= "- ✅ CORRETO: 'Como usar E-E-A-T'\n\n";

        $ctx .= "FINALIZAÇÃO DO OUTLINE:\n";
        $ctx .= "- ÚLTIMA seção H2 NUNCA deve ser: 'Conclusão', 'Finalizando', 'Considerações finais', 'Resumo'\n";
        $ctx .= "- Opções válidas de última seção (escolha uma que faça sentido para o tema):\n";
        $ctx .= "  ✅ 'O que assistir [tempo baseado no título ('hoje', 'fim de semana')]'\n";
        $ctx .= "  ✅ 'Próximos passos para [objetivo]'\n";
        $ctx .= "  ✅ 'Como aplicar [tema] na prática'\n";
        $ctx .= "  ✅ 'Erros comuns ao [ação] e como evitá-los'\n";
        $ctx .= "  ✅ 'Recursos e ferramentas recomendadas para [tema]'\n";
        $ctx .= "  ✅ 'Checklist de implementação de [solução]'\n";
        $ctx .= "- Objetivo: manter engajamento, não encerrar o clique\n\n";

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

        $default = "Você é um especialista em SEO e Copywriting em {$locale}. Pode traduzir incluse a KW.\n"
            . "- Hoje é: " . SELF::date()
            . "\n Sua tarefa é criar uma meta descrição altamente clicável para o Google.\n"
            . "Título: \"{$articleTitle}\"\n"
            . "Palavra-chave principal: \"{$keyword}\"\n"
            . "A meta deve ser gerado em, pode traduzir incluse a KW: \"{$locale}\"\n";

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
            . "Gere um slug de URL para o título: \"{$articleTitle}\"\n"
            . "A slug deve ser gerada em: \"{$locale}\"\n"
            . "- Hoje é: " . SELF::date() . "\n\n";

        return $default . "\n\n" . $base . "\n\n" . self::meta_description_json_suffix();
    }

    public static function build_ws_slide_image_prompt(string $title, string $desc, string $imageProvider = 'pexels'): string
    {
        $provider = strtolower(trim((string)$imageProvider));
        $title = trim((string)$title);
        $desc  = trim((string)$desc);

        if ($title === '' && $desc === '') {
            $title = 'Nature scene';
            $desc  = 'Outdoor landscape';
        }

        // ---- Caso A: bancos de imagem (Pexels/Unsplash) ----
        if ($provider === 'pexels' || $provider === 'unsplash') {
            return ""
                . "You are a search query generator for image banks (Pexels/Unsplash).\n"
                . "OUTPUT REQUIREMENTS:\n"
                . "- Return ONLY ONE search phrase with 2-4 WORDS\n"
                . "- Use CONCRETE and VISUAL elements (people, objects, actions, settings)\n"
                . "- OUTPUT MUST BE IN ENGLISH (lowercase, no punctuation)\n"
                . "- DO NOT use commas or multiple tags\n"
                . "- DO NOT use prefixes like 'image of', 'photo of'\n"
                . "- The image needs ONE central element\n\n"
                . "PHOTOGRAPHIC LANGUAGE:\n"
                . "- Use specific subjects: 'woman', 'man', 'laptop', 'mountain trail'\n"
                . "- NOT abstract concepts: 'marketing', 'digital', 'strategy'\n"
                . "- NOT AI terms: 'illustration', 'render', '3d', 'cinematic', 'realistic'\n"
                . "- NOT generic locations: 'natural reserve brazil' → use 'forest', 'river', 'jungle'\n\n"
                . "CORRECT EXAMPLES:\n"
                . "✅ 'woman working laptop'\n"
                . "✅ 'mountain trail hiker'\n"
                . "✅ 'waterfall forest'\n"
                . "✅ 'coffee cup table'\n\n"
                . "WRONG EXAMPLES:\n"
                . "❌ 'tourists walking natural reserve brazil' (too generic/long)\n"
                . "❌ 'digital marketing' (abstract)\n"
                . "❌ 'illustration of nature' (AI term + prefix)\n"
                . "❌ 'forest, river, mountain' (multiple tags)\n\n"
                . "OUTPUT FORMAT:\n"
                . "Return ONLY valid JSON UTF-8, no markdown, no extra text:\n"
                . "{ \"content\": \"your search term here\" }\n\n"
                . "CONTEXT:\n"
                . "Slide title: {$title}\n"
                . "Slide text: {$desc}\n\n"
                . "Generate the English search term in JSON format now:\n";
        }

        // ---- Caso B: geração (IA) para Web Stories 9:16 ----
        return ""
            . "You are an AI image prompt generator for vertical Web Story images (9:16 aspect ratio).\n"
            . "OUTPUT REQUIREMENTS:\n"
            . "- Return ONLY ONE prompt (short phrase or paragraph)\n"
            . "- OUTPUT MUST BE IN ENGLISH\n"
            . "- Describe a vertical-friendly scene\n\n"
            . "CONTENT RULES:\n"
            . "- Focus on nature/outdoor travel scenes related to the slide content\n"
            . "- NO text, letters, logos, or watermarks in the image\n"
            . "- NO sexualized people, glamour shots, or body-focused imagery\n"
            . "- PREFER landscapes, trails, rivers, waterfalls, forests\n"
            . "- Include simple lighting/atmosphere details (e.g., 'morning light', 'misty forest')\n\n"
            . "STYLE GUIDELINES:\n"
            . "- Photorealistic outdoor photography style\n"
            . "- Natural colors and lighting\n"
            . "- Vertical composition (portrait orientation)\n"
            . "- Clear central subject or focal point\n\n"
            . "CORRECT EXAMPLES:\n"
            . "✅ 'Mountain trail with morning mist, hiker in distance, vertical composition'\n"
            . "✅ 'Waterfall cascading through lush forest, natural lighting, portrait view'\n"
            . "✅ 'Person standing by river at sunset, vertical outdoor scene'\n\n"
            . "CONTEXT:\n"
            . "Slide title: {$title}\n"
            . "Slide text: {$desc}\n\n"
            . "Generate the English image prompt now:\n";
    }

    public static function build_image_prompt(
        string $keyword,
        string $title,
        string $locale,
        string $imageProvider = ''
    ): string {
        $provider = strtolower(trim((string)$imageProvider));
        $tpl = self::get_prompt_for('', 'image');

        // base com vars (serve pros 2 casos)
        $base = self::replace_vars($tpl, [
            'keyword'  => $keyword,
            'locale'   => "English",
            'title'    => $title,
        ]);

        // ---- Caso A: bancos de imagem (Pexels/Unsplash) ----
        if ($provider === 'pexels' || $provider === 'unsplash') {
            $rules = ""
                . "You are a search query generator for image banks (Pexels/Unsplash).\n"
                . "OUTPUT REQUIREMENTS:\n"
                . "- Return ONLY ONE search phrase with 2-4 WORDS\n"
                . "- Use CONCRETE and VISUAL elements (people, objects, actions, settings)\n"
                . "- OUTPUT MUST BE IN ENGLISH (lowercase, no punctuation)\n"
                . "- DO NOT use commas or multiple tags\n"
                . "- DO NOT use prefixes like 'image of', 'photo of'\n\n"
                . "PHOTOGRAPHIC LANGUAGE:\n"
                . "- Use specific subjects: 'woman', 'man', 'laptop', 'coffee cup'\n"
                . "- NOT abstract concepts: 'marketing', 'digital', 'strategy'\n"
                . "- NOT AI terms: 'illustration', 'render', '3d', 'cinematic', 'realistic'\n"
                . "- NOT generic locations: 'natural reserve brazil' → use 'forest', 'river', 'jungle'\n"
                . "- The image needs ONE central element\n\n"
                . "CORRECT EXAMPLES:\n"
                . "✅ 'woman working laptop'\n"
                . "✅ 'coffee cup desk'\n"
                . "✅ 'man walking forest'\n"
                . "✅ 'notebook open table'\n\n"
                . "WRONG EXAMPLES:\n"
                . "❌ 'tourists walking natural reserve brazil' (too generic/long)\n"
                . "❌ 'digital marketing strategy' (abstract)\n"
                . "❌ 'illustration of laptop' (AI term + prefix)\n"
                . "❌ 'laptop, coffee, notebook' (multiple tags)\n\n"
                . "CONTEXT:\n"
                . "Article title: {$title}\n"
                . "Keyword: {$keyword}\n\n"
                . "Generate the English search term now:\n";

            return $rules . "\n" . $base;
        }

        // ---- Caso B: geração (IA) ----
        $s = ""
            . "You are an AI image prompt generator for article thumbnails.\n"
            . "Create a detailed prompt to generate a thumbnail image.\n\n"
            . "CONTEXT:\n"
            . "- Title: \"{$title}\"\n"
            . "- Keyword: \"{$keyword}\"\n\n"
            . "RULES:\n"
            . "- Describe the scene with specific visual elements\n"
            . "- Include main subjects, actions, and environment\n"
            . "- Specify style if relevant (photorealistic, illustration, minimalist)\n"
            . "- Avoid text overlay on the image\n"
            . "- Keep it visually compelling and relevant to the topic\n\n"
            . "Generate the image prompt now:\n";

        return $s . "\n" . $base;
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
            $tpl .= "\n\nYou are a search term generator for image banks (Pexels/Unsplash).\n";
            $tpl .= "MANDATORY RULES:\n";
            $tpl .= "- Return ONLY ONE search phrase with MAX 3-4 WORDS\n";
            $tpl .= "- Use CONCRETE and VISUAL nouns (objects, people, actions, places)\n";
            $tpl .= "- Base it ONLY on the title: \"{$title}\"\n";
            $tpl .= "- DO NOT use commas, DO NOT separate into multiple tags\n";
            $tpl .= "- DO NOT use prefixes like \"Image of\", \"Photo of\"\n";
            $tpl .= "- DO NOT use abstract concepts (marketing, digital, strategy)\n";
            $tpl .= "- USE tangible visual elements\n";
            $tpl .= "- OUTPUT MUST BE IN ENGLISH\n\n";
            $tpl .= "CORRECT EXAMPLES:\n";
            $tpl .= "Title: '7 filmes de terror na Netflix' → 'person watching TV night'\n";
            $tpl .= "Title: 'Marketing digital para WordPress' → 'person working laptop'\n";
            $tpl .= "Title: 'Receitas de bolo de chocolate' → 'chocolate cake table'\n";
            $tpl .= "Title: '5 dicas de produtividade' → 'organized desk notebook'\n";
            $tpl .= "Title: 'Best hiking trails' → 'mountain trail hiker'\n\n";
            $tpl .= "WRONG EXAMPLES:\n";
            $tpl .= "❌ 'marketing, digital, wordpress' (multiple tags)\n";
            $tpl .= "❌ 'digital marketing' (too abstract)\n";
            $tpl .= "❌ 'Image of computer' (has prefix)\n";
            $tpl .= "❌ 'pessoa trabalhando laptop' (not in English)\n\n";
            $tpl .= "Now generate the English search term based on the provided title.\n";
        } else {
            $tpl .= "\n\nSpecific rules for AI image generation:\n";
            $tpl .= "title: \"{$title}\".\n";
            $tpl .= "context: {$content}.\n";
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
    public static function build_story_prompt_for_post(WP_Post $post, string $raw_html, string $brief = '', string $imageProvider = 'pollinations', string $lang = 'pt_BR'): string
    {
        $tpl = self::get_prompt_for('article', 'story');

        $title   = get_the_title($post);
        $content = wp_strip_all_tags($raw_html);
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        $locale  = $lang;

        $provider = strtolower(trim((string)$imageProvider));

        // ---- Regra dinâmica para campo "prompt" (imagens dos slides) ----
        if ($provider === 'pexels' || $provider === 'unsplash') {
            $image_rule = ""
                . "PROMPT FIELD RULES (for image banks - Pexels/Unsplash):\n"
                . "- Generate search queries IN ENGLISH\n"
                . "- Maximum 2-4 simple words per query\n"
                . "- Use CONCRETE visual elements related to the slide title\n"
                . "- Include specific objects: 'laptop', 'coffee cup', 'mountain', 'woman working'\n"
                . "- DO NOT use generic phrases like 'speed loading website image'\n"
                . "- DO NOT use the word 'image' or 'photo' (it's already an image bank)\n"
                . "- DO NOT use prepositions, articles, or compound words\n\n"
                . "CORRECT EXAMPLES:\n"
                . "✅ 'laptop desk coffee' (for productivity slide)\n"
                . "✅ 'mountain trail hiker' (for travel slide)\n"
                . "✅ 'woman phone smiling' (for communication slide)\n\n"
                . "WRONG EXAMPLES:\n"
                . "❌ 'image of speed loading website' (generic + has 'image')\n"
                . "❌ 'digital marketing strategy' (abstract, no visual elements)\n"
                . "❌ 'velocidade carregamento' (not in English)\n";
        } else {
            $image_rule = ""
                . "PROMPT FIELD RULES (for AI image generation):\n"
                . "- Generate prompts IN ENGLISH for VERTICAL PHOTOREALISTIC images\n"
                . "- Style: cinematic, natural lighting, portrait orientation (9:16)\n"
                . "- NO text overlays, logos, or watermarks\n"
                . "- Focus on the slide's main topic with clear visual elements\n"
                . "- Include lighting/atmosphere details when relevant\n";
        }

        $base = self::replace_vars($tpl, [
            'title'             => $title,
            'content'           => $content,
            'brief'             => $brief,
            'image_prompt_rule' => $image_rule,
        ]);

        $s  = "You are a specialist in transforming blog posts into AMP Web Stories.\n\n";
        $s .= "CONTEXT:\n";
        $s .= "- Title: {$title}\n";
        $s .= "- Content: {$content}\n";
        $s .= "- Brief: {$brief}\n";
        $s .= "- Locale, pode traduzir incluse a KW: {$locale}\n\n";
        $s .= "TASK:\n";
        $s .= "Convert the blog post into an engaging Web Story following the rules below.\n\n";

        return $s . $base . "\n\n" . self::story_json_format_block();
    }

    /**
     * $ctx esperado:
     * - slidesCount (int)
     * - locale (pt_BR etc)
     * - title (string)
     * - content (string)  -> enviado por último no prompt
     * - cta_pages (array<int>) -> páginas (1-based) que DEVEM ter CTA
     * - cta_url_default (string) opcional (ex: permalink do post)
     * - cta_text_default (string) opcional
     */
    public static function build_ws_story_prompt(array $a): string
    {
        $slidesCount = max(1, (int)($a['slidesCount'] ?? 6));
        $locale      = (string)($a['locale'] ?? 'pt_BR');
        $title       = trim((string)($a['title'] ?? ''));
        $content     = trim((string)($a['content'] ?? ''));
        $cta_pages   = is_array($a['cta_pages'] ?? null) ? array_values(array_filter(array_map('absint', $a['cta_pages']))) : [];
        $cta_url_def  = trim((string)($a['cta_url_default'] ?? ''));

        if ($title === '') $title = 'Web Story';
        if ($content === '') $content = $title;

        $cta_pages_str = empty($cta_pages) ? 'nenhuma' : implode(', ', $cta_pages);

        $prompt = ""
            . "Você é um gerador de Web Stories a partir de conteúdo.\n"
            . "Todo o conteúdo deve ser gerado em: {$locale} (pode traduzir incluse a KW), é uma informação muito importante, tudo precisa estar no idioma {$locale}, independente do texto base.\n"
            . "Título base: {$title}\n"
            . "Quantidade de páginas: {$slidesCount}\n"
            . "Páginas com CTA (0-indexado): {$cta_pages_str}\n\n"

            . "FORMATO OBRIGATÓRIO:\n"
            . "Responda APENAS em JSON válido UTF-8.\n"
            . "NÃO use markdown. NÃO explique nada.\n\n"

            . "Estrutura obrigatória do JSON:\n"
            . "{\n"
            . "  \"title\": \"\",\n"
            . "  \"desc\": \"\",\n"
            . "  \"slug\": \"\",\n"
            . "  \"pages\": [\n"
            . "    {\n"
            . "      \"heading\": \"\",\n"
            . "      \"body\": \"\",\n"
            . "      \"cta_text\": \"\",\n"
            . "      \"cta_url\": \"\"\n"
            . "    }\n"
            . "  ]\n"
            . "}\n\n"

            . "Regra de CTA:\n"
            . "- Apenas as páginas listadas devem conter CTA.\n"
            . "- Nas páginas com CTA, use para o cta_text, crie CTAs que tenham a ver com o conteúdo, não seja obvia demais como \"veja mais\", \"saiba mais\", mas traga coisas nesse sentido com no máximo 3 palavras etc e cta_url=\"{$cta_url_def}\".\n"
            . "- Nas páginas SEM CTA, cta_text e cta_url devem ser string vazia.\n\n"
            . "Formato obrigatório:\n"
            . "Responda APENAS em JSON UTF-8 válido, COM UMA CHAVE \"content\".\n"
            . "A chave \"content\" deve conter UMA STRING que seja um JSON válido no formato abaixo.\n"
            . "Não use markdown. Não explique nada.\n\n"
            . "JSON alvo (title/desc/slug + pages) que deve estar DENTRO de content:\n"
            . "- Use título, Descrição e slug coerentes com o conteúdo.\n\n"
            . "Regras para a slug:\n"
            . "- Retire qualquer preposição, \"como\", \"é\", \"para\", etc\n"
            . "- Crie uma slug válida com no máximo 5 palavras no formato de tags\n"
            . "- Não inclua numeros sem sentido\n"
            . "- Regras para o tíutlo:\n"
            . "- Crie algo que vá ser coerente com os slides e coerente com o nivel de funil do título principal\n"
            . "- Obrigatório evitar palavras de outros niveis de funil\n"
            . "- Regras para a descrição:\n"
            . "- Analise o nivel do funil do conteúdo e crie algo condizente com isso, a descrição deve ter entre 120 e 160 caracteres com cta no final. CTA levando em conta o nivel de funil e assim proibindo palavras de outros niveis\n"
            . "Regras editoriais:\n"
            . "- Slide 1 = capa com headline forte (máx 38 caracteres) + gancho (1 frase), sempre sem CTA.\n"
            . "- Slides 2+ = progressão (máx 45 caracteres no heading)\n"
            . "- body curto (1 a 2 frases)\n"
            . "- Evite repetição de palavras entre slides\n"
            . "- Deve ter coerencia do primeiro ao ultimo slide, como uma história contada. Se o primeiro slide promete x itens, então o conteúdo tem q demonstrar esses x itens \n"
            . "- Sem 'Slide #', sem listas, sem markdown\n"
            . "- Gere exatamente {$slidesCount} itens em pages.\n\n"
            . "Conteúdo base (use como fonte, não copie literalmente):\n"
            . "Regra CRÍTICA do Slide 1 (capa):\n"
            . "- O item pages[0].heading deve ser MUITO chamativo e gerar curiosidade (headline forte).\n"
            . "- Máx 38 caracteres, sem ponto final, sem 'Slide 1', sem emoji.\n"
            . "- pages[0].body deve ser 1 frase curta (gancho), sem entregar tudo.\n"
            . "- Interprete o conteúdo e avalie e nivel de funil e proiba palavras de outros niveis de funil, se for meio de funil, proiba palavras de topo e fundo, e assim sucessivamente para todos os niveis.\n\n"

            . $content;

        return $prompt;
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
            wp_die(esc_html__('Sem permissão.', 'alpha-suite'));
        }

        self::handle_save();

        $raw = self::get_all_raw();
        $stages = self::stages();

        $tpls = class_exists('AlphaSuite_Orion_Templates')
            ? AlphaSuite_Orion_Templates::get_all()
            : [
                'article' => ['label' => 'Artigo (padrão)', 'builtin' => 1, 'enabled' => 1],
                'modelar_youtube' => ['label' => 'Modelar YouTube', 'builtin' => 1, 'enabled' => 1],
                'rss' => ['label' => 'Modelar RSS', 'builtin' => 1, 'enabled' => 1],
            ];

        // Garante que os 2 core sempre apareçam
        if (!isset($tpls['article'])) {
            $tpls['article'] = ['label' => 'Artigo (padrão)', 'builtin' => 1, 'enabled' => 1];
        }
        if (!isset($tpls['modelar_youtube'])) {
            $tpls['modelar_youtube'] = ['label' => 'Modelar YouTube', 'builtin' => 1, 'enabled' => 1];
        }
        if (!isset($tpls['rss'])) {
            $tpls['rss'] = ['label' => 'Modelar RSS', 'builtin' => 1, 'enabled' => 1];
        }

        // Ordena: core primeiro
        uksort($tpls, function ($a, $b) {
            $prio = ['article' => 0, 'modelar_youtube' => 2, 'rss' => 1, 'global' => 3];
            $pa = $prio[$a] ?? (!empty($tpls_all[$a]['builtin']) ? 10 : 20);
            $pb = $prio[$b] ?? (!empty($tpls_all[$b]['builtin']) ? 10 : 20);

            if ($pa !== $pb) return $pa <=> $pb;
            return strcmp($a, $b);
        });

        $core_templates = ['article', 'modelar_youtube', 'rss'];

        $core_defaults = [];
        foreach ($core_templates as $ct) {
            foreach (array_keys($stages) as $sk) {
                $core_defaults[$ct][$sk] = self::default_prompt_for($ct, $sk);
            }
        }

        // Templates salvos
        $tpls_all = class_exists('AlphaSuite_Orion_Templates')
            ? AlphaSuite_Orion_Templates::get_all()
            : [];

        // Garante os 2 nativos (se por algum motivo não vierem)
        if (empty($tpls_all['article'])) {
            $tpls_all['article'] = ['label' => 'Artigo (padrão)', 'enabled' => 1, 'builtin' => 1];
        }
        if (empty($tpls_all['modelar_youtube'])) {
            $tpls_all['modelar_youtube'] = ['label' => 'Modelar YouTube', 'enabled' => 1, 'builtin' => 1];
        }

        if (empty($tpls_all['rss'])) {
            $tpls_all['rss'] = ['label' => 'Modelar RSS', 'enabled' => 1, 'builtin' => 1];
        }

        if (empty($tpls_all['global'])) {
            $tpls_all['global'] = ['label' => 'Global', 'enabled' => 1, 'builtin' => 1];
        }


        // Só pra organizar: nativos primeiro
        uksort($tpls_all, function ($a, $b) use ($tpls_all) {
            $ab = !empty($tpls_all[$a]['builtin']) ? 0 : 1;
            $bb = !empty($tpls_all[$b]['builtin']) ? 0 : 1;
            if ($ab !== $bb) return $ab <=> $bb;
            return strcmp($a, $b);
        });

        settings_errors('alpha-suite-orion-prompts');
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
                        <h1 class="pga-h1"><?php esc_html_e('Prompts Gerais', 'alpha-suite'); ?></h1>
                        <p class="pga-sub">
                            <?php esc_html_e('Configure o comportamento da IA por modelo e etapa. Campos vazios herdam automaticamente o padrão interno.', 'alpha-suite'); ?>
                        </p>
                    </div>
                    <div class="pga-import-export">
                        <button type="button" class="pga-btn" id="pga-prompts-export">
                            <?php esc_html_e('Exportar prompts', 'alpha-suite'); ?>
                        </button>

                        <button type="button" class="pga-btn" id="pga-prompts-import">
                            <?php esc_html_e('Importar prompts', 'alpha-suite'); ?>
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
                            <span><?php esc_html_e('Global', 'alpha-suite'); ?></span>
                        </button>
                    </div>

                    <?php
                    // ===== PANELS por template =====
                    $panelIndex = 0;
                    foreach ($tpls as $tpl_slug => $tpl_meta):
                        $tpl_slug = sanitize_key((string)$tpl_slug);
                        $label    = (string)($tpl_meta['label'] ?? $tpl_slug);
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
                                            <?php echo  esc_html($stage_label); ?>
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
                                $canRestore = in_array($tpl_slug, ['article', 'modelar_youtube', 'rss'], true);
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
                                            <?php esc_html_e('Restaurar padrão', 'alpha-suite'); ?>
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
                            'image' => __('Imagem Thumbnail', 'alpha-suite'),
                            'post_thumbnail_regen' => __('Regenerar thumbnail', 'alpha-suite'),
                            'image_stock'          => __('Imagem (Pexels / Unsplash)', 'alpha-suite'),
                            'story'                => __('Web Stories', 'alpha-suite'),
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
                                            <?php esc_html_e('Restaurar padrão', 'alpha-suite'); ?>
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
                                <h2 id="pga-templates-title"><?php esc_html_e('Modelos de conteúdo', 'alpha-suite'); ?></h2>
                                <button type="button" class="pga-btn" data-pga-modal-close><?php esc_html_e('Fechar', 'alpha-suite'); ?></button>
                            </div>

                            <p class="pga-table-description" style="margin-top:0;">
                                <?php esc_html_e('Aqui você escolhe quais modelos aparecem no gerador do Órion. O plugin mantém 2 nativos: Artigo e Modelar YouTube.', 'alpha-suite'); ?>
                            </p>

                            <table class="pga-table" id="pga-orion-templates-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Modelo', 'alpha-suite'); ?></th>
                                        <th style="width:240px;"><?php esc_html_e('Ativo', 'alpha-suite'); ?></th>
                                        <th style="width:180px;"><?php esc_html_e('Padrão', 'alpha-suite'); ?></th>
                                        <th style="width:160px;text-align:right;"><?php esc_html_e('Ações', 'alpha-suite'); ?></th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($tpls_all as $slug => $row):
                                        $slug = sanitize_key((string)$slug);
                                        $is_builtin = !empty($row['builtin']) || in_array($slug, ['global', 'article', 'modelar_youtube', 'rss'], true);
                                        $label = (string)($row['label'] ?? $slug);
                                        $enabled = !empty($row['enabled']) ? 1 : 0;
                                        $is_default = !empty($row['is_default']) ? 1 : 0;
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
                                                <?php if ($slug !== 'global'): ?>

                                                    <!-- sempre envia 0, mesmo desmarcado -->
                                                    <input type="hidden"
                                                        name="pga_orion_templates[<?php echo esc_attr($slug); ?>][is_default]"
                                                        value="0">

                                                    <label class="pga-mini" style="display:flex;align-items:center;gap:8px;">
                                                        <input type="checkbox"
                                                            name="pga_orion_templates[<?php echo esc_attr($slug); ?>][is_default]"
                                                            value="1"
                                                            <?php checked((int)$is_default === 1); ?>>
                                                        <span><?php esc_html_e('Novo projeto', 'alpha-suite'); ?></span>
                                                    </label>

                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <div style="display: <?php echo $slug === 'article' || $slug === 'global' || $slug === 'modelar_youtube' ? 'none' : 'block' ?>">
                                                    <label>
                                                        <input type="checkbox"
                                                            name="pga_orion_templates[<?php echo esc_attr($slug); ?>][enabled]"
                                                            value="1"
                                                            <?php checked($enabled === 1); ?>
                                                            <?php echo $is_builtin ? 'disabled' : ''; ?>>
                                                        <strong><?php echo $enabled ? esc_html__('Ativo', 'alpha-suite') : esc_html__('Inativo', 'alpha-suite'); ?></strong>
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
                                                    <button type="button" class="pga-btn pga-remove-tpl-row"><?php esc_html_e('Remover', 'alpha-suite'); ?></button>
                                                <?php else: ?>
                                                    <span class="pga-mini">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>

                                <tfoot>
                                    <tr>
                                        <td colspan="4">
                                            <button type="button" class="pga-btn pga-btn--primary" id="pga-add-tpl-row">+ <?php esc_html_e('Adicionar modelo personalizado', 'alpha-suite'); ?></button>
                                            <span class="pga-mini" style="margin-left:10px;"><?php esc_html_e('Ex.: receitas, review, modelar_url', 'alpha-suite'); ?></span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Loading overlay -->
                    <div class="pga-loading" id="pga-loading" aria-hidden="true">
                        <div class="pga-loading-card"><?php esc_html_e('Carregando…', 'alpha-suite'); ?></div>
                    </div>

                    <!-- ✅ BARRA FIXA (DENTRO DO FORM) -->
                    <div class="pga-bottom-bar">
                        <div class="pga-bottom-left">
                            <button type="submit" class="pga-btn pga-btn--primary">
                                <?php esc_html_e('Salvar prompts', 'alpha-suite'); ?>
                            </button>

                            <button type="button" class="pga-btn" id="pga-open-templates">
                                <?php esc_html_e('Modelos', 'alpha-suite'); ?>
                            </button>
                            <button type="button" class="pga-btn" id="pga-vars-btn">
                                <?php esc_html_e('Variáveis Disponíveis', 'alpha-suite'); ?>
                            </button>
                            <div id="pga-vars-panel" class="pga-vars-panel">
                                <div class="pga-vars-pop" id="pga-vars-pop" aria-hidden="true">
                                    <div class="pga-vars-pop__body">
                                        <div class="pga-vars-grid">
                                            <h3><?php esc_html_e('Título', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{locale}}</code>
                                            <code>{{template}}</code>

                                            <h3><?php esc_html_e('Esboço', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{articleTitle}}</code>
                                            <code>{{locale}}</code>
                                            <code>{{template}}</code>

                                            <h3><?php esc_html_e('Esboço', 'alpha-suite'); ?> Youtube</h3>
                                            <code>{{articleTitle}}</code>
                                            <code>{{locale}}</code>
                                            <code>{{url}}</code>
                                            <code>{{videoTitle}}</code>
                                            <code>{{chapters}}</code>
                                            <code>{{videoDescription}}</code>
                                            <code>{{tags}}</code>

                                            <h3><?php esc_html_e('Sessão', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{articleTitle}}</code>
                                            <code>{{locale}}</code>
                                            <code>{{section_number}}</code>
                                            <code>{{section_heading}}</code>
                                            <code>{{section_level}}</code>
                                            <code>{{section_bullets}}</code>
                                            <code>{{section_children}}</code>
                                            <code>{{sections_count}}</code>

                                            <h3><?php esc_html_e('Descrição', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{articleTitle}}</code>
                                            <code>{{locale}}</code>
                                            <code>{{content}}</code>

                                            <h3><?php esc_html_e('Slug', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{articleTitle}}</code>
                                            <code>{{locale}}</code>

                                            <h3><?php esc_html_e('Re-geração (image_stock)', 'alpha-suite'); ?></h3>
                                            <code>{{content}}</code>
                                            <code>{{title}}</code>
                                            <code>{{locale}}</code>

                                            <h3><?php esc_html_e('Imagem', 'alpha-suite'); ?></h3>
                                            <code>{{keyword}}</code>
                                            <code>{{title}}</code>
                                            <code>{{template}}</code>
                                            <code>{{locale}}</code>

                                            <h3><?php esc_html_e('Stories', 'alpha-suite'); ?></h3>
                                            <code>{{title}}</code>
                                            <code>{{content}}</code>
                                            <code>{{brief}}</code>
                                            <code>{{image_prompt_rule}}</code>

                                            <h3><?php esc_html_e('Keywords', 'alpha-suite'); ?></h3>
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
                            <input id="pga_tpl_label" class="swal2-input" style="margin: 0!important" placeholder="Nome do modelo" autocomplete="off">
                        </div>`,
                        focusConfirm: false,
                        showCancelButton: true,
                        confirmButtonText: 'Adicionar',
                        cancelButtonText: 'Cancelar',
                        preConfirm: () => {
                            const label = (document.getElementById('pga_tpl_label')?.value || '').trim();
                            const enabled = 'enabled';

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
                    'alpha-suite-orion-prompts',
                    'pga_import_nonce',
                    __('Nonce inválido no import.', 'alpha-suite'),
                    'error'
                );
                return;
            }

            if (empty($_FILES['pga_orion_import_file']['tmp_name'])) {
                add_settings_error(
                    'alpha-suite-orion-prompts',
                    'pga_import_file',
                    __('Envie um arquivo JSON para importar.', 'alpha-suite'),
                    'error'
                );
                return;
            }

            $raw = isset($_FILES['pga_orion_import_file']['tmp_name']) ? file_get_contents(sanitize_text_field(wp_unslash($_FILES['pga_orion_import_file']['tmp_name']))) : '';
            $data = json_decode((string) $raw, true);

            if (!is_array($data)) {
                add_settings_error(
                    'alpha-suite-orion-prompts',
                    'pga_import_json',
                    __('JSON inválido.', 'alpha-suite'),
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

        $templates = isset($_POST['pga_orion_templates']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['pga_orion_templates'])) : [];

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

        // templates (do modal)
        $templates_post = [];
        if (isset($_POST['pga_orion_templates'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $templates_post = (array) wp_unslash($_POST['pga_orion_templates']);
        }

        $clean_templates = [];
        // 2) depois, normaliza o que veio do POST
        foreach ($templates_post as $slug => $row) {
            $slug = sanitize_key((string) $slug);
            if ($slug === '' || $slug === 'global') continue;

            // nativos já foram forçados acima
            if (isset($clean_templates[$slug]) && !empty($clean_templates[$slug]['builtin'])) {
                continue;
            }

            $row = is_array($row) ? $row : [];

            $label      = sanitize_text_field((string) ($row['label'] ?? $slug));
            $enabled    = !empty($row['enabled']) ? 1 : 0;
            $is_default = !empty($row['is_default']) ? 1 : 0;

            // se for padrão, tem que estar ativo
            if ($is_default) $enabled = 1;

            $clean_templates[$slug] = [
                'label'      => $label ?: $slug,
                'enabled'    => $enabled,
                'builtin'    => 0,
                'is_default' => $is_default,
            ];
        }

        // 3) limpa prompts de templates que não existem mais
        $keep = array_fill_keys(array_keys($clean_templates), true);

        if (is_array($out)) {
            foreach (array_keys($out) as $tpl_slug) {
                $tpl_slug = sanitize_key((string) $tpl_slug);
                if ($tpl_slug === 'global') continue;

                if (empty($keep[$tpl_slug])) {
                    unset($out[$tpl_slug]);
                }
            }
        }

        update_option('pga_orion_templates', $clean_templates, false);
        update_option(self::OPTION, $out, false);


        add_settings_error(
            'alpha-suite-orion-prompts',
            'pga_orion_prompts_updated',
            __('Prompts salvos com sucesso.', 'alpha-suite'),
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
        if (
            empty($_FILES['file']) ||
            ! isset($_FILES['file']['tmp_name'])
        ) {
            wp_send_json_error(['message' => 'Arquivo não recebido (campo "file").'], 400);
        }

        $f = array_map('sanitize_text_field', wp_unslash($_FILES['file']));


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

        $keys_json = isset($_POST['keys'])
            ? sanitize_text_field(wp_unslash($_POST['keys']))
            : '[]';
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

            if (in_array($slug, ['article', 'modelar_youtube', 'rss'], true)) {
                $clean_templates[$slug] = [
                    'label'      => $label ?: $slug,
                    'enabled'    => 1,
                    'builtin'    => 1,
                    'is_default' => 1,
                ];
            } else {
                $current_templates[$slug] = ['label' => $label ?: $slug, 'enabled' => $enabled, 'builtin' => 0, 'is_default' => 0];
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
                        'builtin' => in_array($tpl_slug, ['article', 'modelar_youtube', 'rss'], true) ? 1 : 0,
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
                return [300, 500];
            case 'medium':
                return [600, 1000];
            case 'long':
                return [1200, 2200];
            case 'extra-long':
            case 'extra_long':
            case 'extra':
                return [2500, 5000];
            default:
                return [300, 500];
        }
    }

    public static function outline_config(string $length): array
    {
        switch ($length) {
            case 'short':
                return ['min_sections' => 1, 'max_sections' => 4];
            case 'medium':
                return ['min_sections' => 4, 'max_sections' => 8];
            case 'long':
                return ['min_sections' => 8, 'max_sections' => 15];
            case 'extra-long':
            case 'extra_long':
            case 'extra':
                return ['min_sections' => 15, 'max_sections' => 30];
            default:
                return ['min_sections' => 1, 'max_sections' => 4];
        }
    }

    public static function build_story_prompt_from_post(string $title, string $content): string
    {
        $tpl = self::get_prompt_for('story', 'story');

        $base = self::replace_vars($tpl, [
            'articleTitle' => $title,
            'content'  => $content,
        ]);

        $rules = "REGRAS IMPORTANTES PARA A HISTÓRIA:\n"
            . "- A história deve ser envolvente e cativante.\n"
            . "- Use uma linguagem simples e clara.\n"
            . "- Mantenha os parágrafos curtos (máx. 2 frases).\n"
            . "- Evite jargões técnicos ou termos complexos.\n"
            . "- Certifique-se de que a história tenha um começo, meio e fim claros.\n"
            . "- Inclua diálogos para tornar a história mais viva.\n"
            . "- Use descrições sensoriais para criar uma imagem vívida na mente do leitor.\n"
            . "- Mantenha o tom apropriado para o público-alvo.\n"
            . "- Revise a história para garantir que não haja erros gramaticais ou ortográficos.\n";

        $final = $base . "\n\n" . $rules;

        return $final;
    }

    public static function build_faq_prompt(array $args): string
    {
        $keyword = trim((string)($args['keyword'] ?? ''));
        $qty     = min(5, max(1, (int)($args['qty'] ?? 3)));
        $locale  = $args['locale'] ?? 'pt_BR';
        $context  = $args['context'] ?? '';

        $lang = match ($locale) {
            'pt_BR' => 'português do Brasil',
            'en_US' => 'inglês',
            'es_ES' => 'espanhol',
            default => 'português',
        };

        $c = '';
        if ($context !== '') {
            $c = "Use o conteudo abaixo como base para criar as perguntas e torna-las mais verdadeiras\n"
                . "-----Inicio---\n"
                . $context . "\n"
                . "-----fim---\n\n";
        }

        $p = "Gere exatamente {$qty} perguntas frequentes (FAQ) sobre \"{$keyword}\"\n."
            . "Regras obrigatórias:\n"
            . "- Escreva em {$lang}.\n"
            . "- Use perguntas reais que um usuário faria.\n"
            . "- Respostas objetivas, claras e diretas.\n"
            . "- Não use listas, markdown ou emojis.\n"
            . "- Não mencione IA, modelos ou processos internos.\n\n"

            . $c

            . "Formato de saída:\n"
            . "Retorne APENAS um objeto JSON válido no padrão Schema.org FAQPage,\n"
            . "com @context, @type e mainEntity.\n"
            . "Não retorne texto fora do JSON.";

        return $p;
    }

    public static function build_section_prompt(
        string $template,
        string $keyword,
        string $articleTitle,
        array  $section,
        string $length,
        string $locale,
        int    $sectionsCount,
        string $section_number,
        string $url = '',
    ): string {
        $tpl = self::get_prompt_for($template, 'section');

        $heading = trim((string)($section['heading'] ?? ''));
        $level   = strtolower(trim((string)($section['level'] ?? 'h2')));
        if ($level !== 'h2' && $level !== 'h3') $level = 'h2';

        $sectionParagraph = trim((string)($section['paragraph'] ?? ''));

        // children detalhado (H3 sugeridos com paragraph)
        $childrenDetailed = '';
        if (!empty($section['children']) && is_array($section['children'])) {
            $list = [];
            $n = 1;
            foreach ($section['children'] as $c) {
                $h = trim((string)($c['heading'] ?? ''));
                $p = trim((string)($c['paragraph'] ?? ''));
                if ($h === '') continue;

                $line = "H3 {$n}: {$h}";
                if ($p !== '') $line .= " — Brief: {$p}";
                $list[] = $line;
                $n++;
            }
            if ($list) $childrenDetailed = implode("\n", $list);
        }

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

        if ($goalMin <= 0 || $goalMax <= 0) {
            $per = max(90, (int) floor($maxWords / max(1, $sectionsCount)));
            $goalMin = (int) max(60, floor($per * 0.55));
            $goalMax = (int) max($goalMin + 30, floor($per * 0.75));
        }

        $childrenCount = 0;
        if (!empty($section['children']) && is_array($section['children'])) {
            $childrenCount = count($section['children']);
        }

        $url = trim((string)$url);

        $base = self::replace_vars($tpl, [
            'keyword'                   => $keyword,
            'articleTitle'              => $articleTitle,
            'locale'                    => $locale,
            'section_heading'           => $heading,
            'section_level'             => $level,
            'section_paragraph'         => $sectionParagraph,
            'section_children'          => $children,
            'section_children_detailed' => $childrenDetailed,
            'section_bullets'           => $bullets,
            'sections_count'            => (string)$sectionsCount,
            'section_number'            => (string)$section_number,
            'url'                       => $url,
        ]);

        $ctx = '';
        $idx = max(1, (int)$section_number);
        $total = max(1, (int)$sectionsCount);
        $remaining = max(0, $total - $idx);

        $state = "CONTEXTO DA SEÇÃO:\n"
            . "Título do artigo: \"{$articleTitle}\"\n"
            . "Esta é a seção {$idx} de {$total} (restam {$remaining})\n"
            . "Data atual: " . SELF::date() . "\n"
            . "Frase chave: \"{$keyword}\"\n"
            . "Título da sessão: \"{$heading}\"\n"
            . "A sessão deve ser gerada no idioma, pode traduzir incluse a KW: {$locale}\n\n"

            . "REGRAS DE FORMATAÇÃO:\n"
            . "- Use HTML limpo: p, strong, ul, ol, li, a... etc\n"

            . "CONTEXTO CRÍTICO:\n"
            . "- Você está gerando APENAS a seção {$section_number} de um total de {$sectionsCount} seções\n"
            . "- Cada seção é gerada ISOLADAMENTE - você NÃO tem acesso ao conteúdo das outras seções\n\n";

        if ($template != 'modelar_youtube') {
            $state .= "PALAVRA-CHAVE DE FOCO:\n"
                . "- Só use quando fizer sentido contextualmente\n"
                . "- Como você sabe, mais importante que frases chaves, para o Google, é o contexto do artigo\n"
                . "- Integre de forma fluida no texto, nunca forçada\n"
                . "- Ex: para a frase chave \"filmes de desenho\", a frase \"Filmes de desenho na Netflix conquistam cada vez mais espaço entre opções familiares\" não é nada fluida como primeira frase do conteúdo\n\n";
        }

        $brief = "BRIEF DA SEÇÃO (siga fielmente — esta é sua fonte principal):\n"
            . "Heading ({$level}): {$heading}\n";

        if ($sectionParagraph !== '') {
            $brief .= "Parágrafo-guia do {$level}: {$sectionParagraph}\n"
                . "REGRA: Desenvolva com clareza e objetividade, mantendo ritmo jornalístico. Evite explicações excessivamente longas ou acadêmicas.\n\n";
        }

        if ($childrenDetailed !== '') {
            $brief .= "Subtítulos H3 sugeridos com briefs:\n{$childrenDetailed}\n"
                . "REGRA: Crie cada H3 e desenvolva seguindo o brief específico de cada um.\n\n";
        } else if ($children !== '') {
            $brief .= "Subtítulos H3 sugeridos:\n{$children}\n\n";
        }

        if ($bullets !== '') {
            $brief .= "Bullets sugeridos (use como guia, não liste literalmente):\n{$bullets}\n\n";
        }

        $brief .= "IMPORTANTE:\n"
            . "- Não invente novos tópicos fora do escopo do brief\n";

        $tech = "REGRAS TÉCNICAS (não discuta, apenas cumpra):\n"
            . "- Escreva SOMENTE esta seção (não escreva o artigo inteiro)\n"
            . "- Comece EXATAMENTE com: <{$level}>{$heading}</{$level}>\n"
            . "- NÃO escreva outros H2 além deste\n"
            . "- Esta seção (incluindo TODOS os H3 e TODO o texto) deve ter entre {$goalMin} e {$goalMax} palavras NO TOTAL\n"
            . "- Se ultrapassar {$goalMax} palavras, encurte antes de finalizar\n"
            . "- Use bullet points (<ul>, <li>) apenas quando realmente melhorar a clareza, mas apenas em sessões de numeros impares ou quando o brief sugerir\n"
            . "- Inclua H3 dentro desta seção se sugeridos no brief\n";

        if ($template === 'modelar_youtube') {
            $tech .= "- NUNCA mencione: vídeo, canal, link, URL, ou qualquer referência à fonte original\n";
        }

        $tech .= "\n";

        return $state . $brief . $tech . $base . "\n" . $ctx . "\n\n";
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
            . "O título deve ser gerada no idioma, pode traduzir incluse a KW: {$locale}\n\n";


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
            "Responda APENAS em JSON UTF-8 válido (uma única linha), sem markdown.\n"
            . "- Hoje é: " . self::date() . "\n"
            . "Regras técnicas (não discuta, apenas cumpra):\n"
            . "- Gere {$count} keywords NOVAS e DIFERENTES.\n"
            . "- Gere em, pode traduzir incluse a KW {$locale}.\n"
            . "- Categoria: {$category}.\n"
            . "- Use o comando como direção (caso tenha): \"{$command}\".\n"
            . "- O JSON deve ser VÁLIDO e em UMA LINHA.\n"
            . "- No campo \"content\", use UMA keyword por linha.\n"
            . "- IMPORTANTE: como o JSON é em uma linha, separe as linhas usando \\n (barra invertida + n).\n"
            . "- NÃO use bullets, NÃO use numeração, NÃO use vírgulas como separador.\n"
            . "- NÃO inclua barras \\ (exceto nos \\n), pipes | ou ponto-e-vírgula ; como separadores.\n"
            . "- Não adicione explicações.\n\n"
            . "Exemplo válido:\n"
            . "Responda SOMENTE em JSON válido no formato: "
            . "{\n"
            . " \"content\": [\n"
            . " \"item 1\",\n"
            . " \"item 2\",\n"
            . " \"item 3\"\n"
            . "]\n"
            . "}";



        return $base . $ban . "\n\n" . $suffix;
    }

    /* =============================
   * DEFAULTS (CORE)
   * ============================= */

    private static function default_keywords_prompt(): string
    {

        return "Nós somos a Alpha Suite, vendemos plugins para WordPress, nosso produto foco atualmente "
            . "é o Alpha Suite, que contém os módulos Alpha Órion e o Alpha Stories, o Orion é um plugin que "
            . "gera conteúdos com IA e o Stories gera Web Stories do Google com apenas 1 clique. \n"
            . "Siga as especificações abaixo para gerar keywords: ";
    }

    private static function default_title_prompt(): string
    {
        return
            "Você é um jornalista sênior especializado em Google Discover, notícias e títulos de alto CTR.\n\n"

            . "OBJETIVO:\n"
            . "Criar títulos CURTOS, CLAROS e IMPACTANTES com estilo jornalístico profissional que geram curiosidade genuína e cliques orgânicos no Google Discover.\n\n"

            . "DIRETRIZES EDITORIAIS OBRIGATÓRIAS:\n"
            . "- Trate o conteúdo como NOTÍCIA ou atualização relevante\n"
            . "- Priorize: acontecimentos, mudanças, revelações, tendências ou fatos recentes\n"
            . "- Use linguagem clara, direta e natural, sem exageros artificiais\n"
            . "- Inclua a palavra-chave naturalmente dentro de um benefício emocional ou promessa de solução\n"
            . "  Fórmula: [Palavra-chave] + [Benefício/Curiosidade]\n\n"

            . "CARACTERÍSTICAS DE TÍTULOS OTIMIZADOS PARA DISCOVER:\n"
            . "1. ESPECIFICIDADE: Use números, dados, nomes próprios ou fatos concretos quando relevante\n"
            . "2. EMOÇÃO E CURIOSIDADE: Desperte interesse genuíno sem clickbait vazio\n"
            . "3. AUTORIDADE: O título deve parecer confiável e informativo\n"
            . "4. CLAREZA: O leitor deve entender imediatamente sobre o que é a matéria\n"
            . "5. URGÊNCIA: Quando aplicável, transmita senso de novidade ou relevância imediata\n"
            . "6. CAPITALIZAÇÃO: Use apenas primeira palavra maiúscula + nomes próprios (padrão jornalístico)\n\n"

            . "REGRAS DE ESTILO:\n"
            . "- Tom jornalístico, direto e profissional\n"
            . "- Frases bem construídas com vocabulário rico mas acessível\n"
            . "- Evite palavras genéricas, promessas vagas ou sensacionalismo\n"
            . "- Pareça escrito por um jornalista humano experiente\n"
            . "- NUNCA use emojis, aspas desnecessárias ou símbolos estranhos\n\n"

            . "CONFORMIDADE COM POLÍTICAS DO DISCOVER:\n"
            . "- NÃO faça promessas médicas, financeiras ou jurídicas específicas\n"
            . "- NÃO use linguagem sensacionalista ou alarmista exagerada\n"
            . "- NÃO mencione conteúdo adulto, violência gráfica ou temas sensíveis\n"
            . "- NÃO use táticas enganosas ou informações falsas\n"
            . "- NÃO prometa resultados garantidos ou milagrosos\n"
            . "- MANTENHA títulos factuais e verificáveis\n\n"

            . "EXEMPLOS DE TÍTULOS APROVADOS:\n"
            . "✅ '7 filmes de terror na Netflix que ninguém está falando'\n"
            . "✅ 'Como o Brasil se posicionou na COP30 em 2026'\n"
            . "✅ 'Novo estudo revela impacto do sono na produtividade'\n"
            . "✅ '5 cidades brasileiras com custo de vida mais acessível em 2026'\n\n"

            . "EXEMPLOS DE TÍTULOS REJEITADOS:\n"
            . "❌ 'Médicos CHOCADOS: Descubra o segredo para emagrecer 10kg' (promessa médica + sensacionalismo)\n"
            . "❌ 'URGENTE: Você PRECISA fazer isso AGORA ou vai se arrepender' (alarmismo + clickbait vazio)\n"
            . "❌ 'Ganhe R$ 10.000 por mês trabalhando de casa' (promessa financeira não verificável)\n"
            . "❌ 'O que ELES não querem que você saiba sobre...' (teoria conspiratória)\n\n"

            . "CHECKLIST FINAL:\n"
            . "☐ Título é factual e verificável?\n"
            . "☐ Evita promessas médicas, financeiras ou jurídicas específicas?\n"
            . "☐ Tom é jornalístico e profissional (não sensacionalista)?\n"
            . "☐ Palavra-chave está incluída naturalmente?\n"
            . "☐ Capitalização correta (só primeira palavra + nomes próprios)?\n"
            . "☐ Zero emojis, aspas desnecessárias ou símbolos?\n"
            . "☐ Geraria cliques genuínos no Discover?\n\n"

            . "IMPORTANTE: Gere os títulos no idioma especificado sem explicações adicionais.\n";
    }

    private static function default_title_rss_prompt(): string
    {
        return
            "Crie um título otimizado e original baseado no RSS fornecido.\n\n"
            . "REGRAS:\n\n"
            . "ORIGINALIDADE (OBRIGATÓRIA):\n"
            . "- NUNCA copie o título original\n"
            . "- Reescreva completamente com suas próprias palavras\n"
            . "- Mude a estrutura e ângulo\n"
            . "- Mantenha o tema/assunto, mas com abordagem diferente\n\n"

            . "OTIMIZAÇÃO:\n"
            . "- Máximo 60-70 caracteres\n"
            . "- Capitalização: só primeira palavra + nomes próprios\n"
            . "- Tom jornalístico e direto\n\n"

            . "EXEMPLO:\n"
            . "RSS: \"10 melhores filmes de ação de 2024\"\n"
            . "❌ ERRADO: \"Os 10 melhores filmes de ação de 2024\"\n"
            . "✅ CORRETO: \"7 filmes de ação brutais que chegaram em 2024\"\n"
            . "✅ CORRETO: \"Filmes de ação de 2024 que ninguém esperava\"\n\n";
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
        return
            "OBJETIVO:\n"
            . "Criar um ESBOÇO (outline) estratégico que maximize E-E-A-T e atenda à intenção de busca do usuário.\n\n"

            . "ANÁLISE DE INTENÇÃO DE BUSCA (FUNIL):\n"
            . "Identifique automaticamente a posição da palavra-chave no funil de busca:\n\n"

            . "TOPO DE FUNIL (Conscientização - descoberta do problema):\n"
            . "- Usuário está explorando um tema ou descobrindo um problema\n"
            . "- Foco: educação, contexto amplo, conceitos básicos, panorama geral\n"
            . "- Estrutura ideal: O que é? Por que importa? Como funciona? Tipos/categorias. Exemplos práticos\n"
            . "- Palavras-chave típicas: 'o que é...', 'como funciona...', 'tipos de...', 'benefícios de...'\n"
            . "- Exemplo: 'o que é marketing digital' → seções sobre definição, importância, canais, benefícios\n\n"

            . "MEIO DE FUNIL (Consideração - avaliação de soluções):\n"
            . "- Usuário conhece o problema e está comparando alternativas\n"
            . "- Foco: comparações, prós e contras, critérios de escolha, cases, alternativas\n"
            . "- Estrutura ideal: Opções disponíveis. Comparação detalhada. Quando usar cada uma. Casos de uso\n"
            . "- Palavras-chave típicas: 'melhores...', 'vs', 'comparação', 'qual escolher...', 'alternativas para...'\n"
            . "- Exemplo: 'WordPress vs Wix' → seções sobre facilidade, custos, recursos, quando escolher cada um\n\n"

            . "FUNDO DE FUNIL (Decisão - pronto para agir):\n"
            . "- Usuário decidido, precisa de instruções específicas para executar\n"
            . "- Foco: tutoriais passo a passo, guias práticos, requisitos técnicos, troubleshooting\n"
            . "- Estrutura ideal: Pré-requisitos. Passo a passo detalhado. Configurações. Erros comuns. Próximos passos\n"
            . "- Palavras-chave típicas: 'como fazer...', 'tutorial...', 'passo a passo...', 'instalar...', 'configurar...'\n"
            . "- Exemplo: 'como instalar WordPress' → seções sobre requisitos, instalação, configuração, primeiros passos\n\n"

            . "DIRETRIZES DO OUTLINE:\n"
            . "- H2s ESTRATÉGICOS: Títulos devem responder diretamente às necessidades do usuário conforme posição no funil\n"
            . "- PRIMEIRA SEÇÃO DE VALOR: Primeiro H2 deve entregar insights imediatos (não use 'Introdução' genérica)\n"
            . "  • Para topo: 'O que é [tema] e por que você deveria conhecer'\n"
            . "  • Para meio: 'Principais critérios para escolher [solução]'\n"
            . "  • Para fundo: 'O que você precisa antes de começar'\n"
            . "- PROFUNDIDADE (H3): Adicione subtópicos que detalham 'como fazer' ou 'por que funciona'\n"
            . "- ORGANIZAÇÃO: Use bullets nas instruções para dados, passos, requisitos, listas\n"
            . "- PROGRESSÃO LÓGICA: Seções devem fluir naturalmente conforme jornada do usuário\n\n"

            . "REGRAS DE CAPITALIZAÇÃO:\n"
            . "- Use APENAS primeira palavra maiúscula + nomes próprios (padrão jornalístico)\n"
            . "- ❌ ERRADO: 'Como Criar Um Site Profissional Em 2026'\n"
            . "- ✅ CORRETO: 'Como criar um site profissional em 2026'\n"
            . "- ✅ CORRETO: 'Melhores práticas de SEO no Google'\n"
            . "- Exceções: siglas (SEO, CRM, API), produtos (WordPress, Netflix, Shopify), nomes próprios, meses\n\n"

            . "QUALIDADE EDITORIAL:\n"
            . "- Esboço deve parecer criado por especialista humano no assunto\n"
            . "- H2s devem ser distintos e complementares (zero repetição de ângulos)\n"
            . "- Inclua H3 apenas quando necessário aprofundar um H2 complexo\n"
            . "- Bullets devem ter pontos concretos: quem, o quê, quando, onde, como, por quê, exemplos, dados\n"
            . "- Evite clichês: 'Guia Completo', 'Tudo Sobre', 'O Melhor', 'Definitivo'\n"
            . "- Cada seção deve ter instruções AUTO-SUFICIENTES (sem referências a outras seções)\n\n"

            . "IMPORTANTE - INSTRUÇÕES ESPECÍFICAS:\n"
            . "- Lembre-se: cada seção será escrita ISOLADAMENTE sem contexto das outras\n"
            . "- Seja EXPLÍCITO sobre o que deve ser incluído em cada seção\n"
            . "- Se pedir tabela: especifique TODAS as colunas/itens/critérios\n"
            . "- Se pedir lista: especifique TODOS os elementos\n"
            . "- Nunca use referências vagas como 'conforme mencionado', 'itens acima', 'critérios selecionados'\n";
    }

    private static function default_outline_rss_prompt(): string
    {
        return
            "DIRETRIZES EDITORIAIS:\n"
            . "- Não copiar estrutura ou frases da fonte.\n"
            . "- Reorganizar os fatos para criar fluxo lógico diferente.\n"
            . "- Não incluir opinião ou especulação.\n"
            . "- Cada bullet deve conter 1 fato verificável.\n"
            . "- Não repetir informações com palavras diferentes.\n"
            . "- Pra essa sessão, mais precisamente, vamos gerar no máximo 2 sessões (2 H2), sem h3 ou filhos, mas há uma excessão dessa regra: Se o título falar sobre quantidade de itens, então é sim necessário criar as sessões para desenrolar do conteudo e pode ter o limite máximo de H2 e pode ter h3 também.\n\n"

            . "PROIBIDO:\n"
            . "- Bullets genéricos sem dados concretos.\n"
            . "- Contexto filosófico ou análise ampla.\n"
            . "- Contexto filosófico ou análise ampla.\n"
            . "- Repetições estruturais.\n\n"

            . "Se não houver dados suficientes, produza apenas os fatos encontrados.\n\n"
            . "Responda SOMENTE com JSON válido.";
    }



    private static function default_section_base_prompt(): string
    {
        return
            "IDENTIDADE E TOM:\n"
            . "Você é um Especialista Sênior de Conteúdo (E-E-A-T) com anos de experiência prática no assunto.\n"
            . "Escreva SEMPRE em PRIMEIRA PESSOA, compartilhando suas experiências, testes e recomendações.\n\n"

            . "ESTILO DE ESCRITA:\n"
            . "- Vá direto ao ponto: explique o 'como' e o 'porquê' com clareza\n"
            . "- Use tom pessoal e autoral: 'Eu testei...', 'Na minha experiência...', 'Prefiro X porque...'\n"
            . "- Demonstre autoridade através de comparações técnicas e opiniões fundamentadas\n"
            . "- Parágrafos curtos: 2-3 frases no máximo (otimizado para mobile)\n"
            . "- Use <strong> apenas para destacar conceitos essenciais (sem exagero)\n\n"

            . "EVITE AI FLUFF (frases vazias de IA):\n"
            . "❌ NÃO use: 'Um X confiável pode fazer toda a diferença'\n"
            . "❌ NÃO use: 'É importante escolher a ferramenta certa'\n"
            . "❌ NÃO use: 'Existem várias opções disponíveis'\n"
            . "✅ USE: Termos técnicos específicos, critérios objetivos, comparações práticas\n"
            . "✅ USE: 'Testei 12 ferramentas e o X se destacou por Y'\n"
            . "✅ USE: 'Prefiro A em vez de B porque...' (com razão técnica)\n\n"

            . "DEMONSTRAÇÃO DE AUTORIDADE:\n"
            . "- Mencione ferramentas específicas que você usa (incluindo as menos conhecidas)\n"
            . "- Faça comparações práticas entre opções ('X vs Y: prefiro X porque...')\n"
            . "- Compartilhe insights de testes reais ('Após testar por 3 meses...')\n"
            . "- Dê sua opinião pessoal fundamentada sobre cada solução\n"
            . "- Cite critérios técnicos objetivos (velocidade, custo, facilidade, suporte)\n\n"

            . "CTAs E LINKS EXTERNOS:\n"
            . "- Inclua links para ferramentas mencionadas de forma NATURAL no texto\n"
            . "- Use: <a href=\"URL\" target=\"_blank\" rel=\"noopener\">nome da ferramenta</a>\n"
            . "- Integre o link no fluxo do texto, não force\n"
            . "- Exemplo: 'Uso o <a href=\"...\" target=\"_blank\" rel=\"noopener\">SEMrush</a> para análise de concorrentes porque...'\n\n"

            . "DADOS E ESTATÍSTICAS:\n"
            . "- NÃO invente estatísticas ou números\n"
            . "- Se não tiver dados concretos, use linguagem prudente: 'Na minha experiência...', 'Percebi que...'\n"
            . "- Prefira evidências qualitativas (seus testes) a quantitativas inventadas\n\n"

            . "CONTEÚDO DENSO E PROFUNDO:\n"
            . "- Desenvolva cada ponto com exemplos práticos e contexto real\n"
            . "- Explique o 'porquê' por trás de cada recomendação\n"
            . "- Inclua critérios de decisão e trade-offs\n"
            . "- Antecipe dúvidas e objeções do leitor\n\n"

            . "BRIEF E SUBTÍTULOS:\n"
            . "Use o brief fornecido como base e os subtítulos sugeridos para estruturar o conteúdo:\n\n"
            . "{{section_bullets}}\n\n"
            . "{{section_children}}\n";
    }

    private static function default_section_rss_prompt(): string
    {
        return
            "Você está MODELANDO conteúdo baseado em RSS fornecido.\n\n"

            . "LINKS EXTERNOS OBRIGATÓRIOS:\n"
            . "- SEMPRE adicione links quando mencionar:\n"
            . "  • Streamings: Netflix, Prime Video, Disney+, Max, Apple TV+, Paramount+, etc\n"
            . "  • Empresas citadas: Warner, Universal, Sony, etc\n"
            . "  • Sites oficiais mencionados\n"
            . "- Formato HTML obrigatório: <a href=\"URL\" target=\"_blank\" rel=\"noopener\">Nome</a>\n"
            . "- NUNCA use formato Markdown [texto](url)\n"
            . "- Exemplo correto: <a href=\"https://www.netflix.com\" target=\"_blank\" rel=\"noopener\">Netflix</a>\n\n"

            . "URLs PADRÃO (use estes):\n"
            . "- Netflix: https://www.netflix.com\n"
            . "- Prime Video: https://www.primevideo.com\n"
            . "- Disney+: https://www.disneyplus.com\n"
            . "- Max: https://www.max.com\n"
            . "- Apple TV+: https://tv.apple.com\n"
            . "- Paramount+: https://www.paramountplus.com\n\n"

            . "ANTI-PLÁGIO (OBRIGATÓRIO):\n"
            . "- NUNCA copie estrutura de frases\n"
            . "- Mude completamente ordem das informações\n"
            . "- Use vocabulário totalmente diferente\n"
            . "- Adicione tom autoral em primeira pessoa plural\n\n"

            . "TOM:\n"
            . "- Primeira pessoa plural: 'Vimos', 'Notamos', 'Descobrimos'\n"
            . "- Natural e conversacional\n"
            . "- Parágrafos: máximo 2-4 linhas (2-3 frases)\n\n"

            . "FORMATAÇÃO HTML:\n"
            . "- Use <p>, <strong>, <a>\n"
            . "- Links: <a href=\"...\" target=\"_blank\" rel=\"noopener\">...</a>\n"
            . "- NUNCA use Markdown\n\n"

            . "E-E-A-T - FATOS VERIFICÁVEIS:\n"
            . "- Sempre inclua datas exatas quando disponíveis\n"
            . "- Mencione fonte oficial: 'Segundo site oficial da [empresa]'\n"
            . "- Use dados concretos: valores, números, prazos\n"
            . "- Exemplo: 'No dia 15 de fevereiro, a Warner anunciou no site oficial...'\n\n"

            . "PROIBIDO:\n"
            . "- Copiar estrutura de frases\n"
            . "- Links em Markdown\n"
            . "- Inventar fatos não presentes no RSS\n"
            . "- Mencionar fonte do RSS (ex: 'Adoro Cinema publicou')\n"
            . "- Usar emojis\n"
            . "- Criar listas de qualquer tipo, sem que seja passado pelo paragrafo ou bullets\n"
            . "- Parágrafos longos (máx 3 frases)\n\n"

            . "PROCESSO:\n"
            . "1. Extraia FATOS do RSS\n"
            . "2. Reescreva com vocabulário diferente\n"
            . "3. Mude ordem das informações\n"
            . "4. Adicione links HTML para streamings/empresas\n"
            . "5. Insira datas e dados verificáveis\n";
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
        return "- Cena única marcante, proporção 16:9.\n"
            . "- Sem texto, sem marcas d’água, sem logos.\n";
    }

    private static function default_post_thumbnail_regen_prompt(): string
    {
        return "Ultra-realistic natural photo, 16:9 aspect ratio.\n"
            . "Avoid text, watermarks, clutter.\n"
            . "- FOTO REALISTA HORIZONTAL 16:9, luz natural, estilo cinematográfico.\n"
            . "- Sem texto, sem logos, sem marca d'água.\n";
    }

    public static function story_default_template(): string
    {
        $s = "- Gere 7 a 10 páginas curtas.\n";
        $s .= "- Linguagem simples e envolvente.\n";
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
            . "      \"prompt\": \"Prompt para gerar uma imagem sobre o slide (sempre em inglês, independente do idioma pedido do conteudo)\"\n"
            . "    }\n"
            . "  ]\n"
            . "}\n";
    }
}
