<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Image {
    public static function generate_featured($prompt, $settings){
        $key   = $settings['apis']['dalle']['key'] ?? '';
        $model = $settings['apis']['dalle']['model'] ?? 'gpt-image-1';
        $size  = $settings['apis']['dalle']['size'] ?? '1024x1024';
        if (!$key) return ''; // opcional

        $res = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'headers'=>['Authorization'=>'Bearer '.$key,'Content-Type'=>'application/json'],
            'timeout'=>120,
            'body'=> wp_json_encode(['model'=>$model,'prompt'=>$prompt,'size'=>$size]),
        ]);
        if (is_wp_error($res)) return $res;
        $code = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res), true);
        if ($code !== 200 || empty($json['data'][0]['url']))
            return new WP_Error('pga_img','Falha ao gerar imagem',$json);

        return $json['data'][0]['url'];
    }

    public static function sideload_to_media($image_url, $post_id){
        if (!function_exists('media_sideload_image')) require_once ABSPATH.'wp-admin/includes/media.php';
        if (!function_exists('download_url'))        require_once ABSPATH.'wp-admin/includes/file.php';
        if (!function_exists('wp_read_image_metadata')) require_once ABSPATH.'wp-admin/includes/image.php';

        $att_id = media_sideload_image($image_url, $post_id, null, 'id');
        if (is_wp_error($att_id)) return $att_id;
        set_post_thumbnail($post_id, $att_id);
        return $att_id;
    }
}
