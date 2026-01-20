<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_REST_Ws_Generator
{
    const NS = 'pga/v1';

    const STORY_CPT = PluginsAlpha_WS_CPT::POST_TYPE;

    // metas
    const META_PAYLOAD     = '_pga_ws_payload';
    const META_SLIDES      = '_pga_ws_slides';
    const META_THEME       = '_pga_ws_theme';
    const META_AI_RAW      = '_pga_ws_ai_raw';
    const META_SOURCE      = '_pga_ws_source_post';
    const META_TITLE       = '_pga_ws_meta_title';
    const META_DESC        = '_pga_ws_meta_desc';
    const META_SLUG        = '_pga_ws_slug';
    const META_LOGO_ID     = '_pga_ws_publisher_logo_id';
    const META_POSTER_ID   = '_pga_ws_poster_id';
    const META_ACCENT      = '_pga_ws_accent_color';
    const META_TEXT_COLOR  = '_pga_ws_text_color';
    const META_LOCALE      = '_pga_ws_locale';
    const META_PAGES       = '_pga_ws_pages';

    // ---------------------- utils (mesmo estilo do Orion) ----------------------
    private static function verify_nonce($req)
    {
        $n = $req->get_header('X-WP-Nonce');
        if (!$n || !wp_verify_nonce($n, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Nonce inválido ou ausente.', ['status' => 403]);
        }
        return true;
    }

    private static function clean($s): string
    {
        return sanitize_text_field((string)$s);
    }

    private static function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return new WP_Error('pga_ws_exception', 'Exceção interna.', [
                'status' => 500,
                'msg'    => $e->getMessage(),
            ]);
        }
    }

    private static function get_story_or_error(int $story_id)
    {
        if ($story_id <= 0) {
            return new WP_Error('pga_ws_bad_story', 'story_id inválido.', ['status' => 400]);
        }

        $post = get_post($story_id);
        if (!$post) {
            return new WP_Error('pga_ws_not_found', 'Story não encontrado.', ['status' => 404]);
        }

        // permissão por post
        if (!current_user_can('edit_post', $story_id)) {
            return new WP_Error('pga_ws_forbidden', 'Sem permissão para editar este story.', ['status' => 403]);
        }

        return $post;
    }
    private static function normalize_color($hex, $fallback): string
    {
        $hex = strtoupper(trim((string)$hex));
        if ($hex === '') return $fallback;
        if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) return $fallback;
        return $hex;
    }

    private static function att_url(int $att_id): string
    {
        if ($att_id <= 0) return '';
        $u = wp_get_attachment_image_url($att_id, 'full');
        return $u ? $u : '';
    }

    private static function as_story_payload(\WP_Post $story): array
    {
        $id = (int) $story->ID;

        $meta_title = (string) get_post_meta($id, self::META_TITLE, true);
        $meta_desc  = (string) get_post_meta($id, self::META_DESC, true);

        $logo_id   = (int) get_post_meta($id, self::META_LOGO_ID, true);
        $poster_id = (int) get_post_meta($id, self::META_POSTER_ID, true);

        $accent = (string) get_post_meta($id, self::META_ACCENT, true);
        $textc  = (string) get_post_meta($id, self::META_TEXT_COLOR, true);

        $source = (int) get_post_meta($id, self::META_SOURCE, true);

        $out = [
            'id'          => $id,
            'status'      => (string) $story->post_status,
            'slug' => [
                'post_name' => (string) $story->post_name,
                'custom'    => (string) get_post_meta($id, self::META_SLUG, true),
            ],
            'post_title'  => (string) get_the_title($id),
            'meta_title'  => $meta_title,
            'meta_desc'   => $meta_desc,

            'logo_id'     => $logo_id,
            'logo_url'    => self::att_url($logo_id),

            'poster_id'   => $poster_id,
            'poster_url'  => self::att_url($poster_id),

            'accent_color' => self::normalize_color($accent, '#3B82F6'),
            'text_color'  => self::normalize_color($textc, '#FFFFFF'),

            'source_post' => $source,

            'edit_url'    => get_edit_post_link($id, '') ?: '',
            'view_url'    => get_permalink($id) ?: '',
            'created_at' => (string) $story->post_date,
        ];

        return $out;
    }

    private static function json_params(WP_REST_Request $req): array
    {
        $p = $req->get_json_params();
        if (empty($p)) $p = $req->get_params();
        return is_array($p) ? $p : [];
    }

    private static function require_classes()
    {
        if (!class_exists('PluginsAlpha_AI')) {
            return new WP_Error('pga_ws_ai_missing', 'PluginsAlpha_AI não encontrado.', ['status' => 500]);
        }
        if (!class_exists('PluginsAlpha_Prompts')) {
            return new WP_Error('pga_ws_prompts_missing', 'PluginsAlpha_Prompts não encontrado.', ['status' => 500]);
        }
        return true;
    }

    private static function get_payload(int $story_id): array
    {
        $p = get_post_meta($story_id, self::META_PAYLOAD, true);
        return is_array($p) ? $p : [];
    }

    private static function merge_payload(array $base, array $patch): array
    {
        // merge recursivo simples (patch sobrescreve base)
        foreach ($patch as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k])) {
                $base[$k] = self::merge_payload($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }

    /**
     * Normaliza para sempre retornar o mesmo formato.
     */
    private static function canonical_payload(int $story_id, array $p): array
    {
        $p = is_array($p) ? $p : [];

        // garante sub-blocos
        $p['meta']     = isset($p['meta']) && is_array($p['meta']) ? $p['meta'] : [];
        $p['layout']   = isset($p['layout']) && is_array($p['layout']) ? $p['layout'] : [];
        $p['settings'] = isset($p['settings']) && is_array($p['settings']) ? $p['settings'] : [];
        $p['source']   = isset($p['source']) && is_array($p['source']) ? $p['source'] : [];

        // meta
        $p['meta']['title'] = self::clean($p['meta']['title'] ?? '');
        $p['meta']['desc']  = sanitize_textarea_field((string)($p['meta']['desc'] ?? ''));

        // layout
        $p['layout']['theme'] = self::clean($p['layout']['theme'] ?? (string)get_post_meta($story_id, self::META_THEME, true));
        if ($p['layout']['theme'] === '') $p['layout']['theme'] = 'theme-normal';

        $p['layout']['slidesCount'] = absint($p['layout']['slidesCount'] ?? 0);
        if ($p['layout']['slidesCount'] <= 0) {
            // tenta inferir pelo META_SLIDES
            $pages = get_post_meta($story_id, self::META_SLIDES, true);
            $p['layout']['slidesCount'] = is_array($pages) ? max(1, count($pages)) : 0;
            if ($p['layout']['slidesCount'] <= 0) $p['layout']['slidesCount'] = 6;
        }

        $rawSlides = $p['layout']['slides'] ?? [];
        $p['layout']['slides'] = self::normalize_slides(is_array($rawSlides) ? $rawSlides : [], (int)$p['layout']['slidesCount']);

        // cta_pages sempre derivado de slides (fonte única)
        $cta_pages = [];
        foreach ($p['layout']['slides'] as $s) {
            if (!empty($s['cta_enabled'])) $cta_pages[] = (int)($s['index'] ?? 0);
        }
        $p['layout']['cta_pages'] = array_values(array_filter(array_unique(array_map('absint', $cta_pages))));

        // settings
        $p['settings']['publisher_logo_id'] = absint($p['settings']['publisher_logo_id'] ?? get_post_meta($story_id, self::META_LOGO_ID, true));
        $p['settings']['poster_id']         = absint($p['settings']['poster_id'] ?? get_post_meta($story_id, self::META_POSTER_ID, true));
        $p['settings']['accent_color']      = self::normalize_color($p['settings']['accent_color'] ?? get_post_meta($story_id, self::META_ACCENT, true), '#3B82F6');
        $p['settings']['text_color']        = self::normalize_color($p['settings']['text_color'] ?? get_post_meta($story_id, self::META_TEXT_COLOR, true), '#FFFFFF');
        $p['settings']['locale']            = self::clean($p['settings']['locale'] ?? get_post_meta($story_id, self::META_LOCALE, true));
        if ($p['settings']['locale'] === '') $p['settings']['locale'] = 'pt_BR';

        // source
        $p['source']['post_id']       = absint($p['source']['post_id'] ?? get_post_meta($story_id, self::META_SOURCE, true));
        $p['source']['post_ids']      = isset($p['source']['post_ids']) && is_array($p['source']['post_ids'])
            ? array_values(array_filter(array_map('absint', $p['source']['post_ids'])))
            : [];
        $p['source']['publish_start'] = self::clean($p['source']['publish_start'] ?? '');

        // opcional: mantém mode se existir
        if (isset($p['mode'])) {
            $p['mode'] = ($p['mode'] === 'bulk') ? 'bulk' : 'single';
        }

        return $p;
    }

    // ---------------------- rotas ----------------------
    public static function register_routes()
    {
        register_rest_route(self::NS, '/ws/story', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'story_get'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('pga/v1', '/ws/generate', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'generate'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route(self::NS, '/ws/story/save', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'story_save'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route(self::NS, '/ws/slide/image/generate', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'slide_image_generate'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route(self::NS, '/ws/slide/image/select', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'slide_image_select'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public static function slide_image_select(\WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {

            $p = self::json_params($req);

            $story_id = absint($p['story_id'] ?? 0);
            $index    = intval($p['index'] ?? -1);
            $url      = esc_url_raw((string)($p['url'] ?? ''));
            $alt      = sanitize_text_field((string)($p['alt'] ?? ''));

            $story = self::get_story_or_error($story_id);
            if (is_wp_error($story)) return $story;

            if ($index < 0) {
                return new \WP_Error('pga_ws_bad_index', 'index inválido.', ['status' => 400]);
            }
            if ($url === '') {
                return new \WP_Error('pga_ws_no_url', 'URL da imagem não informada.', ['status' => 400]);
            }

            // pages
            $pages = get_post_meta($story_id, '_pga_ws_pages', true);
            if (!is_array($pages) || empty($pages)) {
                $pages = get_post_meta($story_id, self::META_SLIDES, true);
            }
            if (!is_array($pages) || empty($pages)) {
                return new \WP_Error('pga_ws_pages', 'Nenhuma página de story encontrada.', ['status' => 400]);
            }
            if (!isset($pages[$index]) || !is_array($pages[$index])) {
                return new \WP_Error('pga_ws_page_index', 'Página inexistente para este índice.', ['status' => 400]);
            }

            // alt fallback
            if ($alt === '') {
                $h = trim((string)($pages[$index]['heading'] ?? ''));
                $alt = $h !== '' ? $h : 'Web Story';
            }

            // baixa + cria attachment (sem estourar RAM)
            $att_id = self::create_attachment_from_url($url, $story_id, $alt, 'pexels');
            if (is_wp_error($att_id)) return $att_id;

            $att_id  = (int)$att_id;
            $img_url = wp_get_attachment_image_url($att_id, 'full') ?: '';

            // salva no slide
            $pages[$index]['image_id']  = $att_id;
            $pages[$index]['image_url'] = $img_url;
            $pages[$index]['image']     = $img_url;
            update_post_meta($story_id, '_pga_ws_pages', $pages);

            return rest_ensure_response([
                'ok'        => true,
                'index'     => $index,
                'image_id'  => $att_id,
                'image_url' => $img_url,
            ]);
        });
    }
    private static function create_attachment_from_url(string $url, int $post_id, string $alt = '', string $source = 'remote')
    {
        if ($post_id <= 0 || $url === '') return new \WP_Error('pga_attach_bad', 'Parâmetros inválidos.');

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        if (!function_exists('wp_read_image_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $tmp = download_url($url, 60);
        if (is_wp_error($tmp)) return $tmp;

        $name = basename(parse_url($url, PHP_URL_PATH) ?: 'image.jpg');
        if (!$name) $name = 'image.jpg';

        $file_array = [
            'name'     => sanitize_file_name($name),
            'tmp_name' => $tmp,
        ];

        $att_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($att_id)) {
            @unlink($tmp);
            return $att_id;
        }

        if ($alt !== '') {
            update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
        }
        update_post_meta($att_id, '_pga_image_source', $source);

        return (int)$att_id;
    }

    public static function slide_image_generate(\WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {

            $p = self::json_params($req);

            $story_id = absint($p['story_id'] ?? 0);
            $index    = intval($p['index'] ?? -1);
            $brief    = sanitize_textarea_field((string)($p['brief'] ?? ''));
            $force    = !empty($p['force']);

            $story = self::get_story_or_error($story_id);
            if (is_wp_error($story)) return $story;

            if ($index < 0) {
                return new \WP_Error('pga_ws_bad_index', 'index inválido.', ['status' => 400]);
            }

            // pages
            $pages = get_post_meta($story_id, '_pga_ws_pages', true);
            if (!is_array($pages) || empty($pages)) {
                $pages = get_post_meta($story_id, self::META_SLIDES, true);
            }
            if (!is_array($pages) || empty($pages)) {
                return new \WP_Error('pga_ws_pages', 'Nenhuma página de story encontrada.', ['status' => 400]);
            }

            if (!isset($pages[$index]) || !is_array($pages[$index])) {
                return new \WP_Error('pga_ws_page_index', 'Página inexistente para este índice.', ['status' => 400]);
            }

            // se já existe e não é force
            if (!$force && !empty($pages[$index]['image_id'])) {
                $img_id  = absint($pages[$index]['image_id']);
                $img_url = $img_id ? (wp_get_attachment_image_url($img_id, 'full') ?: '') : '';
                return rest_ensure_response([
                    'ok' => true,
                    'skipped' => true,
                    'index' => $index,
                    'image_id' => $img_id,
                    'image_url' => $img_url,
                ]);
            }

            // contexto do slide
            $heading = trim((string)($pages[$index]['heading'] ?? ''));
            $body    = trim((string)($pages[$index]['body'] ?? ''));
            $slidePrompt = trim((string)($pages[$index]['prompt'] ?? ''));

            $ctx = '';
            if ($brief !== '') $ctx = $brief;
            else if ($slidePrompt !== '') $ctx = $slidePrompt;
            else $ctx = trim($heading . ' ' . $body);

            if ($ctx === '') {
                return new \WP_Error('pga_ws_no_context', 'Sem contexto para gerar imagem (brief/prompt/heading/body vazio).', ['status' => 400]);
            }

            // provider vindo do settings
            $provider = 'pollinations';
            if (class_exists('PluginsAlpha_Settings')) {
                $opts = PluginsAlpha_Settings::get();
                $orionPosts = $opts['orion_posts'] ?? [];
                if (!empty($orionPosts['images_provider'])) {
                    $provider = (string)$orionPosts['images_provider'];
                }
            }
            $provider = strtolower(trim($provider));

            // Se for banco, retornamos 3 opções (sem salvar nada)
            if ($provider === 'pexels') {

                if (!class_exists('PluginsAlpha_Settings')) {
                    return new \WP_Error('pga_pexels_no_cfg', 'Configurações não encontradas.', ['status' => 500]);
                }

                $opts = PluginsAlpha_Settings::get();
                $api  = $opts['apis']['pexels'] ?? [];
                $key  = trim((string)($api['key'] ?? ''));

                if ($key === '') {
                    return new \WP_Error('pga_pexels_no_key', 'Chave Pexels não configurada.', ['status' => 400]);
                }

                // query curta p/ banco
                $query = $ctx;
                if (class_exists('PluginsAlpha_Prompts') && method_exists('PluginsAlpha_Prompts', 'build_image_prompt')) {
                    // usa teu gerador de query curta pra pexels/unsplash
                    $meta = PluginsAlpha_Prompts::build_image_prompt('ws_generator', '', '', 'en', 'pexels');
                    $meta .= "\nContexto obrigatório: {$ctx}\n";
                    if (class_exists('PluginsAlpha_AI')) {
                        $ai = PluginsAlpha_AI::complete($meta, ['prompt' => 'string'], []);
                        if (!is_wp_error($ai)) {
                            $q = trim((string)($ai['prompt'] ?? ''));
                            if ($q !== '') $query = $q;
                        }
                    }
                }

                $orientation = 'portrait'; // story
                $endpoint = add_query_arg(
                    [
                        'query'       => $query,
                        'per_page'    => 3,
                        'page'        => 1,
                        'orientation' => $orientation,
                    ],
                    'https://api.pexels.com/v1/search'
                );

                $res = wp_remote_get($endpoint, [
                    'timeout' => 30,
                    'headers' => ['Authorization' => $key],
                ]);

                if (is_wp_error($res)) return $res;

                $code = wp_remote_retrieve_response_code($res);
                $bodyJson = wp_remote_retrieve_body($res);
                if ($code < 200 || $code >= 300 || !$bodyJson) {
                    return new \WP_Error('pga_pexels_http', "Erro HTTP {$code} no Pexels.", ['status' => 500]);
                }

                $json = json_decode($bodyJson, true);
                $photos = $json['photos'] ?? [];
                if (!is_array($photos) || empty($photos)) {
                    return new \WP_Error('pga_pexels_empty', 'Nenhuma imagem encontrada no Pexels.', ['status' => 404]);
                }

                $options = [];
                foreach (array_slice(array_values($photos), 0, 3) as $ph) {
                    if (!is_array($ph)) continue;

                    $pid = isset($ph['id']) ? (int)$ph['id'] : 0;
                    $src = $ph['src'] ?? [];

                    // thumb e full (o que você quiser exibir vs baixar)
                    $thumb = $src['medium'] ?? $src['small'] ?? $src['tiny'] ?? ($src['portrait'] ?? ($src['large'] ?? ''));
                    $full  = $src['portrait'] ?? $src['large2x'] ?? $src['large'] ?? $src['original'] ?? '';

                    if ($pid && $full) {
                        $options[] = [
                            'id'    => $pid,
                            'thumb' => (string)$thumb,
                            'full'  => (string)$full,
                        ];
                    }
                }

                if (empty($options)) {
                    return new \WP_Error('pga_pexels_no_options', 'Não foi possível montar opções de imagem.', ['status' => 500]);
                }

                // opcional: guarda as opções no meta pra debug
                update_post_meta($story_id, '_pga_ws_last_image_options_' . $index, $options);

                return rest_ensure_response([
                    'ok'      => true,
                    'mode'    => 'pick',
                    'index'   => $index,
                    'provider' => 'pexels',
                    'options' => $options,
                    'query'   => $query,
                ]);
            }

            // Caso provider NÃO seja banco, gera direto (IA/pollinations/etc)
            if (!class_exists('PluginsAlpha_Images')) {
                return new \WP_Error('pga_ws_images_missing', 'PluginsAlpha_Images ausente.', ['status' => 500]);
            }

            $alt = $heading !== '' ? $heading : $ctx;

            $att_id = PluginsAlpha_Images::generate_story_by_settings($ctx, $story_id, $alt);
            if (is_wp_error($att_id)) return $att_id;

            $att_id = (int)$att_id;
            $img_url = wp_get_attachment_image_url($att_id, 'full') ?: '';

            $pages[$index]['image_id']  = $att_id;
            $pages[$index]['image_url'] = $img_url;
            $pages[$index]['image']     = $img_url;
            update_post_meta($story_id, '_pga_ws_pages', $pages);

            return rest_ensure_response([
                'ok'        => true,
                'mode'      => 'direct',
                'index'     => $index,
                'image_id'  => $att_id,
                'image_url' => $img_url,
            ]);
        });
    }

    public static function story_save(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {
            $p = self::json_params($req);

            $story_id = absint($p['story_id'] ?? 0);

            // ---------------------------
            // STATUS (normaliza)
            // ---------------------------
            $status = sanitize_key((string)($p['status'] ?? ''));
            if (!in_array($status, ['draft', 'publish', 'future', 'trash'], true)) {
                $status = 'draft';
            }

            $want_publish = ($status === 'publish');
            $want_future  = ($status === 'future');

            // ---------------------------
            // PUBLISH_AT (somente future)
            // ---------------------------
            $publish_at_raw = (string)($p['publish_at'] ?? '');
            $publish_at = '';

            if ($want_future) {
                $publish_at = sanitize_text_field($publish_at_raw);

                if ($publish_at === '') {
                    return new WP_Error('pga_ws_future_date', 'Defina a data do agendamento.', ['status' => 400]);
                }

                $ts = strtotime($publish_at);
                if (!$ts) {
                    return new WP_Error('pga_ws_future_date_invalid', 'Data de agendamento inválida.', ['status' => 400]);
                }
            }

            // ---------------------------
            // PAYLOAD
            // ---------------------------
            $meta     = is_array($p['meta'] ?? null) ? $p['meta'] : [];
            $layout   = is_array($p['layout'] ?? null) ? $p['layout'] : [];
            $settings = is_array($p['settings'] ?? null) ? $p['settings'] : [];
            $pages    = is_array($p['pages'] ?? null) ? $p['pages'] : [];

            if (empty($pages)) {
                return new WP_Error('pga_ws_no_pages', 'Sem slides para salvar.', ['status' => 400]);
            }

            $meta_title = self::clean($meta['title'] ?? '');
            $meta_desc  = sanitize_textarea_field((string)($meta['desc'] ?? ''));

            $poster_id_req = absint($settings['poster_id'] ?? 0);

            // publish ou future exigem meta + thumb
            if ($want_publish || $want_future) {
                if ($meta_title === '') {
                    return new WP_Error('pga_ws_pub_title', 'Título é obrigatório.', ['status' => 400]);
                }
                if ($meta_desc === '') {
                    return new WP_Error('pga_ws_pub_desc', 'Meta descrição é obrigatória.', ['status' => 400]);
                }
                if ($poster_id_req <= 0) {
                    return new WP_Error('pga_ws_pub_thumb', 'Thumbnail é obrigatória.', ['status' => 400]);
                }
            }

            // ---------------------------
            // TRASH
            // ---------------------------
            if ($status === 'trash') {
                if ($story_id <= 0) {
                    return new WP_Error('pga_ws_no_story', 'Sem story_id para excluir.', ['status' => 400]);
                }
                $story = self::get_story_or_error($story_id);
                if (is_wp_error($story)) return $story;

                wp_trash_post($story_id);

                return rest_ensure_response([
                    'ok'       => true,
                    'story_id' => $story_id,
                    'status'   => 'trash',
                ]);
            }

            // ---------------------------
            // CREATE / UPDATE POST
            // ---------------------------
            $now_local = current_time('mysql');

            if ($story_id > 0) {
                $story = self::get_story_or_error($story_id);
                if (is_wp_error($story)) return $story;

                $upd = ['ID' => $story_id];

                if ($meta_title !== '') {
                    $upd['post_title'] = $meta_title;
                }

                if ($want_publish) {
                    // evita WP “voltar” pra future por post_date futuro
                    $upd['post_status']   = 'publish';
                    $upd['post_date']     = $now_local;
                    $upd['post_date_gmt'] = get_gmt_from_date($now_local);
                } elseif ($want_future) {
                    $upd['post_status']   = 'future';
                    $upd['post_date']     = $publish_at;
                    $upd['post_date_gmt'] = get_gmt_from_date($publish_at);
                } else {
                    $upd['post_status'] = 'draft';
                }

                $r = wp_update_post($upd, true);
                if (is_wp_error($r)) {
                    return new WP_Error('pga_ws_update_fail', $r->get_error_message(), ['status' => 500]);
                }
            } else {
                $title = $meta_title !== '' ? $meta_title : 'Web Story';

                $ins_arr = [
                    'post_type'    => self::STORY_CPT,
                    'post_title'   => $title,
                    'post_content' => '',
                    'post_status'  => $want_publish ? 'publish' : ($want_future ? 'future' : 'draft'),
                ];

                if ($want_publish) {
                    $ins_arr['post_date']     = $now_local;
                    $ins_arr['post_date_gmt'] = get_gmt_from_date($now_local);
                } elseif ($want_future) {
                    $ins_arr['post_date']     = $publish_at;
                    $ins_arr['post_date_gmt'] = get_gmt_from_date($publish_at);
                }

                $ins = wp_insert_post($ins_arr, true);
                if (is_wp_error($ins) || !$ins) {
                    return new WP_Error('pga_ws_create_fail', 'Falha ao criar story.', ['status' => 500]);
                }

                $story_id = (int)$ins; // ✅ AGORA temos ID real
            }

            // ---------------------------
            // SLUG (aplica no post_name DEPOIS do ID existir)
            // ---------------------------
            $slug_in = isset($p['slug']) ? sanitize_title((string)$p['slug']) : '';
            $slug_desired = $slug_in !== '' ? $slug_in : ('ws-' . $story_id); // ✅ nunca mais ws-0

            $slug_unique = wp_unique_post_slug(
                $slug_desired,
                $story_id,
                $want_publish ? 'publish' : ($want_future ? 'future' : 'draft'),
                self::STORY_CPT,
                0
            );

            // aplica no WP de verdade (slug real)
            wp_update_post([
                'ID' => $story_id,
                'post_name' => $slug_unique,
            ]);

            // (se você quiser guardar “o que ficou” também em meta, ok)
            update_post_meta($story_id, self::META_SLUG, $slug_unique);

            // ---------------------------
            // NORMALIZA/SALVA PAGES
            // ---------------------------
            $out_pages = [];
            foreach ($pages as $pg) {
                if (!is_array($pg)) continue;
                $out_pages[] = [
                    'index'     => absint($pg['index'] ?? 0),
                    'heading'   => sanitize_text_field((string)($pg['heading'] ?? '')),
                    'body'      => sanitize_textarea_field((string)($pg['body'] ?? '')),
                    'cta_text'  => sanitize_text_field((string)($pg['cta_text'] ?? '')),
                    'cta_url'   => esc_url_raw((string)($pg['cta_url'] ?? '')),
                    'template'  => sanitize_key((string)($pg['template'] ?? 'template-1')),
                    'image_id'  => absint($pg['image_id'] ?? 0),
                    'image_url' => esc_url_raw((string)($pg['image_url'] ?? '')),
                ];
            }

            $theme         = self::clean($layout['theme'] ?? 'theme-normal');
            $slidesCount   = absint($layout['slidesCount'] ?? count($out_pages));
            $layout_slides = is_array($layout['slides'] ?? null) ? $layout['slides'] : [];

            // ---------------------------
            // METAS
            // ---------------------------
            update_post_meta($story_id, self::META_TITLE, $meta_title);
            update_post_meta($story_id, self::META_DESC,  $meta_desc);
            update_post_meta($story_id, self::META_THEME, $theme);
            update_post_meta($story_id, self::META_SLIDES, $out_pages);

            $logo_id   = absint($settings['publisher_logo_id'] ?? 0);
            $poster_id = absint($settings['poster_id'] ?? 0);

            update_post_meta($story_id, self::META_LOGO_ID, $logo_id);
            update_post_meta($story_id, self::META_POSTER_ID, $poster_id);

            if (($want_publish || $want_future) && $poster_id > 0) {
                set_post_thumbnail($story_id, $poster_id);
            }

            update_post_meta($story_id, self::META_ACCENT, self::normalize_color($settings['accent_color'] ?? '', '#3B82F6'));
            update_post_meta($story_id, self::META_TEXT_COLOR, self::normalize_color($settings['text_color'] ?? '', '#FFFFFF'));
            update_post_meta($story_id, self::META_LOCALE, self::clean($settings['locale'] ?? 'pt_BR'));

            update_post_meta($story_id, self::META_PAYLOAD, [
                'meta' => ['title' => $meta_title, 'desc' => $meta_desc],
                'layout' => [
                    'theme' => $theme,
                    'slidesCount' => $slidesCount,
                    'slides' => $layout_slides,
                ],
                'settings' => [
                    'publisher_logo_id' => $logo_id,
                    'poster_id' => $poster_id,
                    'accent_color' => self::normalize_color($settings['accent_color'] ?? '', '#3B82F6'),
                    'text_color' => self::normalize_color($settings['text_color'] ?? '', '#FFFFFF'),
                    'locale' => self::clean($settings['locale'] ?? 'pt_BR'),
                ],
                'publish_at' => $want_future ? $publish_at : '',
                'status' => $status,
                'slug' => $slug_unique,
            ]);

            $story = get_post($story_id);

            return rest_ensure_response([
                'ok'       => true,
                'story_id' => $story_id,
                'status'   => $story ? $story->post_status : '',
                'story'    => $story ? self::as_story_payload($story) : [],
            ]);
        });
    }

    // ---------------------- GET story (opcional) ----------------------
    public static function story_get(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {
            $story_id = absint($req->get_param('story_id'));
            $story = self::get_story_or_error($story_id);
            if (is_wp_error($story)) return $story;

            $payload = self::get_payload($story_id);
            $payload = self::canonical_payload($story_id, $payload);

            $pages = get_post_meta($story_id, self::META_SLIDES, true);
            if (!is_array($pages)) $pages = [];

            return rest_ensure_response([
                'ok'    => true,
                'story' => self::as_story_payload($story),

                // 🔥 fonte única pro front
                'payload'  => $payload,

                // conveniência (mantém compat com seu JS atual)
                'meta'     => $payload['meta'],
                'layout'   => $payload['layout'],
                'settings' => $payload['settings'],
                'source'   => $payload['source'],

                'pages'    => $pages,
            ]);
        });
    }

    // ---------------------- POST generate (principal) ----------------------
    public static function generate(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $dep = self::require_classes();
        if (is_wp_error($dep)) return $dep;

        return self::guard(function () use ($req) {

            $p = self::json_params($req);

            // mode: single | bulk (igual teu JS)
            $mode = (isset($p['mode']) && $p['mode'] === 'bulk') ? 'bulk' : 'single';

            // ids (do JS)
            $post_id  = absint($p['post_id'] ?? 0);
            $post_ids = is_array($p['post_ids'] ?? null)
                ? array_values(array_filter(array_map('absint', $p['post_ids'])))
                : [];

            // layout (vem do JS)
            $layout = is_array($p['layout'] ?? null) ? $p['layout'] : [];
            $theme  = self::clean($layout['theme'] ?? 'theme-normal');

            $slidesCount = isset($layout['slidesCount']) ? absint($layout['slidesCount']) : 6;
            $slidesCount = max(1, (int)$slidesCount);

            $rawSlides = $layout['slides'] ?? [];
            $slides = self::normalize_slides(is_array($rawSlides) ? $rawSlides : [], $slidesCount);

            // CTA pages (índices com cta_enabled=true)
            $cta_pages = [];
            foreach ($slides as $s) {
                if (!empty($s['cta_enabled'])) {
                    $cta_pages[] = (int)($s['index'] ?? 0);
                }
            }
            $cta_pages = array_values(array_filter(array_unique(array_map('absint', $cta_pages))));

            // meta (title/desc) - vem do modal
            $meta = is_array($p['meta'] ?? null) ? $p['meta'] : [];
            $meta_title = self::clean($meta['title'] ?? '');
            $meta_desc  = sanitize_textarea_field((string)($meta['desc'] ?? ''));

            // settings (vem do modal)
            $settings = is_array($p['settings'] ?? null) ? $p['settings'] : [];
            $publisher_logo_id = absint($settings['publisher_logo_id'] ?? 0);
            $poster_id         = absint($settings['poster_id'] ?? 0);
            $accent_color      = self::clean($settings['accent_color'] ?? '');
            $text_color        = self::clean($settings['text_color'] ?? '');
            $locale            = self::clean($settings['locale'] ?? 'pt_BR');
            if ($locale === '') $locale = 'pt_BR';

            // publish_start (bulk)
            $publish_start = self::clean($p['publish_start'] ?? '');

            // validações
            if ($mode === 'single') {
                if (!$post_id) {
                    return new WP_Error('pga_ws_missing_post', 'post_id obrigatório (single).', ['status' => 400]);
                }
                if (!get_post($post_id)) {
                    return new WP_Error('pga_ws_bad_post', 'post_id inválido.', ['status' => 400]);
                }
                $post_ids = [$post_id];
            } else {
                if (empty($post_ids)) {
                    return new WP_Error('pga_ws_missing_posts', 'post_ids obrigatório (bulk).', ['status' => 400]);
                }
            }

            // payload bruto salvo no story (reabrir e depurar)
            $payload = [
                'mode' => $mode,
                'meta' => ['title' => $meta_title, 'desc' => $meta_desc],
                'layout' => [
                    'theme'       => $theme,
                    'slidesCount' => $slidesCount,
                    'slides'      => $slides,
                    'cta_pages'   => $cta_pages,
                ],
                'settings' => [
                    'publisher_logo_id' => $publisher_logo_id,
                    'poster_id'         => $poster_id,
                    'accent_color'      => $accent_color,
                    'text_color'        => $text_color,
                    'locale'            => $locale,
                ],
                'source' => [
                    'post_id'       => $post_id,
                    'post_ids'      => $post_ids,
                    'publish_start' => $publish_start,
                ],
            ];

            // agenda simples (você pode evoluir depois)
            $schedule = self::build_schedule($mode, $post_ids, $publish_start);

            $story_ids = [];

            foreach ($post_ids as $idx => $pid) {

                $src = get_post($pid);
                if (!$src) continue;

                // conteúdo base (texto limpo)
                $post_title = get_the_title($pid) ?: '';
                $raw_html   = apply_filters('the_content', $src->post_content);

                $content_txt = wp_strip_all_tags($raw_html);
                $content_txt = html_entity_decode($content_txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $content_txt = trim(preg_replace('/\s+/u', ' ', $content_txt));

                if ($content_txt === '') {
                    $content_txt = $post_title !== '' ? $post_title : '(conteúdo vazio)';
                }

                // título efetivo (se não veio no modal, usa o do post)
                $title_for_prompt = $meta_title !== '' ? $meta_title : $post_title;

                // CTA default
                $cta_url_default = get_permalink($pid) ?: '';
                $cta_text_default = 'Saiba mais';

                // 1) monta prompt WS (NOVO formato)
                if (!class_exists('PluginsAlpha_Prompts') || !method_exists('PluginsAlpha_Prompts', 'build_ws_story_prompt')) {
                    return new WP_Error('pga_ws_prompts_missing', 'Prompts WS não encontrado (build_ws_story_prompt).', ['status' => 500]);
                }

                $prompt = PluginsAlpha_Prompts::build_ws_story_prompt([
                    'slidesCount'      => $slidesCount,
                    'locale'           => $locale,
                    'title'            => $title_for_prompt,
                    'content'          => $content_txt,
                    'cta_pages'        => $cta_pages,
                    'cta_text_default' => $cta_text_default,
                    'cta_url_default'  => $cta_url_default,
                ]);

                $ai_raw = PluginsAlpha_AI::complete($prompt);
                if (is_wp_error($ai_raw)) {
                    return new WP_Error('pga_ws_ai_fail', $ai_raw->get_error_message(), ['status' => 500]);
                }

                // 3) parse JSON -> pages (garante N páginas)
                $ai = self::parse_ws_from_complete($ai_raw, $slidesCount);
                if (is_wp_error($ai)) return $ai;

                // 4) cria story (draft/future)
                $when = isset($schedule[$idx]) ? (int)$schedule[$idx] : 0;
                $story_id = self::create_story_post($pid, $ai, $payload, $when);
                if (is_wp_error($story_id)) return $story_id;

                $story_id = (int)$story_id;

                $thumb_id  = (int) get_post_thumbnail_id($pid);

                $effective_poster_id = $poster_id;
                if ($effective_poster_id <= 0 && $thumb_id > 0) {
                    $effective_poster_id = $thumb_id;
                }

                // payload canônico
                $payload = self::canonical_payload($story_id, $payload);

                // salva payload completo
                update_post_meta($story_id, self::META_PAYLOAD, $payload);

                // salva metas “espelhadas” (opc, mas ajuda)
                update_post_meta($story_id, self::META_THEME, $payload['layout']['theme']);
                update_post_meta($story_id, self::META_TITLE, $payload['meta']['title']);
                update_post_meta($story_id, self::META_DESC,  $payload['meta']['desc']);
                update_post_meta($story_id, self::META_LOGO_ID, $payload['settings']['publisher_logo_id']);
                update_post_meta($story_id, self::META_POSTER_ID, $payload['settings']['poster_id']);
                update_post_meta($story_id, self::META_ACCENT, $payload['settings']['accent_color']);
                update_post_meta($story_id, self::META_TEXT_COLOR, $payload['settings']['text_color']);
                update_post_meta($story_id, self::META_LOCALE, $payload['settings']['locale']);
                update_post_meta($story_id, self::META_SOURCE, $payload['source']['post_id']);

                // páginas (conteúdo)
                update_post_meta($story_id, self::META_SLIDES, $ai['pages']);

                $story_ids[] = $story_id;
            }

            if (empty($story_ids)) {
                return new WP_Error('pga_ws_no_story', 'Nenhum story foi criado.', ['status' => 500]);
            }

            return rest_ensure_response([
                'ok'        => true,
                'mode'      => $mode,
                'story_id'  => $story_ids[0],
                'story_ids' => $story_ids,
            ]);
        });
    }


    private static function parse_ws_from_complete(array $ai_raw, int $slidesCount)
    {
        $slidesCount = max(1, (int)$slidesCount);

        $txt = trim((string)($ai_raw['content'] ?? ''));
        if ($txt === '') {
            return new WP_Error('pga_ws_empty', 'A IA retornou content vazio.', ['status' => 500]);
        }

        // content pode ser:
        // (A) JSON do WS direto: { "pages": [...] }
        // (B) JSON "stringificado": "{\"pages\":[...]}"
        // (C) pode vir com lixo antes/depois (raramente)

        // tenta direto
        $data = json_decode($txt, true);

        // se falhou, tenta "des-stringificar"
        if (!is_array($data)) {
            // remove aspas externas se veio "\"{...}\""
            $maybe = trim($txt);
            if ((str_starts_with($maybe, '"') && str_ends_with($maybe, '"')) ||
                (str_starts_with($maybe, "'") && str_ends_with($maybe, "'"))
            ) {
                $maybe = substr($maybe, 1, -1);
            }
            // desfaz escapes
            $maybe = stripcslashes($maybe);

            $data = json_decode($maybe, true);
        }

        // se ainda falhou, tenta extrair { ... } de dentro do texto
        if (!is_array($data)) {
            $start = strpos($txt, '{');
            $end   = strrpos($txt, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $chunk = substr($txt, $start, $end - $start + 1);
                $data = json_decode($chunk, true);
            }
        }

        if (!is_array($data)) {
            return new WP_Error('pga_ws_bad_json', 'Content não é JSON válido do WS.', [
                'status' => 500,
                'snippet' => substr($txt, 0, 400),
            ]);
        }

        $pages = $data['pages'] ?? null;
        if (!is_array($pages)) {
            return new WP_Error('pga_ws_missing_pages', 'JSON do WS sem "pages".', [
                'status' => 500,
                'snippet' => substr($txt, 0, 400),
            ]);
        }

        $out = [];
        foreach ($pages as $p) {
            if (!is_array($p)) continue;

            $heading  = sanitize_text_field((string)($p['heading'] ?? ''));
            $body     = sanitize_textarea_field((string)($p['body'] ?? ''));
            $cta_text = sanitize_text_field((string)($p['cta_text'] ?? ''));
            $cta_url  = esc_url_raw((string)($p['cta_url'] ?? ''));
            $prompt   = sanitize_text_field((string)($p['prompt'] ?? ''));

            $out[] = [
                'heading'  => $heading,
                'body'     => $body,
                'cta_text' => $cta_text,
                'cta_url'  => $cta_url,
                'prompt'   => $prompt,
            ];
        }

        // força exatamente N
        if (count($out) > $slidesCount) {
            $out = array_slice($out, 0, $slidesCount);
        } elseif (count($out) < $slidesCount) {
            return new WP_Error('pga_ws_wrong_count', 'IA retornou ' . count($out) . ' páginas, esperado ' . $slidesCount . '.', ['status' => 500]);
        }

        return ['pages' => $out];
    }

    private static function normalize_slides(array $slides, int $slidesCount): array
    {
        $slidesCount = max(1, (int)$slidesCount);

        $out = [];

        foreach ($slides as $s) {
            if (!is_array($s)) continue;

            $idx = isset($s['index']) ? absint($s['index']) : 0;
            if ($idx < 1 || $idx > $slidesCount) continue;

            $tpl = isset($s['template']) ? sanitize_key((string)$s['template']) : 'template-1';
            if (!in_array($tpl, ['template-1', 'template-2', 'template-3'], true)) {
                $tpl = 'template-1';
            }

            $cta = $s['cta_enabled'] ?? false;
            $cta_enabled = filter_var($cta, FILTER_VALIDATE_BOOLEAN);

            $out[$idx] = [
                'index'       => $idx,
                'template'    => $tpl,
                'cta_enabled' => $cta_enabled,
            ];
        }

        // completa faltantes (1..N)
        for ($i = 1; $i <= $slidesCount; $i++) {
            if (!isset($out[$i])) {
                $out[$i] = [
                    'index'       => $i,
                    'template'    => 'template-1',
                    'cta_enabled' => false,
                ];
            }
        }

        ksort($out);
        return array_values($out);
    }

    private static function build_schedule(string $mode, array $post_ids, string $publish_start): array
    {
        // por padrão: SEM agendar, cria como draft (0)
        if ($mode !== 'bulk') return array_fill(0, count($post_ids), 0);

        // se veio start, cria uma escadinha simples a cada 15 min com aleatoriedade leve
        $base = 0;
        if ($publish_start !== '') {
            $ts = strtotime($publish_start);
            if ($ts !== false) $base = $ts;
        }
        if (!$base) return array_fill(0, count($post_ids), 0);

        $out = [];
        foreach ($post_ids as $k => $_) {
            $t = $base + ($k * 15 * MINUTE_IN_SECONDS) + wp_rand(-4 * MINUTE_IN_SECONDS, 4 * MINUTE_IN_SECONDS);
            $out[] = $t;
        }
        return $out;
    }

    private static function parse_ai_json($ai_raw)
    {
        // seu complete pode retornar string já, ou array, etc.
        $txt = is_string($ai_raw) ? $ai_raw : (string)$ai_raw;

        $txt = trim($txt);
        if ($txt === '') {
            return new WP_Error('pga_ws_ai_empty', 'IA retornou vazio.', ['status' => 500]);
        }

        // tenta achar JSON dentro do texto
        $json = null;

        // 1) se já for JSON puro
        $try = json_decode($txt, true);
        if (is_array($try)) $json = $try;

        // 2) se vier com lixo antes/depois, tenta extrair o primeiro {...}
        if ($json === null) {
            $pos1 = strpos($txt, '{');
            $pos2 = strrpos($txt, '}');
            if ($pos1 !== false && $pos2 !== false && $pos2 > $pos1) {
                $cut = substr($txt, $pos1, ($pos2 - $pos1 + 1));
                $try2 = json_decode($cut, true);
                if (is_array($try2)) $json = $try2;
            }
        }

        if (!is_array($json)) {
            return new WP_Error('pga_ws_ai_bad_json', 'Falha ao decodificar JSON do modelo.', ['status' => 500]);
        }

        // contrato mínimo
        // esperado: { "slides": [ { "title": "...", "text": "...", "cta_text": "...", "cta_url": "..." }, ... ] }
        $slides = $json['slides'] ?? null;
        if (!is_array($slides) || empty($slides)) {
            return new WP_Error('pga_ws_ai_no_slides', 'JSON sem slides.', ['status' => 500]);
        }

        return $json;
    }

    private static function create_story_post(int $source_post_id, array $ai, array $payload, int $whenTs = 0)
    {
        $title = get_the_title($source_post_id);
        if ($title === '') $title = 'Web Story';

        $status = 'draft';
        $post_date = current_time('mysql');
        $post_date_gmt = current_time('mysql', 1);

        // se agendado, cria como future
        if ($whenTs > time() + 60) {
            $status = 'future';
            $post_date     = date('Y-m-d H:i:s', $whenTs);
            $post_date_gmt = gmdate('Y-m-d H:i:s', $whenTs);
        }

        $story_id = wp_insert_post([
            'post_type'    => self::STORY_CPT,
            'post_status'  => $status,
            'post_title'   => $title,
            'post_content' => '', // o render pega das metas/slides
            'post_date'    => $post_date,
            'post_date_gmt' => $post_date_gmt,
        ], true);

        if (is_wp_error($story_id) || !$story_id) {
            return new WP_Error('pga_ws_create_fail', 'Falha ao criar story.', ['status' => 500]);
        }

        // salva meta: payload + slides + raw
        update_post_meta($story_id, self::META_SOURCE, $source_post_id);
        update_post_meta($story_id, self::META_PAYLOAD, $payload);
        update_post_meta($story_id, self::META_THEME, $payload['layout']['theme'] ?? 'theme-normal');
        update_post_meta($story_id, self::META_SLIDES, $ai['pages']);
        update_post_meta($story_id, self::META_AI_RAW, $ai);

        return (int)$story_id;
    }
}
