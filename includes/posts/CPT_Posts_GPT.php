<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_CPT_Posts_GPT
{

  /** Bootstrap */
  public static function init(): void
  {
    add_action('init', [__CLASS__, 'register']);
    add_filter('post_type_link', function ($link, $post) {
      if ($post->post_type === 'posts_gpt') {
        return home_url(user_trailingslashit($post->post_name));
      }
      return $link;
    }, 10, 2);
    add_filter('request', function ($vars) {
      // se é uma requisição por nome (slug) e não especificou tipo
      if (!empty($vars['name']) && empty($vars['post_type']) && empty($vars['pagename'])) {
        // inclua post, page e seu CPT
        $vars['post_type'] = ['post', 'page', 'posts_gpt'];
      }
      return $vars;
    });
  }

  /** Registra o CPT */
  public static function register(): void
  {
    $labels = [
      'name'               => __('Posts GPT', 'plugins-alpha'),
      'singular_name'      => __('Post GPT', 'plugins-alpha'),
      'menu_name'          => __('Posts GPT', 'plugins-alpha'),
      'add_new'            => __('Adicionar novo', 'plugins-alpha'),
      'add_new_item'       => __('Adicionar novo Post GPT', 'plugins-alpha'),
      'edit_item'          => __('Editar Post GPT', 'plugins-alpha'),
      'new_item'           => __('Novo Post GPT', 'plugins-alpha'),
      'view_item'          => __('Ver Post GPT', 'plugins-alpha'),
      'search_items'       => __('Buscar Posts GPT', 'plugins-alpha'),
      'not_found'          => __('Nenhum Post GPT encontrado', 'plugins-alpha'),
      'not_found_in_trash' => __('Nenhum Post GPT na lixeira', 'plugins-alpha'),
      'all_items'          => __('Posts GPT', 'plugins-alpha'),
    ];

    // mesmos supports do post padrão
    $supports = ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'page-attributes', 'post-formats'];

    register_post_type('posts_gpt', [
      'public'             => true,
      'show_ui'            => true,
      'show_in_menu'       => false,              // item próprio no menu
      'menu_icon'          => 'dashicons-edit',
      'labels'             => $labels,
      'show_in_rest'       => true,                 // Gutenberg/SEO
      'supports'           => $supports,
      'taxonomies'         => ['category', 'post_tag'],
      'capability_type'    => 'post',
      'publicly_queryable' => true,
      'rewrite'     => false,
      'has_archive' => true, // ou true
    ]);
  }
}
