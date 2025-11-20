<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PluginsAlpha_OpenAI')) require_once __DIR__ . '/OpenAI.php';

class PluginsAlpha_Pages_Generator
{
  public static function render(): void
  {
    $opt = PluginsAlpha_Settings::get(); ?>
    <div class="wrap pga-wrap pga-layout">
      <div class="pga-main">
        <h1>Gerador — Alpha Órion</h1>
        <div class="pga-card">
          <div class="pga-row between">
            <h2 style="margin:0">Frases pendentes (1 por linha)</h2>
            <div class="pga-actions">
              <button class="button" id="pga_save_keywords">Salvar</button>
              <button class="button" id="pga_kw_import">Importar .txt</button>
              <button class="button" id="pga_kw_export">Exportar .txt</button>
              <button class="button button-link-delete" id="pga_kw_clear_pending">Limpar</button>
            </div>
          </div>
          <textarea id="pga_keywords" rows="16" placeholder="Uma por linha"></textarea>
        </div>

        <div class="pga-card">
          <div class="pga-row">
            <div class="pga-field" style="width: 100%; flex: 1 1 100%; display: none;">
              <label>URL (opcional)</label>
              <input id="pga_source_url" type="url" placeholder="https://...">
            </div>
            <div class="pga-field">
              <label>Locale</label>
              <select id="pga_locale">
                <option value="pt_BR" <?php selected(($opt['defaults']['locale'] ?? '') === 'pt_BR'); ?>>Português (Brasil)</option>
                <option value="en_US" <?php selected(($opt['defaults']['locale'] ?? '') === 'en_US'); ?>>English (US)</option>
                <option value="es_ES" <?php selected(($opt['defaults']['locale'] ?? '') === 'es_ES'); ?>>Español</option>
                <option value="fr_FR" <?php selected(($opt['defaults']['locale'] ?? '') === 'fr_FR'); ?>>Français</option>
              </select>
            </div>

            <div class="pga-field">
              <label>Modelo de Post</label>
              <select id="pga_template_key">
                <option value="discover_article">Discover (artigo)</option>
                <option value="faq">FAQ</option>
                <option value="review_roundup">Review comparativo (vários)</option>
                <option value="review_single">Review (1 produto)</option>
                <!--<option value="article">Artigo</option>-->
                <option value="howto">Guia / How-to</option>
                <!--<option value="list">Lista</option>-->
                <option value="news">Notícia</option>
                <option value="modelar">Modelar URL</option>
              </select>
            </div>

            <div class="pga-field">
              <label>Categoria</label>
              <?php
              // dropdown de categorias (padrão do WP)
              wp_dropdown_categories([
                'show_option_none' => '— Sem categoria —',
                'option_none_value' => '0',
                'taxonomy'         => 'category',
                'hide_empty'       => 0,
                'name'             => 'pga_category',
                'id'               => 'pga_category',
                'class'            => 'regular-text',
                'orderby'          => 'name',
                'hierarchical'     => true,
                'value_field'      => 'term_id',
                'selected'         => 0,
              ]);
              ?>
            </div>
            <div class="pga-field"><label>Quantidade total</label><input id="pga_total" type="number" min="1" step="1" value="6"></div>
            <div class="pga-field"><label>Posts por dia</label><input id="pga_per_day" type="number" min="1" step="1" value="3"></div>
            <div class="pga-field"><label>Primeira publicação ≥</label><input id="pga_first_delay_hours" type="number" min="2" step="1" value="2"> horas</div>
            <div class="pga-field">
              <label for="pga_length">Extensão</label>
              <select id="pga_length">
                <option value="short">Pequeno (600 a 800 palavras)</option>
                <option value="medium">Médio (800 a 1500 palavras)</option>
                <option value="long">Longo (1500 a 2500 palavras)</option>
                <option value="extra-long">Extra Longo (2500 a 5000 palavras)</option>
              </select>
              <p class="description">
                Pequeno = post rápido • Médio = artigo completo • Longo = artigo aprofundado.
              </p>
            </div>
          </div>

          <div class="pga-row radio" style="display:none">
            <label><input type="radio" name="pga_mode" value="multi" checked> 1 post por palavra-chave</label>
            <label><input type="radio" name="pga_mode" value="single"> 1 artigo combinando todas</label>
          </div>

          <?php
          $chk = PluginsAlpha_License::check('orion');

          if (!$chk['ok']) {
            $url = admin_url('admin.php?page=plugins-alpha-dashboard');

            echo '<div class="notice notice-error is-dismissible"><p>'
              . esc_html__('Módulo não ativado.', 'plugins-alpha')
              . ' <a href="' . esc_url($url) . '">'
              . esc_html__('Clique aqui para ativar', 'plugins-alpha')
              . '</a></p></div>';
          }

          echo $chk['ok']
            ? '<button class="button button-primary" id="pga_plan">Planejar & Gerar</button>'
            : '<button class="button button-primary" id="pga_plan" disabled>Planejar & Gerar</button>';
          ?>


        </div>
      </div>

      <aside class="pga-sidebar">
        <div class="pga-card">
          <div class="pga-row between">
            <h2 style="margin:0">Concluídas</h2>
            <button class="button button-link-delete" id="pga_kw_clear_done">Limpar</button>
          </div>
          <ul id="pga_kw_done" class="pga-list done"></ul>
        </div>
      </aside>
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
    if (!class_exists('PluginsAlpha_OpenAI')) {
      return '';
    }

    $resp = PluginsAlpha_OpenAI::meta_description($prompt);

    if (is_wp_error($resp)) {
      return '';
    }

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
    // KEYWORD pode vir como array OU como string "\n"
    $kwSrc = $args['keyword'] ?? $args['keywords'] ?? '';

    if (is_array($kwSrc)) {
      $keyword = trim((string)($kwSrc[0] ?? ''));
    } else {
      // string: pode ser só uma keyword ou várias linhas
      $lines   = preg_split('/\r\n|\r|\n/', (string)$kwSrc);
      $keyword = trim($lines[0] ?? '');
    }

    // template pode vir como 'template' ou 'template_key'
    $template  = $args['template']     ?? $args['template_key'] ?? 'discover_article';
    $length    = $args['length']       ?? 'short';
    $locale    = $args['locale']       ?? 'pt_BR';
    $url       = $args['source_url']   ?? '';

    $publish_ts = 0;

    if ($keyword === '' && !empty($url)) {
      $keyword = self::derive_keyword_from_url($url);
    }

    // 🔹 se ainda assim não tiver nada, aí sim erro
    if ($keyword === '' && $url === '') {
      return new WP_Error('pga_no_kw', 'Keyword vazia e URL ausente.');
    }

    // se ainda não conseguiu keyword mas tem URL, usa algo genérico
    if ($keyword === '' && $url !== '') {
      $keyword = 'Artigo baseado em ' . parse_url($url, PHP_URL_HOST);
    }

    // 1) Se veio publish_time no args, pode ser timestamp OU string de data
    if (!empty($args['publish_time'])) {
      $raw = $args['publish_time'];

      if (is_numeric($raw)) {
        // timestamp em segundos
        $publish_ts = (int) $raw;
      } else {
        // tenta interpretar como data/hora
        $t = strtotime((string) $raw);
        if ($t !== false) {
          $publish_ts = $t;
        }
      }
    }

    // 2) Se ainda não temos nada válido, cai pro compute_publish_time
    if (!$publish_ts) {
      $publish_ts = self::compute_publish_time($args);
    }

    $category_id = intval($args['category_id'] ?? 0);
    $post_type   = !empty($args['post_type']) ? sanitize_key($args['post_type']) : 'posts_orion';

    if ($keyword === '') {
      return new WP_Error('pga_no_kw', 'Keyword vazia.');
    }

    $slug = sanitize_title($keyword);

    // 0) Cria rascunho
    $draft_id = wp_insert_post([
      'post_type'    => $post_type,
      'post_status'  => 'draft', // se você já deixou assim pra garantir agendamento
      'post_title'   => '(Gerando) ' . $keyword,
      'post_name'    => $slug,
      'post_content' => '',
      'post_author'  => get_current_user_id(),
    ], true);

    if (is_wp_error($draft_id)) {
      return $draft_id;
    }

    // salva o horário pra usar no finalize
    update_post_meta($draft_id, '_pga_publish_ts', $publish_ts);
    update_post_meta($draft_id, '_pga_job_started', time());

    if ($category_id > 0 && taxonomy_exists('posts_orion_cat') && term_exists($category_id, 'posts_orion_cat')) {
      wp_set_post_terms($draft_id, [$category_id], 'posts_orion_cat', false);
    }

    // 1) TÍTULO
    $titlePrompt = PluginsAlpha_Prompts::build_title_prompt(
      $keyword,
      3,
      5,
      $locale
    );

    $titles = PluginsAlpha_OpenAI::titles($titlePrompt);
    if (is_wp_error($titles)) {
      update_post_meta($draft_id, '_pga_job_status', 'error');
      return new WP_Error(
        $titles->get_error_code() ?: 'pga_ai_titles',
        $titles->get_error_message(),
        ['status' => 504, 'post_id' => $draft_id]
      );
    }

    $chosenTitle = self::pick_best_title($titles, $keyword);
    if (!$chosenTitle) {
      $chosenTitle = ucfirst($keyword);
    }

    // Salva tudo como base pra próximas chamadas
    update_post_meta($draft_id, '_pga_outline_length',   $length);
    update_post_meta($draft_id, '_pga_outline_locale',   $locale);
    update_post_meta($draft_id, '_pga_outline_keyword',  $keyword);
    update_post_meta($draft_id, '_pga_outline_template', $template);
    update_post_meta($draft_id, '_pga_outline_url',      $url);
    update_post_meta($draft_id, '_pga_chosen_title',     $chosenTitle);
    update_post_meta($draft_id, '_pga_publish_ts',       $publish_ts);

    update_post_meta($draft_id, '_pga_job_status', 'outline_done');

    // lista completa de keywords, se veio como array
    $allKeywords = [];
    if (is_array($kwSrc)) {
      $allKeywords = array_values(array_filter(array_map('trim', $kwSrc)));
    }

    // escolher QUAL prompt de outline usar
    if ($template === 'modelar') {
      $outlinePrompt = PluginsAlpha_Prompts::build_outline_prompt_modelar(
        $keyword,
        $chosenTitle,
        $length,
        $locale,
        $url,
        $allKeywords
      );
    } else {
      $outlinePrompt = PluginsAlpha_Prompts::build_outline_prompt(
        $keyword,
        $chosenTitle,
        $length,
        $locale
      );
    }

    $outline = PluginsAlpha_OpenAI::outline($outlinePrompt);

    if (is_wp_error($outline)) {
      update_post_meta($draft_id, '_pga_job_status', 'error');
      return $outline;
    }

    // Se o retorno vier como { "sections": [...] }, pega só o array interno
    $sections = $outline['sections'] ?? $outline;
    if (!is_array($sections)) {
      $sections = [];
    }

    // NORMALIZA as seções pra garantir que TODA seção tenha "id"
    $normalized = [];
    $h2Index    = 1;

    foreach ($sections as $sec) {
      // Se vier string, transforma em array básico
      if (!is_array($sec)) {
        $sec = [
          'heading' => (string)$sec,
          'level'   => 'h2',
        ];
      }

      // level padrão
      if (empty($sec['level'])) {
        $sec['level'] = 'h2';
      }

      // id da H2
      if (empty($sec['id'])) {
        $sec['id'] = (string)$h2Index;
      }

      // children (H3) – também ganham id se não tiver
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

    // Salva o outline normalizado
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
    $path = parse_url($url, PHP_URL_PATH);
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
      $sectionsCount
    );

    // aumenta timeout só pra essa chamada
    $tmpTimeout = function ($t) {
      return max((int)$t, 120);
    };
    add_filter('http_request_timeout', $tmpTimeout, 9999, 1);

    $resp = PluginsAlpha_OpenAI::complete($sectionPrompt);

    remove_filter('http_request_timeout', $tmpTimeout, 9999);

    if (is_wp_error($resp)) {
      return $resp;
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


  public static function finalize_from_sections(int $post_id)
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

    // --- 2) Publish_ts corrigido / reaproveitado ---
    $publish_ts = (int) get_post_meta($post_id, '_pga_publish_ts', true);

    if (!$publish_ts) {
      $publish_ts = self::compute_publish_time([
        'keywords'                   => [$keyword],
        'schedule_idx'               => 0,
        'schedule_total'             => 1,
        'schedule_per_day'           => 1,
        'schedule_first_delay_hours' => 24,
      ]);
    }

    if ($publish_ts < (time() + 60)) {
      $publish_ts = time() + 60;
    }

    update_post_meta($post_id, '_pga_publish_ts', $publish_ts);

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
    $content_html = preg_replace('#</?h1[^>]*>#i', '', $content_html);

    if ($content_html === '') {
      return new WP_Error('pga_final_empty', 'Nenhum conteúdo de seção encontrado para juntar.');
    }

    // --- 4) Meta dados ---
    $meta_title = get_post_meta($post_id, '_pga_meta_title',       true) ?: $title;
    $meta_desc  = get_post_meta($post_id, '_pga_meta_description', true) ?: '';
    $image_alt  = get_post_meta($post_id, '_pga_image_alt',        true) ?: '';

    // --- 5) Agenda / criação final do post ---
    $res = self::do_schedule_post($post_id, [
      'keyword'        => $keyword,
      'title'          => $title,
      'content'        => $content_html,
      'locale'         => $locale,
      'post_id'        => $post_id,
      'template'       => $template,
      'publish_time'   => $publish_ts,
      'post_type'      => $post_type,
      'meta_title'     => $meta_title,
      'meta_desc'      => $meta_desc,
      'image_alt'      => $image_alt,
      'generate_image' => true,
      'edit'           => get_edit_post_link($post_id, ''),
    ]);

    if (is_wp_error($res)) {
      return $res;
    }

    // ❗ AQUI: Generator só devolve os dados. Nada de kw_get_* aqui.
    return [
      'ok'        => true,
      'post_id'   => $post_id,
      'edit'      => get_edit_post_link($post_id, ''),
      'view_link' => get_permalink($post_id),
      'keyword'   => $keyword, // <- devolve pro REST poder mexer nas listas
    ];
  }


  protected static function generate_content_from_outline(
    string $keyword,
    string $articleTitle,
    array $sections,
    string $length,
    string $locale,

  ) {
    if (empty($sections)) {
      return new WP_Error('pga_outline_empty', 'Esboço vazio.');
    }

    $htmlParts  = [];
    $meta_title = '';
    $meta_desc  = '';
    $image_alt  = '';

    $tmpTimeout = function ($t) {
      return max((int)$t, 120);
    };
    add_filter('http_request_timeout', $tmpTimeout, 9999, 1);
    $sectionsCount = count($sections);

    foreach ($sections as $section) {
      $sectionPrompt = PluginsAlpha_Prompts::build_section_prompt(
        $keyword,
        $articleTitle,
        $section,
        $length,
        $locale,
        $sectionsCount
      );

      $resp = PluginsAlpha_OpenAI::complete($sectionPrompt);

      if (is_wp_error($resp)) {
        remove_filter('http_request_timeout', $tmpTimeout, 9999);
        return $resp;
      }

      $html = trim((string)($resp['content'] ?? ''));
      if ($html !== '') {
        $htmlParts[] = $html;
      }

      if (!$meta_title && !empty($resp['meta_title'])) {
        $meta_title = (string)$resp['meta_title'];
      }
      if (!$meta_desc && !empty($resp['meta_description'])) {
        $meta_desc = (string)$resp['meta_description'];
      }
      if (!$image_alt && !empty($resp['image_alt'])) {
        $image_alt = (string)$resp['image_alt'];
      }
    }

    remove_filter('http_request_timeout', $tmpTimeout, 9999);

    $content_html = trim(implode("\n\n", $htmlParts));
    if ($content_html === '') {
      return new WP_Error('pga_outline_content_empty', 'Nenhum conteúdo gerado a partir do esboço.');
    }

    if ($meta_desc === '') {
      $meta_desc = self::generate_meta_description_ai(
        $keyword,
        $articleTitle,
        $locale,
        $content_html
      );
    }

    return [
      'content'           => $content_html,
      'meta_title'        => $meta_title,
      'meta_description'  => $meta_desc,
      'image_alt'         => $image_alt,
    ];
  }

  private static function generate_openai_thumbnail(
    string $prompt,
    int $post_id,
    string $alt,
    array $imgSettings = []
  ) {
    if ($prompt === '' || $post_id <= 0) {
      return 0;
    }

    $opts = class_exists('PluginsAlpha_Settings') ? PluginsAlpha_Settings::get() : [];
    $api  = $opts['apis']['openai'] ?? [];
    $key  = trim((string) ($api['key'] ?? ''));

    if ($key === '') {
      return new \WP_Error('pga_openai_no_key', 'Chave da OpenAI não configurada.');
    }

    $model   = $imgSettings['model']   ?? 'dall-e-3';
    $size    = $imgSettings['size']    ?? '1200x670';
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

    if ($code !== 200 || !$raw) {
      return new \WP_Error(
        'pga_openai_http',
        'Erro ao gerar imagem na OpenAI (HTTP ' . $code . ').'
      );
    }

    $json = json_decode($raw, true);
    if (empty($json['data'][0]['url'])) {
      return new \WP_Error(
        'pga_openai_bad_response',
        'Resposta inesperada da API de imagens.'
      );
    }

    $img_url = $json['data'][0]['url'];

    // baixa a imagem gerada
    $img_res = wp_remote_get($img_url, ['timeout' => 60]);
    if (is_wp_error($img_res)) {
      return $img_res;
    }

    $img_body = wp_remote_retrieve_body($img_res);
    if (!$img_body) {
      return new \WP_Error(
        'pga_openai_empty_image',
        'Imagem vazia retornada pela OpenAI.'
      );
    }

    // usa o mesmo helper que você já tem pra salvar binário como attachment
    return self::create_attachment_from_binary(
      $img_body,
      $post_id,
      $alt,
      'openai'
    );
  }
  private static function create_attachment_from_binary(
    string $binary,
    int $post_id,
    string $alt,
    string $prefix = 'img'
  ) {
    if ($binary === '' || $post_id <= 0) {
      return 0;
    }

    $mime = 'image/jpeg';
    if (function_exists('getimagesizefromstring')) {
      $info = @getimagesizefromstring($binary);
      if (!empty($info['mime'])) {
        $mime = $info['mime'];
      }
    }

    $ext = 'jpg';
    if ($mime === 'image/png') {
      $ext = 'png';
    } elseif ($mime === 'image/webp') {
      $ext = 'webp';
    }

    $filename = $prefix . '-' . $post_id . '-' . time() . '.' . $ext;

    $upload = wp_upload_bits($filename, null, $binary);
    if (!empty($upload['error'])) {
      return new \WP_Error('pga_upload_failed', $upload['error']);
    }

    $filetype   = wp_check_filetype(basename($upload['file']), null);
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
      update_post_meta($attach_id, '_wp_attachment_image_alt', wp_strip_all_tags($alt));
    }

    return (int) $attach_id;
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

  public static function plan_and_schedule(array $payload)
  {
    // **não usado no novo fluxo** — mantido apenas por compatibilidade
    return new WP_Error('deprecated', 'Use /plan (que retorna jobs) + /generate para cada job.');
  }

  private static function generate_pollinations_thumbnail(string $prompt, int $post_id, string $alt = '')
  {
    if ($prompt === '' || $post_id <= 0) {
      return 0;
    }

    $base_url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt);

    // 1200x675 é um bom padrão para thumbnail / OpenGraph
    $url = add_query_arg([
      'width'  => 1280,
      'height' => 720,
      'model'  => 'flux',
      // se um dia você tiver conta, dá pra ligar: 'nologo' => 'true',
    ], $base_url);
    $res = wp_remote_get($url, [
      'timeout'   => 60,
      'headers'   => [
        'Accept' => 'image/avif,image/webp,image/jpeg,image/png,*/*',
      ],
    ]);

    if (is_wp_error($res)) {
      return $res;
    }

    $code = wp_remote_retrieve_response_code($res);
    if ($code !== 200) {
      return new \WP_Error('pga_pollinations_http', 'Falha ao gerar imagem (HTTP ' . $code . ').');
    }

    $body = wp_remote_retrieve_body($res);
    if (! $body) {
      return new \WP_Error('pga_pollinations_empty', 'Resposta de imagem vazia.');
    }

    // tenta deduzir mime/ extensão
    $mime = 'image/jpeg';
    if (function_exists('getimagesizefromstring')) {
      $info = @getimagesizefromstring($body);
      if (! empty($info['mime'])) {
        $mime = $info['mime'];
      }
    }

    $ext = 'jpg';
    if ($mime === 'image/png') {
      $ext = 'png';
    } elseif ($mime === 'image/webp') {
      $ext = 'webp';
    }

    $filename = 'pollinations-' . $post_id . '-' . time() . '.' . $ext;

    $upload = wp_upload_bits($filename, null, $body);
    $filetype   = wp_check_filetype(basename($upload['file']), null);
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
      update_post_meta($attach_id, '_wp_attachment_image_alt', wp_strip_all_tags($alt));
    }

