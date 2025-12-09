<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_REST
{


    /**
     * Normaliza array de strings: trim, remove vazios e duplicados (case-insensitive)
     */
    private static function unique_clean(array $arr): array
    {
        $arr = array_map('trim', $arr);

        $arr = array_filter($arr, function ($s) {
            return $s !== '';
        });

        $lower = function ($s) {
            return function_exists('mb_strtolower')
                ? mb_strtolower($s, 'UTF-8')
                : strtolower($s);
        };

        $seen = array();
        $out  = array();

        foreach ($arr as $s) {
            $k = $lower($s);
            if (!isset($seen[$k])) {
                $seen[$k] = 1;
                $out[]    = $s;
            }
        }

        return array_values($out);
    }

    /**
     * Salva PENDENTES como string "a\nb\nc"
     */
    private static function kw_set_pending(array $a): void
    {
        $clean = self::unique_clean($a);
        update_option('pga_kw_pending', implode("\n", $clean), false);
    }

    /**
     * Salva CONCLUÍDAS como string "a\nb\nc"
     */
    private static function kw_set_done(array $a): void
    {
        $clean = self::unique_clean($a);
        update_option('pga_kw_done', implode("\n", $clean), false);
    }

    /**
     * Limpa pendentes
     */
    private static function kw_clear_pending(): void
    {
        update_option('pga_kw_pending', '', false);
    }

    /**
     * Limpa concluídas
     */
    private static function kw_clear_done(): void
    {
        update_option('pga_kw_done', '', false);
    }

    /**
     * Lê pendentes como ARRAY
     */
    protected static function kw_get_pending(): array
    {
        $raw = (string) get_option('pga_kw_pending', '');
        if ($raw === '') return array();

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function ($s) {
            return $s !== '';
        });

        return array_values($lines);
    }

    /**
     * Lê concluídas como ARRAY
     */
    protected static function kw_get_done(): array
    {
        $raw = (string) get_option('pga_kw_done', '');
        if ($raw === '') return array();

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, function ($s) {
            return $s !== '';
        });

        return array_values($lines);
    }

    /**
     * Move UMA keyword de pending -> done
     */
    protected static function kw_move_to_done_one(string $kw): void
    {
        $kw = trim($kw);
        if ($kw === '') return;

        $pending = self::kw_get_pending();
        $done    = self::kw_get_done();

        // remove da pending (case-insensitive)
        $pending = array_values(array_filter($pending, function ($item) use ($kw) {
            return mb_strtolower($item) !== mb_strtolower($kw);
        }));

        // adiciona em done se ainda não tiver
        $exists = false;
        foreach ($done as $d) {
            if (mb_strtolower($d) === mb_strtolower($kw)) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $done[] = $kw;
        }

        // grava de volta usando os helpers
        self::kw_set_pending($pending);
        self::kw_set_done($done);
    }

    // ---------------------- utils ----------------------
    private static function verify_nonce($req)
    {
        $n = $req->get_header('X-WP-Nonce');
        if (!$n || !wp_verify_nonce($n, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Nonce inválido ou ausente.', ['status' => 403]);
        }
        return true;
    }
    private static function clean($s)
    {
        return sanitize_text_field((string)$s);
    }

    private static function lines_to_array($txt)
    {
        if (!is_string($txt) || $txt === '') return array();

        $lines = preg_split('/\r\n|\r|\n/', $txt);
        $lines = array_map('trim', $lines);

        // sem arrow function
        $lines = array_filter($lines, function ($s) {
            return $s !== '';
        });

        return self::unique_clean($lines);
    }

    private static function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return new WP_Error('pga_exception', 'Exceção interna.', ['status' => 500]);
        }
    }

    // ---------------------- rotas ----------------------
    public static function register_routes()
    {
        register_rest_route('pga/v1', '/orion/outline', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_outline'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // 2) Gera UMA seção do esboço
        register_rest_route('pga/v1', '/orion/section', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_section'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // 3) Junta tudo e finaliza o post
        register_rest_route('pga/v1', '/orion/finalize', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_finalize'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
        
        // 4) Gera/aplica apenas a imagem destacada do post
        register_rest_route('pga/v1', '/orion/image', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_image'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);


        register_rest_route('pga/v1', '/license/activate', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => [__CLASS__, 'activate'],
        ]);

        register_rest_route('pga/v1', '/license/status', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => [__CLASS__, 'status'],
        ]);

        register_rest_route('pga/v1', '/plan', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'plan'],
        ]);

        register_rest_route('pga/v1', '/status', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'status_licence'],
        ]);

        register_rest_route('pga/v1', '/keywords', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'keywords_get'],
        ]);

        register_rest_route('pga/v1', '/keywords', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'keywords_save'],
        ]);

        register_rest_route('pga/v1', '/keywords/clear', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'keywords_clear'],
        ]);

        // --- self test da OpenAI (para tela de Configurações)
        register_rest_route('pga/v1', '/selftest', [
            'methods'  => ['GET', 'POST'], // mantém GET por compat, mas vamos usar POST
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => [__CLASS__, 'selftest'],
        ]);
    }
    public static function permission()
    {
        return current_user_can('edit_posts');
    }
    
    /**
     * POST /wp-json/pga/v1/orion/image
     * Body: { post_id: 123, keyword?: "...", locale?: "...", template?: "..." }
     */
    public static function handle_image(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
    
        $params = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params();
        }
    
        $post_id = intval($params['post_id'] ?? 0);
        if (!$post_id || get_post_type($post_id) === null) {
            return new WP_Error(
                'pga_bad_request',
                __('post_id inválido.', 'plugins-alpha'),
                ['status' => 400]
            );
        }
    
        // keyword / locale / template com fallbacks
        $keyword  = trim((string)($params['keyword'] ?? ''));
        if ($keyword === '') {
            $keyword = (string) get_post_meta($post_id, '_pga_outline_keyword', true);
        }
    
        $locale = (string)($params['locale'] ?? '');
        if ($locale === '') {
            $locale = get_post_meta($post_id, '_pga_outline_locale', true) ?: 'pt_BR';
        }
    
        $template = (string)($params['template'] ?? '');
        if ($template === '') {
            $template = get_post_meta($post_id, '_pga_outline_template', true) ?: 'discover_article';
        }
    
        $title = get_post_meta($post_id, '_pga_chosen_title', true);
        if ($title === '') {
            $title = get_the_title($post_id) ?: $keyword;
        }
    
        $image_alt = get_post_meta($post_id, '_pga_image_alt', true);
        if ($image_alt === '') {
            $image_alt = $title;
        }
    
        if ($keyword === '' || $title === '') {
            return new WP_Error(
                'pga_img_missing_data',
                __('Dados insuficientes para gerar imagem.', 'plugins-alpha'),
                ['status' => 400]
            );
        }
    
        // 1) META-PROMPT vindo do Prompts.php
        if (!class_exists('PluginsAlpha_Prompts')) {
            return new WP_Error('pga_prompts_missing', 'Classe de prompts não encontrada.', ['status' => 500]);
        }
    
        $meta_img_prompt = PluginsAlpha_Prompts::build_image_prompt(
            $keyword,
            $title,
            $locale,
            $template
        );
    
        $img_prompt = '';
    
        if ($meta_img_prompt && class_exists('PluginsAlpha_OpenAI')) {
            $resolved = PluginsAlpha_OpenAI::image_prompt($meta_img_prompt);
            if (!is_wp_error($resolved)) {
                $img_prompt = trim((string) $resolved);
            }
        }
    
        if ($img_prompt === '') {
            $img_prompt = $meta_img_prompt;
        }
    
        if ($img_prompt === '') {
            return new WP_Error(
                'pga_img_prompt_empty',
                __('Não foi possível montar o prompt de imagem.', 'plugins-alpha'),
                ['status' => 500]
            );
        }
    
        if (!class_exists('PluginsAlpha_Images')) {
            return new WP_Error('pga_images_missing', 'Classe de imagens não encontrada.', ['status' => 500]);
        }
    
        $thumb_id = PluginsAlpha_Images::generate_by_settings(
            $img_prompt,
            $post_id,
            $image_alt
        );
    
        if (is_wp_error($thumb_id) || !$thumb_id) {
            return new WP_Error(
                'pga_img_fail',
                is_wp_error($thumb_id) ? $thumb_id->get_error_message() : 'Falha ao gerar imagem.',
                ['status' => 500]
            );
        }
    
        // define thumbnail do post
        set_post_thumbnail($post_id, $thumb_id);
    
        // guarda metadados pra referência
        update_post_meta($post_id, '_pga_image_prompt', $img_prompt);
    
        $opts     = class_exists('PluginsAlpha_Settings') ? PluginsAlpha_Settings::get() : [];
        $img_opts = $opts['apis']['images'] ?? [];
        $provider = $img_opts['provider'] ?? 'pollinations';
    
        update_post_meta($post_id, '_pga_image_provider', $provider);
        update_post_meta($post_id, '_pga_image_alt', $image_alt);
    
        return rest_ensure_response([
            'ok'        => true,
            'post_id'   => $post_id,
            'thumb_id'  => $thumb_id,
            'provider'  => $provider,
            'prompt'    => $img_prompt,
        ]);
    }

    /**
     * POST /wp-json/pga/v1/orion/outline
     * Body: { keywords: [...], length, template, locale, publish_time, category_id, post_type }
     */
    public static function handle_outline(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $params = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params(); // fallback pra form-urlencoded
        }

        // Gera rascunho + outline
        $res = PluginsAlpha_Pages_Generator::create_draft_and_outline($params);

        if (is_wp_error($res)) {
            return $res;
        }
        return rest_ensure_response($res);
    }

    /**
     * POST /wp-json/pga/v1/orion/section
     * Body: { post_id: 123, section_id: "1" }
     */
    public static function handle_section(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $params     = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params();
        }

        $post_id    = intval($params['post_id'] ?? 0);
        $section_id = (string)($params['section_id'] ?? '');

        if (!$post_id || $section_id === '') {
            return new WP_Error(
                'pga_bad_request',
                __('post_id ou section_id ausentes.', 'plugins-alpha'),
                ['status' => 400]
            );
        }

        $res = PluginsAlpha_Pages_Generator::generate_section_content($post_id, $section_id);
        if (is_wp_error($res)) {
            return $res;
        }

        return rest_ensure_response($res);
    }


    /**
     * Remove o primeiro <h2> do conteúdo e injeta bloco "Leia também"
     * com links internos (mesmo CPT + mesma categoria, quando existir).
     */
    private static function tweak_final_content_and_internal_links(int $post_id): void
    {
        if ($post_id <= 0) {
            return;
        }

        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            return;
        }

        $content = $post->post_content;

        // 1) Remove APENAS o primeiro <h2>...</h2>
        $new_content = preg_replace('/<h2\b[^>]*>.*?<\/h2>/is', '', $content, 1);
        if (!is_string($new_content) || $new_content === '') {
            $new_content = $content;
        }

        // 2) Monta bloco "Leia também" se houver posts relacionados
        //    (mesmo CPT, published, mesma categoria quando possível)
        $post_type = get_post_type($post_id) ?: 'post';

        $terms = wp_get_post_terms($post_id, 'category');
        $cat_ids = array();
        if (!is_wp_error($terms) && !empty($terms)) {
            $cat_ids = wp_list_pluck($terms, 'term_id');
        }

        $q_args = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'post__not_in'   => array($post_id),
            'no_found_rows'  => true,
        );

        if (!empty($cat_ids)) {
            $q_args['tax_query'] = array(
                array(
                    'taxonomy' => 'category',
                    'field'    => 'term_id',
                    'terms'    => $cat_ids,
                ),
            );
        }

        $related_html = '';

        // evita injetar duas vezes se já tiver o bloco no conteúdo
        if (strpos($new_content, 'pga-orion-related') === false) {
            $rel_q = new WP_Query($q_args);

            if ($rel_q->have_posts()) {
                $items = array();
                while ($rel_q->have_posts()) {
                    $rel_q->the_post();
                    $items[] = sprintf(
                        '<li><a href="%s" rel="internal">%s</a></li>',
                        esc_url(get_permalink()),
                        esc_html(get_the_title())
                    );
                }
                wp_reset_postdata();

                if (!empty($items)) {
                    $related_html  = "\n\n";
                    $related_html .= '<section class="pga-orion-related">';
                    $related_html .= '<h2>' . esc_html__('Leia também', 'plugins-alpha') . '</h2>';
                    $related_html .= '<ul>' . implode('', $items) . '</ul>';
                    $related_html .= '</section>';
                    $related_html .= "\n\n";
                }
            }
        }

        // 3) Junta tudo e salva de volta
        $final_content = $new_content . $related_html;

        // só atualiza se realmente mudou
        if ($final_content !== $post->post_content) {
            wp_update_post(array(
                'ID'           => $post_id,
                'post_content' => $final_content,
            ));
        }
    }
    /**
     * Aplica remoção do primeiro <h2> e insere links internos
     * com base em $options: ['mode' => ..., 'max' => int, 'manual_ids' => '...'].
     */
    /**
     * Remove o primeiro H2 do conteúdo e insere links internos
     * de forma distribuída (final / meio), evitando ficar logo abaixo de um H2.
     *
     * @param int   $post_id
     * @param array $internal_opts ['mode' => none|auto|manual|pillar, 'max' => int, 'manual_ids' => '1,2,3']
     */
    protected static function apply_internal_links_and_cleanup(int $post_id, array $internal_opts = []): void
    {
        $post_id = absint($post_id);
        if (!$post_id) return;

        $content = get_post_field('post_content', $post_id);
        if (!$content) return;

        // 1) Remove apenas o PRIMEIRO <h2>...</h2>
        $content = preg_replace('#<h2\b[^>]*>.*?</h2>#is', '', $content, 1);
        $content = preg_replace("/\n{3,}/", "\n\n", $content);

        $mode  = isset($internal_opts['mode']) ? sanitize_key($internal_opts['mode']) : 'none';
        $max   = max(0, (int)($internal_opts['max'] ?? 0));
        $manual_raw = (string)($internal_opts['manual_ids'] ?? '');

        // Se não tem links internos configurados → só salva o conteúdo sem H2
        if ($mode === 'none' || $max <= 0) {
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $content,
            ]);
            return;
        }

        // IDs manuais (se houver)
        $manual_ids = array_filter(array_map('absint', preg_split('/[,\s]+/', $manual_raw)));

        // 2) Resolve quais posts serão usados como alvo
        $targets = self::resolve_internal_link_targets($post_id, $mode, $manual_ids, $max);
        if (empty($targets)) {
            // nada pra linkar, mas já salva sem o primeiro H2
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $content,
            ]);
            return;
        }

        // 3) Quebra o conteúdo em blocos (H2 / P / outros)
        $blocks  = [];
        $pattern = '#(<h2\b[^>]*>.*?</h2>|<p\b[^>]*>.*?</p>)#is';
        $offset  = 0;
        $len     = strlen($content);

        if (preg_match_all($pattern, $content, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $match) {
                $pos = (int)$match[1];
                $html = $match[0];

                if ($pos > $offset) {
                    $blocks[] = substr($content, $offset, $pos - $offset);
                }

                $blocks[] = $html;
                $offset   = $pos + strlen($html);
            }

            if ($offset < $len) {
                $blocks[] = substr($content, $offset);
            }
        } else {
            // fallback: não achou H2/P “bem formados”, não vamos enlouquecer
            $blocks[] = $content;
        }

        $countBlocks = count($blocks);

        // 4) Encontra índices candidatos para inserir CTA:
        //    - apenas parágrafos (<p>...</p>)
        //    - cujo bloco anterior NÃO é um <h2> (para não ficar logo abaixo de H2)
        $candidateIdx = [];
        for ($i = 0; $i < $countBlocks; $i++) {
            $b = $blocks[$i];

            if (stripos($b, '<p') !== false && stripos($b, '</p>') !== false) {
                $prevIsH2 = ($i > 0 && stripos($blocks[$i - 1], '<h2') !== false);
                if ($prevIsH2) {
                    continue;
                }
                $candidateIdx[] = $i;
            }
        }

        if (empty($candidateIdx)) {
            // sem parágrafos “bons” pra inserir CTA
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $content,
            ]);
            return;
        }

        // quantos CTAs vamos realmente inserir?
        $maxLinks = min($max, count($targets), count($candidateIdx));
        if ($maxLinks <= 0) {
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $content,
            ]);
            return;
        }

        // 5) Define posições-alvo "de baixo pra cima"
        //    - 1 link  -> ~95% do texto (quase final)
        //    - 2 links -> meio (~50%) e final (~90%)
        //    - 3 links -> ~1/3, ~2/3, ~93%
        $positions = [];
        $candCount = count($candidateIdx);

        for ($k = 0; $k < $maxLinks; $k++) {
            if ($maxLinks === 1) {
                $frac = 0.95;
            } elseif ($maxLinks === 2) {
                $frac = ($k === 0) ? 0.5 : 0.9;
            } elseif ($maxLinks === 3) {
                $fractions = [0.33, 0.66, 0.93];
                $frac = $fractions[$k];
            } else {
                // 4+ links: distribui mais linearmente
                $frac = ($k + 1) / ($maxLinks + 1); // 1/(n+1), 2/(n+1) ... → meio bem espalhado
            }

            $idxCand = (int) floor($frac * ($candCount - 1));
            $idxCand = max(0, min($candCount - 1, $idxCand));
            $targetBlockIdx = $candidateIdx[$idxCand];

            // evita duplicar exatamente o mesmo bloco (se der colisão, sobe uma posição)
            while (in_array($targetBlockIdx, $positions, true) && $targetBlockIdx > 0) {
                $targetBlockIdx--;
            }

            $positions[] = $targetBlockIdx;
        }

        // 6) Mapeia: bloco → CTAs a serem inseridos ANTES DELE
        //    Queremos: link mais importante (primeiro da lista) mais pro FINAL do post
        $inject = [];
        $positionsDesc = $positions;
        rsort($positionsDesc); // de baixo pra cima

        for ($i = 0; $i < $maxLinks; $i++) {
            $blockIndex = $positionsDesc[$i];
            $postTarget = $targets[$i]; // 0 = "mais importante", vai mais embaixo

            if (!($postTarget instanceof WP_Post)) continue;

            $ctaHtml = self::build_internal_link_cta($postTarget);
            if (!$ctaHtml) continue;

            if (!isset($inject[$blockIndex])) {
                $inject[$blockIndex] = [];
            }
            $inject[$blockIndex][] = $ctaHtml;
        }

        // 7) Reconstrói o HTML final
        $newContent = '';
        for ($i = 0; $i < $countBlocks; $i++) {
            // insere CTAs antes do bloco-alvo
            if (!empty($inject[$i])) {
                $newContent .= implode("\n", $inject[$i]) . "\n";
            }
            $newContent .= $blocks[$i];
        }

        $newContent = preg_replace("/\n{3,}/", "\n\n", $newContent);

        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $newContent,
        ]);
    }

    /**
     * Resolve quais posts serão usados como links internos.
     * - mode = manual  → usa somente IDs informados
     * - mode = auto    → busca posts relacionados (mesmo CPT / categoria)
     * - mode = pillar  → por enquanto trata igual ao auto (dá pra evoluir depois)
     *
     * Em modo MANUAL, se tiver menos posts do que $max, duplica em ciclo
     * (ex: 2 posts, max=3 => [post1, post2, post1]).
     */
    protected static function resolve_internal_link_targets(int $post_id, string $mode, array $manual_ids, int $max): array
    {
        $post_id = absint($post_id);
        $max     = max(1, (int) $max);
        $post_type = get_post_type($post_id) ?: 'posts_orion';

        // ---- MANUAL ----
        if ($mode === 'manual') {
            $manual_ids = array_values(array_filter(array_map('absint', $manual_ids)));
            if (empty($manual_ids)) {
                return [];
            }

            $posts = get_posts([
                'post_type'      => $post_type,
                'post__in'       => $manual_ids,
                'posts_per_page' => -1,
                'orderby'        => 'post__in',
                'post_status'    => 'publish',
            ]);

            if (empty($posts)) {
                return [];
            }

            // Se tem posts suficientes, só corta no max
            if (count($posts) >= $max) {
                return array_slice($posts, 0, $max);
            }

            // Se tem menos posts que max → duplica em ciclo
            $out = [];
            $c   = count($posts);
            for ($i = 0; $i < $max; $i++) {
                $out[] = $posts[$i % $c];
            }
            return $out;
        }

        // ---- AUTO / PILLAR (MVP) ----
        $args = [
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'post__not_in'   => [$post_id],
            'posts_per_page' => $max,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // tenta cluster por categoria
        $cats = wp_get_post_terms($post_id, 'category', ['fields' => 'ids']);
        if (!is_wp_error($cats) && !empty($cats)) {
            $args['category__in'] = $cats;
        }

        // TODO: se quiser, aqui dá pra tratar 'pillar' filtrando por meta específica (pilar = 1)

        $posts = get_posts($args);
        return $posts ?: [];
    }
    /**
     * Monta um CTA simples "Leia também" com o link interno.
     */
    protected static function build_internal_link_cta(WP_Post $post): string
    {
        $url   = get_permalink($post);
        if (!$url) return '';

        $title = get_the_title($post) ?: __('Artigo relacionado', 'plugins-alpha');
        $label = __('Leia também:', 'plugins-alpha');

        return sprintf(
            '<p class="pga-internal-link-cta"><strong>%s</strong> <a href="%s">%s</a></p>',
            esc_html($label),
            esc_url($url),
            esc_html($title)
        );
    }


    /**
     * POST /wp-json/pga/v1/orion/finalize
     * Body: { post_id: 123 }
     */
    public static function handle_finalize(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
    
        $params = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params();
        }
    
        $post_id = intval($params['post_id'] ?? 0);
        if (!$post_id) {
            return new WP_Error(
                'pga_bad_request',
                __('post_id ausente.', 'plugins-alpha'),
                ['status' => 400]
            );
        }
    
        // opções de link interno vindas do JS
        $internal_opts = [];
        if (!empty($params['internal_links']) && is_array($params['internal_links'])) {
            $internal_opts = $params['internal_links'];
        }
    
        // NOVO: controla se a finalização vai ou não gerar imagem
        $skip_images    = !empty($params['skip_images']);
        $generate_image = !$skip_images;
    
        // 1) Finaliza via Generator
        $res = PluginsAlpha_Pages_Generator::finalize_from_sections(
            $post_id,
            [
                'internal_links' => $internal_opts,
                'generate_image' => $generate_image,
            ]
        );
    
        if (is_wp_error($res)) {
            return $res;
        }

        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword === '' && is_array($res) && !empty($res['keyword'])) {
            $keyword = (string)$res['keyword'];
        }
        if ($keyword === '') {
            $keyword = (string) get_post_meta($post_id, '_pga_outline_keyword', true);
        }

        // 3) Atualiza listas de palavras (pending → done)
        $state = null;

        if ($keyword !== '' && method_exists(__CLASS__, 'kw_move_to_done_one')) {
            self::kw_move_to_done_one($keyword);
        }

        if (method_exists(__CLASS__, 'kw_get_pending') && method_exists(__CLASS__, 'kw_get_done')) {
            $state = [
                'pending' => self::kw_get_pending(),
                'done'    => self::kw_get_done(),
            ];
        }

        // garante que $res seja array pra anexar o estado
        if (!is_array($res)) {
            $res = ['result' => $res];
        }

        if ($state !== null) {
            $res['state'] = $state;
        }

        return rest_ensure_response($res);
    }

    public static function activate(WP_REST_Request $req)
    {
        $nonce = $req->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Nonce inválido.', ['status' => 403]);
        }
        $p = $req->get_json_params();
        $email = sanitize_email($p['email'] ?? '');
        $pid   = sanitize_text_field($p['purchase_id'] ?? '');
        if (!$email || !$pid) return new WP_Error('pga_lic', 'Informe e-mail e ID da compra.', ['status' => 400]);

        $lic = PluginsAlpha_License::get_state($email, $pid);
        return ['ok' => PluginsAlpha_License::is_active($lic), 'license' => $lic];
    }

    public static function status_licence()
    {
        $lic = PluginsAlpha_License::get_state();
        return [
            'ok'      => PluginsAlpha_License::is_active(),
            'license' => $lic,
        ];
    }

    public static function plan($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
    
        return self::guard(function () use ($req) {
            $p    = $req->get_json_params();
            $mode = (isset($p['mode']) && $p['mode'] === 'single') ? 'single' : 'multi';
    
            $il_raw = is_array($p['internal_links'] ?? null) ? $p['internal_links'] : [];
    
            // modo: none | auto | pillar | manual
            $link_mode = self::clean($il_raw['mode'] ?? 'none');
            if (!in_array($link_mode, ['none', 'auto', 'pillar', 'manual'], true)) {
                $link_mode = 'none';
            }
    
            // máximo de links por post
            $link_max = max(0, intval($il_raw['max'] ?? 0));
    
            // "12,34, 56" -> [12, 34, 56]
            $link_manual_ids = array_values(array_filter(array_map(
                'absint',
                explode(',', (string)($il_raw['manual_ids'] ?? ''))
            )));
    
            // textarea (um por linha)
            $kw_in = self::lines_to_array($p['keywords'] ?? '');
    
            $locale = self::clean($p['locale'] ?? 'pt_BR');
            $tpl    = self::clean($p['template_key'] ?? 'article');
            $length = self::clean($p['length'] ?? 'short');
    
            $isModelar = ($tpl === 'modelar');
    
            if ($tpl === 'modelar') {
                $kw_in = array_map(function ($u) {
                    $u = trim($u);
    
                    // remove espaços invisíveis / caracteres ruins
                    $u = preg_replace('/[\x00-\x1F\x7F]/u', '', $u);
    
                    // evita prefixos quebrados tipo "/-xxxxx"
                    if (str_starts_with($u, '/-')) {
                        $u = ltrim($u, '/-');
                    }
    
                    // se não começa com http, tenta consertar
                    if (!preg_match('~^https?://~i', $u)) {
                        $u = 'https://' . ltrim($u, '/');
                    }
    
                    return esc_url_raw($u);
                }, $kw_in);
            }
    
            // VALIDAÇÃO
            if (!$isModelar) {
                if ($mode === 'multi' && empty($kw_in)) {
                    return new WP_Error(
                        'pga_kw',
                        'Informe palavras-chave (modo múltiplo).',
                        ['status' => 400]
                    );
                }
                if ($mode === 'single' && empty($kw_in)) {
                    return new WP_Error(
                        'pga_kw',
                        'Informe ao menos 1 palavra (modo único).',
                        ['status' => 400]
                    );
                }
            } else {
                if (empty($kw_in)) {
                    return new WP_Error(
                        'pga_kw',
                        'Para modelar, informe pelo menos 1 URL (uma por linha).',
                        ['status' => 400]
                    );
                }
            }
    
            $total  = max(1, intval($p['total'] ?? ($mode === 'single' ? 1 : count($kw_in))));
            $perDay = max(1, intval($p['per_day'] ?? 3));
    
            // ---------- PRIMEIRA PUBLICAÇÃO (DATETIME-LOCAL) ----------
            $first_raw = trim((string)($p['first_delay_hours'] ?? ''));
            $now       = time();
    
            // baseTs = timestamp base do plano
            //  - se veio data/hora futura → data escolhida
            //  - senão → agora + 2h (fallback antigo)
            $baseTs = $now + 2 * HOUR_IN_SECONDS;
    
            if ($first_raw !== '') {
                $ts = strtotime($first_raw);
                if ($ts !== false && $ts > $now) {
                    $baseTs = $ts;
                }
            }
    
            $transition = [
                'strict'    => !empty($p['transition']['strict'] ?? false),
                'min_ratio' => floatval($p['transition']['min_ratio'] ?? 0.3),
                'words'     => is_array($p['transition']['words'] ?? null)
                    ? array_values(array_filter(array_map('trim', $p['transition']['words'])))
                    : [],
            ];
    
            // monta agenda leve
            $jobs   = [];
            $days   = (int) ceil($total / max(1, $perDay));
            $i      = 0;
            $cat_id = max(0, intval($p['category_id'] ?? 0));
    
            for ($d = 0; $d < $days; $d++) {
                $slotsToday = min($perDay, $total - count($jobs));
                $base       = [9 * 3600, 14 * 3600, 19 * 3600];
    
                for ($s = 0; $s < $slotsToday; $s++) {
                    $baseIdx = min($s, count($base) - 1);
                    $offset  = wp_rand(-40 * MINUTE_IN_SECONDS, 40 * MINUTE_IN_SECONDS);
    
                    // sempre a partir do baseTs, somando dias pra frente
                    $t = strtotime('+' . $d . ' day', $baseTs) + $base[$baseIdx] + $offset;
    
                    // escolhe LINHA (keyword OU URL) para este job
                    $lineValue = '';
                    if ($mode === 'single') {
                        $lineValue = $kw_in[0] ?? '';
                    } else {
                        $lineValue = $kw_in[$i] ?? '';
                    }
    
                    if ($lineValue === '') {
                        continue;
                    }
    
                    $jobs[] = [
                        'keyword'        => $lineValue,
                        'locale'         => $locale,
                        'length'         => $length,
                        'template_key'   => $tpl,
                        'publish_time'   => $t,
                        'transition'     => $transition,
                        'category_id'    => $cat_id,
                        'internal_links' => [
                            'mode'       => $link_mode,
                            'max'        => $link_max,
                            'manual_ids' => $link_manual_ids,
                        ],
                    ];
    
                    $i++;
                    if (count($jobs) >= $total) {
                        break;
                    }
                }
            }
    
            // multi: no máx. 1 job por linha
            if ($mode === 'multi' && count($kw_in) < $total) {
                $jobs = array_slice($jobs, 0, count($kw_in));
            }
    
            return [
                'ok'                 => true,
                'mode'               => $mode,
                'total_requested'    => $total,
                'jobs'               => $jobs,
                'available_keywords' => count($kw_in),
            ];
        });
    }

    /**
     * Monta a lista de jobs (agenda) a partir dos parâmetros do planejamento.
     *
     * Cada job já sai pronto para ser enviado para o /generate depois.
     */
    public static function status()
    {
        return ['ok' => true, 'time' => current_time('mysql')];
    }

    // ---------------------- keywords ----------------------
    public static function keywords_get($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
        return [
            'ok' => true,
            'pending' => self::kw_get_pending(),
            'done'   => self::kw_get_done(),
        ];
    }

    public static function keywords_save($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
        return self::guard(function () use ($req) {
            $p           = $req->get_json_params();
            $pending_txt = (string)($p['pending_text'] ?? '');
            $pending     = self::lines_to_array($pending_txt);
            self::kw_set_pending($pending);
            return [
                'ok' => true,
                'pending' => self::kw_get_pending(),
                'done'   => self::kw_get_done(),
            ];
        });
    }

    public static function keywords_clear($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {
            $p   = $req->get_json_params();
            $who = self::clean($p['who'] ?? 'pending');

            if ($who === 'done') {
                self::kw_clear_done();
            } else {
                self::kw_clear_pending();
            }

            return [
                'ok' => true,
                'pending' => self::kw_get_pending(),
                'done'   => self::kw_get_done(),
            ];
        });
    }


    // ---------------------- diagnóstico da OpenAI ----------------------
    public static function selftest($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $p = $req->get_json_params();
        if (!is_array($p)) $p = [];

        $opt       = PluginsAlpha_Settings::get();
        $key       = trim($p['key'] ?? ($opt['apis']['openai']['key'] ?? ''));
        $model     = trim($p['model'] ?? ($opt['apis']['openai']['model_text'] ?? 'gpt-4o-mini'));
        $temp      = floatval($p['temperature'] ?? ($opt['apis']['openai']['temperature'] ?? 0.6));
        $maxTokens = intval($p['max_tokens'] ?? ($opt['apis']['openai']['max_tokens'] ?? 512));

        if (!$key) {
            return new WP_Error('agp_no_key', 'Nenhuma chave OpenAI informada.', ['status' => 400]);
        }

        $args = [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $key,
            ],
            'timeout' => 20,
            'body'    => json_encode([
                'model'       => $model,
                'messages'    => [['role' => 'user', 'content' => 'Responda apenas: pong']],
                'temperature' => $temp,
                'max_tokens'  => max(16, min($maxTokens, 256)), // limite baixo só pro teste
            ]),
        ];

        $t0 = microtime(true);
        $r  = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        $ms = round((microtime(true) - $t0) * 1000);

        if (is_wp_error($r)) {
            return new WP_Error('agp_http', $r->get_error_message(), ['status' => 500]);
        }

        $code = wp_remote_retrieve_response_code($r);
        $body = json_decode(wp_remote_retrieve_body($r), true);

        if ($code !== 200) {
            $msg = $body['error']['message'] ?? 'HTTP ' . $code;
            return new WP_Error('agp_api_fail', $msg, ['status' => $code]);
        }

        $text = (string)($body['choices'][0]['message']['content'] ?? '');
        $ok   = (stripos($text, 'pong') !== false);

        return [
            'ok'        => $ok,
            'latencyMs' => $ms,
            'model'     => $model,
            'sample'    => $text,
        ];
    }
}
