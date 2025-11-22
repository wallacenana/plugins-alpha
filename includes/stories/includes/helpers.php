<?php
if (!defined('ABSPATH')) exit;

/** =========================
 *  Opções e utilitários
 *  ========================= */
function alpha_storys_options()
{
  $o = get_option('alpha_storys_options', []);
  return is_array($o) ? $o : [];
}

function alpha_opt($key, $default = null)
{
  $opts = alpha_storys_options();
  return array_key_exists($key, $opts) ? $opts[$key] : $default;
}

function alpha_ai_get_api_key(): string
{

  // 1) PRIORIDADE: settings do Alpha GPT Posts (agp_settings[apis][openai][key])
  if (class_exists('PluginsAlpha_Settings')) {
    $opt = PluginsAlpha_Settings::get();
    if (!empty($opt['apis']['openai']['key'])) {
      $k = trim((string)$opt['apis']['openai']['key']);
      if ($k !== '') return $k;
    }
  }

  // 3) Variável de ambiente
  $env = getenv('OPENAI_API_KEY');
  if (is_string($env) && trim($env) !== '') {
    return trim($env);
  }

  // 4) Options específicas de outro plugin (Alpha Storys, por ex.)
  if (function_exists('alpha_storys_options')) {
    $o = alpha_storys_options();
    if (!empty($o['ai_api_key'])) {
      return trim((string)$o['ai_api_key']);
    }
  }

  // 5) Options legadas / genéricas
  foreach (['alpha_ai_openai_api_key', 'alpha_ai_api_key', 'openai_api_key'] as $name) {
    $v = get_option($name);
    if (is_string($v) && trim($v) !== '') {
      return trim($v);
    }
  }

  // nada encontrado
  return '';
}


function alpha_ai_get_model(): string
{
  $o = alpha_storys_options();
  return !empty($o['ai_model']) ? (string)$o['ai_model'] : 'gpt-4o-mini';
}

function alpha_ai_get_temperature(): float
{
  $o = alpha_storys_options();
  $t = isset($o['ai_temperature']) ? (float)$o['ai_temperature'] : 0.4;
  return max(0, min(1, $t));
}

function alpha_ai_get_default_brief(): string
{
  $o = alpha_storys_options();
  return isset($o['ai_brief_default']) ? (string)$o['ai_brief_default'] : '';
}

function alpha_get_ga4_id(): string
{
  $mode = alpha_opt('ga_mode', 'auto'); // auto|manual|off
  if ($mode === 'off') return '';
  if ($mode === 'manual') {
    $id = trim((string) alpha_opt('ga_manual_id', ''));
    return preg_match('/^G-[A-Z0-9\-]{4,}$/i', $id) ? $id : '';
  }
  $candidates = [
    'googlesitekit_analytics-4_settings',
    'googlesitekit_analytics-4',
    'googlesitekit_analytics_settings',
    'googlesitekit_gtag_settings',
  ];
  foreach ($candidates as $opt_name) {
    $opt = get_option($opt_name);
    if (is_array($opt)) {
      foreach (['measurementID', 'measurementId', 'measurement_id', 'ga4MeasurementId'] as $k) {
        if (!empty($opt[$k]) && preg_match('/^G-[A-Z0-9\-]{4,}$/i', $opt[$k])) return $opt[$k];
      }
      $flat = json_decode(json_encode($opt), true);
      $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($flat));
      foreach ($it as $v) {
        if (is_string($v) && preg_match('/^G-[A-Z0-9\-]{4,}$/i', $v)) return $v;
      }
    }
  }
  $id = trim((string) alpha_opt('ga_manual_id', ''));
  return preg_match('/^G-[A-Z0-9\-]{4,}$/i', $id) ? $id : '';
}

function alpha_get_publisher_logo_url($size = 'full')
{
  $id = (int) alpha_opt('publisher_logo_id', 0);
  return $id ? wp_get_attachment_image_url($id, $size) : '';
}

/** =========================
 *  storys: criar ou localizar
 *  ========================= */
function alpha_get_or_create_storys_for_post($post_id)
{
  $post = get_post($post_id);
  if (!$post) return 0;

  $storys_id = (int) get_post_meta($post_id, '_alpha_storys_id', true);
  if ($storys_id && get_post($storys_id)) return $storys_id;
  // phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
  $q = new WP_Query([
    'post_type'      => 'alpha_storys',
    'posts_per_page' => 1,
    'post_status'    => ['publish', 'draft', 'pending'],
    'meta_query'     => [[
      'key'   => '_alpha_storys_source_post',
      'value' => $post_id,
    ]]
  ]);
  // phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

  if ($q->have_posts()) {
    $storys_id = (int)$q->posts[0]->ID;
    wp_reset_postdata();
    update_post_meta($post_id, '_alpha_storys_id', $storys_id);
    return $storys_id;
  }

  $args = [
    'post_type'   => 'alpha_storys',
    'post_title'  => $post->post_title,
    'post_status' => 'draft',
    'post_author' => (int)$post->post_author,
  ];
  $storys_id = wp_insert_post($args);

  if ($storys_id) {
    update_post_meta($storys_id, '_alpha_storys_source_post', $post_id);
    update_post_meta($post_id,  '_alpha_storys_id',         $storys_id);
    $thumb = get_post_thumbnail_id($post_id);
    if ($thumb) set_post_thumbnail($storys_id, $thumb);
    $publisher = alpha_opt('publisher_name', get_bloginfo('name'));
    update_post_meta($storys_id, '_alpha_storys_publisher', sanitize_text_field($publisher));
    $logo_id = (int) alpha_opt('publisher_logo_id', 0);
    if ($logo_id) update_post_meta($storys_id, '_alpha_storys_logo_id', $logo_id);
  }

  return (int)$storys_id;
}

