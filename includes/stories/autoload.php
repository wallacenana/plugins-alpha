<?php
if (!defined('ABSPATH')) exit;

define('ALPHA_STORYS_FILE', __FILE__);
define('ALPHA_STORYS_PATH', plugin_dir_path(__FILE__));
define('ALPHA_STORYS_URL',  plugin_dir_url(__FILE__));

require_once ALPHA_STORYS_PATH . 'includes/plugin.php';

// registra CPT
add_action('init', 'alpha_register_cpt_storys', 0);

function alpha_register_cpt_storys()
{

  require_once PGA_PATH . 'includes/stories/MetaBox.php';
  PluginsAlpha_Stories_MetaBox::init();

  $base = trim((string) get_option('pga_story_base', ''), '/');
  if ($base === '') $base = '';

  $args = [
    'label'               => __('Alpha Stories', 'alpha-storys'),
    'public'              => true,
    'publicly_queryable'  => true,
    'show_ui'             => true,
    'show_in_menu'        => false,
    'show_in_admin_bar'   => true,
    'show_in_nav_menus'   => true,
    'show_in_rest'        => true,
    'menu_icon'           => 'dashicons-slides',
    'menu_position'       => 20,
    'capability_type'     => 'post',
    'map_meta_cap'        => true,
    'hierarchical'        => false,
    'has_archive'         => $base,
    'exclude_from_search' => false,
    'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'author', 'comments', 'custom-fields', 'revisions'],
    'taxonomies'          => ['category'],
    'rewrite'             => [
      'slug'       => $base,
      'with_front' => false,
      'feeds'      => false,
      'pages'      => true,
    ],
  ];

  register_post_type('alpha_storys', $args);
}

// Flush quando a base mudar nos Links Permanentes
add_action('update_option_pga_story_base', function ($old, $new) {
  if ($old !== $new) flush_rewrite_rules(false);
}, 10, 2);
