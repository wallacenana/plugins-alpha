<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_RSS
{
    public static function render(): void
    {
        $opt = PluginsAlpha_Settings::get();
        $chk = PluginsAlpha_License::check('ddddddd');
?>
        <div class="pga-wrap">
            <?php
            if (!$chk['ok']) {
                $url = admin_url('admin.php?page=plugins-alpha-dashboard');

                echo '<div class="notice notice-error is-dismissible"><p>'
                    . esc_html__('Módulo Ainda sem funcionamento.', 'plugins-alpha')
                    . '</p></div>';
            }

            $tpls = (array) get_option('pga_orion_templates', []);
            $default_tpls = [];

            foreach ($tpls as $slug => $row) {
                $slug = sanitize_key((string) $slug);
                if (!$slug) continue;

                $enabled = !empty($row['enabled']);
                $is_default = !empty($row['is_default']);

                if ($enabled && $is_default) {
                    $default_tpls[] = $slug;
                }
            }

            // fallback: se user não marcou nada, evita “novo projeto vazio”
            if (!$default_tpls) {
                $default_tpls = ['article'];
            }
            ?>
            <div class="wrap pga-layout">
                <div class="pga-header-fixed">
                    <div class="pga-header-col pga-a-center">
                        <div>
                            <h1><?php esc_html_e('Gerador RSS', 'plugins-alpha'); ?></h1>
                            <p class="pga-descricao"><?php esc_html_e('Criação de artigos com base em RSS', 'plugins-alpha'); ?></p>
                        </div>
                    </div>
                    <div class="pga-header-col pga-a-center ">
                        <?php
                        $label = esc_html__('Salvar e agendar', 'plugins-alpha');

                        echo $chk['ok']
                            ? '<button type="button" id="pga_save_keywords" class="pga-rss pga_save_box"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2">
                                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                            </svg> ' . $label . '</button>'
                            : '<button type="button" id="pga_save_keywords" class="pga-rss"> <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2">
                                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                            </svg>' . $label . '</button>';
                        ?>
                    </div>
                </div>
                <div class="pga-main">
                    <!-- Tabs -->
                    <div class="pga-tabsbar">
                        <div id="pga_tabs"></div>
                        <button type="button" class="button" id="pga_tab_add"
                            data-default-templates="<?php echo esc_attr(wp_json_encode(array_values($default_tpls))); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="lucide lucide-plus h-4 w-4">
                                <path d="M5 12h14"></path>
                                <path d="M12 5v14"></path>
                            </svg> <?php esc_html_e('Novo projeto', 'plugins-alpha'); ?></button>
                    </div>

                    <style>
                        .pga-row .pga-field {
                            flex: 1 1 calc(24% - 10px);
                        }
                    </style>
                    <!-- Contêiner de grupos -->
                    <div id="pga_gen_container">
                        <div class="pga-gen-box pga-collapse  pga-collapse--open" data-gen="1">
                            <div class="pga-collapse-head">
                                <button type="button" class="button pga-collapse-toggle">
                                    <span class="pga-gen-title"><?php esc_html_e('Título', 'plugins-alpha'); ?></span>
                                    <span class="pga-actions-colapse">
                                        <label style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                                            <span class="pga-switch">
                                                <input type="checkbox" id="pga_make_faq" class="pga_active" checked>
                                                <span class="pga-switch-ui" aria-hidden="true"></span>
                                                <span class="pga-switch-label"><?php esc_html_e('Ativo', 'plugins-alpha'); ?></span>
                                            </span>
                                        </label>
                                        <span type="button" class="pga-copy-box" title="Duplicar este grupo" data-tooltip="Duplicar este grupo">
                                            <span class="pga-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="#e3e3e3">
                                                    <path d="M360-240q-33 0-56.5-23.5T280-320v-480q0-33 23.5-56.5T360-880h360q33 0 56.5 23.5T800-800v480q0 33-23.5 56.5T720-240H360Zm0-80h360v-480H360v480ZM200-80q-33 0-56.5-23.5T120-160v-560h80v560h440v80H200Zm160-240v-480 480Z" />
                                                </svg></span>
                                        </span>
                                    </span>
                                </button>
                            </div>
                            <div class="pga-collapse-body">
                                <div class="pga-card">

                                    <div class="pga-row">
                                        <div class="pga-field">
                                            <label for="pga_keywords"><?php esc_html_e('URL do RSS', 'plugins-alpha'); ?></label>
                                            <input
                                                type="url"
                                                id="pga_keywords"
                                                class="pga_keywords"
                                                rows="14"
                                                placeholder="<?php esc_html_e('Insira sua url', 'plugins-alpha'); ?>" />
                                        </div>
                                        <div class="pga-field">
                                            <label for="pga_category"><?php esc_html_e('Categoria', 'plugins-alpha'); ?></label>
                                            <?php
                                            wp_dropdown_categories([
                                                'show_option_none'  => '— Sem categoria —',
                                                'option_none_value' => '0',
                                                'taxonomy'          => 'category',
                                                'hide_empty'        => 0,
                                                'name'              => 'pga_category',
                                                'id'                => 'pga_category',
                                                'class'             => 'regular-text pga_category',
                                                'orderby'           => 'name',
                                                'hierarchical'      => true,
                                                'value_field'       => 'term_id',
                                                'selected'          => 0,
                                            ]);
                                            ?>
                                        </div>

                                        <div class="pga-field">
                                            <label for="pga_length"><?php esc_html_e('Extensão', 'plugins-alpha'); ?></label>
                                            <select id="pga_length" class="pga_length">
                                                <option value="short"><?php esc_html_e('Pequeno', 'plugins-alpha'); ?></option>
                                                <option value="medium"><?php esc_html_e('Médio', 'plugins-alpha'); ?></option>
                                                <option value="long"><?php esc_html_e('Longo', 'plugins-alpha'); ?></option>
                                                <option value="extra-long"><?php esc_html_e('Extra Longo', 'plugins-alpha'); ?></option>
                                            </select>
                                        </div>
                                        <!-- ... dentro da pga-row de campos do grupo ... -->

                                        <div class="pga-field">
                                            <label for="pga_link_mode"><?php esc_html_e('Links internos', 'plugins-alpha'); ?></label>
                                            <select id="pga_link_mode" class="pga_link_mode">
                                                <option value="none"><?php esc_html_e('Sem link interno', 'plugins-alpha'); ?></option>
                                                <option value="auto"><?php esc_html_e('Automático', 'plugins-alpha'); ?></option>
                                                <option value="pillar"><?php esc_html_e('Post pilar', 'plugins-alpha'); ?></option>
                                                <option value="manual"><?php esc_html_e('Manual', 'plugins-alpha'); ?></option>
                                            </select>
                                        </div>

                                        <div class="pga-field pga_link_extra" style="display:none">
                                            <label><?php esc_html_e('Links por post', 'plugins-alpha'); ?></label>
                                            <select class="pga_link_max">
                                                <option value="1">1 link</option>
                                                <option value="2">2 links</option>
                                                <option value="3">3 links</option>
                                                <option value="4">4 links</option>
                                                <option value="5">5 links</option>
                                                <option value="6">6 links</option>
                                                <option value="7">7 links</option>
                                                <option value="8">8 links</option>
                                                <option value="9">9 links</option>
                                                <option value="10">10 links</option>
                                                <option value="11">11 links</option>
                                                <option value="12">12 links</option>
                                                <option value="13">13 links</option>
                                                <option value="14">14 links</option>
                                                <option value="15">15 links</option>
                                            </select>
                                        </div>
                                        <div class="pga-field pga_link_manual_wrapper" style="display:none">
                                            <label><?php esc_html_e('Posts para linkar (modo manual)', 'plugins-alpha'); ?></label>
                                            <?php
                                            // últimos posts Orion (ajuste o post_type se for outro)
                                            $orion_posts = get_posts([
                                                'post_type'      => ['post', 'page', 'posts_orion'],
                                                'post_status'    => 'any',
                                                'numberposts'    => -1,
                                                'orderby'        => 'date',
                                                'order'          => 'DESC',
                                            ]);
                                            ?>
                                            <select
                                                class="pga_link_manual pga-link-manual-select"
                                                multiple="multiple"
                                                size="6">
                                                <?php if (!empty($orion_posts)) : ?>
                                                    <?php foreach ($orion_posts as $p) : ?>
                                                        <option value="<?php echo esc_attr($p->ID); ?>">
                                                            <?php echo esc_html(get_the_title($p)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php else : ?>
                                                    <option value="" disabled><?php esc_html_e('Nenhum post Órion publicado ainda.', 'plugins-alpha'); ?></option>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="pga-field">
                                            <label for="pga_locale"><?php esc_html_e('Idioma', 'plugins-alpha'); ?></label>
                                            <select id="pga_locale" class="pga_locale">
                                                <option value="pt_BR" <?php selected(($opt['defaults']['locale'] ?? '') === 'pt_BR'); ?>>Português (Brasil)</option>
                                                <option value="en_US" <?php selected(($opt['defaults']['locale'] ?? '') === 'en_US'); ?>>English (US)</option>
                                                <option value="es_ES" <?php selected(($opt['defaults']['locale'] ?? '') === 'es_ES'); ?>>Español</option>
                                                <option value="fr_FR" <?php selected(($opt['defaults']['locale'] ?? '') === 'fr_FR'); ?>>Français</option>
                                            </select>
                                        </div>
                                        <div class="pga-field">
                                            <label><?php esc_html_e('Tags', 'plugins-alpha'); ?></label>

                                            <select class="pga_tags pga-select2" multiple="multiple" style="width:100%">
                                                <?php
                                                $tags = get_terms([
                                                    'taxonomy'   => 'post_tag',
                                                    'hide_empty' => false,
                                                    'number'     => 0, // não limita
                                                    'orderby'    => 'name',
                                                    'order'      => 'ASC',
                                                ]);

                                                if (!is_wp_error($tags) && !empty($tags)) :
                                                    foreach ($tags as $t) :
                                                ?>
                                                        <option value="<?php echo esc_attr($t->term_id); ?>">
                                                            <?php echo esc_html($t->name); ?>
                                                        </option>
                                                <?php
                                                    endforeach;
                                                endif;
                                                ?>
                                            </select>
                                        </div>
                                        <div class="pga-field pga_quota_wrap" style="display:none">
                                            <label for="pga_quota_day"><?php esc_html_e('Tempo de atualização', 'plugins-alpha'); ?></label>
                                            <input class="pga_quota_day" type="number" min="0" step="1" value="1">
                                        </div>

                                        <div class="pga-field">
                                            <label for="pga_start_hour">Iniciar às:</label>
                                            <input type="number" min="0" max="23" id="pga_start_hour" class="pga_start_hour">
                                        </div>
                                        <div class="pga-field">
                                            <label for="pga_end_hour">Parar às:</label>
                                            <input type="number" min="0" max="23" id="pga_end_hour" class="pga_end_hour">
                                        </div>

                                        <div class="pga-field">
                                            <label for="pga_interval_hours">Interval de hours:</label>
                                            <input type="number" min="0" max="23" id="pga_interval_hours" class="pga_interval_hours">
                                        </div>

                                        <div class="pga-field">
                                            <label for="rssKeyword"><?php esc_html_e('Palavras para filtro', 'plugins-alpha'); ?></label>
                                            <input type="text" id="rssKeyword" placeholder="Ex: politica, economia, futebol" class="rssKeyword">
                                        </div>
                                        <div class="pga-field">
                                            <div class="pga-faq-qty-wrap" style="display:none;align-items:center;gap:8px;">
                                                <label for="pga_faq_qty"><?php esc_html_e('Perguntas', 'plugins-alpha'); ?></label>
                                                <input
                                                    id="pga_faq_qty"
                                                    class="pga_faq_qty"
                                                    type="number"
                                                    min="1"
                                                    step="1"
                                                    max="5"
                                                    value="5">
                                            </div>
                                        </div>
                                        <div class="pga-field">
                                            <label style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                                                <span class="pga-switch">
                                                    <input type="checkbox" id="pga_make_faq" class="pga_make_faq">
                                                    <span class="pga-switch-ui" aria-hidden="true"></span>
                                                    <span class="pga-switch-label"><?php esc_html_e('Criar FAQ', 'plugins-alpha'); ?></span>
                                                </span>
                                            </label>
                                        </div>

                                    </div>

                                </div>
                                <div class="pga-generator-footer">
                                    <button type="button" class="pga_test_box">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-zap h-4 w-4 mr-2">
                                            <path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z">
                                            </path>
                                        </svg>
                                        <?php esc_html_e('Gerar agora', 'plugins-alpha'); ?>
                                    </button>
                                    <button type="button" class="pga_save_box">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2">
                                            <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                                            <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                            <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                                        </svg>
                                        <?php esc_html_e('Salvar gerador', 'plugins-alpha'); ?>
                                    </button>
                                    <button type="button" class="pga_clear_box">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash2 h-4 w-4 mr-2">
                                            <path d="M3 6h18"></path>
                                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                                            <line x1="10" x2="10" y1="11" y2="17"></line>
                                            <line x1="14" x2="14" y1="11" y2="17"></line>
                                        </svg>
                                        <?php esc_html_e('Excluir', 'plugins-alpha'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="pga-add-generator">
                        <button class="pga-add-container" id="pga_add_box">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus h-4 w-4">
                                <path d="M5 12h14"></path>
                                <path d="M12 5v14"></path>
                            </svg>
                            <?php esc_html_e('Adicionar gerador', 'plugins-alpha'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="pga-done-dropup">
            <button
                type="button"
                id="pga_done_toggle"
                class="button pga-floating-btn pga-icon-btn"
                aria-expanded="false"
                aria-controls="pga_done_panel"
                data-tooltip="Ver frases já geradas">
                <svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px">
                    <path d="M382-240 154-468l57-57 171 171 367-367 57 57-424 424Z" />
                </svg>
            </button>

            <div
                id="pga_done_panel"
                class="pga-card pga-done-panel"
                aria-hidden="true">
                <div class="pga-row">
                    <h2>Concluídas</h2>
                    <button
                        type="button"
                        id="pga_kw_clear_done"
                        class="pga-icon-btn pga-btn-delete"
                        data-tooltip="Limpar frases geradas">
                        <span class="pga-icon">🗑️</span>
                    </button>
                </div>
                <ul id="pga_kw_done" class="pga-list done"></ul>
            </div>
        </div>
<?php
    }
}
