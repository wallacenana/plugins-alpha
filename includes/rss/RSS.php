<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_RSS
{
    public static function render(): void
    {
        $opt = PluginsAlpha_Settings::get();
        $chk = PluginsAlpha_License::check('alpha_orion');
?>
        <div class="pga-wrap">
            <?php
            if (!$chk['ok']) {
                $url = admin_url('admin.php?page=plugins-alpha-dashboard');

                echo '<div class="notice notice-error is-dismissible"><p>'
                    . esc_html__('Módulo não ativado.', 'plugins-alpha')
                    . ' <a href="' . esc_url($url) . '">'
                    . esc_html__('Clique aqui para ativar', 'plugins-alpha')
                    . '</a></p></div>';
            }

            $tpls = (array) get_option('pga_orion_templates', []);
            $default_tpls = [];

            foreach ($tpls as $slug => $row) {
                $slug = sanitize_key((string) $slug);
                if (!$slug) continue;

                $enabled = !empty($row['enabled']);
                $is_default = !empty($row['is_default']);

                if ($enabled && $is_default) {
                    $default_tpls[] = $slug;
                }
            }

            // fallback: se user não marcou nada, evita “novo projeto vazio”
            if (!$default_tpls) {
                $default_tpls = ['article'];
            }
            ?>
            <div class="wrap pga-layout">
                <div class="pga-header-fixed">
                    <div class="pga-header-col pga-a-center">
                        <div>
                            <h1><?php esc_html_e('Gerador RSS', 'plugins-alpha'); ?></h1>
                            <p class="pga-descricao"><?php esc_html_e('Criação de artigos com base em RSS', 'plugins-alpha'); ?></p>
                        </div>
                    </div>
                    <div class="pga-header-col pga-a-center ">
                        <button
                            title="<?php esc_html_e('Salvar palavras-chave', 'plugins-alpha'); ?>"
                            type="button"
                            class="pga_save_box"
                            id="pga_save_keywords">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2">
                                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                            </svg>
                        </button>
                        <?php
                        $label = esc_html__('Planejar & Gerar', 'plugins-alpha');

                        echo $chk['ok']
                            ? '<button type="button" id="pga_plan"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-4 w-4 mr-2"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"></path><path d="M20 3v4"></path><path d="M22 5h-4"></path><path d="M4 17v2"></path><path d="M5 18H3"></path></svg> ' . $label . '</button>'
                            : '<button type="button" id="pga_plan" disabled> <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-4 w-4 mr-2"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"></path><path d="M20 3v4"></path><path d="M22 5h-4"></path><path d="M4 17v2"></path><path d="M5 18H3"></path></svg>' . $label . '</button>';
                        ?>
                    </div>
                </div>
                <div class="pga-main">

                <b>Breve…</b>
                    <!-- Tabs -->
                    
                </div>
            </div>
        </div>
        <div class="pga-done-dropup">
            <button
                type="button"
                id="pga_done_toggle"
                class="button pga-floating-btn pga-icon-btn"
                aria-expanded="false"
                aria-controls="pga_done_panel"
                data-tooltip="Ver frases já geradas">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
                    <path d="M382-240 154-468l57-57 171 171 367-367 57 57-424 424Z" />
                </svg>
            </button>

            <div
                id="pga_done_panel"
                class="pga-card pga-done-panel"
                aria-hidden="true">
                <div class="pga-row">
                    <h2>Concluídas</h2>
                    <button
                        type="button"
                        id="pga_kw_clear_done"
                        class="pga-icon-btn pga-btn-delete"
                        data-tooltip="Limpar frases geradas">
                        <span class="pga-icon">🗑️</span>
                    </button>
                </div>
                <ul id="pga_kw_done" class="pga-list done"></ul>
            </div>
        </div>
<?php
    }

    /**
     * $args:
     *  - keywords[]  (usa a 1ª como foco)
     *  - locale      (pt_BR|en_US...)
     *  - publish_time  (timestamp futuro)
     *  - category_id   (int)
     */


    public static function create_draft_and_outline(array $args)
    {
        // 0) item único (primeira linha) 
        $kwSrc = $args['keyword'] ?? $args['keywords'] ?? '';
        if (is_array($kwSrc)) {
            $raw = trim((string)($kwSrc[0] ?? ''));
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string)$kwSrc);
            $raw = trim((string)($lines[0] ?? ''));
        }
        if ($raw === '') {
            return new WP_Error('pga_no_kw', 'Item (linha 1) vazio.');
        }

        // 1) parâmetros básicos (sem if por template) 
        $template = $args['template'] ?? $args['template_key'] ?? 'article';
        $length = $args['length'] ?? 'short';
        $locale = $args['locale'] ?? 'pt_BR';
        $provider = $args['provider'] ?? (class_exists('PluginsAlpha_AI') ? PluginsAlpha_AI::get_text_provider() : '');
        $jobArgs = ['provider' => $provider, 'template' => $template, 'length' => $length, 'locale' => $locale,];

        // 2) publish_time: NÃO calcula, só recebe e repassa (timestamp ou string) 
        $publish_ts = 0;
        if (!empty($args['publish_time'])) {
            $publish_ts = is_numeric($args['publish_time']) ? (int)$args['publish_time'] : (int)strtotime((string)$args['publish_time']);
        }
        $category_id = (int)($args['category_id'] ?? 0);
        $post_type = !empty($args['post_type']) ? sanitize_key((string)$args['post_type']) : 'posts_orion';

        // SE LICENÇA FOR VITALÍCIA → força post normal
        $lic = class_exists('PluginsAlpha_License') ? PluginsAlpha_License::check('alpha_orion') : ['ok' => false];
        $is_lifetime = !empty($lic['lifetime']) || (!empty($lic['plan']) && $lic['plan'] === 'lifetime');

        if ($is_lifetime && post_type_exists('post')) {
            $post_type = 'post';
        }

        // 3) contexto neutro: keyword = raw; url = raw se for URL 
        $keyword = $raw;
        $url = filter_var($raw, FILTER_VALIDATE_URL) ? $raw : '';

        // 4) slug base (temporário; depois atualiza com o título final) 
        $slug = sanitize_title($keyword);
        if ($slug === '') {
            $slug = sanitize_title(uniqid('orion_', true));
        }

        // 5) fallback de post_type (mantém teu comportamento) 
        if (!post_type_exists($post_type)) {
            if (post_type_exists('posts_orion')) {
                $post_type = 'posts_orion';
            } elseif (post_type_exists('post_orion')) {
                $post_type = 'post_orion';
            } else {
                $post_type = 'post';
            }
        } // 6) cria draft 
        $postarr = ['post_type' => $post_type, 'post_status' => 'draft', 'post_title' => '(Gerando) ' . $keyword, 'post_name' => $slug, 'post_content' => '', 'post_author' => get_current_user_id(),]; // só aplica se vier publish_time (sem “ajustes”) 
        if ($publish_ts > 0) {
            $postarr['post_date'] = date('Y-m-d H:i:s', $publish_ts);
            $postarr['post_date_gmt'] = gmdate('Y-m-d H:i:s', $publish_ts);
        }
        $draft_id = wp_insert_post($postarr, true);
        if (is_wp_error($draft_id)) {
            return $draft_id;
        }
        $draft_id = (int)$draft_id; // metas base 
        if ($publish_ts > 0) {
            update_post_meta($draft_id, '_pga_publish_ts', $publish_ts);
        }
        update_post_meta($draft_id, '_pga_job_started', time());
        if ($category_id > 0) {
            wp_set_post_terms($draft_id, [$category_id], 'category', false);
            update_post_meta($draft_id, '_pga_orion_category_ids', [$category_id]);
        }

        // --- TAGS (salva contexto do job) ------------------------
        if (!empty($args['tags']) && is_array($args['tags'])) {
            $clean = [];

            foreach ($args['tags'] as $t) {
                $t = trim((string)$t);
                if ($t !== '') {
                    $clean[] = $t;
                }
            }

            if ($clean) {
                update_post_meta($draft_id, '_pga_job_tags', $clean);
            }
        }

        if ($template === 'modelar_youtube') {
            $yt = PluginsAlpha_Youtube::fetch_video_data($url);
            if (is_wp_error($yt)) return $yt;

            // Aqui "keyword" pode ser:
            // - o próprio $keyword (se você usa URL no campo)
            // - OU um assunto derivado
            // - OU simplesmente $yt['title'] (muita gente prefere isso)
            $topic = $keyword ?: ($yt['title'] ?? '');

            $titlePrompt = PluginsAlpha_Prompts::build_title_prompt_modelar_youtube(
                $yt,
                $topic,
                3,
                5,
                $locale
            );
        } else {
            $titlePrompt = PluginsAlpha_Prompts::build_title_prompt(
                $template,
                $keyword,
                3,
                5,
                $locale,
                $url
            );
        }

        $titles = PluginsAlpha_AI::titles($titlePrompt, $jobArgs);
        if (is_wp_error($titles)) {
            return self::fail_job($draft_id, $titles, 'titles');
        }
        $chosenTitle = self::pick_best_title((array)$titles, $keyword);
        if (!$chosenTitle) {
            $chosenTitle = ucfirst($keyword);
        }

        // atualiza draft title com o título escolhido (importa pro WP/SEO) 
        wp_update_post(['ID' => $draft_id, 'post_title' => '(Gerando) ' . $chosenTitle,]);

        $promptSlug = PluginsAlpha_Prompts::build_slug_prompt(
            (string)$template,
            (string)$keyword,
            (string)$chosenTitle,
            (string)$locale
        );

        // chama endpoint dedicado (ou complete, se você não tiver meta_description)
        $respSlug = PluginsAlpha_AI::slug($promptSlug);
        // SLUG: aplica no post (update)
        if (!is_wp_error($respSlug)) {
            $slugTxt = '';

            if (is_string($respSlug)) {
                $slugTxt = $respSlug;
            } elseif (is_array($respSlug)) {
                $slugTxt = (string)($respSlug['slug'] ?? $respSlug['content'] ?? '');
            } elseif (is_object($respSlug)) {
                $slugTxt = (string)($respSlug->slug ?? $respSlug->content ?? '');
            }

            $slugTxt = trim(wp_strip_all_tags(html_entity_decode($slugTxt, ENT_QUOTES, 'UTF-8')));

            // se vier {"slug":"..."} como texto
            if ($slugTxt !== '' && $slugTxt[0] === '{') {
                $j = json_decode($slugTxt, true);
                if (is_array($j)) {
                    $slugTxt = trim((string)($j['slug'] ?? $j['content'] ?? $slugTxt));
                }
            }

            // se vier com prefixo tipo "slug: ..."
            $slugTxt = preg_replace('/^\s*(slug|post_name)\s*:\s*/i', '', $slugTxt);

            // pega só a primeira linha
            $slugTxt = preg_split("/\r\n|\r|\n/", $slugTxt)[0] ?? $slugTxt;
            $slugTxt = trim($slugTxt);

            // sanitiza e fallback
            $newSlug = sanitize_title($slugTxt);
            if ($newSlug === '') {
                $newSlug = sanitize_title($chosenTitle);
            }
            if ($newSlug === '') {
                $newSlug = sanitize_title($keyword);
            }
            if ($newSlug === '') {
                $newSlug = sanitize_title(uniqid('orion_', false));
            }

            // garante unicidade pro post_type atual
            $newSlug = wp_unique_post_slug($newSlug, $draft_id, 'draft', $post_type, 0);

            // atualiza post_name
            wp_update_post([
                'ID'       => $draft_id,
                'post_name' => $newSlug,
            ]);

            update_post_meta($draft_id, '_pga_generated_slug', $newSlug);
        }


        // jobArgs úteis pro provider/prompt 
        $jobArgs['keyword'] = $keyword;
        $jobArgs['url'] = $url;
        $jobArgs['chosen_title'] = $chosenTitle; // salva base do job 
        update_post_meta($draft_id, '_pga_outline_length', $length);
        update_post_meta($draft_id, '_pga_outline_locale', $locale);
        update_post_meta($draft_id, '_pga_outline_keyword', $keyword);
        update_post_meta($draft_id, '_pga_outline_template', $template);
        update_post_meta($draft_id, '_pga_outline_url', $url);
        update_post_meta($draft_id, '_pga_chosen_title', $chosenTitle);

        // 8) OUTLINE (prompt resolve via template) 
        if ($template === 'modelar_youtube') {
            $yt = PluginsAlpha_Youtube::fetch_video_data($url);
            if (is_wp_error($yt)) return $yt; // ou trate como você trata erros no endpoint

            $outlinePrompt = PluginsAlpha_Prompts::build_outline_prompt_modelar_youtube(
                $url,
                $yt,
                $chosenTitle,
                $length,
                $locale
            );
        } else {
            // fluxo normal
            $outlinePrompt = PluginsAlpha_Prompts::build_outline_prompt($template, $keyword, $chosenTitle, $length, $locale, $url);
        }

        $outline = PluginsAlpha_AI::outline($outlinePrompt, $jobArgs);

        if (is_wp_error($outline)) {
            return self::fail_job($draft_id, $outline, 'outline');
        }

        // Se vier { "sections": [...] }, pega só o array interno 
        $sections = $outline['sections'] ?? $outline;
        if (!is_array($sections)) {
            $sections = [];
        }

        // 9) NORMALIZA ids (mantém teu padrão) 
        $normalized = [];
        $h2Index = 1;
        foreach ($sections as $sec) {
            if (!is_array($sec)) {
                $sec = ['heading' => (string)$sec, 'level' => 'h2',];
            }
            if (empty($sec['level'])) {
                $sec['level'] = 'h2';
            }
            if (empty($sec['id'])) {
                $sec['id'] = (string)$h2Index;
            }
            if (!empty($sec['children']) && is_array($sec['children'])) {
                $childIndex = 1;
                foreach ($sec['children'] as $ci => $child) {
                    if (!is_array($child)) {
                        $child = ['heading' => (string)$child, 'level' => 'h3',];
                    }
                    if (empty($child['level'])) {
                        $child['level'] = 'h3';
                    }
                    if (empty($child['id'])) {
                        $child['id'] = $sec['id'] . '.' . $childIndex;
                    }
                    $sec['children'][$ci] = $child;
                    $childIndex++;
                }
            }
            $normalized[] = $sec;
            $h2Index++;
        }
        update_post_meta($draft_id, '_pga_outline_sections', wp_json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        update_post_meta($draft_id, '_pga_job_status', 'outline_done');
        return ['post_id' => $draft_id, 'title' => $chosenTitle, 'sections' => $normalized, 'length' => $length, 'locale' => $locale, 'post_type' => $post_type,];
    }

    private static function pick_best_title(array $cands, string $kw): string
    {
        $cands = array_values(array_filter(array_map('trim', $cands)));
        if (!$cands) return '';
        usort($cands, function ($a, $b) use ($kw) {
            $score = function ($t) use ($kw) {
                $s = 0;
                if (stripos($t, $kw) !== false) $s += 2;      // contém keyword
                if (preg_match('/\b\d+\b/', $t)) $s += 1;    // tem número
                if (mb_strlen($t) <= 60) $s += 1;           // curto (Discover)
                if (stripos($t, 'guia completo') !== false) $s -= 2; // evita esse padrão
                return $s;
            };
            return $score($b) <=> $score($a);
        });
        return $cands[0];
    }

    private static function fail_job($post_id, WP_Error $err)
    {
        $data = $err->get_error_data() ?: [];

        wp_update_post([
            'ID'          => $post_id,
            'post_status' => 'draft',
            'post_title'  => '(Falhou) ' . get_the_title($post_id),
        ]);

        update_post_meta($post_id, '_pga_last_error', [
            'code'    => $err->get_error_code(),
            'message' => $err->get_error_message(),
            'data'    => $data,
            'time'    => time(),
        ]);

        return $err;
    }
}
