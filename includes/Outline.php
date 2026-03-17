<?php

if (!defined('ABSPATH')) exit;

class AlphaSuite_Outline
{

    public static function generate(
        string $template,
        string $keyword,
        string $chosenTitle,
        string $length,
        string $locale,
        string $url = ''
    ) {

        // youtube template
        if ($template === 'modelar_youtube') {

            $yt = AlphaSuite_Youtube::fetch_video_data($url);

            if (is_wp_error($yt)) {
                return $yt;
            }

            $prompt = AlphaSuite_Prompts::build_outline_prompt_modelar_youtube(
                $url,
                $yt,
                $chosenTitle,
                $length,
                $locale
            );
        } else {

            $prompt = AlphaSuite_Prompts::build_outline_prompt(
                $template,
                $keyword,
                $chosenTitle,
                $length,
                $locale,
                $url
            );
        }

        $outline = AlphaSuite_AI::complete($prompt);

        if (is_wp_error($outline)) {
            return $outline;
        }

        $outline = json_decode($outline, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'invalid_outline_json',
                'Erro ao decodificar JSON do outline.'
            );
        }

        return $outline;
    }
}
