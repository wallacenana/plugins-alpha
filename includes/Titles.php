<?php
// includes/License.php
if (!defined('ABSPATH')) exit;

class AlphaSuite_Titles
{
    public static function getTitle(
        int $draft_id,
        string $template = '',
        string $keyword = '',
        string $locale = 'pt-br',
        string $url = '',
        $seed = ''
    ) {
        if ($template === 'modelar_youtube') {
            $yt = AlphaSuite_Youtube::fetch_video_data($url);
            if (is_wp_error($yt)) return $yt;

            // Aqui "keyword" pode ser:
            // - o próprio $keyword (se você usa URL no campo)
            // - OU um assunto derivado
            // - OU simplesmente $yt['title'] (muita gente prefere isso)
            $topic = $keyword ?: ($yt['title'] ?? '');

            $titlePrompt = AlphaSuite_Prompts::build_title_prompt_modelar_youtube(
                $yt,
                $topic,
                3,
                5,
                $locale
            );
        } else if ($template === 'rss') {
            $titlePrompt = AlphaSuite_Prompts::build_title_rss_prompt(
                $seed,
                $locale,
                $url
            );
        } else {
            $titlePrompt = AlphaSuite_Prompts::build_title_prompt(
                $template,
                $keyword,
                3,
                5,
                $locale
            );
        }

        $titles = AlphaSuite_AI::complete(
            $titlePrompt,
            ['title' => 'string'],
            [
                'max_tokens'  => 400,
                'temperature' => 0.5,
            ]
        );

        if (is_wp_error($titles)) {
            return AlphaSuite_FailJob::fail_job($draft_id, $titles);
        }

        $newTitle = '';

        if (isset($titles['title'])) {

            if (is_string($titles['title'])) {
                $newTitle = trim($titles['title']);
            } elseif (is_array($titles['title'])) {

                // Se for array com texto dentro
                if (isset($titles['title']['text'])) {
                    $newTitle = trim($titles['title']['text']);
                }

                // Se for array indexado
                elseif (isset($titles['title'][0])) {
                    $newTitle = trim((string)$titles['title'][0]);
                }
            }
        }

        return  $newTitle;
    }
}
