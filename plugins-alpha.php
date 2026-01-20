<?php

/**
 * Plugin Name: Alpha Suite
 * Description: Tudo o que você precisa para criar seus conteúdos na velocidade de 1 clique — Alpha Órion, Alpha Stories e muito mais.
 * Version: 2.1.1
 * Author: Wallace Tavares
 * Author URI: https://pluginsalpha.com/
 * Text Domain: plugins-alpha
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Tested up to: 6.8
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

define('PLUGINS_ALPHA_VERSION', '2.1.1');

// Constantes
define('PGA_FILE', __FILE__);
define('PGA_PATH', plugin_dir_path(__FILE__));
define('PGA_URL',  plugin_dir_url(__FILE__));

// === Constantes de diretórios (ajuste aqui se mudar) ===
if (!defined('PGA_INC_DIR'))        define('PGA_INC_DIR',        rtrim(PGA_PATH, '/\\') . '/includes');
if (!defined('PGA_INC_POSTS_DIR'))  define('PGA_INC_POSTS_DIR',  PGA_INC_DIR . '/orion');
if (!defined('PGA_INC_STORYS_DIR')) define('PGA_INC_STORYS_DIR', PGA_INC_DIR . '/stories'); // "stories" mesmo


// Versão de asset por filemtime (cache-bust)
function pga_asset_ver(string $relpath): string
{
  $path = PGA_PATH . ltrim($relpath, '/');
  return file_exists($path) ? (string) filemtime($path) : (string) time();
}

spl_autoload_register(function ($class) {
  if (strpos($class, 'PluginsAlpha_') !== 0) return;

  // Normaliza: PluginsAlpha_Foo_Bar  -> Foo_Bar
  // (se usar namespace no futuro, também troca "\" por "_")
  $short = substr($class, strlen('PluginsAlpha_'));
  $short = str_replace('\\', '_', $short);

  $dirs = array_filter([
    PGA_INC_DIR,
    PGA_INC_POSTS_DIR,
    PGA_INC_STORYS_DIR,
  ]);

  // 1) candidatos diretos (em todas as pastas)
  $candidates = [];
  foreach ($dirs as $dir) {
    $base = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    $candidates[] = $base . $short . '.php';                                   // includes/Pages_Generator.php
    $candidates[] = $base . str_replace('_', DIRECTORY_SEPARATOR, $short) . '.php'; // includes/Pages/Generator.php
  }
  foreach ($candidates as $file) {
    if (is_file($file)) {
      require_once $file;
      return;
    }
  }

  // 2) fallback: indexa todos os .php (uma vez) e tenta por nome
  static $index = null;
  if ($index === null) {
    $index = [];
    foreach ($dirs as $dir) {
      if (!is_dir($dir)) continue;
      $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
      );
      foreach ($it as $f) {
        if (strtolower($f->getExtension()) !== 'php') continue;
        $basename = strtolower($f->getBasename('.php')); // ex.: generator
        // mapeia pelo nome do arquivo (sem extensão)
        if (!isset($index[$basename])) {
          $index[$basename] = $f->getPathname();
        }
        // opcional: mapeia também pelo caminho "com underscores"
        $rel = strtolower(str_replace(
          DIRECTORY_SEPARATOR,
          '_',
          trim(str_replace($dir, '', $f->getPath()), '/\\') . '/' . $f->getBasename('.php')
        ));
        $rel = trim($rel, '_/');
        if ($rel && !isset($index[$rel])) {
          $index[$rel] = $f->getPathname(); // ex.: pages_generator
        }
      }
    }
  }

  $keyFull = strtolower($short);
  $leaf    = strtolower(basename(str_replace('_', '/', $short)));

  foreach ([$keyFull, $leaf] as $k) {
    if (isset($index[$k]) && is_file($index[$k])) {
      require_once $index[$k];
      return;
    }
  }
});

// Bootstrap
add_action('plugins_loaded', function () {
  add_action('init', function () {
    register_post_type('ws_story', [
      'label' => 'WS Generator',
      'public' => false,
      'show_ui' => true,
      'show_in_menu' => true,
      'supports' => ['title'],
      'show_in_rest' => true,
    ]);
  });


  if (class_exists('PluginsAlpha_Plugin')) {
    PluginsAlpha_Plugin::init();

    if (class_exists('PluginsAlpha_REST')) {
      add_action('rest_api_init', ['PluginsAlpha_REST', 'register_routes']);
    }

    if (class_exists('PluginsAlpha_REST_Ws_Generator')) {
      add_action('rest_api_init', ['PluginsAlpha_REST_Ws_Generator', 'register_routes']);
    }

    if (class_exists('PluginsAlpha_License')) {
      PluginsAlpha_License::init();
    }

    if (class_exists('PluginsAlpha_Updater')) {
      PluginsAlpha_Updater::init(__FILE__);
    }

    if (class_exists('PluginsAlpha_WS_CPT')) {
      PluginsAlpha_WS_CPT::init();
    }
  }
});

add_action('wp_ajax_pga_orion_prompts_export', ['PluginsAlpha_Prompts', 'ajax_export']);
PluginsAlpha_Prompts::register_ajax();

// Ativação/Desativação
register_activation_hook(PGA_FILE, function () {

  // 🔒 evita rodar fora do admin (segurança)
  if (!is_admin()) {
    return;
  }

  // ✅ garante que o CPT vai existir no flush
  if (class_exists('PluginsAlpha_CPT_Posts_Orion')) {
    PluginsAlpha_CPT_Posts_Orion::register();
  }

  // ✅ flush final
  flush_rewrite_rules(false);

  // cron/licença depois não atrapalha rewrite
  do_action('plugins_alpha/activate');
  if (class_exists('PluginsAlpha_License')) {
    PluginsAlpha_License::schedule_cron();
  }
});

register_deactivation_hook(PGA_FILE, function () {
  do_action('plugins_alpha/deactivate');

  if (class_exists('PluginsAlpha_License')) {
    PluginsAlpha_License::clear_cron();
  }

  flush_rewrite_rules(false);
});

add_action('update_option_pga_story_base', function ($old, $new) {
  if ($old !== $new) flush_rewrite_rules(false);
}, 10, 2);


// Link “Dashboard” na tela de Plugins
add_filter('plugin_action_links_' . plugin_basename(PGA_FILE), function ($links) {
  $links[] = '<a href="' . esc_url(admin_url('admin.php?page=plugins-alpha-dashboard')) . '">' . esc_html__('Dashboard', 'plugins-alpha') . '</a>';
  return $links;
});

// Ajuste do ícone no menu (20x20)
add_action('admin_head', function () {
  echo '<style>
    #adminmenu .toplevel_page_plugins-alpha-dashboard .wp-menu-image img{
      width:16px;height:16px;object-fit:contain;opacity:1;
    }
  </style>';
});
