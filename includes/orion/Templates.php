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
 *     'article' => ['label'=>'Artigo (padrão)', 'builtin'=>1, 'enabled'=>1],
 *     'modelar_youtube' => ['label'=>'Modelar vídeo do YouTube', 'builtin'=>1, 'enabled'=>1],
 *     'receitas' => ['label'=>'Receitas', 'builtin'=>0, 'enabled'=>1],
 *   ]
 */
class PluginsAlpha_Orion_Templates
{
    const OPTION = 'pga_orion_templates';

    public static function init(): void
    {
        add_action('init', [self::class, 'maybe_migrate'], 5);
    }

    public static function defaults(): array
    {
        return [
            'article' => [
                'label'   => __('Artigo (padrão)', 'plugins-alpha'),
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

    public static function get_all(): array
    {
        $opt = get_option(self::OPTION, []);
        $opt = is_array($opt) ? $opt : [];

        // garante defaults sempre
        $opt = array_merge(self::defaults(), $opt);

        // normaliza
        $out = [];
        foreach ($opt as $k => $v) {
            $k = sanitize_key((string)$k);
            if ($k === '') continue;

            $v = is_array($v) ? $v : [];
            $out[$k] = [
                'label'   => isset($v['label']) ? sanitize_text_field((string)$v['label']) : $k,
                'builtin' => !empty($v['builtin']) ? 1 : 0,
                'enabled' => array_key_exists('enabled', $v) ? (!empty($v['enabled']) ? 1 : 0) : 1,
            ];
        }

        // salva normalizado se estava vazio/estranho
        if ($out !== $opt) {
            update_option(self::OPTION, $out, false);
        }

        return $out;
    }

    public static function get_enabled(): array
    {
        $all = self::get_all();
        $out = [];

        foreach ($all as $k => $tpl) {
            if (!empty($tpl['enabled'])) {
                $out[$k] = $tpl;
            }
        }

        // garante core sempre presente
        foreach (self::defaults() as $k => $d) {
            if (!isset($out[$k])) {
                $out[$k] = $d;
            }
        }

        return $out;
    }

    /**
     * Salva modelos a partir do POST (mesmo form do settings).
     * Espera array:
     *  pga_orion_templates[slug][label]
     *  pga_orion_templates[slug][enabled]
     */
    public static function save_from_post($raw): void
    {
        $raw = is_array($raw) ? $raw : [];

        $current = self::get_all();
        $out = self::defaults(); // sempre re-injeta core

        // preserva builtin flags do que existir
        foreach ($current as $k => $v) {
            if (!isset($out[$k])) $out[$k] = $v;
        }

        foreach ($raw as $slug => $row) {
            $slug = sanitize_key((string)$slug);
            if ($slug === '') continue;

            $row = is_array($row) ? $row : [];

            // impede mexer no core aqui
            $is_builtin = !empty($out[$slug]['builtin']);
            $label = isset($row['label']) ? sanitize_text_field((string)$row['label']) : ($out[$slug]['label'] ?? $slug);

            $enabled = !empty($row['enabled']) ? 1 : 0;

            // se for builtin, não permite desabilitar (pra evitar “sumir”)
            if ($is_builtin) $enabled = 1;

            $out[$slug] = [
                'label'   => $label !== '' ? $label : $slug,
                'builtin' => $is_builtin ? 1 : 0,
                'enabled' => $enabled,
            ];
        }

        // remove custom vazios (label vazio e sem uso) — opcional, aqui deixo conservador
        update_option(self::OPTION, $out, false);
    }

    /**
     * Migra:
     *  - garante option templates
     *  - chama migração de prompts (formato antigo -> novo)
     */
    public static function maybe_migrate(): void
    {
        // 1) garante templates
        self::get_all();

        // 2) migra prompts se necessário
        if (class_exists('PluginsAlpha_Prompts')) {
            PluginsAlpha_Prompts::maybe_migrate_prompts_structure();
        }
    }
}
