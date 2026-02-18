<?php
// includes/License.php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Titles
{
    public static function getTitle($template = '', $keyword = '', $locale = 'pt-br', $url = '', $draft_id, $seed = '')
    {
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
        } else if ($template === 'rss') {
            $titlePrompt = PluginsAlpha_Prompts::build_title_rss_prompt(
                $seed,
                $locale,
                $url
            );
        } else {
            $titlePrompt = PluginsAlpha_Prompts::build_title_prompt(
                $template,
                $keyword,
                3,
                5,
                $locale
            );
        }

        $titles = PluginsAlpha_AI::complete(
            $titlePrompt,
            ['title' => 'string'],
            [
                'max_tokens'  => 400,
                'temperature' => 0.5,
            ]
        );

        if (is_wp_error($titles)) {
            return PluginsAlpha_FailJob::fail_job($draft_id, $titles, 'titles');
        }

        return  $titles;
    }
}