/** =========================
 *  Mídia: sideload de imagens
 *  ========================= */
function alpha_sideload_image_to_post($image_url, $attach_to_post_id = 0)
{
  $image_url = trim((string)$image_url);
  if ($image_url === '' || !filter_var($image_url, FILTER_VALIDATE_URL)) return 0;

  if (!function_exists('media_sideload_image')) {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
  }

  $att_id = 0;
  // media_sideload_image pode retornar HTML ou ID se pedirmos 'id'
  $result = media_sideload_image($image_url, $attach_to_post_id, null, 'id');

  if (is_wp_error($result)) return 0;
  $att_id = (int) $result;
  return $att_id > 0 ? $att_id : 0;
}

/** =========================
 *  Render: páginas -> blocos
 *  ========================= */
function alpha_render_storys_pages_to_blocks(array $pages)
{
  $blocks = '';

  foreach ($pages as $idx => $p) {
    $heading  = isset($p['heading']) ? wp_strip_all_tags($p['heading']) : '';
    $body     = isset($p['body'])    ? wp_kses_post($p['body']) : '';
    $cta_text = isset($p['cta_text']) ? sanitize_text_field($p['cta_text']) : '';
    $cta_url = isset($p['cta_url']) ? sanitize_text_field($p['cta_url']) : '';
    $prompt = isset($p['prompt']) ? sanitize_text_field($p['prompt']) : '';

    $blocks .= "<!-- wp:group {\"className\":\"alpha-storys-page\"} -->\n";
    $blocks .= "<div class=\"wp-block-group alpha-storys-page\">\n";

    if ($heading !== '') {
      $blocks .= "<!-- wp:heading {\"level\":2} -->\n";
      $blocks .= "<h2>" . esc_html($heading) . "</h2>\n";
      $blocks .= "<!-- /wp:heading -->\n";
    }

    if ($body !== '') {
      $blocks .= "<!-- wp:paragraph -->\n";
      $blocks .= "<p>" . wp_kses($body, [
        'a' => ['href' => [], 'rel' => [], 'target' => []],
        'strong' => [],
        'em' => [],
        'br' => []
      ]) . "</p>\n";
      $blocks .= "<!-- /wp:paragraph -->\n";
    }

    if ($cta_text !== '' || $cta_url !== '') {
      $blocks .= "<!-- wp:paragraph -->\n";
      $blocks .= "<a href=\"" . esc_attr($cta_url) . "\" target=\"_blank\">" . esc_html($cta_text) . "</a>\n";
      $blocks .= "<!-- /wp:paragraph -->\n";
    }

    if ($prompt !== '') {
      $blocks .= "<!-- wp:paragraph -->\n";
      $blocks .= "<p>" . wp_kses($prompt, [
        'a' => ['href' => [], 'rel' => [], 'target' => []],
        'strong' => [],
        'em' => [],
        'br' => []
      ]) . "</p>\n";
      $blocks .= "<!-- /wp:paragraph -->\n";
    }

    $blocks .= "</div>\n";
    $blocks .= "<!-- /wp:group -->\n";
  }

  return $blocks;
}

/** =========================
 *  IA: gerar e salvar conteúdo
 *  ========================= */
