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
    // registra o CPT
    add_action('init', [self::class, 'register']);

    // adiciona regras de rewrite baseadas na option
    add_action('init', [self::class, 'add_rewrite_rules'], 20);

    // registra query var custom
    add_filter('query_vars', [self::class, 'register_query_var']);

    // converte query var em query de CPT
    add_action('parse_request', [self::class, 'parse_request']);

    // monta o permalink bonito (root ou com base)
    add_filter('post_type_link', [self::class, 'filter_permalink'], 10, 4);

    // restrições de edição/visualização no admin
    if (is_admin()) {
      add_filter('post_row_actions', [self::class, 'filter_row_actions'], 10, 2);
      add_filter('get_edit_post_link', [self::class, 'filter_edit_link'], 10, 3);
      add_action('admin_notices', [self::class, 'admin_license_notices']);
    }

    // bloqueia publicação (inclui cron) se a licença/módulo não estiver ok
    add_action('transition_post_status', [self::class, 'block_publish_if_no_license'], 10, 3);
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
    if ($screen->post_type !== 'orion') {
      return;
    }

    if (!class_exists('PluginsAlpha_License')) {
      return;
    }

    $chk = PluginsAlpha_License::check(self::MODULE_SLUG);

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

      if (! $post_id) {
        return;
      }
      if ($post_id > 0) {
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
  }


  /**
   * Lê a base de URL do banco de dados.
   * - Se vazio: usamos slug direto na raiz (/slug-do-post).
   * - Se preenchido: /base/slug-do-post.
   */
  protected static function get_base_slug(): string
  {
    $base = trim((string) get_option(self::OPTION_BASE, ''), '/');
    return $base; // '' é válido e significa "sem base"
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
      'rewrite'            => false,
      'has_archive'        => false,
      'query_var'          => false,
    ]);
  }

  /**
   * Regras de rewrite:
   * - Se base vazia:   /slug-do-post -> posts_orion
   * - Se base "orion":   /orion/slug-do-post -> posts_orion
   */
  public static function add_rewrite_rules(): void
  {
    $base = self::get_base_slug();

    if ($base === '') {
      // Sem base -> /slug
      // ⚠ Isso concorre com páginas/posts normais.
      add_rewrite_rule(
        '^([^/]+)/?$',
        'index.php?' . self::QUERY_VAR . '=$matches[1]',
        'top'
      );
    } else {
      // Com base -> /base/slug
      $base_regex = preg_quote($base, '#');

      add_rewrite_rule(
        '^' . $base_regex . '/([^/]+)/?$',
        'index.php?' . self::QUERY_VAR . '=$matches[1]',
        'top'
      );
    }
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
    $wp->query_vars['post_type'] = 'orion';
    $wp->query_vars['name']      = $slug;

    // Evita conflito com pagename
    unset($wp->query_vars['pagename']);
  }

  /**
   * Ajusta o permalink dos posts_orion para bater com as nossas regras:
   * - base vazia   -> /slug
   * - base "orion"   -> /orion/slug
   */
  public static function filter_permalink($permalink, $post, $leavename, $sample)
  {
    if ($post->post_type !== 'orion') {
      return $permalink;
    }

    $base = self::get_base_slug();
    $slug = $post->post_name;

    if ($base === '') {
      $path = $slug;                 // raiz
    } else {
      $path = $base . '/' . $slug;   // /base/slug
    }

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

    if ($post->post_type !== 'orion') {
      return $actions;
    }

    if (!class_exists('PluginsAlpha_License')) {
      return $actions;
    }

    $chk = PluginsAlpha_License::check(self::MODULE_SLUG);

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
    if (!$post || $post->post_type !== 'orion') {
      return $link;
    }

    if (!class_exists('PluginsAlpha_License')) {
      return $link;
    }

    $chk = PluginsAlpha_License::check(self::MODULE_SLUG);

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
    if ($post->post_type !== 'orion') {
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

    $chk = PluginsAlpha_License::check(self::MODULE_SLUG);

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
