<?php
// includes/orion/Templates.php
if (!defined('ABSPATH')) exit;

/**
 * Cadastro de modelos (templates) do Órion + migração.
 *
 * - Core (imutáveis): article, modelar_youtube
 * - Usuário pode criar modelos custom.
 *
 * Storage (option):
 *   pga_orion_templates = [
 *     'article' => ['label'=>'Artigo', 'builtin'=>1, 'enabled'=>1],
 *     'modelar_youtube' => ['label'=>'Modelar vídeo do YouTube', 'builtin'=>1, 'enabled'=>1],
 *     'receitas' => ['label'=>'Receitas', 'builtin'=>0, 'enabled'=>1],
 *   ]
 */
class PluginsAlpha_Orion_Templates
{
    const OPTION = 'pga_orion_templates';

    public static function builtins(): array
    {
        return [
            'article' => [
                'label'   => 'Artigo',
                'enabled' => 1,
                'builtin' => 1,
            ],
            'modelar_youtube' => [
                'label'   => 'Modelar YouTube',
                'enabled' => 1,
                'builtin' => 1,
            ],
        ];
    }

    public static function get_all(): array
    {
        $saved = get_option(self::OPTION, []);
        $saved = is_array($saved) ? $saved : [];

        // garante formato
        $norm = [];
        foreach ($saved as $slug => $row) {
            $slug = sanitize_key($slug);
            if (!$slug) continue;

            $norm[$slug] = [
                'label'   => sanitize_text_field($row['label'] ?? $slug),
                'enabled' => !empty($row['enabled']) ? 1 : 0,
                'builtin' => !empty($row['builtin']) ? 1 : 0, // se quiser guardar
            ];
        }

        // builtin sempre por cima e sempre enabled=1
        $all = self::builtins();
        foreach ($norm as $slug => $row) {
            if (!isset($all[$slug])) {
                $row['builtin'] = 0;
                $all[$slug] = $row;
            }
        }

        // força builtins
        $all['article']['enabled'] = 1;
        $all['article']['builtin'] = 1;
        $all['modelar_youtube']['enabled'] = 1;
        $all['modelar_youtube']['builtin'] = 1;

        return $all;
    }
    public static function defaults(): array
    {
        return [
            'article' => [
                'label'   => __('Artigo', 'plugins-alpha'),
                'builtin' => 1,
                'enabled' => 1,
            ],
            'modelar_youtube' => [
                'label'   => __('Modelar vídeo do YouTube', 'plugins-alpha'),
                'builtin' => 1,
                'enabled' => 1,
            ],
        ];
    }
    public static function get_enabled(): array
    {
        $all = self::get_all(); // ou get_option etc

        // ✅ remove global sempre
        unset($all['global']);

        // filtra enabled...
        $out = [];
        foreach ($all as $slug => $row) {
            if (!empty($row['enabled'])) $out[$slug] = $row;
        }

        // garante core
        if (empty($out['article'])) $out['article'] = ['label' => 'Artigo (padrão)', 'enabled' => 1, 'builtin' => 1];
        if (empty($out['modelar_youtube'])) $out['modelar_youtube'] = ['label' => 'Modelar YouTube', 'enabled' => 1, 'builtin' => 1];

        return $out;
    }


    public static function save_from_post($raw): void
    {
        $raw = is_array($raw) ? $raw : [];

        $builtins = self::builtins();
        $out = $builtins; // começa pelos obrigatórios

        foreach ($raw as $slug => $row) {
            $slug = sanitize_key($slug);
            if (!$slug) continue;

            // ignora tentativa de “mexer” em builtin
            if (isset($builtins[$slug])) {
                continue;
            }

            $row = is_array($row) ? $row : [];

            $label = sanitize_text_field($row['label'] ?? $slug);
            $enabled = !empty($row['enabled']) ? 1 : 0;

            $out[$slug] = [
                'label'   => $label,
                'enabled' => $enabled,
                'builtin' => 0,
            ];
        }

        // salva substituindo tudo (isso resolve o "remover volta")
        update_option(self::OPTION, $out, false);
    }
}
