<?php
if (!defined('ABSPATH')) exit;

/**
 * Endpoints REST para Alpha Stories
 * - Geração do esboço (texto + prompts) via IA
 * - Geração de IMAGENS Pollinations, 1 slide por vez
 */
class PluginsAlpha_StoriesRest
{
    const NS = 'pga/v1';

    /**
     * Registra rotas REST
     */
    public static function register_routes()
    {
        register_rest_route(
            self::NS,
            '/stories/generate',
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'rest_generate_story'],
                'permission_callback' => [__CLASS__, 'can_edit_post_from_request'],
                'args'                => [
                    'post_id' => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            self::NS,
            '/stories/image',
            [
                'methods'             => 'POST',
                'callback'            => [__CLASS__, 'rest_generate_image_for_page'],
                'permission_callback' => [__CLASS__, 'can_edit_post_from_request'],
                'args'                => [
                    'post_id' => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                    'index'   => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                ],
            ]
        );
    }

    /**
     * Verifica permissão a partir de post_id enviado no corpo/params
     */
    public static function can_edit_post_from_request(\WP_REST_Request $req): bool
    {
        $post_id = (int) $req->get_param('post_id');
        if ($post_id <= 0) {
            return current_user_can('edit_posts');
        }
        return current_user_can('edit_post', $post_id);
    }

    /**
     * POST /pga/v1/stories/generate
     * Gera o esboço do Web Story (texto + prompts), SEM imagens
     */
    public static function rest_generate_story(\WP_REST_Request $req)
    {
        $post_id = (int) $req->get_param('post_id');
        if ($post_id <= 0) {
            return new \WP_Error('pga_story_post', 'post_id inválido.');
        }

        // aqui usamos o helper que você já tem
        $result = PluginsAlpha_Helpers::alpha_ai_generate_for_post($post_id);

        if (is_wp_error($result)) {
            return $result;
        }

        // resultado padrão do helper:
        // ['ok' => true, 'count' => N, 'target_id' => X]
        return [
            'ok'        => !empty($result['ok']),
            'count'     => (int) ($result['count'] ?? 0),
            'target_id' => (int) ($result['target_id'] ?? $post_id),
            'edit_url'  => isset($result['edit_url']) ? (string) $result['edit_url'] : '',
            'view_url'  => isset($result['view_url']) ? (string) $result['view_url'] : '',
        ];
    }

    /**
     * POST /pga/v1/stories/image
     * Gera uma IMAGEM Pollinations para UM slide (index) de um story
     */
    public static function rest_generate_image_for_page(\WP_REST_Request $req)
    {
        $post_id = (int) $req->get_param('post_id');
        $index   = (int) $req->get_param('index');

        if ($post_id <= 0) {
            return new \WP_Error('pga_story_post', 'post_id inválido.');
        }

        if ($index < 0) {
            return new \WP_Error('pga_story_index', 'index inválido.');
        }

        $pages = get_post_meta($post_id, '_alpha_storys_pages', true);
        if (!is_array($pages) || empty($pages)) {
            return new \WP_Error('pga_story_pages', 'Nenhuma página de story encontrada.');
        }

        if (!isset($pages[$index])) {
            return new \WP_Error('pga_story_page_index', 'Página de story inexistente para este índice.');
        }

        // se já tem imagem, só devolve e não faz nada pesado
        if (!empty($pages[$index]['image_id'])) {
            return [
                'ok'       => true,
                'skipped'  => true,
                'index'    => $index,
                'image_id' => (int) $pages[$index]['image_id'],
            ];
        }

        $p      = $pages[$index];
        $prompt = isset($p['prompt']) ? trim((string) $p['prompt']) : '';

        if ($prompt === '') {
            return new \WP_Error('pga_story_no_prompt', 'Esta página não possui prompt de imagem.');
        }

        $alt = !empty($p['heading']) ? $p['heading'] : $prompt;

        // usa o helper que você já tem, mas no tamanho de story
        $att_id = PluginsAlpha_Images::generate_pollinations_story_image(
            $prompt,
            $post_id,
            $alt
        );

        if (is_wp_error($att_id) || !$att_id) {
            return $att_id instanceof \WP_Error
                ? $att_id
                : new \WP_Error('pga_story_image', 'Falha ao gerar a imagem.');
        }

        $pages[$index]['image_id'] = (int) $att_id;

        update_post_meta($post_id, '_alpha_storys_pages', $pages);

        // opcional: regenerar blocos para já aparecer a imagem no editor
        $blocks = PluginsAlpha_Helpers::alpha_render_storys_pages_to_blocks($pages);
        wp_update_post([
            'ID'           => $post_id,
            'post_content' => $blocks,
        ]);

        return [
            'ok'       => true,
            'index'    => $index,
            'image_id' => (int) $att_id,
        ];
    }
}