function alpha_ai_generate_for_post($post_id)
{
  $o   = alpha_storys_options();
  $key = alpha_ai_get_api_key();

  if (! $key) {
    return new WP_Error('alpha_ai_key', 'Configure sua OpenAI API Key nas Configurações.');
  }

  $post = get_post($post_id);
  if (! $post) {
    return new WP_Error('alpha_ai_post', 'Post inválido.');
  }

  $raw_html = apply_filters('the_content', $post->post_content);
  $title    = get_the_title($post);
  $brief    = alpha_ai_get_default_brief();

  // ========= PROMPT =========
  // 1) Prompt da tela de settings (se houver), senão o padrão do plugin
  $system_pt = '';

  if (isset($o['ai_prompt_template']) && is_string($o['ai_prompt_template'])) {
    $system_pt = trim((string) $o['ai_prompt_template']);
  }

  if ('' === $system_pt) {
    $system_pt = function_exists('alpha_storys_default_prompt_template')
      ? (string) alpha_storys_default_prompt_template()
      : 'Você transforma posts em Web stories AMP...';
  }

  // 2) Bloco fixo com o FORMATO JSON obrigatório
  $format_block = function_exists('alpha_storys_json_format_block')
    ? alpha_storys_json_format_block()
    : "Responda APENAS em JSON no formato {\"pages\":[{\"heading\":\"\",\"body\":\"\",\"cta_text\":\"\",\"cta_url\":\"\",\"prompt\":\"\"}]}\n";

  $json_header  = "IMPORTANTE: a resposta deve ser APENAS um JSON válido. ";
  $json_header .= "A palavra 'json' aparece aqui para atender o requisito da OpenAI.\n\n";


  // 3) Monta o texto final que vai para o modelo
  $input_text  = $json_header;
  $input_text .= $system_pt . "\n\n";
  $input_text .= "TÍTULO DO POST:\n" . $title . "\n\n";
  $input_text .= "HTML DO POST (sem tags):\n" . wp_strip_all_tags($raw_html) . "\n\n";
  $input_text .= "BRIEF PADRÃO:\n" . $brief . "\n\n";
  $input_text .= $format_block;


  // ========= CHAMADA PARA OPENAI =========
  $payload = [
    'model' => alpha_ai_get_model(),
    'input' => [
      [
        'role'    => 'user',
        'content' => [
          [
            'type' => 'input_text',
            'text' => $input_text,
          ],
        ],
      ],
    ],
    'text' => [
      'format' => [
        'type' => 'json_object',
      ],
    ],
    'temperature'       => alpha_ai_get_temperature(),
    'max_output_tokens' => 6000,
  ];

  $res = wp_remote_post(
    'https://api.openai.com/v1/responses',
    [
      'timeout' => 60,
      'headers' => [
        'Authorization' => 'Bearer ' . $key,
        'Content-Type'  => 'application/json',
      ],
      'body'    => wp_json_encode($payload),
    ]
  );

  if (is_wp_error($res)) {
    return $res;
  }

  error_log(print_r($res, true));

  $code = wp_remote_retrieve_response_code($res);
  $body = wp_remote_retrieve_body($res);

  if (200 !== $code) {
    return new WP_Error(
      'alpha_ai_http',
      'OpenAI retornou ' . $code . ': ' . substr((string) $body, 0, 300)
    );
  }

  $obj = json_decode((string) $body, true);
  if (!is_array($obj)) {
    return new WP_Error('alpha_ai_json', 'Resposta da OpenAI não é um JSON válido no topo.');
  }

  // tenta ler status tanto no topo quanto no output[0]
  $status = $obj['status'] ?? ($obj['output'][0]['status'] ?? '');

  if ($status && $status !== 'completed') {
    // log pra debug
    error_log('OpenAI status: ' . $status);
    return new WP_Error(
      'alpha_ai_incomplete',
      'OpenAI não conseguiu concluir o JSON (status: ' . $status . ').'
    );
  }

  // Extrai texto do output (Responses API)
  $json_text = '';
  if (! empty($obj['output'][0]['content'])) {
    foreach ($obj['output'][0]['content'] as $chunk) {
      if (! empty($chunk['text'])) {
        $json_text .= $chunk['text'];
      }
      if (! empty($chunk['raw'])) {
        $json_text .= $chunk['raw'];
      }
    }
  } elseif (! empty($obj['output_text'])) {
    $json_text = $obj['output_text'];
  }

  $data = json_decode($json_text, true);

  // Fallback: tenta pegar só o primeiro {...} se vier embrulhado em texto
  if (! $data && preg_match('/\{.*\}/s', (string) $json_text, $m)) {
    $data = json_decode($m[0], true);
  }

  error_log(print_r($data, true));

  if (! $data || empty($data['pages']) || ! is_array($data['pages'])) {
    return new WP_Error('alpha_ai_parse', 'Não consegui interpretar o JSON de páginas.');
  }

  // Normaliza páginas
  $pages = [];
  foreach ($data['pages'] as $p) {
    $pages[] = [
      'heading'  => isset($p['heading']) ? wp_strip_all_tags($p['heading']) : '',
      'body'     => isset($p['body']) ? wp_strip_all_tags($p['body']) : '',
      'cta_text' => isset($p['cta_text']) ? sanitize_text_field($p['cta_text']) : '',
      'cta_url'  => isset($p['cta_url']) ? sanitize_text_field($p['cta_url']) : '',
      'prompt'   => isset($p['prompt']) ? sanitize_text_field($p['prompt']) : '',
    ];
  }

  // Decide destino: se já for um alpha_storys, usa o próprio; senão, cria/pega o irmão
  $target_id = ('alpha_storys' === get_post_type($post_id))
    ? (int) $post_id
    : alpha_get_or_create_storys_for_post((int) $post_id);

  if (! $target_id) {
    return new WP_Error('alpha_storys_target', 'Não foi possível criar ou localizar o Web Story.');
  }

  // Renderiza blocos e salva no editor
  $blocks = alpha_render_storys_pages_to_blocks($pages, $target_id);

  wp_update_post(
    [
      'ID'           => $target_id,
      'post_content' => $blocks,
      'post_title'   => get_post_field('post_title', $target_id) ?: $title,
    ]
  );

  update_post_meta($target_id, '_alpha_storys_pages', $pages);

  return [
    'ok'        => true,
    'count'     => count($pages),
    'target_id' => (int) $target_id,
  ];
}
