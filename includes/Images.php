<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Central de geração de imagens (OpenAI / Pollinations) para posts e stories.
 */
class PluginsAlpha_Images
{

    /**
     * Gera uma imagem de acordo com as configurações globais do plugin
     * (provider + opções) e retorna o ID do attachment.
     *
     * $imgSettings é opcional. Se estiver vazio, pega de PluginsAlpha_Settings.
     *
     * @param string $prompt
     * @param int    $post_id
     * @param string $alt
     * @param array  $imgSettings
     *
     * @return int|\WP_Error Attachment ID ou erro.
     */
    public static function generate_by_settings(
        string $prompt,
        int $post_id,
        string $alt = '',
        array $imgSettings = []
    ) {
        if ('' === $prompt || $post_id <= 0) {
            return 0;
        }

        // Carrega settings globais se não veio override
        if (empty($imgSettings) && class_exists('PluginsAlpha_Settings')) {
            $opts       = PluginsAlpha_Settings::get();
            $imgSettings = isset($opts['apis']['images']) && is_array($opts['apis']['images'])
                ? $opts['apis']['images']
                : [];
        }

        $provider = isset($imgSettings['provider']) ? (string) $imgSettings['provider'] : 'pollinations';

        if ('' === $alt) {
            $alt = get_the_title($post_id) ?: '';
        }

        if ('openai' === $provider) {
            return self::generate_openai_thumbnail($prompt, $post_id, $alt, $imgSettings);
        }

        // Default / fallback
        return self::generate_pollinations_thumbnail($prompt, $post_id, $alt);
    }

