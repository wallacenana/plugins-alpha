<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_RSS
{
    public static function render(): void
    {
        $opt = PluginsAlpha_Settings::get();
        $chk = PluginsAlpha_License::check('alpha_orion');
?>
        <div class="pga-wrap">
            <?php
            if (!$chk['ok']) {
                $url = admin_url('admin.php?page=plugins-alpha-dashboard');

                echo '<div class="notice notice-error is-dismissible"><p>'
                    . esc_html__('Módulo não ativado.', 'plugins-alpha')
                    . ' <a href="' . esc_url($url) . '">'
                    . esc_html__('Clique aqui para ativar', 'plugins-alpha')
                    . '</a></p></div>';
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
                        <button
                            title="<?php esc_html_e('Salvar palavras-chave', 'plugins-alpha'); ?>"
                            type="button"
                            class="pga_save_box"
                            id="pga_save_keywords">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-save h-4 w-4 mr-2">
                                <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path>
                                <path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"></path>
                                <path d="M7 3v4a1 1 0 0 0 1 1h7"></path>
                            </svg>
                        </button>
                        <?php
                        $label = esc_html__('Planejar & Gerar', 'plugins-alpha');

                        echo $chk['ok']
                            ? '<button type="button" id="pga_plan"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-4 w-4 mr-2"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"></path><path d="M20 3v4"></path><path d="M22 5h-4"></path><path d="M4 17v2"></path><path d="M5 18H3"></path></svg> ' . $label . '</button>'
                            : '<button type="button" id="pga_plan" disabled> <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-sparkles h-4 w-4 mr-2"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"></path><path d="M20 3v4"></path><path d="M22 5h-4"></path><path d="M4 17v2"></path><path d="M5 18H3"></path></svg>' . $label . '</button>';
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
                        <div class="pga-gen-box pga-collapse" data-gen="1">
                            <div class="pga-collapse-head">
                                <button type="button" class="button pga-collapse-toggle">
                                    <span class="pga-gen-title"><?php esc_html_e('Título', 'plugins-alpha'); ?></span>
                                    <span class="pga-actions-colapse">
                                        <label class="pga-switch  pga_custom_wrap" style="display: none;">
                                            <input type="checkbox" class="pga_custom_enabled" checked>
                                            <span class="pga-switch-ui" aria-hidden="true"></span>
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
                                                'post_type'      => 'post',
                                                'post_status'    => 'publish',
                                                'numberposts'    => 100,
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
                                            <label for="pga_per_day"><?php esc_html_e('Posts por dia', 'plugins-alpha'); ?></label>
                                            <input id="pga_per_day" class="pga_per_day" type="number" min="1" step="1" value="3">
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
                                    </div>

                                </div>
                                <div class="pga-generator-footer">
                                    <button type="button" id="pga_test_box">
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

    /**
     * $args:
     *  - keywords[]  (usa a 1ª como foco)
     *  - locale      (pt_BR|en_US...)
     *  - publish_time  (timestamp futuro)
     *  - category_id   (int)
     */


    public static function create_draft_and_outline(array $args)
    {
        // 0) item único (primeira linha) 
        $kwSrc = $args['keyword'] ?? $args['keywords'] ?? '';
        if (is_array($kwSrc)) {
            $raw = trim((string)($kwSrc[0] ?? ''));
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string)$kwSrc);
            $raw = trim((string)($lines[0] ?? ''));
        }
        if ($raw === '') {
            return new WP_Error('pga_no_kw', 'Item (linha 1) vazio.');
        }

        // 1) parâmetros básicos (sem if por template) 
        $template = $args['template'] ?? $args['template_key'] ?? 'article';
        $length = $args['length'] ?? 'short';
        $locale = $args['locale'] ?? 'pt_BR';
        $provider = $args['provider'] ?? (class_exists('PluginsAlpha_AI') ? PluginsAlpha_AI::get_text_provider() : '');
        $jobArgs = ['provider' => $provider, 'template' => $template, 'length' => $length, 'locale' => $locale,];

        // 2) publish_time: NÃO calcula, só recebe e repassa (timestamp ou string) 
        $publish_ts = 0;
        if (!empty($args['publish_time'])) {
            $publish_ts = is_numeric($args['publish_time']) ? (int)$args['publish_time'] : (int)strtotime((string)$args['publish_time']);
        }
        $category_id = (int)($args['category_id'] ?? 0);
        $post_type = !empty($args['post_type']) ? sanitize_key((string)$args['post_type']) : 'posts_orion';

        // SE LICENÇA FOR VITALÍCIA → força post normal
        $lic = class_exists('PluginsAlpha_License') ? PluginsAlpha_License::check('alpha_orion') : ['ok' => false];
        $is_lifetime = !empty($lic['lifetime']) || (!empty($lic['plan']) && $lic['plan'] === 'lifetime');

        if ($is_lifetime && post_type_exists('post')) {
            $post_type = 'post';
        }

        // 3) contexto neutro: keyword = raw; url = raw se for URL 
        $keyword = $raw;
        $url = filter_var($raw, FILTER_VALIDATE_URL) ? $raw : '';

        // 4) slug base (temporário; depois atualiza com o título final) 
        $slug = sanitize_title($keyword);
        if ($slug === '') {
            $slug = sanitize_title(uniqid('orion_', true));
        }

        // 5) fallback de post_type (mantém teu comportamento) 
        if (!post_type_exists($post_type)) {
            if (post_type_exists('posts_orion')) {
                $post_type = 'posts_orion';
            } elseif (post_type_exists('post_orion')) {
                $post_type = 'post_orion';
            } else {
                $post_type = 'post';
            }
        } // 6) cria draft 
        $postarr = ['post_type' => $post_type, 'post_status' => 'draft', 'post_title' => '(Gerando) ' . $keyword, 'post_name' => $slug, 'post_content' => '', 'post_author' => get_current_user_id(),]; // só aplica se vier publish_time (sem “ajustes”) 
        if ($publish_ts > 0) {
            $postarr['post_date'] = date('Y-m-d H:i:s', $publish_ts);
            $postarr['post_date_gmt'] = gmdate('Y-m-d H:i:s', $publish_ts);
        }
        $draft_id = wp_insert_post($postarr, true);
        if (is_wp_error($draft_id)) {
            return $draft_id;
        }
        $draft_id = (int)$draft_id; // metas base 
        if ($publish_ts > 0) {
            update_post_meta($draft_id, '_pga_publish_ts', $publish_ts);
        }
        update_post_meta($draft_id, '_pga_job_started', time());
        if ($category_id > 0) {
            wp_set_post_terms($draft_id, [$category_id], 'category', false);
            update_post_meta($draft_id, '_pga_orion_category_ids', [$category_id]);
        }

        // --- TAGS (salva contexto do job) ------------------------
        if (!empty($args['tags']) && is_array($args['tags'])) {
            $clean = [];

            foreach ($args['tags'] as $t) {
                $t = trim((string)$t);
                if ($t !== '') {
                    $clean[] = $t;
                }
            }

            if ($clean) {
                update_post_meta($draft_id, '_pga_job_tags', $clean);
            }
        }

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
        } else {
            $titlePrompt = PluginsAlpha_Prompts::build_title_prompt(
                $template,
                $keyword,
                3,
                5,
                $locale,
                $url
            );
        }

        $titles = PluginsAlpha_AI::titles($titlePrompt, $jobArgs);
        if (is_wp_error($titles)) {
            return self::fail_job($draft_id, $titles, 'titles');
        }
        $chosenTitle = self::pick_best_title((array)$titles, $keyword);
        if (!$chosenTitle) {
            $chosenTitle = ucfirst($keyword);
        }

        // atualiza draft title com o título escolhido (importa pro WP/SEO) 
        wp_update_post(['ID' => $draft_id, 'post_title' => '(Gerando) ' . $chosenTitle,]);

        $promptSlug = PluginsAlpha_Prompts::build_slug_prompt(
            (string)$template,
            (string)$keyword,
            (string)$chosenTitle,
            (string)$locale
        );

        // chama endpoint dedicado (ou complete, se você não tiver meta_description)
        $respSlug = PluginsAlpha_AI::slug($promptSlug);
        // SLUG: aplica no post (update)
        if (!is_wp_error($respSlug)) {
            $slugTxt = '';

            if (is_string($respSlug)) {
                $slugTxt = $respSlug;
            } elseif (is_array($respSlug)) {
                $slugTxt = (string)($respSlug['slug'] ?? $respSlug['content'] ?? '');
            } elseif (is_object($respSlug)) {
                $slugTxt = (string)($respSlug->slug ?? $respSlug->content ?? '');
            }

            $slugTxt = trim(wp_strip_all_tags(html_entity_decode($slugTxt, ENT_QUOTES, 'UTF-8')));

            // se vier {"slug":"..."} como texto
            if ($slugTxt !== '' && $slugTxt[0] === '{') {
                $j = json_decode($slugTxt, true);
                if (is_array($j)) {
                    $slugTxt = trim((string)($j['slug'] ?? $j['content'] ?? $slugTxt));
                }
            }

            // se vier com prefixo tipo "slug: ..."
            $slugTxt = preg_replace('/^\s*(slug|post_name)\s*:\s*/i', '', $slugTxt);

            // pega só a primeira linha
            $slugTxt = preg_split("/\r\n|\r|\n/", $slugTxt)[0] ?? $slugTxt;
            $slugTxt = trim($slugTxt);

            // sanitiza e fallback
            $newSlug = sanitize_title($slugTxt);
            if ($newSlug === '') {
                $newSlug = sanitize_title($chosenTitle);
            }
            if ($newSlug === '') {
                $newSlug = sanitize_title($keyword);
            }
            if ($newSlug === '') {
                $newSlug = sanitize_title(uniqid('orion_', false));
            }

            // garante unicidade pro post_type atual
            $newSlug = wp_unique_post_slug($newSlug, $draft_id, 'draft', $post_type, 0);

            // atualiza post_name
            wp_update_post([
                'ID'       => $draft_id,
                'post_name' => $newSlug,
            ]);

            update_post_meta($draft_id, '_pga_generated_slug', $newSlug);
        }


        // jobArgs úteis pro provider/prompt 
        $jobArgs['keyword'] = $keyword;
        $jobArgs['url'] = $url;
        $jobArgs['chosen_title'] = $chosenTitle; // salva base do job 
        update_post_meta($draft_id, '_pga_outline_length', $length);
        update_post_meta($draft_id, '_pga_outline_locale', $locale);
        update_post_meta($draft_id, '_pga_outline_keyword', $keyword);
        update_post_meta($draft_id, '_pga_outline_template', $template);
        update_post_meta($draft_id, '_pga_outline_url', $url);
        update_post_meta($draft_id, '_pga_chosen_title', $chosenTitle);

        // 8) OUTLINE (prompt resolve via template) 
        if ($template === 'modelar_youtube') {
            $yt = PluginsAlpha_Youtube::fetch_video_data($url);
            if (is_wp_error($yt)) return $yt; // ou trate como você trata erros no endpoint

            $outlinePrompt = PluginsAlpha_Prompts::build_outline_prompt_modelar_youtube(
                $url,
                $yt,
                $chosenTitle,
                $length,
                $locale
            );
        } else {
            // fluxo normal
            $outlinePrompt = PluginsAlpha_Prompts::build_outline_prompt($template, $keyword, $chosenTitle, $length, $locale, $url);
        }

        $outline = PluginsAlpha_AI::outline($outlinePrompt, $jobArgs);

        if (is_wp_error($outline)) {
            return self::fail_job($draft_id, $outline, 'outline');
        }

        // Se vier { "sections": [...] }, pega só o array interno 
        $sections = $outline['sections'] ?? $outline;
        if (!is_array($sections)) {
            $sections = [];
        }

        // 9) NORMALIZA ids (mantém teu padrão) 
        $normalized = [];
        $h2Index = 1;
        foreach ($sections as $sec) {
            if (!is_array($sec)) {
                $sec = ['heading' => (string)$sec, 'level' => 'h2',];
            }
            if (empty($sec['level'])) {
                $sec['level'] = 'h2';
            }
            if (empty($sec['id'])) {
                $sec['id'] = (string)$h2Index;
            }
            if (!empty($sec['children']) && is_array($sec['children'])) {
                $childIndex = 1;
                foreach ($sec['children'] as $ci => $child) {
                    if (!is_array($child)) {
                        $child = ['heading' => (string)$child, 'level' => 'h3',];
                    }
                    if (empty($child['level'])) {
                        $child['level'] = 'h3';
                    }
                    if (empty($child['id'])) {
                        $child['id'] = $sec['id'] . '.' . $childIndex;
                    }
                    $sec['children'][$ci] = $child;
                    $childIndex++;
                }
            }
            $normalized[] = $sec;
            $h2Index++;
        }
        update_post_meta($draft_id, '_pga_outline_sections', wp_json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        update_post_meta($draft_id, '_pga_job_status', 'outline_done');
        return ['post_id' => $draft_id, 'title' => $chosenTitle, 'sections' => $normalized, 'length' => $length, 'locale' => $locale, 'post_type' => $post_type,];
    }

    private static function pick_best_title(array $cands, string $kw): string
    {
        $cands = array_values(array_filter(array_map('trim', $cands)));
        if (!$cands) return '';
        usort($cands, function ($a, $b) use ($kw) {
            $score = function ($t) use ($kw) {
                $s = 0;
                if (stripos($t, $kw) !== false) $s += 2;      // contém keyword
                if (preg_match('/\b\d+\b/', $t)) $s += 1;    // tem número
                if (mb_strlen($t) <= 60) $s += 1;           // curto (Discover)
                if (stripos($t, 'guia completo') !== false) $s -= 2; // evita esse padrão
                return $s;
            };
            return $score($b) <=> $score($a);
        });
        return $cands[0];
    }

    private static function fail_job($post_id, WP_Error $err)
    {
        $data = $err->get_error_data() ?: [];

        wp_update_post([
            'ID'          => $post_id,
            'post_status' => 'draft',
            'post_title'  => '(Falhou) ' . get_the_title($post_id),
        ]);

        update_post_meta($post_id, '_pga_last_error', [
            'code'    => $err->get_error_code(),
            'message' => $err->get_error_message(),
            'data'    => $data,
            'time'    => time(),
        ]);

        return $err;
    }
}