    return (int) $attach_id;
  }

  private static function compute_publish_time(array $args): int
  {
    $now = time();

    $idx   = isset($args['schedule_idx']) ? (int)$args['schedule_idx'] : -1;
    $total = max(1, (int)($args['schedule_total'] ?? 1));
    $per   = max(1, (int)($args['schedule_per_day'] ?? 1));
    $first = max(2, (int)($args['schedule_first_delay_hours'] ?? 2));

    // uso manual: sem plano → só delay simples
    if ($idx < 0) {
      return $now + $first * HOUR_IN_SECONDS;
    }

    // === Lógica de distribuição (igual ao que você tinha no plan) ===
    $dayIndex  = (int)floor($idx / $per);
    $slotIndex = $idx % $per;

    // meia-noite hoje
    $today_midnight = strtotime('today', $now);

    $base = [9 * 3600, 14 * 3600, 19 * 3600];
    $baseIdx = min($slotIndex, count($base) - 1);
    $offset  = wp_rand(-40 * MINUTE_IN_SECONDS, 40 * MINUTE_IN_SECONDS);

    $t = $today_midnight + ($dayIndex * DAY_IN_SECONDS) + $base[$baseIdx] + $offset;

    // primeiro post respeita delay
    if ($idx === 0) {
      $min = $now + $first * HOUR_IN_SECONDS;
      if ($t < $min) {
        $t = $min + wp_rand(300, 2400);
      }
    }

    // anti-madrugada
    $offset_tz = get_option('gmt_offset') * HOUR_IN_SECONDS;
    $local_ts  = $t + $offset_tz;
    $hour      = (int)gmdate('G', $local_ts);

    if ($hour >= 0 && $hour < 6) {
      $diff = (6 - $hour) * HOUR_IN_SECONDS;
      $t    = $t + $diff + wp_rand(300, 2400);
    }

    return $t;
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

    // 1) PRIORIDADE: publish_time vindo nos args (planner / outline / generate)
    $publish_ts = isset($args['publish_time']) ? (int)$args['publish_time'] : 0;

    // 2) Se não veio nos args, tenta meta salvo (_pga_publish_ts)
    if (!$publish_ts) {
      $publish_ts = (int) get_post_meta($post_id, '_pga_publish_ts', true);
    }

    // 3) Se ainda não tiver nada, fallback genérico
    if (!$publish_ts) {
      $publish_ts = self::compute_publish_time($args); // aqui usa schedule_* se tiver, senão cai no default
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
        ['status' => 500, 'post_id' => $post_id]
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

    // Thumb (se pedido)
    if ($generate_img && $keyword !== '' && $title !== '') {
      // 1) META-PROMPT (instruções) vindo do Prompts.php
      $meta_img_prompt = PluginsAlpha_Prompts::build_image_prompt(
        $keyword,
        $title,
        $locale,
        $template
      );

      $img_prompt = '';

      if ($meta_img_prompt && class_exists('PluginsAlpha_OpenAI')) {
        // 2) Converte para o PROMPT FINAL de imagem (string única)
        $resolved = PluginsAlpha_OpenAI::image_prompt($meta_img_prompt);
        if (!is_wp_error($resolved)) {
          $img_prompt = trim((string)$resolved);
        }
      }

      // fallback: se IA falhar, usa o meta-prompt mesmo (comportamento antigo)
      if ($img_prompt === '') {
        $img_prompt = $meta_img_prompt;
      }

      if ($img_prompt) {
        $opts     = class_exists('PluginsAlpha_Settings') ? PluginsAlpha_Settings::get() : [];
        $imgOpts  = $opts['apis']['images'] ?? [];
        $provider = $imgOpts['provider'] ?? 'pollinations';

        $alt = $image_alt !== '' ? $image_alt : $title;

        if ($provider === 'openai') {
          $thumb_id = self::generate_openai_thumbnail($img_prompt, $post_id, $alt, $imgOpts);
        } elseif ($provider === 'pollinations') {
          $thumb_id = self::generate_pollinations_thumbnail($img_prompt, $post_id, $alt);
        } else {
          $thumb_id = 0;
        }

        if (!is_wp_error($thumb_id) && $thumb_id) {
          set_post_thumbnail($post_id, $thumb_id);
          update_post_meta($post_id, '_pga_image_prompt',   $img_prompt);
          update_post_meta($post_id, '_pga_image_provider', $provider);
        }
      }
    }

    // status do job
    update_post_meta($post_id, '_pga_job_status', 'done');
    delete_post_meta($post_id, '_pga_job_started');

    return [
      'edit'      => get_edit_post_link($post_id, ''),
      'post_id'   => $post_id,
      'view_link' => get_permalink($post_id),
    ];
  }

  /**
   * Transforma um META-PROMPT de imagem (tipo "Você é um gerador de prompts...")
   * em um único prompt final, pronto pra ser enviado pro provider (Pollinations/OpenAI).
   */
  protected static function resolve_image_prompt(string $raw, string $locale = 'pt_BR'): string
  {
    $raw = trim($raw);
    if ($raw === '') {
      return '';
    }

    // Heurística: se o template ainda tem cara de "sistema" (meta-prompt),
    // pedimos pra OpenAI devolver SÓ o prompt final.
    $looks_like_meta =
      stripos($raw, 'você é um gerador de prompts') !== false ||
      stripos($raw, 'you are a prompt generator') !== false;

    if (!$looks_like_meta) {
      // Já parece um prompt direto → devolve como está.
      return $raw;
    }

    // Monta instrução pra IA devolver apenas UM prompt de imagem.
    $prompt_for_ai = $raw . "\n\n" .
      "Agora, com base em TODAS as instruções acima, responda APENAS com um único prompt " .
      "de imagem, em {$locale}, em uma única linha, sem aspas, sem explicações, " .
      "sem quebra de linha extra e sem repetir as regras.";

    if (!class_exists('PluginsAlpha_OpenAI')) {
      // fallback: usa o meta-prompt direto, como já estava.
      return $raw;
    }

    $resp = PluginsAlpha_OpenAI::complete($prompt_for_ai);

    if (is_wp_error($resp)) {
      // se a IA falhar, volta pro comportamento antigo
      return $raw;
    }

    $content = trim((string)($resp['content'] ?? ''));

    // se vier vazio, mantém o original pra não quebrar
    return $content !== '' ? $content : $raw;
  }
}
