<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_CPT_Posts_Orion
{
  /**
   * Slug interno do módulo no sistema de licença.
   */
  const MODULE_SLUG = 'post-orion';

  /**
   * Option usada na tela de Links Permanentes para base dos Órion Posts.
   * Ex.: 'orion', 'ia-posts' etc.
   */
  const OPTION_BASE = 'pga_posts_base';

  /**
   * Query var interna usada nas regras de rewrite para resolver o slug.
   */
  const QUERY_VAR = 'pga_posts_orion_slug';

  /**
   * Bootstrap
   */
  public static function init(): void
  {
    // já existentes
    add_action('init', [self::class, 'register']);
    add_action('update_option_' . self::OPTION_BASE, [self::class, 'on_change_base'], 10, 2);
    // add_action('init', [self::class, 'add_rewrite_rules'], 20);
    // add_filter('query_vars', [self::class, 'register_query_var']);
    // add_action('parse_request', [self::class, 'parse_request']);
    add_filter('post_type_link', [self::class, 'filter_permalink'], 10, 4);

    if (is_admin()) {
      add_filter('post_row_actions', [self::class, 'filter_row_actions'], 10, 2);
      add_filter('get_edit_post_link', [self::class, 'filter_edit_link'], 10, 3);
      add_action('admin_notices', [self::class, 'admin_license_notices']);
    }

    add_action('transition_post_status', [self::class, 'block_publish_if_no_license'], 10, 3);

    add_action('pre_get_posts', [self::class, 'include_in_term_archives']);

    add_action('add_meta_boxes', function () {
      add_meta_box(
        'pga_regen_thumb',
        'Thumbnail',
        [self::class, 'pga_render_regen_thumb_box'],
        'posts_orion',
        'side',
        'low'
      );
    });
    add_action('wp_ajax_pga_regen_thumb', function () {
      if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'pga_regen_thumb')) {
        wp_send_json_error('Nonce inválido.');
      }

      $post_id = intval($_POST['post_id'] ?? 0);
      if ($post_id <= 0) {
        wp_send_json_error('ID de post inválido.');
      }

      $post = get_post($post_id);
      if (!$post) {
        wp_send_json_error('Post inexistente.');
      }

      // prompt vindo do textarea (opcional)
      $raw_prompt = isset($_POST['prompt']) ? trim((string) wp_unslash($_POST['prompt'])) : '';

      // Se estiver vazio, monta um prompt padrão baseado no conteúdo do post
      if ($raw_prompt === '') {
        if (!class_exists('PluginsAlpha_Prompts')) {
          wp_send_json_error('Classe de prompts ausente.');
        }

        $title   = get_the_title($post_id) ?: '';
        $content = get_post_field('post_content', $post_id) ?: '';

        // limpa HTML e limita um pouco o tamanho para o meta-prompt
        $content = wp_strip_all_tags($content);
        // corta para ~800 caracteres só pro contexto
        if (function_exists('wp_html_excerpt')) {
          $content = wp_html_excerpt($content, 800, '...');
        } else {
          if (mb_strlen($content) > 800) {
            $content = mb_substr($content, 0, 800) . '...';
          }
        }

        // locale padrão (pode ser fixo 'pt_BR' ou algo vindo das settings)
        $locale = get_locale() ?: 'pt_BR';

        $prompt = PluginsAlpha_Prompts::build_post_thumbnail_regen_prompt(
          $title,
          $content,
          $locale
        );
      } else {
        // usuário digitou algo → usa direto
        $prompt = $raw_prompt;
      }
      if (!class_exists('PluginsAlpha_Images')) {
        wp_send_json_error('Classe de imagem ausente.');
      }

      // Gera usando provider configurado (OpenAI, Pollinations, etc.)
      $thumb_id = PluginsAlpha_Images::generate_by_settings($prompt, $post_id);

      if (is_wp_error($thumb_id)) {
        wp_send_json_error($thumb_id->get_error_message());
      }

      $thumb_id = (int) $thumb_id;
      if ($thumb_id <= 0) {
        wp_send_json_error('Falha ao gerar a miniatura (ID inválido).');
      }

      // Define como thumbnail do post
      set_post_thumbnail($post_id, $thumb_id);

      // guarda prompt e provider pra referência
      update_post_meta($post_id, '_pga_image_prompt', $prompt);

      // se quiser salvar o provider real:
      if (class_exists('PluginsAlpha_Settings')) {
        $opts       = PluginsAlpha_Settings::get();
        $imgSettings = $opts['apis']['images'] ?? [];
        $provider   = isset($imgSettings['provider']) ? (string)$imgSettings['provider'] : 'pollinations';
        update_post_meta($post_id, '_pga_image_provider', $provider);
      }

      // HTML atualizado do box de imagem destacada
      if (!function_exists('_wp_post_thumbnail_html')) {
        require_once ABSPATH . 'wp-admin/includes/post.php';
      }

      $thumb_html = _wp_post_thumbnail_html($thumb_id, $post_id);

      wp_send_json_success([
        'thumb_id'   => $thumb_id,
        'thumb_html' => $thumb_html,
        'prompt'     => $prompt,
      ]);
    });
  }

  public static function on_change_base($old_value, $value): void
  {
    // Evita flush desnecessário
    $old = trim((string)$old_value);
    $new = trim((string)$value);

    if ($old === $new) {
      return;
    }

    // Garante que o CPT esteja registrado antes de flushear
    self::register();

    flush_rewrite_rules(false);
  }

  public static function pga_render_regen_thumb_box($post)
  {
    wp_nonce_field('pga_regen_thumb', 'pga_regen_thumb_nonce');

    echo '<p>Use IA para gerar ou substituir a imagem destacada deste Órion Post.</p>';

    echo '<p><label for="pga_regen_thumb_prompt"><strong>Prompt da imagem</strong></label><br />';
    echo '<textarea id="pga_regen_thumb_prompt" rows="3" style="width:100%;" placeholder="Ex.: Ilustração realista de um gato usando coleira com rastreador, fundo claro, estilo fotográfico, 16:9."></textarea></p>';

    echo '<button 
        type="button" 
        class="button button-primary" 
        id="pga_regen_thumb_btn"
        data-post="' . esc_attr($post->ID) . '">
        Gerar nova imagem com IA
    </button>';

    echo '<div id="pga_regen_thumb_status" style="margin-top:8px;font-size:12px;color:#555;"></div>';

?>
    <script>
      jQuery(function($) {
        const $btn = $('#pga_regen_thumb_btn');
        const $status = $('#pga_regen_thumb_status');
        const $prompt = $('#pga_regen_thumb_prompt');

        function showAlert(type, title, text) {
          if (window.Swal) {
            Swal.fire({
              icon: type,
              title: title,
              html: text,
            });
          } else {
            alert(title + "\n\n" + $(text).text());
          }
        }

        $btn.on('click', function() {
          const postId = $(this).data('post');
          const nonce = $('#pga_regen_thumb_nonce').val();
          let prompt = ($prompt.val() || '').trim();

          if (!postId) {
            showAlert('error', 'Erro', 'ID de post inválido.');
            return;
          }


          $status.text('Gerando imagem... isso pode levar alguns segundos.');

          $.ajax({
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
              action: 'pga_regen_thumb',
              post_id: postId,
              prompt: prompt,
              _wpnonce: nonce
            },
            success: function(r) {
              if (!r || !r.success) {
                const msg = (r && r.data) ? r.data : 'Falha desconhecida.';
                $status.text('Erro: ' + msg);
                showAlert('error', 'Erro ao gerar imagem', '<p>' + msg + '</p>');
                return;
              }

              const data = r.data || {};
              $status.text('Thumbnail atualizada com sucesso!');

              // Atualiza o box de imagem destacada, se veio HTML
              if (data.thumb_html) {
                $('#postimagediv .inside').html(data.thumb_html);
              }


              showAlert('success', 'Imagem gerada!',
                '<p>A nova thumbnail foi criada e aplicada ao post.</p>'
              );
            },
            error: function(xhr) {
              $status.text('Erro de comunicação com o servidor.');
              showAlert('error', 'Erro de comunicação', '<p>Falha ao contatar o servidor (AJAX).</p>');
            }
          });
        });
      });
    </script>
