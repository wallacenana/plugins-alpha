<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('PluginsAlpha_OpenAI')) require_once __DIR__ . '/OpenAI.php';
if (!class_exists('PluginsAlpha_Prompt')) require_once __DIR__ . '/Prompt.php';

class PluginsAlpha_Pages_Generator
{
  public static function render(): void
  {
    $opt = PluginsAlpha_Settings::get(); ?>
    <div class="wrap pga-wrap pga-layout">
      <div class="pga-main">
        <h1>Gerador — Alpha GPT Posts</h1>
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
            <div class="pga-field" style="width: 100%; flex: 1 1 100%">
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
          </div>

          <div class="pga-row radio" style="display:none">
            <label><input type="radio" name="pga_mode" value="multi" checked> 1 post por palavra-chave</label>
            <label><input type="radio" name="pga_mode" value="single"> 1 artigo combinando todas</label>
          </div>

          <?php
          $chk = PluginsAlpha_License::check('post-gpt');

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

  /**
   * $args:
   *  - keywords[]  (usa a 1ª como foco)
   *  - template    (article|review|news|howto|list)
   *  - locale      (pt_BR|en_US...)
   *  - publish_time  (timestamp futuro)
   *  - category_id   (int)
   */
  public static function generate_and_insert(array $args, array $settings = [])
  {
    $kw          = trim((string)($args['keywords'][0] ?? ''));
    $template    = $args['template'] ?? 'discover_article';
    $locale      = $args['locale'] ?? 'pt_BR';
    $url         = $args['source_url'] ?? '';
    $publish_ts  = intval($args['publish_time'] ?? (time() + 2 * HOUR_IN_SECONDS));
    $category_id = intval($args['category_id'] ?? 0);
    $post_type   = !empty($args['post_type']) ? sanitize_key($args['post_type']) : 'posts_gpt'; // <<< CPT

    if ($kw === '') return new WP_Error('pga_no_kw', 'Keyword vazia.');
    if ($publish_ts < (time() + 60)) $publish_ts = time() + 60;

    $slug = sanitize_title($kw);

    // 0) Cria rascunho ANTES (para não perder em caso de timeout)
    $draft_id = wp_insert_post([
      'post_type'    => $post_type,
      'post_status'  => 'draft',
      'post_title'   => '(Gerando) ' . $kw,
      'post_name'    => $slug,
      'post_content' => '',
      'post_author'  => get_current_user_id(),
    ], true);

    if (is_wp_error($draft_id)) return $draft_id;

    update_post_meta($draft_id, '_pga_job_status', 'generating');
    update_post_meta($draft_id, '_pga_job_started', time());
    if ($category_id > 0 && taxonomy_exists('posts_gpt_cat') && term_exists($category_id, 'posts_gpt_cat')) {
      wp_set_post_terms($draft_id, [$category_id], 'posts_gpt_cat', false);
    }


    // 1) TÍTULOS
    $titlePrompt = PluginsAlpha_PromptTitle::build($template, $kw, [
      'locale' => $locale,
      'min'    => 3,
      'max'    => 6,
      'style'  => 'discover_article',
    ]);
    $titles = PluginsAlpha_OpenAI::titles($titlePrompt);
    if (is_wp_error($titles)) {
      update_post_meta($draft_id, '_pga_job_status', 'error');
      return new WP_Error($titles->get_error_code() ?: 'pga_ai_titles', $titles->get_error_message(), ['status' => 504, 'post_id' => $draft_id]);
    }

    $chosenTitle = self::pick_best_title($titles, $kw);
    if (!$chosenTitle) $chosenTitle = ucfirst($kw);

    // 2) CONTEÚDO
    $contentPrompt = PluginsAlpha_Prompt::build($template, $kw, $url, [
      'locale'       => $locale,
      'forced_title' => $chosenTitle,
    ]);

    // (opcional) aumentar o timeout das requisições HTTP só durante essa chamada
    $tmpTimeout = function ($t) {
      return max((int)$t, 120);
    };
    add_filter('http_request_timeout', $tmpTimeout, 9999, 1);

    $resp = PluginsAlpha_OpenAI::complete($contentPrompt);

    remove_filter('http_request_timeout', $tmpTimeout, 9999);

    if (is_wp_error($resp)) {
      update_post_meta($draft_id, '_pga_job_status', 'error');
      $code = $resp->get_error_code();
      $msg  = $resp->get_error_message();
      // Se for timeout/cURL 28, normalize p/ 504
      $status = (stripos($msg, 'cURL error 28') !== false) ? 504 : 400;
      return new WP_Error($code ?: 'pga_ai_complete', $msg, ['status' => $status, 'post_id' => $draft_id]);
    }

    $title        = $resp['title'] ?: $chosenTitle;
    $content_html = $resp['content'] ?? '';
    $meta_title   = $resp['meta_title'] ?? '';
    $meta_desc    = $resp['meta_description'] ?? '';
    $image_alt    = $resp['image_alt'] ?? '';

    // remove <h1>
    $content_html = preg_replace('#</?h1[^>]*>#i', '', $content_html);

    if ($title === '' || $content_html === '') {
      update_post_meta($draft_id, '_pga_job_status', 'error');
      return new WP_Error('empty_content', 'O conteúdo, o título e o resumo estão vazios.', ['status' => 400, 'post_id' => $draft_id]);
    }

    // 3) Atualiza o rascunho para agendado no CPT
    $upd = [
      'ID'            => $draft_id,
      'post_title'    => wp_strip_all_tags($title),
      'post_content'  => $content_html,
      'post_status'   => 'future',
      'post_type'     => $post_type,
      'post_date'     => get_date_from_gmt(gmdate('Y-m-d H:i:s', $publish_ts), 'Y-m-d H:i:s'),
      'post_date_gmt' => gmdate('Y-m-d H:i:s', $publish_ts),
    ];
    $post_id = wp_update_post($upd, true);
    if (is_wp_error($post_id)) {
      update_post_meta($draft_id, '_pga_job_status', 'error');
      return new WP_Error($post_id->get_error_code() ?: 'pga_wp_update', $post_id->get_error_message(), ['status' => 500, 'post_id' => $draft_id]);
    }

    // SEO/meta
    if (class_exists('PluginsAlpha_SEO')) {
      PluginsAlpha_SEO::apply_meta($post_id, [
        'title'         => $meta_title ?: $title,
        'description'   => $meta_desc,
        'focus_keyword' => $kw,
      ]);
    }
    if ($meta_title) update_post_meta($post_id, '_pga_meta_title', $meta_title);
    if ($meta_desc)  update_post_meta($post_id, '_pga_meta_description', $meta_desc);
    if ($image_alt)  update_post_meta($post_id, '_pga_image_alt', $image_alt);

    update_post_meta($post_id, '_pga_job_status', 'done');
    delete_post_meta($post_id, '_pga_job_started');

    return [
      'post_id'   => $post_id,
      'view_link' => get_permalink($post_id),
    ];
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

  private static function map_template($tpl)
  {
    $allowed = ['discover_article', 'faq', 'review_roundup', 'review_single', 'article', 'howto', 'list', 'news', 'review'];
    $tpl = strtolower(trim($tpl));
    // compat: "review" vira "review_roundup" (vários produtos) por padrão
    if ($tpl === 'review') return 'review_roundup';
    return in_array($tpl, $allowed, true) ? $tpl : 'article';
  }

  private static function soft_title_with_keyword($title, $kw)
  {
    if (!$kw) return $title;
    if (stripos($title, $kw) !== false) return $title;
    return rtrim($title, " \t\n\r\0\x0B:") . ': ' . $kw;
  }

  // (mantida caso reative imagem)
  private static function save_base64_as_attachment($b64, $post_id, $filename, $alt)
  {
    $data = base64_decode($b64);
    if (!$data) return new \WP_Error('decode_failed', 'Falha ao decodificar imagem.');
    $upload = wp_upload_bits($filename, null, $data);
    if (!empty($upload['error'])) return new \WP_Error('upload_failed', $upload['error']);

    $filetype = wp_check_filetype(basename($upload['file']), null);
    $attachment = [
      'guid'           => $upload['url'],
      'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
      'post_title'     => preg_replace('/\.[^.]+$/', '', basename($upload['file'])),
      'post_content'   => '',
      'post_status'    => 'inherit',
    ];
    $attach_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
    if (is_wp_error($attach_id) || !$attach_id) return $attach_id;

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);
    if ($alt) update_post_meta($attach_id, '_wp_attachment_image_alt', wp_strip_all_tags($alt));
    return $attach_id;
  }
}
