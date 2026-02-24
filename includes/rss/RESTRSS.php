<?php

use Soap\Url;

if (!defined('ABSPATH')) exit;

class AlphaSuite_RESTRSS
{
    public static function register_routes()
    {
        $base = 'pga/v1';

        register_rest_route($base, '/rss/get', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'get_rss'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/rss/faq', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'generate_faq'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/generators/save', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'pga_rest_save_generators'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route($base, '/rss/start', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'start'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/title', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_title'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/meta', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_meta'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/slug', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_slug'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/outline', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_outline'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/section', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_section'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/finalize', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_finalize'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route($base, '/rss/extract-image', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_extract_image'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);
    }

    public static function pga_rest_save_generators(WP_REST_Request $req)
    {
        global $wpdb;

        $tab_id     = sanitize_text_field($req['tab_id']);
        $generators = $req['generators'];

        if (!$tab_id || !is_array($generators)) {
            return new WP_Error('invalid_data', 'Dados inválidos');
        }

        /*
    |--------------------------------------------------------------------------
    | 1️⃣ Buscar interval e next_run antigos
    |--------------------------------------------------------------------------
    */
        $old_runtime = [];

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $old = $wpdb->get_results(
            $wpdb->prepare(
                "
        SELECT g.id, r.interval_hours, r.next_run
        FROM {$wpdb->prefix}pga_generators g
        LEFT JOIN {$wpdb->prefix}pga_generator_runtime r
            ON r.generator_id = g.id
        WHERE g.tab_id = %s
        ORDER BY g.id ASC
        ",
                $tab_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching


        foreach ($old as $index => $o) {
            $old_runtime[$index] = [
                'interval' => intval($o->interval_hours),
                'next_run' => $o->next_run
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Apaga tudo (sua arquitetura atual)
        |--------------------------------------------------------------------------
        */
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        foreach ($old as $o) {
            $wpdb->delete("{$wpdb->prefix}pga_generators", ['id' => $o->id]);
            $wpdb->delete("{$wpdb->prefix}pga_generator_config", ['generator_id' => $o->id]);
            $wpdb->delete("{$wpdb->prefix}pga_generator_runtime", ['generator_id' => $o->id]);
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching


        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Recria com comparação correta
        |--------------------------------------------------------------------------
        */
        foreach ($generators as $index => $g) {

            $active         = !empty($g['active']) ? 1 : 0;
            $start          = intval($g['start_hour'] ?? 0);
            $end            = intval($g['end_hour'] ?? 23);
            $interval_hours = intval($g['interval_hours'] ?? 1);

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert("{$wpdb->prefix}pga_generators", [
                'tab_id'         => $tab_id,
                'name'           => sanitize_text_field($g['template_key'] ?? 'Gerador'),
                'active'         => $active,
                'start_hour'     => $start,
                'end_hour'       => $end,
                'interval_hours' => $interval_hours,
            ]);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching


            $gen_id = $wpdb->insert_id;

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert("{$wpdb->prefix}pga_generator_config", [
                'generator_id' => $gen_id,
                'config_json'  => wp_json_encode($g),
            ]);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            $old_interval = $old_runtime[$index]['interval'] ?? null;
            $old_next_run = $old_runtime[$index]['next_run'] ?? null;

            /*
            |--------------------------------------------------------------------------
            | 🔥 SUA LÓGICA CORRETA AQUI
            |--------------------------------------------------------------------------
            */
            if ($old_interval !== null && $old_interval == $interval_hours) {

                // Intervalo não mudou → mantém next_run antigo
                $next = $old_next_run ?: wp_date(
                    'Y-m-d H:i:s',
                    current_time('timestamp') + ($interval_hours * HOUR_IN_SECONDS)
                );
            } else {

                // Intervalo mudou → recalcula
                $next = wp_date(
                    'Y-m-d H:i:s',
                    current_time('timestamp') + ($interval_hours * HOUR_IN_SECONDS)
                );
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert("{$wpdb->prefix}pga_generator_runtime", [
                'generator_id'  => $gen_id,
                'next_run'      => $next,
                'interval_hours' => $interval_hours,
            ]);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        return ['success' => true];
    }

    public static function rest_extract_image(WP_REST_Request $request)
    {
        $post_id = (int) $request->get_param('post_id');
        $url     = (string) $request->get_param('url');

        return self::extract_image($post_id, $url);
    }

    public static function extract_image($postId, $url = '')
    {
        if (!$postId) {
            return false;
        }

        // 🔥 Se já tem thumbnail, não faz nada
        if (has_post_thumbnail($postId)) {
            return true;
        }

        $title   = get_the_title($postId);

        $image_alt = trim($title ?? 'Imagem ilustrativa');

        $attachmentId = 0;

        /*
    |--------------------------------------------------------------------------
    | 1️⃣ Tenta extrair imagem da página
    |--------------------------------------------------------------------------
    */

        if (!empty($url)) {

            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (AlphaSuiteBot)'
                ]
            ]);

            if (!is_wp_error($response)) {

                $html = wp_remote_retrieve_body($response);

                if ($html) {

                    $imageUrl = '';

                    // og:image
                    if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
                        $imageUrl = esc_url_raw($m[1]);
                    }

                    // twitter:image
                    if (!$imageUrl && preg_match('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
                        $imageUrl = esc_url_raw($m[1]);
                    }

                    // fallback img
                    if (!$imageUrl && preg_match('/<img[^>]+src=["\']([^"\']+)/i', $html, $m)) {
                        $imageUrl = esc_url_raw($m[1]);
                    }

                    if ($imageUrl) {

                        // normaliza relativa
                        if (!preg_match('#^https?://#', $imageUrl)) {
                            $imageUrl = esc_url_raw(
                                rtrim($url, '/') . '/' . ltrim($imageUrl, '/')
                            );
                        }

                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';

                        $attachmentId = media_sideload_image($imageUrl, $postId, null, 'id');
                    }
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 2️⃣ Se não conseguiu extrair → gera via IA
    |--------------------------------------------------------------------------
    */

        if (!$attachmentId || is_wp_error($attachmentId)) {
            if (!class_exists('AlphaSuite_Prompts') || !class_exists('AlphaSuite_Images')) {
                return false;
            }

            $imageProvider = class_exists('AlphaSuite_AI')
                ? AlphaSuite_AI::get_image_provider()
                : 'pollinations';

            $meta_img_prompt = AlphaSuite_Prompts::build_image_prompt(
                $title,
                $title,
                '',
                'rss',
                $imageProvider
            );

            $img_prompt = $meta_img_prompt;

            if (class_exists('AlphaSuite_AI')) {
                $resolved = AlphaSuite_AI::image_prompt($meta_img_prompt, []);
                if (!is_wp_error($resolved) && is_string($resolved) && $resolved !== '') {
                    $img_prompt = trim($resolved);
                }
            }

            if ($img_prompt) {

                $attachmentId = AlphaSuite_Images::generate_by_settings(
                    $img_prompt,
                    intval($postId),
                    $image_alt
                );
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 3️⃣ Finaliza
    |--------------------------------------------------------------------------
    */

        if (!is_wp_error($attachmentId) && $attachmentId) {

            set_post_thumbnail($postId, $attachmentId);

            update_post_meta($attachmentId, '_wp_attachment_image_alt', $image_alt);
            update_post_meta($postId, '_pga_image_alt', $image_alt);

            return $attachmentId;
        } else {
            AlphaSuite_FailJob::fail_job($postId, $respSlug);
        }

        return false;
    }


    public static function start(WP_REST_Request $req)
    {
        $title  = sanitize_text_field($req->get_param('title'));
        $hash   = sanitize_text_field($req->get_param('hash'));
        $link   = esc_url_raw($req->get_param('link'));
        $source = sanitize_text_field($req->get_param('source'));
        $length = sanitize_text_field($req->get_param('length') ?: 'short');
        $locale = sanitize_text_field($req->get_param('locale') ?: 'pt_BR');
        $category_id = intval($req->get_param('category_id'));
        $tags        = (array) $req->get_param('tags');
        $link_mode   = sanitize_text_field($req->get_param('link_mode') ?: 'none');
        $link_max    = intval($req->get_param('link_max') ?: 1);
        $link_manual = (array) $req->get_param('pga_link_max');
        $link_ids = (array) $req->get_param('link_manual_ids');
        $make_faq    = !empty($req->get_param('make_faq'));
        $faq_qty     = intval($req->get_param('faq_qty') ?: 0);

        if (!$title || !$hash) {
            return new WP_Error('pga_invalid_data', 'Título ou hash inválido.');
        }

        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Verifica duplicidade
        |--------------------------------------------------------------------------
        */

        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        $exists = new WP_Query([
            'post_type'              => 'posts_orion',
            'meta_key'               => '_pga_news_hash',
            'meta_value'             => $hash,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        if ($exists->have_posts()) {
            return ['duplicate' => true];
        }

        /*
    |--------------------------------------------------------------------------
    | 2️⃣ Cria post base
    |--------------------------------------------------------------------------
    */

        $postId = wp_insert_post([
            'post_title'  => "GERANDO " . $title,
            'post_status' => 'draft',
            'post_type'   => 'posts_orion'
        ]);

        if (is_wp_error($postId) || !$postId) {
            return new WP_Error('pga_insert_failed', 'Falha ao criar post.');
        }

        /*
    |--------------------------------------------------------------------------
    | 3️⃣ Salva dados RSS
    |--------------------------------------------------------------------------
    */

        update_post_meta($postId, '_pga_news_hash', $hash);

        update_post_meta($postId, '_pga_rss_context', [
            'link'   => $link,
            'source' => $source
        ]);

        update_post_meta($postId, '_pga_rss_seed_title', $title);

        update_post_meta($postId, '_pga_outline_length', $length);
        update_post_meta($postId, '_pga_outline_locale', $locale);
        update_post_meta($postId, '_pga_length', $length);
        update_post_meta($postId, '_pga_locale', $locale);

        update_post_meta($postId, '_pga_link_mode', $link_mode);
        update_post_meta($postId, '_pga_link_max', $link_max);
        update_post_meta($postId, '_pga_link_manual', $link_ids);
        update_post_meta($postId, 'pga_link_max', $link_manual);

        update_post_meta($postId, '_pga_make_faq', $make_faq);
        update_post_meta($postId, '_pga_faq_qty', $faq_qty);

        update_post_meta($postId, '_pga_job_status', 'started');

        if ($category_id > 0) {
            wp_set_post_terms($postId, [$category_id], 'category');
        }

        if (!empty($tags)) {
            wp_set_object_terms($postId, $tags, 'post_tag', false);
        }

        /*
    |--------------------------------------------------------------------------
    | 4️⃣ Tenta extrair conteúdo (OPCIONAL)
    |--------------------------------------------------------------------------
    */

        $hasSourceContent = false;

        if (!empty($link)) {

            $data = self::extract_article_data($link);

            if (!empty($data['content'])) {
                update_post_meta($postId, '_pga_source_content', $data['content']);
                update_post_meta($postId, '_pga_source_url', $link);
                $hasSourceContent = true;
            }
        }

        update_post_meta($postId, '_pga_has_source_content', $hasSourceContent);

        /*
    |--------------------------------------------------------------------------
    | 5️⃣ Retorno
    |--------------------------------------------------------------------------
    */

        return [
            'duplicate' => false,
            'post_id'   => $postId,
            'has_source_content' => $hasSourceContent
        ];
    }

    public static function generate_faq(WP_REST_Request $req)
    {
        $postId = (int) $req['post_id'];

        $keyword = get_post_meta($postId, '_pga_rss_seed_title', true) ?: '';
        $qty = get_post_meta($postId, '_pga_faq_qty', true) ?: 5;
        $locale = get_post_meta($postId, '_pga_locale', true) ?: 'pt_BR';
        $context = get_post_meta($postId, '_pga_rss_context', true) ?: [];

        // gera FAQ via IA
        $faq = AlphaSuite_AI::faq([
            'keyword' => $keyword,
            'qty'     => $qty,
            'locale'  => $locale,
            'context' => $context
        ]);

        if (is_wp_error($faq)) {
            return $faq;
        }

        // salva JSON-LD no meta
        update_post_meta($postId, '_pga_faq_jsonld', $faq);

        return ['ok' => true];
    }

    private static function extract_article_data(string $url)
    {
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (AlphaSuiteBot)'
            ]
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return false;
        }

        $html = wp_remote_retrieve_body($response);
        if (!$html || strlen($html) < 500) {
            return false;
        }

        // Remove scripts e styles
        $html = preg_replace('#<script(.*?)</script>#is', '', $html);
        $html = preg_replace('#<style(.*?)</style>#is', '', $html);

        $title = '';
        $description = '';
        $content = '';

        /*
    |--------------------------------------------------------------------------
    | 1️⃣ OG TITLE
    |--------------------------------------------------------------------------
    */
        if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            $title = trim($m[1]);
        }

        /*
    |--------------------------------------------------------------------------
    | 2️⃣ OG DESCRIPTION
    |--------------------------------------------------------------------------
    */
        if (preg_match('/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            $description = trim($m[1]);
        }

        /*
    |--------------------------------------------------------------------------
    | 3️⃣ JSON-LD Article
    |--------------------------------------------------------------------------
    */
        if (preg_match('/<script type=["\']application\/ld\+json["\']>(.*?)<\/script>/is', $html, $m)) {
            $json = json_decode($m[1], true);
            if (is_array($json)) {
                if (!empty($json['articleBody'])) {
                    $content = trim($json['articleBody']);
                }
                if (!$title && !empty($json['headline'])) {
                    $title = trim($json['headline']);
                }
                if (!$description && !empty($json['description'])) {
                    $description = trim($json['description']);
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 4️⃣ Fallback: primeiros parágrafos
    |--------------------------------------------------------------------------
    */
        if (!$content) {
            preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches);

            if (!empty($matches[1])) {
                $paragraphs = array_slice($matches[1], 0, 5);
                $clean = [];

                foreach ($paragraphs as $p) {
                    $p = wp_strip_all_tags($p);
                    $p = html_entity_decode($p, ENT_QUOTES, 'UTF-8');
                    $p = trim($p);

                    if (strlen($p) > 50) {
                        $clean[] = $p;
                    }
                }

                $content = implode("\n\n", $clean);
            }
        }

        if (!$content && !$description) {
            return false;
        }

        /*
    |--------------------------------------------------------------------------
    | 5️⃣ Limita tamanho (importante!)
    |--------------------------------------------------------------------------
    */

        $content = mb_substr($content, 0, 2000);
        $description = mb_substr($description, 0, 500);

        return [
            'title'       => $title,
            'description' => $description,
            'content'     => $content,
        ];
    }


    public static function generate_slug(int $postId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $template = get_post_meta($postId, '_pga_outline_template', true) ?: 'article';
        $locale   = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';

        $chosenTitle = get_post_meta($postId, '_pga_chosen_title', true);
        if (!$chosenTitle) {
            $chosenTitle = get_post_field('post_title', $postId);
        }

        $keyword = get_post_meta($postId, '_pga_keyword', true) ?: $chosenTitle;

        if (!$chosenTitle) {
            return new WP_Error('pga_no_title', 'Título não encontrado para gerar slug.');
        }

        // 🔥 MESMO PADRÃO DO ORION
        $promptSlug = AlphaSuite_Prompts::build_slug_prompt(
            (string)$template,
            (string)$keyword,
            (string)$chosenTitle,
            (string)$locale
        );

        $respSlug = AlphaSuite_AI::slug($promptSlug);

        if (is_wp_error($respSlug)) {
            return AlphaSuite_FailJob::fail_job($postId, $respSlug);
            return $respSlug;
        }

        $slugTxt = '';

        // --------- EXTRAÇÃO SEGURA ----------
        if (is_string($respSlug)) {
            $slugTxt = $respSlug;
        } elseif (is_array($respSlug)) {
            $slugTxt = (string)($respSlug['slug'] ?? $respSlug['content'] ?? '');
        } elseif (is_object($respSlug)) {
            $slugTxt = (string)($respSlug->slug ?? $respSlug->content ?? '');
        }

        $slugTxt = trim($slugTxt);

        // --------- JSON DENTRO DE TEXTO ----------
        if ($slugTxt !== '' && ($slugTxt[0] === '{' || $slugTxt[0] === '[')) {
            $j = json_decode($slugTxt, true);
            if (is_array($j)) {
                $slugTxt = (string)($j['slug'] ?? $j['content'] ?? '');
            }
        }

        // --------- REMOVE PREFIXOS ----------
        $slugTxt = preg_replace('/^\s*(slug|post_name)\s*:\s*/i', '', $slugTxt);

        // --------- PRIMEIRA LINHA ----------
        $slugTxt = preg_split("/\r\n|\r|\n/", $slugTxt)[0] ?? $slugTxt;
        $slugTxt = trim($slugTxt);

        // --------- SANITIZA ----------
        $newSlug = sanitize_title($slugTxt);

        // --------- FALLBACKS ----------
        if ($newSlug === '') {
            $newSlug = sanitize_title($chosenTitle);
        }
        if ($newSlug === '') {
            $newSlug = sanitize_title($keyword);
        }
        if ($newSlug === '') {
            $newSlug = sanitize_title(uniqid('rss_', false));
        }

        $postType = get_post_type($postId) ?: 'posts_orion';

        $newSlug = wp_unique_post_slug(
            $newSlug,
            $postId,
            'draft',
            $postType,
            0
        );

        wp_update_post([
            'ID'        => $postId,
            'post_name' => $newSlug,
        ]);

        update_post_meta($postId, '_pga_generated_slug', $newSlug);
        update_post_meta($postId, '_pga_job_status', 'slug_done');

        return [
            'post_id' => $postId,
            'slug'    => $newSlug,
        ];
    }

    public static function rest_generate_slug(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('pga_invalid_post', 'Post ID inválido.');
        }

        $result = self::generate_slug($postId);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'   => true,
            'slug' => $result['slug'],
        ];
    }

    public static function rest_generate_title(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('invalid_post', 'Post ID inválido.');
        }

        $result = self::generate_title($postId);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'    => true,
            'title' => $result['title'],
        ];
    }

    public static function generate_title(int $postId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('invalid_post', 'Post inválido.');
        }

        $context = get_post_meta($postId, '_pga_rss_context', true) ?: [];

        // 👇 seed central
        $seed = get_post_meta($postId, '_pga_rss_seed_title', true);
        if (!$seed) {
            $seed = get_post_field('post_title', $postId);
        }

        if (!$seed) {
            return new WP_Error('no_seed', 'Título base vazio.');
        }

        $locale = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';
        $url    = $context['link'] ?? '';

        $newTitle = AlphaSuite_Titles::getTitle(
            $postId,
            'rss',
            '',
            $locale,
            $url,
            $seed
        );

        if (is_wp_error($newTitle)) {
            return $newTitle; // ESSENCIAL
        }

        if (!is_string($newTitle) || trim($newTitle) === '') {
            return new WP_Error('empty_title', 'Título retornado vazio.');
        }

        wp_update_post([
            'ID' => $postId,
            'post_title' => $newTitle,
        ]);


        update_post_meta($postId, '_pga_chosen_title', $newTitle);
        update_post_meta($postId, '_pga_job_status', 'title_done');

        return [
            'post_id' => $postId,
            'title'   => $newTitle,
        ];
    }

    public static function generate_meta(int $postId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $locale   = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';
        $category = get_post_meta($postId, '_pga_outline_category', true);
        $title    = get_post_meta($postId, '_pga_chosen_title', true);

        if (!$title) {
            $title = get_post_field('post_title', $postId);
        }

        if (!$title) {
            return new WP_Error('pga_no_title', 'Título não encontrado para gerar meta.');
        }

        // 🔥 Monta prompt igual ao Orion
        $promptMeta = AlphaSuite_Prompts::build_meta_description_prompt(
            (string)$category,
            (string)$title,
            (string)$locale,
            ''
        );

        $respMeta = AlphaSuite_AI::meta_description($promptMeta);

        if (is_wp_error($respMeta)) {
            return AlphaSuite_FailJob::fail_job($postId, $respMeta);
            return $respMeta;
        }

        $meta_desc = '';
        $raw = '';

        // --------- EXTRAÇÃO SEGURA ----------
        if (is_string($respMeta)) {
            $raw = $respMeta;
        } elseif (is_array($respMeta)) {
            $raw = (string)($respMeta['meta_description'] ?? $respMeta['description'] ?? $respMeta['content'] ?? '');
        } elseif (is_object($respMeta)) {
            $raw = (string)($respMeta->meta_description ?? $respMeta->description ?? $respMeta->content ?? '');
        }

        $raw = trim($raw);

        // --------- JSON DENTRO DE TEXTO ----------
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                $raw = (string)(
                    $j['meta_description']
                    ?? $j['description']
                    ?? $j['content']
                    ?? ''
                );
            }
        }

        // --------- REMOVE PREFIXOS ----------
        $raw = preg_replace(
            '/^\s*(meta\s*description|meta\s*descri[cç][aã]o|description)\s*:\s*/i',
            '',
            $raw
        );

        // --------- PRIMEIRA LINHA ----------
        $raw = preg_split("/\r\n|\r|\n/", $raw)[0] ?? $raw;
        $raw = trim($raw);

        // --------- SANITIZA ----------
        if ($raw !== '') {
            $raw = wp_strip_all_tags($raw);
            $raw = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
            $raw = preg_replace('/\s+/', ' ', $raw);
            $raw = trim($raw);
        }

        if ($raw !== '') {
            $meta_desc = $raw;
            update_post_meta($postId, '_pga_meta_description', $meta_desc);
            update_post_meta($postId, '_pga_job_status', 'meta_done');
        }

        return [
            'post_id' => $postId,
            'meta'    => $meta_desc,
        ];
    }

    public static function rest_generate_meta(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('pga_invalid_post', 'Post ID inválido.');
        }

        $result = self::generate_meta($postId);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'   => true,
            'meta' => $result['meta'],
        ];
    }

    public static function generate_outline(int $postId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $sourceContent = get_post_meta($postId, '_pga_source_content', true);
        $length        = get_post_meta($postId, '_pga_outline_length', true) ?: 'short';
        $locale        = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';

        $title = get_post_meta($postId, '_pga_chosen_title', true);
        if (!$title) {
            $title = get_post_field('post_title', $postId);
        }

        if (!$title) {
            return new WP_Error('pga_no_title', 'Título não encontrado.');
        }

        $seed = get_post_meta($postId, '_pga_rss_seed_title', true) ?: $title;

        $context = get_post_meta($postId, '_pga_rss_context', true) ?: [];
        $url     = $context['link'] ?? '';
        $font     = $context['source'] ?? '';

        $prompt = AlphaSuite_Prompts::build_outline_rss_prompt(
            $title,
            $seed,
            $length,
            $locale,
            $url,
            $font,
            $sourceContent
        );

        $outline = AlphaSuite_AI::outline($prompt, [
            'use_search' => true
        ]);

        if (is_wp_error($outline)) {
            return AlphaSuite_FailJob::fail_job($postId, $outline);
            return $outline;
        }

        $sections = self::normalize_outline($outline);

        if (!is_array($sections)) {
            return new WP_Error('pga_outline_invalid', 'Outline inválido "outline".');
        }

        // Normalização igual Orion
        $normalized = [];
        $h2Index = 1;

        foreach ($sections as $sec) {

            if (!is_array($sec)) {
                $sec = [
                    'heading' => (string)$sec,
                    'level'   => 'h2',
                ];
            }

            $sec['level'] = 'h2';
            $sec['id']    = $sec['id'] ?? (string)$h2Index;
            $sec['children'] = $sec['children'] ?? [];

            $childIndex = 1;

            foreach ($sec['children'] as $ci => $child) {

                if (!is_array($child)) {
                    $child = [
                        'heading' => (string)$child,
                        'level'   => 'h3',
                    ];
                }

                $child['level'] = 'h3';
                $child['id']    = $child['id'] ?? ($sec['id'] . '.' . $childIndex);

                $sec['children'][$ci] = $child;
                $childIndex++;
            }

            $normalized[] = $sec;
            $h2Index++;
        }

        $linkMode = get_post_meta($postId, '_pga_link_mode', true) ?: 'none';

        if ($linkMode !== 'none') {

            $maxLinks = intval(get_post_meta($postId, 'pga_link_max', true) ?: 1);

            $internalLinks = [];
            if ($linkMode === 'manual') {

                $manualIds = get_post_meta($postId, '_pga_link_manual', true) ?: [];

                foreach ((array)$manualIds as $pid) {
                    $p = get_post(intval($pid));
                    if ($p) {
                        $internalLinks[] = [
                            'anchor' => $p->post_title,
                            'url'    => get_permalink($p->ID),
                        ];
                    }
                }
            } elseif ($linkMode === 'auto') {

                $recent = get_posts([
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => $maxLinks,
                    'orderby'        => 'date',
                    'order'          => 'DESC'
                ]);

                foreach ($recent as $p) {
                    $internalLinks[] = [
                        'anchor' => $p->post_title,
                        'url'    => get_permalink($p->ID),
                    ];
                }
            }

            $internalLinks = array_slice($internalLinks, 0, $maxLinks);

            if (!empty($internalLinks)) {

                $totalSections = count($normalized);
                $totalLinks    = count($internalLinks);

                // 🔥 Se não tem seção, aborta distribuição
                if ($totalSections === 0) {

                    $normalized[] = [
                        'id' => 1,
                        'level' => 'h2',
                        'heading' => 'Conteúdo',
                        'paragraph' => '',
                        '_internal_links' => []
                    ];

                    $totalSections = 1;
                }

                for ($i = 0; $i < $totalLinks; $i++) {

                    $pos = $i % $totalSections;

                    $normalized[$pos]['_internal_links'][] = $internalLinks[$i];
                }
            }
        }

        update_post_meta(
            $postId,
            '_pga_outline_sections',
            wp_json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        update_post_meta($postId, '_pga_job_status', 'outline_done');

        return [
            'post_id'  => $postId,
            'sections' => $normalized,
        ];
    }

    private static function normalize_outline($resp)
    {
        if (is_array($resp)) {

            if (isset($resp['sections']) && is_array($resp['sections'])) {
                return $resp['sections'];
            }

            if (isset($resp[0]) && is_array($resp[0])) {
                return $resp;
            }
        }

        if (is_string($resp)) {
            $decoded = json_decode($resp, true);

            if (isset($decoded['sections'])) {
                return $decoded['sections'];
            }

            if (isset($decoded[0])) {
                return $decoded;
            }
        }

        return [];
    }

    public static function rest_generate_outline(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('pga_invalid_post', 'Post ID inválido.');
        }

        $result = self::generate_outline($postId);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'       => true,
            'sections' => $result['sections'],
        ];
    }

    public static function generate_section(int $postId, string $sectionId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $sectionsRaw = get_post_meta($postId, '_pga_outline_sections', true);

        if (!$sectionsRaw) {
            return new WP_Error('pga_no_outline', 'Outline não encontrado.');
        }

        // 🔥 HÍBRIDO
        if (is_array($sectionsRaw)) {

            // Caso array contendo JSON string
            if (isset($sectionsRaw[0]) && is_string($sectionsRaw[0])) {
                $sections = json_decode($sectionsRaw[0], true);
            } else {
                $sections = $sectionsRaw;
            }
        } elseif (is_string($sectionsRaw)) {

            $sections = json_decode($sectionsRaw, true);
        } else {
            $sections = [];
        }

        if (!is_array($sections) || empty($sections)) {
            return new WP_Error('pga_outline_invalid', 'Outline inválido "section".');
        }

        $section = null;
        $index   = 0;

        foreach ($sections as $i => $sec) {
            if ((string)($sec['id'] ?? '') === (string)$sectionId) {
                $section = $sec;
                $index   = $i + 1;
                break;
            }
        }

        if (!$section) {
            return new WP_Error('pga_section_not_found', 'Seção não encontrada.');
        }

        $metaKey = '_pga_section_content_' . sanitize_key($sectionId);

        // 🔒 Evita regeração
        if (get_post_meta($postId, $metaKey, true)) {
            return ['already_done' => true];
        }

        $title    = get_the_title($postId);
        $length   = get_post_meta($postId, '_pga_outline_length', true) ?: 'medium';
        $locale   = get_post_meta($postId, '_pga_outline_locale', true) ?: 'pt_BR';
        $context = get_post_meta($postId, '_pga_rss_context', true) ?: [];
        $url     = $context['link'] ?? '';
        $font    = $context['source'] ?? '';

        $sectionsCount = count($sections);

        /*
        |--------------------------------------------------------------------------
        | 🔗 LINK INTERNO INJETADO NO PROMPT
        |--------------------------------------------------------------------------
        */

        $internalLinks = $section['_internal_links'] ?? [];

        $linkInstruction = '';

        if (!empty($internalLinks)) {
            $linkInstruction .= "OBRIGATÓRIO INSERIR OS SEGUINTES LINKS INTERNOS:\n";

            foreach ($internalLinks as $link) {

                $anchor = esc_html($link['anchor']);
                $href   = esc_url($link['url']);

                $linkInstruction .=
                    "No lugar de \"{$anchor}\" resuma para algum termo referente ao título:\n"
                    . "- Use nesse formato HTML:\n"
                    . "<a href=\"{$href}\">'termo'</a>\n"
                    . "- Não altere a URL\n"
                    . "- Insira o link de maneira fluida, se encaixando no texto, nada de \"clique para saber mais\", \"acesse o link\"... "
                    . "ou seja, zero CTA em texto, apenas o texto fluído\n"
                    . "- Ex: \"Quando Jorge Kimberland <a href target>inventou a invenção x</a>, todos se alegraram.\"\n"
                    . "- Use cada link apenas uma vez\n\n";
            }

            $linkInstruction .=
                "REGRA:\n"
                . "- Distribua os links naturalmente ao longo do texto\n"
                . "- Nunca coloque todos os links no mesmo parágrafo\n"
                . "- Não crie seção apenas para link\n\n";
        }

        /*
        |--------------------------------------------------------------------------
        | 🧠 PROMPT BASE
        |--------------------------------------------------------------------------
        */

        $prompt = AlphaSuite_Prompts::build_section_rss_prompt(
            $title,
            $section,
            $length,
            $locale,
            $sectionsCount,
            (string)$index,
            $url,
            $font
        );

        // 🔥 adiciona instrução de link ao final
        $prompt .= "\n\n" . $linkInstruction;

        $resp = AlphaSuite_AI::complete(
            $prompt,
            [],
            [
                'max_tokens'  => 2000,
                'temperature' => 0.6,
                'template'    => 'section',
            ]
        );

        if (is_wp_error($resp)) {
            return AlphaSuite_FailJob::fail_job($postId, $resp);
            return $resp;
        }

        $content_html = trim((string)($resp ?? ''));

        if ($content_html === '') {
            return new WP_Error('pga_section_empty', 'Conteúdo vazio.');
        }

        update_post_meta($postId, $metaKey, $content_html);

        return [
            'post_id'    => $postId,
            'section_id' => $sectionId,
        ];
    }

    public static function rest_generate_section(WP_REST_Request $req)
    {
        $postId    = intval($req->get_param('post_id'));
        $sectionId = (string)$req->get_param('section_id');

        if (!$postId || !$sectionId) {
            return new WP_Error('pga_invalid_params', 'Parâmetros inválidos.');
        }

        $result = self::generate_section($postId, $sectionId);

        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'ok'         => true,
            'section_id' => $sectionId,
        ];
    }

    public static function finalize(int $postId)
    {
        $postId = intval($postId);

        if (!$postId || !get_post($postId)) {
            return new WP_Error('pga_invalid_post', 'Post inválido.');
        }

        $sectionsJson      = get_post_meta($postId, '_pga_outline_sections', true);
        $metaDescription   = get_post_meta($postId, '_pga_meta_description', true);
        $meta_title        = get_post_meta($postId, '_pga_chosen_title', true);

        if (!$sectionsJson) {
            return new WP_Error('pga_no_outline', 'Outline não encontrado.');
        }

        $sections = json_decode($sectionsJson, true);

        if (!is_array($sections)) {
            return new WP_Error('pga_outline_invalid', 'Outline inválido "finalize".');
        }

        $contentParts = [];

        foreach ($sections as $sec) {

            $sid = sanitize_key($sec['id'] ?? '');
            if (!$sid) continue;

            $metaKey = '_pga_section_content_' . $sid;
            $text    = get_post_meta($postId, $metaKey, true);

            if (!$text) continue;

            $contentParts[] = $text; // 🔥 NÃO adiciona H2 manualmente
        }

        $content = trim(implode("\n\n", $contentParts));

        if ($content === '') {
            return new WP_Error('pga_empty_content', 'Nenhuma seção encontrada.');
        }

        // 🔥 Remove H1
        $content = preg_replace('#</?h1[^>]*>#i', '', $content);

        // 🔥 Remove o PRIMEIRO H2 (introdução geral)
        $content = preg_replace('#<h2[^>]*>.*?</h2>#i', '', $content, 1);

        $faq_json = get_post_meta($postId, '_pga_faq_jsonld', true);

        if ($faq_json) {
            $faq = is_string($faq_json)
                ? json_decode($faq_json, true)
                : $faq_json;

            if (is_array($faq)) {
                $faq_block = AlphaSuite_FAQ::render_faq_block($faq, $content);

                if ($faq_block !== '') {
                    $content .= "\n\n" . $faq_block;
                }
            }
        }

        // Atualiza conteúdo
        wp_update_post([
            'ID'           => $postId,
            'post_content' => $content,
        ]);

        // 🔥 PUBLICA O POST
        wp_update_post([
            'ID'          => $postId,
            'post_status' => 'publish',
        ]);

        update_post_meta($postId, '_pga_job_status', 'finalized');

        if (class_exists('AlphaSuite_SEO')) {
            AlphaSuite_SEO::apply_meta($postId, [
                'title'         => $meta_title,
                'description'   => $metaDescription,
                'focus_keyword' => '',
            ]);
        }

        return [
            'post_id' => $postId,
            'title'   => get_the_title($postId),
            'url'     => get_permalink($postId),
        ];
    }

    public static function rest_finalize(WP_REST_Request $req)
    {
        $postId = intval($req->get_param('post_id'));

        if (!$postId) {
            return new WP_Error('pga_invalid_post', 'Post ID inválido.');
        }

        $result = self::finalize($postId);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response([
            'ok'      => true,
            'post_id' => $result['post_id'],
            'title'   => $result['title'],
            'url'     => $result['url'],
        ]);
    }

    public static function get_rss(WP_REST_Request $req)
    {
        $params = $req->get_json_params();
        $url    = trim($params['url'] ?? '');
        $limit  = min(20, intval($params['limit'] ?? 10));

        if (!$url) {
            return new WP_Error('no_url', 'URL is required', ['status' => 400]);
        }

        /*
    |--------------------------------------------------------------------------
    | 1️⃣ Detecta tipo de URL
    |--------------------------------------------------------------------------
    */

        // Google News → converte para RSS search
        if (str_contains($url, 'news.google.com')) {

            $parsed = wp_parse_url($url);

            if (empty($parsed['query'])) {
                return new WP_Error('invalid_url', 'Invalid Google News URL', ['status' => 400]);
            }

            $rssUrl = 'https://news.google.com/rss/search?' . $parsed['query'];
        } else {

            // Se já for RSS
            if (preg_match('#(rss|feed|\.xml)$#i', $url)) {
                $rssUrl = $url;
            } else {

                // Tenta descobrir feed automaticamente
                $rssUrl = self::discover_feed($url);

                if (!$rssUrl) {
                    return new WP_Error(
                        'no_feed_found',
                        'Nenhum RSS/XML encontrado para este domínio.',
                        ['status' => 404]
                    );
                }
            }
        }

        /*
    |--------------------------------------------------------------------------
    | 2️⃣ Fetch RSS
    |--------------------------------------------------------------------------
    */

        $response = wp_remote_get($rssUrl, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (AlphaSuiteRSS)'
            ]
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('fetch_error', 'Failed to fetch RSS', ['status' => 500]);
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            return new WP_Error('empty_body', 'Empty RSS body', ['status' => 500]);
        }

        /*
    |--------------------------------------------------------------------------
    | 3️⃣ Parse XML
    |--------------------------------------------------------------------------
    */

        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($rss === false || empty($rss->channel->item)) {
            return new WP_Error('invalid_rss', 'Invalid RSS feed', ['status' => 500]);
        }

        /*
    |--------------------------------------------------------------------------
    | 4️⃣ Extrai e normaliza itens
    |--------------------------------------------------------------------------
    */

        $items = [];

        foreach ($rss->channel->item as $item) {

            $title = trim((string) $item->title);

            // remove " - Fonte"
            $title = preg_replace('/\s+-\s+.+$/', '', $title);

            $link = trim((string) $item->link);

            // ignora redirect do Google
            if (str_contains($link, 'news.google.com')) {
                $link = '';
            }

            $source = '';
            if (!empty($item->source)) {
                $source = trim((string) $item->source);
            }

            $items[] = [
                'title'   => $title,
                'link'    => $link,
                'source'  => $source,
                'pubDate' => (string) ($item->pubDate ?? ''),
                'hash'    => md5(strtolower($title . '|' . $source))
            ];

            if (count($items) >= $limit) break;
        }

        /*
    |--------------------------------------------------------------------------
    | 5️⃣ Retorno final
    |--------------------------------------------------------------------------
    */

        return rest_ensure_response([
            'rss_url' => $rssUrl,
            'count'   => count($items),
            'items'   => $items
        ]);
    }

    private static function discover_feed($url)
    {
        $base = rtrim($url, '/');

        $candidates = [
            $base . '/feed',
            $base . '/rss',
            $base . '/rss.xml',
            $base . '/feed.xml'
        ];

        foreach ($candidates as $try) {
            $res = wp_remote_get($try, ['timeout' => 10]);
            if (!is_wp_error($res)) {
                $body = wp_remote_retrieve_body($res);
                if ($body && str_contains($body, '<rss')) {
                    return $try;
                }
            }
        }

        return false;
    }

    public static function process_feed($rssUrl, $generator_id = 0)
    {
        $items = self::fetch_feed_items($rssUrl, 3);

        if (empty($items)) return;

        foreach ($items as $item) {

            if (self::already_exists($item['hash'])) {
                continue;
            }

            $postId = self::create_base_post($item);

            self::generate_title($postId);
            self::generate_slug($postId);
            self::generate_meta($postId);
            $outline = self::generate_outline($postId);
            $sections = self::normalize_outline($outline);

            foreach ($sections as $sec) {
                if (!empty($sec['id'])) {
                    self::generate_section($postId, (string)$sec['id']);
                }
            }

            $result = self::finalize($postId);

            if (is_wp_error($result)) {
                return;
            }

            // 🔥 Aqui sim marca como done
            self::mark_as_done($generator_id, $item['hash'], $postId);

            if (!empty($item['link'])) {
                self::extract_image($postId, $item['link']);
            }

            break; // 👈 recomendável: 1 post por cron
        }
    }

    public static function mark_as_done($generator_id, $hash, $postId)
    {
        global $wpdb;

        if (!$generator_id || !$hash) {
            return;
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            "{$wpdb->prefix}pga_generator_items",
            [
                'generator_id' => $generator_id,
                'keyword'      => $hash,
                'status'       => 'done',
                'post_id'      => $postId,
                'created_at'   => current_time('mysql'),
                'generated_at' => current_time('mysql'),
            ]
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }


    public static function fetch_feed_items($rssUrl, $limit = 5)
    {
        $response = wp_remote_get($rssUrl, [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (AlphaSuiteRSS)'
            ]
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) {
            return [];
        }

        libxml_use_internal_errors(true);
        $rss = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if (!$rss || empty($rss->channel->item)) {
            return [];
        }

        $items = [];

        foreach ($rss->channel->item as $item) {

            $title = trim((string) $item->title);
            $title = preg_replace('/\s+-\s+.+$/', '', $title);

            $link = trim((string) $item->link);

            // ignora redirect do Google
            if (empty($link)) {
                continue;
            }


            if (empty($link)) {
                continue;
            }

            // 🔥 NORMALIZA LINK (remove parâmetros)
            $normalizedLink = preg_replace('/\?.*/', '', strtolower(trim($link)));

            $source = '';
            if (!empty($item->source)) {
                $source = trim((string) $item->source);
            }

            $items[] = [
                'title'   => $title,
                'link'    => $link,
                'source'  => $source,
                'pubDate' => (string) ($item->pubDate ?? ''),
                'hash'    => md5($normalizedLink)
            ];

            if (count($items) >= $limit) break;
        }

        return $items;
    }

    public static function create_base_post(array $item)
    {
        $postId = wp_insert_post([
            'post_title'  => $item['title'],
            'post_status' => 'draft',
            'post_type'   => 'posts_orion'
        ]);

        update_post_meta($postId, '_pga_news_hash', $item['hash']);

        update_post_meta($postId, '_pga_rss_context', [
            'link'   => $item['link'],
            'source' => $item['source']
        ]);

        update_post_meta($postId, '_pga_rss_seed_title', $item['title']);

        // 🔥 ESSENCIAL
        update_post_meta($postId, '_pga_outline_length', 'short');
        update_post_meta($postId, '_pga_outline_locale', 'pt_BR');
        update_post_meta($postId, '_pga_length', 'short');
        update_post_meta($postId, '_pga_locale', 'pt_BR');

        update_post_meta($postId, '_pga_job_status', 'started');

        return $postId;
    }

    private static function already_exists($hash)
    {
        // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        $q = new WP_Query([
            'post_type'              => 'posts_orion',
            'meta_key'               => '_pga_news_hash',
            'meta_value'             => $hash,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value

        return $q->have_posts();
    }
}