<?php
  }

  /**
   * Inclui o CPT posts_orion nas páginas de categoria e tag.
   */
  public static function include_in_term_archives($query): void
  {
    // somente front-end + query principal
    if (is_admin() || ! $query->is_main_query()) {
      return;
    }

    // category ou tag (se quiser só category, remove o is_tag)
    if (! $query->is_category() && ! $query->is_tag()) {
      return;
    }

    $post_types = $query->get('post_type');

    if (empty($post_types)) {
      // padrão do WP é "post", então a gente força os dois
      $post_types = ['post', 'posts_orion'];
    } elseif (is_string($post_types)) {
      $post_types = [$post_types];
    }

    if (! in_array('posts_orion', $post_types, true)) {
      $post_types[] = 'posts_orion';
    }

    $query->set('post_type', $post_types);
  }

  public static function admin_license_notices(): void
  {
    if (!function_exists('get_current_screen')) {
      return;
    }

    $screen = get_current_screen();
    if (!$screen) {
      return;
    }

    // Só nos interessa telas do nosso CPT
    if ($screen->post_type !== 'posts_orion') {
      return;
    }

    if (!class_exists('PluginsAlpha_License')) {
      return;
    }

    $chk = PluginsAlpha_License::check('orion');

    // 1) Aviso geral: licença/módulo não ativo
    if (empty($chk['ok'])) {
      // link para o painel Plugins Alpha (ajusta o slug se for diferente)
      $url = admin_url('admin.php?page=plugins-alpha-dashboard');

      $msg = $chk['message'] ?: __('Licença do módulo Alpha Órion inativa. Ative o módulo para continuar gerando e publicando posts.', 'plugins-alpha');

      echo '<div class="notice notice-error is-dismissible"><p>'
        . esc_html($msg)
        . ' <a href="' . esc_url($url) . '">'
        . esc_html__('Clique aqui para ativar a licença.', 'plugins-alpha')
        . '</a></p></div>';
    }

    // 2) Aviso específico na tela de edição se a publicação foi bloqueada
    if ('post' === $screen->base) {
      global $post;
      $post_id = ($post instanceof \WP_Post) ? (int) $post->ID : 0;

      if (!$post_id) {
        return;
      }

      $reason = get_post_meta($post_id, '_pga_blocked_publish_reason', true);
      if ($reason) {
        // Mensagem mais amigável independente do código
        $msg2 = __('Este post não pôde ser publicado porque a licença do módulo Alpha Órion não está ativa ou não inclui este módulo.', 'plugins-alpha');

        echo '<div class="notice notice-warning is-dismissible"><p>'
          . esc_html($msg2)
          . '</p></div>';

        // remove a meta pra não ficar mostrando pra sempre
        delete_post_meta($post_id, '_pga_blocked_publish_reason');
      }
    }
  }

  /**
   * Lê a base de URL do banco de dados.
   * - Se vazio: usamos slug direto na raiz (/slug-do-post).
   * - Se preenchido: /base/slug-do-post.
   */
  protected static function get_base_slug(): string
  {
    $base = trim((string) get_option(self::OPTION_BASE, ''), '/');

    // se vazio, define um padrão seguro
    if ($base === '') {
      $base = 'blog';
    }

    return $base;
  }

  public static function register(): void
  {
    $labels = [
      'name'               => __('Órion Posts', 'plugins-alpha'),
      'singular_name'      => __('Órion Post', 'plugins-alpha'),
      'menu_name'          => __('Órion Posts', 'plugins-alpha'),
      'add_new'            => __('Adicionar novo', 'plugins-alpha'),
      'add_new_item'       => __('Adicionar novo Órion Post', 'plugins-alpha'),
      'edit_item'          => __('Editar Órion Post', 'plugins-alpha'),
      'new_item'           => __('Novo Órion Post', 'plugins-alpha'),
      'view_item'          => __('Ver Órion Post', 'plugins-alpha'),
      'search_items'       => __('Buscar Órion Posts', 'plugins-alpha'),
      'not_found'          => __('Nenhum Órion Post encontrado', 'plugins-alpha'),
      'not_found_in_trash' => __('Nenhum Órion Post na lixeira', 'plugins-alpha'),
      'all_items'          => __('Órion Posts', 'plugins-alpha'),
    ];

    $supports = [
      'title',
      'editor',
      'author',
      'thumbnail',
      'excerpt',
      'trackbacks',
      'custom-fields',
      'comments',
      'revisions',
      'page-attributes',
      'post-formats',
    ];

    $base = self::get_base_slug();

    register_post_type('posts_orion', [
      'public'             => true,
      'show_ui'            => true,
      'show_in_menu'       => false,
      'menu_icon'          => 'dashicons-edit',
      'labels'             => $labels,
      'show_in_rest'       => true,
      'supports'           => $supports,
      'taxonomies'         => ['category', 'post_tag'],
      'capability_type'    => 'post',
      'publicly_queryable' => true,
      'rewrite'            => [
        'slug'       => $base,
        'with_front' => false,
      ],
      'has_archive'        => $base,
      'query_var'          => true,
    ]);
  }

  /**
   * Regras de rewrite:
   * - Se base vazia:   /slug-do-post -> posts_orion
   * - Se base "orion": /orion/slug-do-post -> posts_orion
   */
  public static function add_rewrite_rules(): void
  {
    $base = self::get_base_slug();      // aqui NUNCA é vazio
    $base_regex = preg_quote($base, '#');

    add_rewrite_rule(
      '^' . $base_regex . '/([^/]+)/?$',
      'index.php?' . self::QUERY_VAR . '=$matches[1]',
      'top'
    );
  }

  /**
   * Registra nossa query var custom pra WP não descartar.
   */
  public static function register_query_var(array $vars): array
  {
    $vars[] = self::QUERY_VAR;
    return $vars;
  }

  /**
   * Converte a query var interna em uma query padrão de single de posts_orion.
   */
  public static function parse_request(\WP $wp): void
  {
    if (empty($wp->query_vars[self::QUERY_VAR])) {
      return;
    }

    $slug = sanitize_title($wp->query_vars[self::QUERY_VAR]);

    // Dizemos ao WP: é um single de posts_orion com esse slug
    $wp->query_vars['post_type'] = 'posts_orion';
    $wp->query_vars['name']      = $slug;

    // Evita conflito com pagename
    unset($wp->query_vars['pagename']);
  }

  /**
   * Ajusta o permalink dos posts_orion para bater com as nossas regras:
   * - base vazia:   /slug
   * - base "orion": /orion/slug
   */
  public static function filter_permalink($permalink, $post, $leavename, $sample)
  {
    if (!($post instanceof \WP_Post)) {
      return $permalink;
    }

    if ($post->post_type !== 'posts_orion') {
      return $permalink;
    }

    $base = self::get_base_slug(); // ex: "blog"

    /**
     * Caso especial: SAMPLE LINK (usado no editor quando você mexe na slug)
     *
     * Nesse momento o WP quer manter o marcador %postname%
     * ou o slug que ainda nem foi salvo. Se a gente sempre
     * usar $post->post_name, quebra a edição da slug.
     */
    if ($sample) {
      // Se o permalink original tem %postname%, mantemos ele
      if (strpos($permalink, '%postname%') !== false) {
        $slug_part = '%postname%';
      } else {
        // fallback: usa o marcador mesmo assim
        $slug_part = '%postname%';
      }

      $path = $base . '/' . $slug_part;
      return home_url(user_trailingslashit($path));
    }

    // Caso normal (front, links reais)
    $slug = $post->post_name ?: sanitize_title($post->post_title);
    $path = $base . '/' . $slug;

    return home_url(user_trailingslashit($path));
  }


  /**
   * Remove ações (Editar, Edição rápida, Ver) para posts_orion
   * quando a licença do módulo não está ok E o post não está publicado.
   */
  public static function filter_row_actions($actions, $post)
  {
    if (!($post instanceof \WP_Post)) {
      return $actions;
    }

    if ($post->post_type !== 'posts_orion') {
      return $actions;
    }

    if (!class_exists('PluginsAlpha_License')) {
      return $actions;
    }

    $chk = PluginsAlpha_License::check('orion');

    // Se licença ok OU post já publicado → deixa tudo normal
    if (!empty($chk['ok']) || $post->post_status === 'publish') {
      return $actions;
    }

    // Licença não ok + post NÃO publicado → remove edições/visualização
    unset($actions['edit']);
    unset($actions['inline hide-if-no-js']); // Edição rápida
    unset($actions['view']);

    return $actions;
  }

  /**
   * Remove o link de edição do título quando licença não está ok
   * e o post ainda não foi publicado.
   */
  public static function filter_edit_link($link, $post_id)
  {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'posts_orion') {
      return $link;
    }

    if (!class_exists('PluginsAlpha_License')) {
      return $link;
    }

    $chk = PluginsAlpha_License::check('orion');

    // Licença ok ou post publicado → mantém link
    if (!empty($chk['ok']) || $post->post_status === 'publish') {
      return $link;
    }

    // Licença não ok + não publicado → sem link de edição
    return '';
  }

  /**
   * Bloqueia a publicação (inclui cron do WP) quando a licença/módulo não está ok.
   * - Só age em posts_orion
   * - Só quando status está indo PARA publish
   * - Não interfere em updates de posts já publicados.
   */
  public static function block_publish_if_no_license($new_status, $old_status, $post)
  {
    if (!($post instanceof \WP_Post)) {
      return;
    }

    // Só nos importa o nosso CPT
    if ($post->post_type !== 'posts_orion') {
      return;
    }

    // Só queremos quando está indo pra "publish"
    if ($new_status !== 'publish') {
      return;
    }

    // Se já era publish, ignora (edição de post já publicado)
    if ($old_status === 'publish') {
      return;
    }

    if (!class_exists('PluginsAlpha_License')) {
      return;
    }

    $chk = PluginsAlpha_License::check('orion');

    // Se licença OK, deixa publicar normal
    if (!empty($chk['ok'])) {
      return;
    }

    // Evita loop recursivo ao chamar wp_update_post
    remove_action('transition_post_status', [self::class, 'block_publish_if_no_license'], 10);

    // Volta o post para "draft"
    wp_update_post([
      'ID'          => $post->ID,
      'post_status' => 'draft',
    ]);

    // Marca meta explicando o motivo (pra usar em avisos se quiser)
    add_post_meta(
      $post->ID,
      '_pga_blocked_publish_reason',
      $chk['code'] ?? 'licenca_invalida',
      true
    );

    // Re-anexa o hook
    add_action('transition_post_status', [self::class, 'block_publish_if_no_license'], 10, 3);
  }
}
