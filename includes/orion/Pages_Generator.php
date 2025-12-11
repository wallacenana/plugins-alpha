<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Pages_Generator
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
      ?>
      <h1>Gerador — Alpha Órion</h1>
      <div class="wrap pga-layout">
        <div class="pga-main">
          <!-- Contêiner de grupos -->
          <div id="pga_gen_container">

            <!-- GRUPO 1 (colapse) -->
            <div class="pga-gen-box pga-collapse pga-collapse--open" data-gen="1">
              <!-- Cabeçalho do colapse com título dinâmico -->
              <button
                type="button"
                class="button pga-collapse-toggle">
                <span class="pga-gen-title">
                  <!-- título inicial (JS vai atualizar sempre que mudar algo) -->
                  Título
                </span>
                <span class="dashicons dashicons-arrow-up-alt2"></span>
              </button>

              <!-- Corpo do colapse -->
              <div class="pga-collapse-body">

                <div class="pga-card">
                  <div class="pga-row between">
                    <div class="pga-field" style="width:100%;">
                      <label for="pga_keywords">Keywords</label>
                      <textarea
                        id="pga_keywords"
                        class="pga_keywords"
                        rows="16"
                        placeholder="Uma por linha"></textarea>
                    </div>
                  </div>

                  <div class="pga-row">
                    <div class="pga-field">
                      <label for="pga_locale">Locale</label>
                      <select id="pga_locale" class="pga_locale">
                        <option value="pt_BR" <?php selected(($opt['defaults']['locale'] ?? '') === 'pt_BR'); ?>>Português (Brasil)</option>
                        <option value="en_US" <?php selected(($opt['defaults']['locale'] ?? '') === 'en_US'); ?>>English (US)</option>
                        <option value="es_ES" <?php selected(($opt['defaults']['locale'] ?? '') === 'es_ES'); ?>>Español</option>
                        <option value="fr_FR" <?php selected(($opt['defaults']['locale'] ?? '') === 'fr_FR'); ?>>Français</option>
                      </select>
                    </div>

                    <div class="pga-field">
                      <label>Modelo de Post</label>
                      <select id="pga_template_key" class="pga_template_key">
                        <option value="discover_article">Discover (artigo)</option>
                        <option value="faq">FAQ</option>
                        <option value="review_roundup">Review comparativo (vários)</option>
                        <option value="review_single">Review (1 produto)</option>
                        <!--<option value="article">Artigo</option>-->
                        <option value="howto">Guia / How-to</option>
                        <!--<option value="list">Lista</option>-->
                        <option value="news">Notícia</option>
                        <option value="modelar">Modelar URL</option>
                        <option value="modelar_youtube">Modelar vídeo do YouTube</option>
                      </select>
                    </div>

                    <div class="pga-field">
                      <label>Categoria</label>
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
                      <label>Quantidade total</label>
                      <input id="pga_total" class="pga_total" type="number" min="1" step="1" value="6">
                    </div>

                    <div class="pga-field">
                      <label>Posts por dia</label>
                      <input id="pga_per_day" class="pga_per_day" type="number" min="1" step="1" value="3">
                    </div>

                    <div class="pga-field">
                      <label for="pga_first_delay_hours">Inicio da programação</label>
                      <?php
                      $ts_default  = current_time('timestamp') + 2 * HOUR_IN_SECONDS;
                      $val_default = date_i18n('Y-m-d\TH:i', $ts_default);
                      ?>
                      <input
                        id="pga_first_delay_hours"
                        class="pga_first_delay_hours"
                        type="datetime-local"
                        class="regular-text"
                        value="<?php echo esc_attr($val_default); ?>" />
                    </div>

                    <div class="pga-field">
                      <label for="pga_length">Extensão</label>
                      <select id="pga_length" class="pga_length">
                        <option value="short">Pequeno</option>
                        <option value="medium">Médio</option>
                        <option value="long">Longo</option>
                        <option value="extra-long">Extra Longo</option>
                      </select>
                    </div>
                    <!-- ... dentro da pga-row de campos do grupo ... -->

                    <div class="pga-field">
                      <label for="">Links internos</label>
                      <select class="pga_link_mode">
                        <option value="none">Sem link interno</option>
                        <option value="auto">Automático</option>
                        <option value="pillar">Post pilar</option>
                        <option value="manual">Manual</option>
                      </select>
                    </div>

                    <div class="pga-field pga_link_extra" style="display:none">
                      <label>Links por post</label>
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
                      <label>Posts para linkar (modo manual)</label>
                      <?php
                      // últimos posts Orion (ajuste o post_type se for outro)
                      $orion_posts = get_posts([
                        'post_type'      => 'posts_orion',
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
                          <option value="" disabled>Nenhum post Órion publicado ainda.</option>
                        <?php endif; ?>
                      </select>
                    </div>

                    <div class="pga-actions-unit pga-icon-buttons">
                      <button
                        type="button"
                        disabled
                        class="pga_generate_box pga-icon-btn"
                        data-tooltip="(breve) Gerar sugestão de keywords">
                        <span class="pga-icon">⚡</span>
                      </button>
                      <!-- Gerar -->
                      <button
                        type="button"
                        class="pga_generate_box pga-icon-btn pga-btn-generate"
                        data-tooltip="Gerar deste grupo">
                        <span class="pga-icon">🪄</span>
                      </button>

                      <!-- Salvar configurações -->
                      <button
                        type="button"
                        class="pga_save_box pga-icon-btn pga-btn-save"
                        data-tooltip="Salvar configurações deste grupo">
                        <span class="pga-icon">💾</span>
                      </button>

                      <!-- Importar -->
                      <button
                        type="button"
                        class="pga_import_box pga-icon-btn pga-btn-import"
                        data-tooltip="Importar keywords (.txt)">
                        <span class="pga-icon">⬅️</span>
                      </button>

                      <!-- Exportar -->
                      <button
                        type="button"
                        class="pga_export_box pga-icon-btn pga-btn-export"
                        data-tooltip="Exportar keywords (.txt)">
                        <span class="pga-icon">➡️</span>
                      </button>

                      <!-- Limpar -->
                      <button
                        type="button"
                        class="pga_clear_box pga-icon-btn pga-btn-delete"
                        data-tooltip="Limpar keywords deste grupo">
                        <span class="pga-icon">🗑️</span>
                      </button>

                    </div>
                  </div>
                  <span
                    class="pga_remove_box"
                    aria-label="Remover grupo de geração"
                    title="Remover este grupo"
                    data-tooltip="Remover este grupo">
                    ❌
                  </span>
                </div><!-- /.pga-card -->
              </div><!-- /.pga-collapse-body -->

            </div><!-- /.pga-gen-box -->

          </div>
        </div>

      </div>
    </div>
    <div class="pga-footer-fixed">

      <?php
      echo $chk['ok']
        ? '<button class="button button-primary" id="pga_plan">🪄 Planejar &amp; Gerar</button>'
        : '<button class="button button-primary" id="pga_plan" disabled>🪄 Planejar &amp; Gerar</button>';
      ?>
      <div class="pga-footer-actions">
        <div class="pga-actions-unit pga-icon-buttons">
          <!-- Salvar configurações -->
          <button type="button" class="pga_save_box pga-icon-btn pga-btn-save" data-tooltip="Salvar todas configurações" id="pga_save_keywords">
            <span class="pga-icon">💾</span>
          </button>

          <!-- Importar -->
          <button type="button" class="pga_import_box pga-icon-btn pga-btn-import" id="pga_add_box" data-tooltip="Adicionar grupo de geração">
            <span class="pga-icon">➕</span>
          </button>
          <button type="button" class="pga_import_box pga-icon-btn pga-btn-import" data-tooltip="Importar keywords (.txt)">
            <span class="pga-icon">⬅️</span>
          </button>

          <!-- Exportar -->
          <button type="button" class="pga_export_box pga-icon-btn pga-btn-export" data-tooltip="Exportar keywords (.txt)">
            <span class="pga-icon">➡️</span>
          </button>

          <div class="pga-done-dropup">
            <button
              type="button"
              id="pga_done_toggle"
              class="button pga-floating-btn pga-icon-btn"
              aria-expanded="false"
              aria-controls="pga_done_panel"
              data-tooltip="Ver frases já geradas">
              ✔
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
        </div>
      </div>
    </div>
<?php
  }


  protected static function generate_meta_description_ai(
    string $keyword,
    string $title,
    string $locale,
    string $content_html
  ): string {
    $keyword = trim($keyword);
    $title   = trim($title);
    $locale  = $locale ?: 'pt_BR';

    if ($title === '') {
      return '';
    }

    // 1) monta o prompt “inteligente” com contexto
    $prompt = PluginsAlpha_Prompts::build_meta_description_prompt(
      $keyword,
      $title,
      $locale,
      $content_html
    );

    // 2) chama o endpoint dedicado de meta description
    $resp = PluginsAlpha_AI::meta_description($prompt);

    if (is_wp_error($resp)) {
      return '';
    }

    $meta_desc = (string)$resp;


    // $resp é a descrição bruta (string)
    $raw = trim((string)$resp);
    if ($raw === '') {
      return '';
    }

    // 3) sanitiza: sem tags, uma linha só, tamanho ok
    $raw = wp_strip_all_tags($raw);
    $raw = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
    $raw = preg_replace('/\s+/', ' ', $raw); // uma linha só

    // corta entre 130 e 160 chars (segurança de tamanho)
    if (mb_strlen($raw) > 160) {
      $raw = mb_substr($raw, 0, 157) . '...';
    }

    return trim($raw);
  }

  /**
   * $args:
   *  - keywords[]  (usa a 1ª como foco)
   *  - template    (article|review|news|howto|list)
   *  - locale      (pt_BR|en_US...)
   *  - publish_time  (timestamp futuro)
   *  - category_id   (int)
   */


  public static function create_draft_and_outline(array $args)
  {
    // keyword pode vir como array ou string com \n (mas no fim usamos só a primeira linha)
    $kwSrc = $args['keyword'] ?? $args['keywords'] ?? '';

    if (is_array($kwSrc)) {
      $keywordRaw = trim((string)($kwSrc[0] ?? ''));
    } else {
      $lines      = preg_split('/\r\n|\r|\n/', (string)$kwSrc);
      $keywordRaw = trim($lines[0] ?? '');
    }

    // template pode vir como 'template' ou 'template_key'
    $template = $args['template']     ?? $args['template_key'] ?? 'discover_article';
    $length   = $args['length']       ?? 'short';
    $locale   = $args['locale']       ?? 'pt_BR';
    $provider = $args['provider'] ?? PluginsAlpha_AI::get_text_provider();
    $jobArgs = [
      'provider' => $provider,
      'template' => $template,
      'length'   => $length,
      'locale'   => $locale
    ];
    // publish_time vem pronto do plan
    $publish_ts = 0;
    if (!empty($args['publish_time'])) {
      $raw = $args['publish_time'];
      if (is_numeric($raw)) {
        $publish_ts = (int)$raw;
      } else {
        $t = strtotime((string)$raw);
        if ($t !== false) {
          $publish_ts = $t;
        }
      }
    }

    $category_id = intval($args['category_id'] ?? 0);
    $post_type   = !empty($args['post_type']) ? sanitize_key($args['post_type']) : 'posts_orion';

    // 🔹 Aqui a diferença: em MODELAR, keywordRaw é a URL
    $url     = '';
    $keyword = '';

    // 🔹 Aqui a diferença: em MODELAR, keywordRaw é a URL
    // E em MODELAR_YOUTUBE também, mas vamos buscar dados via API.
    $url     = '';
    $keyword = '';

    if ($template === 'modelar') {
      $url = $keywordRaw;

      if ($url === '') {
        return new WP_Error('pga_no_kw', 'URL vazia.');
      }

      // tenta derivar um “tema” a partir da URL (só pra título/slug/SEO)
      $derived = self::derive_keyword_from_url($url);
      if ($derived !== '') {
        $keyword = $derived;
      } else {
        $host = wp_parse_url($url, PHP_URL_HOST);
        $host = $host ?: $url;
        $keyword = 'Artigo baseado em ' . $host;
      }
    } elseif ($template === 'modelar_youtube') {
      $url = $keywordRaw;

      if ($url === '') {
        return new WP_Error('pga_no_kw', 'URL do YouTube vazia.');
      }

      if (!class_exists('PluginsAlpha_Youtube')) {
        return new WP_Error(
          'pga_youtube_missing_class',
          'Classe PluginsAlpha_Youtube não encontrada.'
        );
      }

      $yt = PluginsAlpha_Youtube::fetch_video_data($url);
      if (is_wp_error($yt)) {
        return $yt;
      }

      // título do artigo = título do vídeo (pode ajustar depois)
      $keyword = trim($yt['title'] ?? '');
      if ($keyword === '') {
        $keyword = 'Artigo baseado em vídeo do YouTube';
      }

      // injeta os dados do vídeo no jobArgs, para o provider/prompt se quiser usar
      $jobArgs['youtube'] = $yt;
    } else {
      // templates normais → keyword normal
      $keyword = $keywordRaw;
      if ($keyword === '') {
        return new WP_Error('pga_no_kw', 'Keyword vazia.');
      }
    }

    // slug seguro
    $slug = sanitize_title($keyword);
    if ($slug === '') {
      $slug = sanitize_title(uniqid('orion_', true));
    }

    if (!post_type_exists($post_type)) {
      if (post_type_exists('posts_orion')) {
        $post_type = 'posts_orion';
      } elseif (post_type_exists('post_orion')) {
        $post_type = 'post_orion';
      } else {
        // último fallback só pra não quebrar o fluxo
        $post_type = 'post';
      }
    }

    $postarr = [
      'post_type'    => $post_type,
      'post_status'  => 'draft',
      'post_title'   => '(Gerando) ' . $keyword,
      'post_name'    => $slug,
      'post_content' => '',
      'post_author'  => get_current_user_id(),
    ];

    if ($publish_ts > 0) {
      $postarr['post_date']     = date('Y-m-d H:i:s', $publish_ts);
      $postarr['post_date_gmt'] = gmdate('Y-m-d H:i:s', $publish_ts);
    }

    $draft_id = wp_insert_post($postarr, true);

    $draft_id = (int)$draft_id;

    if ($publish_ts > 0) {
      update_post_meta($draft_id, '_pga_publish_ts', $publish_ts);
    }
    update_post_meta($draft_id, '_pga_job_started', time());

    if ($category_id > 0) {
      wp_set_post_terms($draft_id, [(int)$category_id], 'category', false);
      update_post_meta($draft_id, '_pga_orion_category_ids', [(int)$category_id]);
    }
    // 1) TÍTULO — aqui já usamos o keyword DERIVADO e, no modelar, passamos a URL pro prompt
    $titlePrompt = PluginsAlpha_Prompts::build_title_prompt(
      $keyword,
      3,
      5,
      $locale,
      $url // vazio nos templates normais; preenchido no modelar
    );
    $titles = PluginsAlpha_AI::titles($titlePrompt, $jobArgs);
    if (is_wp_error($titles)) {
      return self::fail_job($draft_id, $titles, 'titles');
    }

    $chosenTitle = self::pick_best_title($titles, $keyword);
    if (!$chosenTitle) {
      $chosenTitle = ucfirst($keyword);
    }

    $jobArgs['keyword']      = $keyword;
    $jobArgs['url']          = $url;
    $jobArgs['chosen_title'] = $chosenTitle;

    // Salva base pra próximas chamadas
    update_post_meta($draft_id, '_pga_outline_length',   $length);
    update_post_meta($draft_id, '_pga_outline_locale',   $locale);
    update_post_meta($draft_id, '_pga_outline_keyword',  $keyword);
    update_post_meta($draft_id, '_pga_outline_template', $template);
    update_post_meta($draft_id, '_pga_outline_url',      $url);
    update_post_meta($draft_id, '_pga_chosen_title',     $chosenTitle);

    if ($publish_ts > 0) {
      update_post_meta($draft_id, '_pga_publish_ts', $publish_ts);
    }

    update_post_meta($draft_id, '_pga_job_status', 'outline_done');

    // lista completa de "keywords" só se vier como array – aqui tanto faz, não vamos usar extra pra modelar
    $allKeywords = [];
    if (is_array($kwSrc)) {
      $allKeywords = array_values(array_filter(array_map('trim', $kwSrc)));
    }

    // 2) OUTLINE – se for modelar, passa SÓ a URL
    // 2) OUTLINE – se for modelar URL ou modelar YouTube, tratamos diferente
    if ($template === 'modelar') {
      $outlinePrompt = PluginsAlpha_Prompts::build_outline_prompt_modelar(
        $url,
        $chosenTitle,
        $length,
        $locale
      );
    } elseif ($template === 'modelar_youtube') {
      $yt = $jobArgs['youtube'] ?? [];

      $outlinePrompt = PluginsAlpha_Prompts::build_outline_prompt_modelar_youtube(
        $url,
        $yt,
        $chosenTitle,
        $length,
        $locale
      );
    } else {
      $outlinePrompt = PluginsAlpha_Prompts::build_outline_prompt(
        $keyword,
        $chosenTitle,
        $length,
        $locale
      );
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

    // NORMALIZA as seções pra garantir que TODA seção tenha "id"
    $normalized = [];
    $h2Index    = 1;

    foreach ($sections as $sec) {
      if (!is_array($sec)) {
        $sec = [
          'heading' => (string)$sec,
          'level'   => 'h2',
        ];
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
            $child = [
              'heading' => (string)$child,
              'level'   => 'h3',
            ];
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

    update_post_meta($draft_id, '_pga_outline_sections', wp_json_encode($normalized));
    update_post_meta($draft_id, '_pga_job_status', 'outline_done');

    return [
      'post_id'   => $draft_id,
      'title'     => $chosenTitle,
      'sections'  => $normalized,
      'length'    => $length,
      'locale'    => $locale,
      'post_type' => $post_type,
    ];
  }

  /**
   * Tenta derivar uma keyword razoável a partir da URL:
   * - Título da página, se conseguir
   * - Senão, último segmento do path
   */
  protected static function derive_keyword_from_url(string $url): string
  {
    $url = trim($url);
    if ($url === '') return '';

    $resp = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($resp)) return '';

    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) return '';

    $body = wp_remote_retrieve_body($resp);
    if (!$body) return '';

    // tenta pegar o <title>
    if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m)) {
      $title = trim(wp_strip_all_tags($m[1]));
      if ($title !== '') return $title;
    }

    // fallback: último segmento da URL
    $path = wp_parse_url($url, PHP_URL_PATH);
    $path = trim((string)$path, "/");
    if ($path !== '') {
      $parts = explode('/', $path);
      $last  = end($parts);
      $last  = str_replace(['-', '_'], ' ', $last);
      return trim($last);
    }

    return '';
  }


  public static function generate_section_content(int $post_id, string $section_id)
  {
    $post_id = intval($post_id);
    if (!$post_id || get_post_type($post_id) === null) {
      return new WP_Error('pga_invalid_post', 'Post inválido.');
    }

    $sections_json = get_post_meta($post_id, '_pga_outline_sections', true);
    $sections      = json_decode($sections_json, true) ?: [];

    if (!$sections) {
      return new WP_Error('pga_no_outline', 'Esboço não encontrado para este post.');
    }

    // 🔧 NORMALIZAÇÃO EXTRA — garante heading/id mesmo se o outline antigo não tiver
    $normalized = [];
    $h2Index    = 1;

    foreach ($sections as $sec) {
      // Se vier string simples, transforma em array com heading
      if (!is_array($sec)) {
        $sec = [
          'heading' => (string) $sec,
          'level'   => 'h2',
        ];
      }

      // Se tiver "title" mas não "heading", usa "title"
      if (empty($sec['heading']) && !empty($sec['title'])) {
        $sec['heading'] = (string) $sec['title'];
      }
      // fallback extra: se tiver "text"
      if (empty($sec['heading']) && !empty($sec['text'])) {
        $sec['heading'] = (string) $sec['text'];
      }

      // Garante level
      if (empty($sec['level'])) {
        $sec['level'] = 'h2';
      }

      // ID da H2
      if (empty($sec['id'])) {
        $sec['id'] = (string) $h2Index;
      }

      // Normaliza children também
      if (!empty($sec['children']) && is_array($sec['children'])) {
        $childIndex = 1;
        foreach ($sec['children'] as $ci => $child) {
          if (!is_array($child)) {
            $child = [
              'heading' => (string) $child,
              'level'   => 'h3',
            ];
          }

          if (empty($child['heading']) && !empty($child['title'])) {
            $child['heading'] = (string) $child['title'];
          }
          if (empty($child['heading']) && !empty($child['text'])) {
            $child['heading'] = (string) $child['text'];
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

    $sections = $normalized;

    $length  = get_post_meta($post_id, '_pga_outline_length',   true) ?: 'short';
    $locale  = get_post_meta($post_id, '_pga_outline_locale',   true) ?: 'pt_BR';
    $keyword = get_post_meta($post_id, '_pga_outline_keyword',  true) ?: '';
    $title   = get_post_meta($post_id, '_pga_chosen_title',     true) ?: $keyword;
    $url     = get_post_meta($post_id, '_pga_outline_url',      true) ?: '';

    if ($keyword === '') {
      return new WP_Error('pga_no_kw', 'Keyword vazia no outline.');
    }

    // acha a seção pelo id JÁ NORMALIZADO
    $section = null;
    foreach ($sections as $s) {
      if ((string)($s['id'] ?? '') === (string)$section_id) {
        $section = $s;
        break;
      }
    }

    if (!$section) {
      return new WP_Error('pga_section_not_found', "Seção {$section_id} não encontrada no esboço.");
    }

    // se já tiver conteúdo salvo pra essa seção, não precisa gerar de novo
    $meta_key = '_pga_section_content_' . sanitize_key($section_id);
    $existing = get_post_meta($post_id, $meta_key, true);
    if (!empty($existing)) {
      return [
        'post_id'     => $post_id,
        'section_id'  => $section_id,
        'content'     => $existing,
        'alreadyDone' => true,
      ];
    }
    $sectionsCount = count($sections);
    // monta prompt da seção
    $sectionPrompt = PluginsAlpha_Prompts::build_section_prompt(
      $keyword,
      $title,
      $section,
      $length,
      $locale,
      $sectionsCount,
      $url
    );
    // aumenta timeout só pra essa chamada
    $tmpTimeout = function ($t) {
      return max((int)$t, 120);
    };
    add_filter('http_request_timeout', $tmpTimeout, 9999, 1);

    $resp = PluginsAlpha_AI::complete($sectionPrompt);

    remove_filter('http_request_timeout', $tmpTimeout, 9999);

    if (is_wp_error($resp)) {
      if ($resp->get_error_code() === 'pga_parse') {
        $data    = (array) $resp->get_error_data();
        $snippet = isset($data['snippet']) ? (string) $data['snippet'] : '';

        // se parece HTML de seção, trata como sucesso
        if ($snippet !== '' && preg_match('/<(h2|h3|p|ul|ol|li)[^>]*>/i', $snippet)) {
          $resp = [
            'title'             => '',
            'titles_suggestions' => [],
            'content'           => $snippet,
            'meta_title'        => '',
            'meta_description'  => '',
            'image_alt'         => '',
            'links'             => [
              'internal' => [],
              'external' => [],
            ],
          ];
        } else {
          // não deu pra aproveitar → falha normal
          return self::fail_job($post_id, $resp, 'section_' . $section_id);
        }
      } else {
        // qualquer outro erro (HTTP, timeout, etc.)
        return self::fail_job($post_id, $resp, 'section_' . $section_id);
      }
    }

    $content_html = trim((string)($resp['content'] ?? ''));

    if ($content_html === '') {
      return new WP_Error('pga_section_empty', 'Nenhum conteúdo gerado para a seção.');
    }

    // salva no meta
    update_post_meta($post_id, $meta_key, $content_html);

    // opcional: guarda primeiros meta_title/description gerados
    $meta_title = (string)($resp['meta_title'] ?? '');
    $meta_desc  = (string)($resp['meta_description'] ?? '');
    $image_alt  = (string)($resp['image_alt'] ?? '');

    if ($meta_title && !get_post_meta($post_id, '_pga_meta_title', true)) {
      update_post_meta($post_id, '_pga_meta_title', $meta_title);
    }
    if ($meta_desc && !get_post_meta($post_id, '_pga_meta_description', true)) {
      update_post_meta($post_id, '_pga_meta_description', $meta_desc);
    }
    if ($image_alt && !get_post_meta($post_id, '_pga_image_alt', true)) {
      update_post_meta($post_id, '_pga_image_alt', $image_alt);
    }

    return [
      'post_id'    => $post_id,
      'section_id' => $section_id,
      'content'    => $content_html,
    ];
  }


  public static function finalize_from_sections(int $post_id, array $args = [])
  {
    $post_id = intval($post_id);
    if (!$post_id || get_post_type($post_id) === null) {
      return new WP_Error('pga_invalid_post', 'Post inválido.');
    }

    // --- 1) Carrega outline e dados base ---
    $sections_json = get_post_meta($post_id, '_pga_outline_sections', true);
    $sections      = json_decode($sections_json, true) ?: [];

    if (!$sections) {
      return new WP_Error('pga_no_outline', 'Esboço não encontrado para este post.');
    }

    $locale    = get_post_meta($post_id, '_pga_outline_locale',   true) ?: 'pt_BR';
    $keyword   = get_post_meta($post_id, '_pga_outline_keyword',  true) ?: '';
    $template  = get_post_meta($post_id, '_pga_outline_template', true) ?: 'discover_article';
    $title     = get_post_meta($post_id, '_pga_chosen_title',     true) ?: $keyword;
    $post_type = get_post_type($post_id) ?: 'posts_orion';

    // --- 3) Monta conteúdo final a partir das seções ---
    $htmlParts = [];
    foreach ($sections as $s) {
      $sid      = (string)($s['id'] ?? '');
      $meta_key = '_pga_section_content_' . sanitize_key($sid);
      $chunk    = get_post_meta($post_id, $meta_key, true);

      if ($chunk) {
        $htmlParts[] = $chunk;
      }
    }

    $content_html = trim(implode("\n\n", $htmlParts));

    // remove QUALQUER H1 gerado pela IA
    $content_html = preg_replace('#</?h1[^>]*>#i', '', $content_html);

    if ($content_html === '') {
      return new WP_Error('pga_final_empty', 'Nenhum conteúdo de seção encontrado para juntar.');
    }

    // --- 3.1) Remove APENAS o primeiro H2 (introdução)
    $content_html = self::remove_first_h2($content_html);

    // --- 3.2) Aplica links internos, se houver configuração ---
    $internal = [];
    if (!empty($args['internal_links']) && is_array($args['internal_links'])) {
      $internal = $args['internal_links'];
    }

    if (!empty($internal)) {
      $content_html = self::apply_internal_links_to_content(
        $content_html,
        $internal,
        $keyword,
        $post_id
      );
    }

    // --- 4) Meta dados ---
    $meta_title = get_post_meta($post_id, '_pga_meta_title',       true) ?: $title;
    $meta_desc  = get_post_meta($post_id, '_pga_meta_description', true) ?: '';
    $image_alt  = get_post_meta($post_id, '_pga_image_alt',        true) ?: '';

    // --- 5) Agenda / criação final do post ---
    // se veio do REST com generate_image definido, respeita.
    // se não vier nada, padrão é TRUE (comportamento antigo).
    $generate_image = array_key_exists('generate_image', $args)
      ? !empty($args['generate_image'])
      : true;

    $res = self::do_schedule_post($post_id, [
      'keyword'        => $keyword,
      'title'          => $title,
      'content'        => $content_html,
      'locale'         => $locale,
      'post_id'        => $post_id,
      'template'       => $template,
      'post_type'      => $post_type,
      'meta_title'     => $meta_title,
      'meta_desc'      => $meta_desc,
      'image_alt'      => $image_alt,
      'generate_image' => $generate_image,
      'edit'           => get_edit_post_link($post_id, ''),
    ]);

    if (is_wp_error($res)) {
      return self::fail_job($post_id, $res, 'finalize');
    }

    return [
      'ok'        => true,
      'post_id'   => $post_id,
      'edit'      => get_edit_post_link($post_id, ''),
      'view_link' => get_permalink($post_id),
      'keyword'   => $keyword,
    ];
  }

  /**
   * Define limite "saudável" de links internos por tamanho de texto.
   */
  protected static function max_links_for_length(int $wordCount): int
  {
    if ($wordCount < 600)  return 2;
    if ($wordCount < 1200) return 4;
    if ($wordCount < 2000) return 5;
    return 6;
  }

  /**
   * Monta e injeta links internos no HTML final.
   * - Respeita modo (none/manual/auto/pillar)
   * - NUNCA passa do limite configurado e nem do limite por tamanho
   * - Distribui de baixo pra cima (final + meio).
   */
  protected static function apply_internal_links_to_content(
    string $html,
    array $opts,
    string $keyword,
    int $post_id
  ): string {
    $mode = isset($opts['mode']) ? trim((string)$opts['mode']) : 'none';
    if ($mode === 'none') {
      return $html;
    }

    $maxUser = max(0, intval($opts['max'] ?? 0));

    // conta palavras do conteúdo para limitar quantidade
    $plain      = wp_strip_all_tags($html);
    $wordCount  = max(0, str_word_count($plain));
    $maxBySize  = self::max_links_for_length($wordCount);

    // se usuário não pôs nada, usa limite natural
    if ($maxUser <= 0) {
      $maxUser = $maxBySize;
    }

    // limite final = não passar nem do tamanho nem do configurado
    $maxFinal = min($maxUser, $maxBySize);
    if ($maxFinal <= 0) {
      return $html;
    }

    // --- MONTA LISTA DE POSTS ALVO ---
    $targets = [];

    if ($mode === 'manual') {
      $idsRaw = isset($opts['manual_ids']) ? (string)$opts['manual_ids'] : '';
      $ids    = array_filter(array_map('intval', preg_split('/[,\s]+/', $idsRaw)));

      if (!$ids) {
        return $html;
      }

      $q = new \WP_Query([
        'post_type'      => 'posts_orion',
        'post__in'       => $ids,
        'posts_per_page' => count($ids),
        'orderby'        => 'post__in',
      ]);

      if ($q->have_posts()) {
        foreach ($q->posts as $p) {
          if ((int)$p->ID === (int)$post_id) {
            continue; // não linkar para si mesmo
          }
          $targets[] = [
            'url'   => get_permalink($p),
            'title' => get_the_title($p),
          ];
        }
      }
      wp_reset_postdata();
    } elseif ($mode === 'auto' || $mode === 'pillar') {

      // mesmo post_type do post atual
      $post_type = get_post_type($post_id) ?: 'posts_orion';

      // categorias do post atual
      $cat_ids = wp_get_post_terms($post_id, 'category', ['fields' => 'ids']);
      if (is_wp_error($cat_ids)) {
        $cat_ids = [];
      }

      // base: mesma categoria (quando existir)
      $base_args = [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'post__not_in'   => [$post_id],
        'posts_per_page' => $maxFinal * 2,
        'orderby'        => 'date',
        'order'          => 'DESC',
      ];

      if (!empty($cat_ids)) {
        $base_args['category__in'] = $cat_ids;
      }

      $q = null;

      if ($mode === 'pillar') {
        // 1) TENTA PRIMEIRO: posts PILAR (Yoast, Rank Math, AIOSEO) da mesma categoria
        $pillar_args = $base_args;

        $pillar_args['meta_query'] = [
          'relation' => 'OR',

          // Yoast: conteúdo pilar / cornerstone
          [
            'key'   => '_yoast_wpseo_is_cornerstone',
            'value' => '1',
          ],

          // Rank Math: conteúdo pilar
          [
            'key'   => '_rank_math_pillar_content',
            'value' => '1',
          ],
          [
            'key'   => '_rank_math_pillar_content',
            'value' => 'on',
          ],

          // AIOSEO (ajustável se precisar)
          [
            'key'   => '_aioseo_pillar_content',
            'value' => '1',
          ],
        ];

        $q = new \WP_Query($pillar_args);

        // 2) SE NÃO TIVER NENHUM PILAR, cai para a base normal (mesma categoria)
        if (!$q->have_posts()) {
          $q = new \WP_Query($base_args);
        }
      } else {
        // modo AUTO → só mesma categoria, sem filtro de pilar
        $q = new \WP_Query($base_args);
      }


      if ($q && $q->have_posts()) {
        foreach ($q->posts as $p) {
          if ((int) $p->ID === (int) $post_id) {
            continue; // não linka pra ele mesmo
          }

          $targets[] = [
            'url'   => get_permalink($p),
            'title' => get_the_title($p),
          ];
        }
      }

      wp_reset_postdata();
    }

    if (empty($targets)) {
      return $html;
    }

    // garante que não vamos extrapolar a quantidade de posts
    // se tiver menos targets do que maxFinal, podemos repetir alguns
    $links = [];
    $i     = 0;
    while (count($links) < $maxFinal && !empty($targets)) {
      $links[] = $targets[$i % count($targets)];
      $i++;
    }

    if (!$links) {
      return $html;
    }

    return self::inject_internal_links_in_html($html, $links);
  }

  /**
   * Insere CTAs "Leia também" distribuídos no texto:
   * - Sempre a partir do meio pra baixo
   * - Nunca imediatamente em cima de <h2> (não cola no título)
   */
  protected static function inject_internal_links_in_html(string $html, array $links): string
  {
    // Normaliza lista de links (garante que tem url e title)
    $links = array_values(array_filter($links, function ($l) {
      return !empty($l['url']) && !empty($l['title']);
    }));

    $totalLinks = count($links);
    if ($totalLinks === 0 || trim($html) === '') {
      return $html;
    }

    // Se não tivermos H2, tudo vai pro final (regra do "último pode ir no final")
    $parts = preg_split(
      '~(<h2\b[^>]*>.*?</h2>)~is',
      $html,
      -1,
      PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
    );

    if (!$parts || count($parts) === 1) {
      $ctaHtml = '';
      foreach ($links as $l) {
        $ctaHtml .= sprintf(
          '<p><strong>Leia também:</strong> <a href="%s">%s</a></p>',
          esc_url($l['url']),
          esc_html($l['title'])
        );
      }
      return $html . "\n\n" . $ctaHtml;
    }

    // Índices dos blocos que são H2
    $h2Idx = [];
    foreach ($parts as $idx => $chunk) {
      if (preg_match('~^<h2\b~i', trim($chunk))) {
        $h2Idx[] = $idx;
      }
    }

    if (empty($h2Idx)) {
      // nenhum H2 detectado → tudo no final
      $ctaHtml = '';
      foreach ($links as $l) {
        $ctaHtml .= sprintf(
          '<p><strong>Leia também:</strong> <a href="%s">%s</a></p>',
          esc_url($l['url']),
          esc_html($l['title'])
        );
      }
      return $html . "\n\n" . $ctaHtml;
    }

    // Quantos links vamos colocar ACIMA de H2:
    // - se só tiver 1 link → ele pode ir no final do post
    // - se tiver 2+ → (total - 1) acima de H2, o último no final
    $linksAboveH2 = ($totalLinks > 1) ? $totalLinks - 1 : 0;
    $linksAboveH2 = min($linksAboveH2, count($h2Idx));

    $positions = [];

    if ($linksAboveH2 > 0) {
      $nCandidates = count($h2Idx);

      // Distribui os CTAs entre os H2, mais concentrado do meio pra baixo
      for ($i = 0; $i < $linksAboveH2; $i++) {
        if ($linksAboveH2 === 1) {
          // um só → perto do final
          $frac = 0.9;
        } else {
          // vários → espalha entre meio e final
          $frac = ($i + 1) / ($linksAboveH2 + 1);
        }

        $candIdx = (int) round($frac * ($nCandidates - 1));
        $candIdx = max(0, min($nCandidates - 1, $candIdx));
        $positions[] = $h2Idx[$candIdx];
      }

      // remove duplicados e ordena
      $positions = array_values(array_unique($positions));
      sort($positions);
    }

    // Mapeia: índice do bloco H2 -> CTAs que vão antes dele
    $injectMap = [];
    $linkIndex = 0;

    foreach ($positions as $pos) {
      if (!isset($links[$linkIndex])) break;

      $l = $links[$linkIndex];
      $cta = sprintf(
        '<p><strong>Leia também:</strong> <a href="%s">%s</a></p>',
        esc_url($l['url']),
        esc_html($l['title'])
      );

      if (!isset($injectMap[$pos])) {
        $injectMap[$pos] = [];
      }
      $injectMap[$pos][] = $cta;

      $linkIndex++;
    }

    // Reconstrói HTML inserindo CTAs ANTES dos H2 escolhidos
    $out = '';
    foreach ($parts as $idx => $chunk) {
      if (!empty($injectMap[$idx])) {
        $out .= implode("\n", $injectMap[$idx]) . "\n";
      }
      $out .= $chunk;
    }

    // Se ainda houver link sobrando (último), ele vai no FINAL do conteúdo
    if ($linkIndex < $totalLinks) {
      $out .= "\n\n";
      for (; $linkIndex < $totalLinks; $linkIndex++) {
        $l = $links[$linkIndex];
        $out .= sprintf(
          '<p><strong>Leia também:</strong> <a href="%s">%s</a></p>',
          esc_url($l['url']),
          esc_html($l['title'])
        );
      }
    }

    return $out;
  }

  /**
   * Remove apenas o primeiro <h2>...</h2> do conteúdo (introdução).
   */
  protected static function remove_first_h2(string $html): string
  {
    return preg_replace('/<h2\b[^>]*>.*?<\/h2>/is', '', $html, 1);
  }


  /**
   * Remove APENAS o primeiro <h2>...</h2> do conteúdo.
   * Assim o post final fica:
   *   H1 (do título do WP)
   *   parágrafo já de cara, sem H2 "Introdução".
   */
  protected static function drop_first_intro_h2(string $html): string
  {
    return (string) preg_replace('/<h2\b[^>]*>.*?<\/h2>/is', '', $html, 1);
  }
  /**
   * Decide quais posts serão alvo dos links internos.
   *
   * $cfg:
   *   - mode: 'none' | 'auto' | 'pillar' | 'manual'
   *   - max:  int
   *   - manual_ids: string "12,34,56" ou array
   */
  protected static function resolve_internal_link_targets(array $cfg, int $post_id): array
  {
    $mode = isset($cfg['mode']) ? (string) $cfg['mode'] : 'none';
    $max  = max(0, intval($cfg['max'] ?? 0));

    if ($mode === 'none' || $max <= 0) {
      return [];
    }

    $post_type = get_post_type($post_id) ?: 'posts_orion';

    // --- MANUAL: usa IDs enviados ---
    if ($mode === 'manual') {
      $raw = $cfg['manual_ids'] ?? '';
      if (is_array($raw)) {
        $ids = array_map('intval', $raw);
      } else {
        $ids = array_filter(array_map('intval', preg_split('/[,\s]+/', (string) $raw)));
      }

      // remove o próprio post
      $ids = array_diff($ids, [$post_id]);
      if (empty($ids)) return [];

      $query = get_posts([
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'post__in'       => $ids,
        'orderby'        => 'post__in', // mantém ordem que veio no select
        'posts_per_page' => $max,
      ]);

      return is_array($query) ? $query : [];
    }

    // --- AUTO / PILAR: MVP simples -> usa posts recentes ---
    // Se no futuro quiser diferenciar "pillar", pode filtrar por categoria/meta aqui.
    $query = get_posts([
      'post_type'      => $post_type,
      'post_status'    => 'publish',
      'post__not_in'   => [$post_id],
      'orderby'        => 'date',
      'order'          => 'DESC',
      'posts_per_page' => $max * 2, // pega um pouco mais, se quiser filtrar depois
    ]);

    return is_array($query) ? $query : [];
  }
  /**
   * Injeta parágrafos "Leia também" dentro do conteúdo.
   *
   * - Nunca repete o mesmo post várias vezes.
   * - Usa no máximo min(max, número de posts disponíveis).
   * - Tenta encaixar depois de parágrafos <p>…</p>.
   */
  protected static function inject_internal_links(string $html, int $post_id, array $cfg): string
  {
    $mode = isset($cfg['mode']) ? (string) $cfg['mode'] : 'none';
    $max  = max(0, intval($cfg['max'] ?? 0));

    if ($mode === 'none' || $max <= 0) {
      return $html;
    }

    $targets = self::resolve_internal_link_targets($cfg, $post_id);
    if (empty($targets)) {
      return $html;
    }

    // NUNCA excede o número de posts disponíveis
    $limit   = min($max, count($targets));
    $targets = array_slice($targets, 0, $limit);

    // quebra em blocos incluindo o </p> como delimitador
    $parts = preg_split('~(</p>)~i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts) || count($parts) < 2) {
      // fallback: sem <p>, só adiciona ao final
      $linksHtml = '';
      foreach ($targets as $t) {
        $linksHtml .= sprintf(
          '<p><strong>%s</strong> <a href="%s">%s</a></p>',
          esc_html__('Leia também:', 'plugins-alpha'),
          esc_url(get_permalink($t->ID)),
          esc_html(get_the_title($t->ID))
        );
      }
      return $html . "\n\n" . $linksHtml;
    }

    $out       = '';
    $inserted  = 0;
    $paragraph = 0;

    foreach ($parts as $chunk) {
      $out .= $chunk;

      // sempre que achar um </p>, é chance de inserir CTA
      if (preg_match('~</p>~i', $chunk)) {
        if ($inserted < $limit) {
          $t = $targets[$inserted];

          $out .= sprintf(
            '<p><strong>%s</strong> <a href="%s">%s</a></p>',
            esc_html__('Leia também:', 'plugins-alpha'),
            esc_url(get_permalink($t->ID)),
            esc_html(get_the_title($t->ID))
          );

          $inserted++;
        }

        $paragraph++;
      }
    }

    return $out;
  }


  /** Seleciona o melhor título (keyword + número + curto) */
  /** Escolhe o melhor título (contém keyword, tem número, é curto, evita “guia completo”). */
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

  private static function do_schedule_post(int $post_id, array $args = [])
  {
    $post_id = intval($post_id);
    if (!$post_id || get_post_type($post_id) === null) {
      return new WP_Error('pga_invalid_post', 'Post inválido.');
    }

    $keyword      = (string)($args['keyword']      ?? '');
    $title        = (string)($args['title']        ?? '');
    $content_html = (string)($args['content']      ?? '');
    $locale       = (string)($args['locale']       ?? 'pt_BR');
    $template     = (string)($args['template']     ?? 'discover_article');
    $publish_ts = (int) get_post_meta($post_id, '_pga_publish_ts', true);

    // se por algum motivo não tiver meta (fallback raro)
    if (!$publish_ts) {
      // tenta args (caso fluxos antigos ainda enviem)
      if (!empty($args['publish_time'])) {
        $raw = $args['publish_time'];
        if (is_numeric($raw)) {
          $publish_ts = (int)$raw;
        } else {
          $t = strtotime((string)$raw);
          if ($t !== false) {
            $publish_ts = $t;
          }
        }
      }
    }

    $post_type    = !empty($args['post_type']) ? sanitize_key($args['post_type']) : (get_post_type($post_id) ?: 'posts_orion');

    $meta_title   = (string)($args['meta_title']   ?? '');
    $meta_desc    = (string)($args['meta_desc']    ?? '');
    $image_alt    = (string)($args['image_alt']    ?? '');
    $generate_img = !empty($args['generate_image']);

    if ($title === '' || $content_html === '') {
      return new WP_Error(
        'empty_content',
        'O conteúdo, o título e o resumo estão vazios.',
        ['status' => 400, 'post_id' => $post_id]
      );
    }

    // Garante mínimo de 60s no futuro
    if ($publish_ts < (time() + 60)) {
      $publish_ts = time() + 60;
    }

    // --- NÃO POSTAR DE MADRUGADA (00:00–05:59) ---
    $offset   = get_option('gmt_offset') * HOUR_IN_SECONDS;
    $local_ts = $publish_ts + $offset;
    $hour     = (int)gmdate('G', $local_ts);

    if ($hour >= 0 && $hour < 6) {
      $diff       = (6 - $hour) * HOUR_IN_SECONDS;
      $publish_ts = $publish_ts + $diff + wp_rand(300, 2400);
    }

    // Limpa qualquer H1
    $content_html = preg_replace('#</?h1[^>]*>#i', '', $content_html);
    $upd = [
      'ID'            => $post_id,
      'post_title'    => wp_strip_all_tags($title),
      'post_content'  => $content_html,
      'post_status'   => 'future',
      'post_type'     => $post_type,
      'post_date'     => get_date_from_gmt(gmdate('Y-m-d H:i:s', $publish_ts), 'Y-m-d H:i:s'),
      'post_date_gmt' => gmdate('Y-m-d H:i:s', $publish_ts),
    ];

    $updated_id = wp_update_post($upd, true);
    if (is_wp_error($updated_id)) {
      return new WP_Error(
        $updated_id->get_error_code() ?: 'pga_wp_update',
        $updated_id->get_error_message(),
        [
          'status'   => 500,
          'post_id'  => $post_id,
          'step'     => 'wp_update_post',
          'payload'  => $upd,
        ]
      );
    }

    // SEO / Meta
    if (!$meta_title) {
      $meta_title = $title;
    }

    if (class_exists('PluginsAlpha_SEO')) {
      PluginsAlpha_SEO::apply_meta($post_id, [
        'title'         => $meta_title,
        'description'   => $meta_desc,
        'focus_keyword' => $keyword,
      ]);
    }

    if ($meta_title) update_post_meta($post_id, '_pga_meta_title',       $meta_title);
    if ($meta_desc)  update_post_meta($post_id, '_pga_meta_description', $meta_desc);
    if ($image_alt)  update_post_meta($post_id, '_pga_image_alt',        $image_alt);

    // status do job
    update_post_meta($post_id, '_pga_job_status', 'done');
    delete_post_meta($post_id, '_pga_job_started');

    return [
      'edit'      => get_edit_post_link($post_id, ''),
      'post_id'   => $post_id,
      'view_link' => get_permalink($post_id),
    ];
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



  /**
   * Transforma um META-PROMPT de imagem (tipo "Você é um gerador de prompts...")
   * em um único prompt final, pronto pra ser enviado pro provider.
   */
  protected static function resolve_image_prompt(string $raw, string $locale = 'pt_BR'): string
  {
    $raw = trim($raw);
    if ($raw === '') {
      return '';
    }

    $looks_like_meta =
      stripos($raw, 'você é um gerador de prompts') !== false ||
      stripos($raw, 'you are a prompt generator') !== false;

    if (!$looks_like_meta) {
      return $raw;
    }

    $prompt_for_ai = $raw . "\n\n" .
      "Agora, com base em TODAS as instruções acima, responda APENAS com um único prompt " .
      "de imagem, em {$locale}, em uma única linha, sem aspas, sem explicações, " .
      "sem quebra de linha extra e sem repetir as regras.";

    if (!class_exists('PluginsAlpha_AI')) {
      return $raw;
    }
    $resp = PluginsAlpha_AI::complete($prompt_for_ai);

    if (is_wp_error($resp)) {
      return $raw;
    }

    $content = trim((string)($resp['content'] ?? ''));

    return $content !== '' ? $content : $raw;
  }
}