    /**
     * Gera thumbnail com OpenAI (DALL·E, etc) e salva como attachment.
     *
     * @param string $prompt
     * @param int    $post_id
     * @param string $alt
     * @param array  $imgSettings
     *
     * @return int|\WP_Error
     */
    public static function generate_openai_thumbnail(
        string $prompt,
        int $post_id,
        string $alt,
        array $imgSettings = []
    ) {
        if ('' === $prompt || $post_id <= 0) {
            return 0;
        }

        // Pega chave da OpenAI das settings globais, se existir
        $opts = class_exists('PluginsAlpha_Settings') ? PluginsAlpha_Settings::get() : [];
        $api  = $opts['apis']['openai'] ?? [];
        $key  = trim((string) ($api['key'] ?? ''));

        if ($key === '') {
            return new \WP_Error('pga_openai_no_key', 'Chave da OpenAI não configurada.');
        }

        $model   = $imgSettings['model']   ?? 'dall-e-3';
        $size    = $imgSettings['size']    ?? '1792x1024'; // para Stories você pode passar 1080x1920
        $quality = $imgSettings['quality'] ?? 'standard';

        $body = [
            'model'   => $model,
            'prompt'  => $prompt,
            'n'       => 1,
            'size'    => $size,
            'quality' => $quality,
        ];

        $res = wp_remote_post(
            'https://api.openai.com/v1/images/generations',
            [
                'timeout' => 60,
                'headers' => [
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($body),
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);

        if (200 !== $code || ! $raw) {
            return new \WP_Error(
                'pga_openai_http',
                sprintf(
                    /* translators: %d = HTTP code */
                    __('Erro ao gerar imagem na OpenAI (HTTP %d).', 'plugins-alpha'),
                    (int) $code
                )
            );
        }

        $json = json_decode($raw, true);
        if (empty($json['data'][0]['url'])) {
            return new \WP_Error(
                'pga_openai_bad_response',
                __('Resposta inesperada da API de imagens.', 'plugins-alpha')
            );
        }

        $img_url = (string) $json['data'][0]['url'];

        // Baixa a imagem gerada
        $img_res = wp_remote_get(
            $img_url,
            [
                'timeout' => 60,
            ]
        );
        if (is_wp_error($img_res)) {
            return $img_res;
        }

        $img_body = wp_remote_retrieve_body($img_res);
        if (! $img_body) {
            return new \WP_Error(
                'pga_openai_empty_image',
                __('Imagem vazia retornada pela OpenAI.', 'plugins-alpha')
            );
        }

        // Usa helper comum para salvar
        return self::create_attachment_from_binary(
            $img_body,
            $post_id,
            $alt,
            'openai'
        );
    }

    /**
     * Gera thumbnail via Pollinations e salva como attachment.
     *
     * @param string $prompt
     * @param int    $post_id
     * @param string $alt
     *
     * @return int|\WP_Error
     */
    public static function generate_pollinations_image(
        string $prompt,
        int $post_id,
        array $opts = [],
        string $alt = ''
    ) {
        if ('' === $prompt || $post_id <= 0) {
            return 0;
        }

        $base_url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt);

        // monta query seguindo o espírito do exemplo oficial
        $query = [];

        // se ainda quiser mandar width/height, beleza (mesmo que eles limitem)
        if (!empty($opts['width'])) {
            $query['width'] = (int) $opts['width'];
        }
        if (!empty($opts['height'])) {
            $query['height'] = (int) $opts['height'];
        }

        // modelo (flux, turbo, etc.)
        if (!empty($opts['model'])) {
            $query['model'] = (string) $opts['model'];
        }

        // SEED – igual ao exemplo deles
        $query['seed'] = !empty($opts['seed'])
            ? (int) $opts['seed']
            : wp_rand(1, 1000000);

        $url = add_query_arg($query, $base_url);

        $res = wp_remote_get(
            $url,
            [
                'timeout' => 60,
                'headers' => [
                    'Accept' => 'image/avif,image/webp,image/jpeg,image/png,*/*',
                ],
            ]
        );

        if (is_wp_error($res)) {
            return $res;
        }

        $code = wp_remote_retrieve_response_code($res);

        if (200 !== $code) {
            return new \WP_Error(
                'pga_pollinations_http',
                sprintf(__('Falha ao gerar imagem (HTTP %d).', 'plugins-alpha'), (int) $code),
                [
                    'status'    => $code,
                    'http_code' => $code,
                ]
            );
        }

        $body = wp_remote_retrieve_body($res);
        if (!$body) {
            return new \WP_Error(
                'pga_pollinations_empty',
                __('Resposta de imagem vazia.', 'plugins-alpha')
            );
        }

        // detecta mime/ext
        $mime = 'image/jpeg';
        if (function_exists('getimagesizefromstring')) {
            $info = @getimagesizefromstring($body);
            if (!empty($info['mime'])) {
                $mime = $info['mime'];
            }
        }

        $ext = 'jpg';
        if ('image/png' === $mime) {
            $ext = 'png';
        } elseif ('image/webp' === $mime) {
            $ext = 'webp';
        }

        $filename = 'pollinations-' . $post_id . '-' . time() . '.' . $ext;

        $upload = wp_upload_bits($filename, null, $body);
        if (!empty($upload['error'])) {
            return new \WP_Error(
                'pga_pollinations_upload',
                $upload['error']
            );
        }

        $filetype = wp_check_filetype(basename($upload['file']), null);

        $attachment = [
            'guid'           => $upload['url'],
            'post_mime_type' => $filetype['type'] ?: $mime,
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        if (is_wp_error($attach_id) || !$attach_id) {
            return $attach_id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        if ($alt) {
            update_post_meta(
                $attach_id,
                '_wp_attachment_image_alt',
                wp_strip_all_tags($alt)
            );
        }

        return (int) $attach_id;
    }


    /**
     * Mantém a assinatura atual (thumb 1280x720)
     * – retrocompatível
     */
    public static function generate_pollinations_thumbnail(
        string $prompt,
        int $post_id,
        string $alt = ''
    ) {
        return self::generate_pollinations_image(
            $prompt,
            $post_id,
            [
                'width'  => 1200,
                'height' => 630,
                'nologo' => true,
                'model'  => 'flux',
            ],
            $alt
        );
    }

    /**
     * Novo helper: imagem para Web Stories (portrait)
     * – 1080x1920
     */
    public static function generate_pollinations_story_image(
        string $prompt,
        int $post_id,
        string $alt = ''
    ) {
        return self::generate_pollinations_image(
            $prompt,
            $post_id,
            [
                'width'  => 640,
                'height' => 900,
                'nologo' => true,
                'model'  => 'flux',
            ],
            $alt
        );
    }


    /**
     * Helper para salvar binário de imagem como attachment.
     *
     * @param string $binary
     * @param int    $post_id
     * @param string $alt
     * @param string $prefix
     *
     * @return int|\WP_Error
     */
    protected static function create_attachment_from_binary(
        string $binary,
        int $post_id,
        string $alt = '',
        string $prefix = 'img'
    ) {
        if ('' === $binary || $post_id <= 0) {
            return 0;
        }

        // tenta detectar mime
        $mime = 'image/jpeg';
        if (function_exists('getimagesizefromstring')) {
            $info = @getimagesizefromstring($binary);
            if (! empty($info['mime'])) {
                $mime = $info['mime'];
            }
        }

        $ext = 'jpg';
        if ('image/png' === $mime) {
            $ext = 'png';
        } elseif ('image/webp' === $mime) {
            $ext = 'webp';
        }

        $filename = $prefix . '-' . $post_id . '-' . time() . '.' . $ext;

        $upload = wp_upload_bits($filename, null, $binary);
        if (! empty($upload['error'])) {
            return new \WP_Error(
                'pga_upload_error',
                $upload['error']
            );
        }

        $filetype = wp_check_filetype(basename($upload['file']), null);

        $attachment = [
            'guid'           => $upload['url'],
            'post_mime_type' => $filetype['type'] ?: $mime,
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
        if (is_wp_error($attach_id) || ! $attach_id) {
            return $attach_id;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        if ($alt) {
            update_post_meta(
                $attach_id,
                '_wp_attachment_image_alt',
                wp_strip_all_tags($alt)
            );
        }

        return (int) $attach_id;
    }
}
