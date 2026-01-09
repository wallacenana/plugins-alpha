<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Plugin
{
  public static function init(): void
  {
    if (class_exists('PluginsAlpha_CPT_Posts_Orion')) PluginsAlpha_CPT_Posts_Orion::init();
    if (class_exists('PluginsAlpha_PermalinkSettings')) PluginsAlpha_PermalinkSettings::init();
    if (class_exists('PluginsAlpha_Settings')) PluginsAlpha_Settings::init();
    if (class_exists('PluginsAlpha_Adminbar')) PluginsAlpha_Adminbar::init();
    if (class_exists('PluginsAlpha_Orion_Migrator')) PluginsAlpha_Orion_Migrator::init();

    require_once PGA_PATH . 'includes/stories/autoload.php';
    // Menus e assets
    add_action('admin_menu', ['PluginsAlpha_AdminMenus', 'register']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
  }

  public static function assets($hook): void
  {
    // 1) Detecta telas do plugin (menus próprios) usando o hook recebido
    // Ex.: $hook = 'toplevel_page_plugins-alpha', 'plugins-alpha_page_plugins-alpha-license', etc.
    $is_plugin_page = (false !== strpos((string) $hook, 'plugins-alpha'));

    if (! $is_plugin_page) {
      return;
    }

    // 2) Detecta telas dos CPTs (lista, novo, editar)
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_cpt_screen = false;

    if ($screen) {
      // CPTs que vão usar esse JS
      $allowed_post_types = ['posts_orion', 'alpha_storys'];

      if (!empty($screen->post_type) && in_array($screen->post_type, $allowed_post_types, true)) {
        $is_cpt_screen = true;
      }
    }

    if (!$is_plugin_page && !$is_cpt_screen) {
      return; // não carrega fora dos nossos contexts
    }

    // === CSS/JS do admin ===
    wp_enqueue_style(
      'pga-admin',
      PGA_URL . 'assets/admin.css',
      [],
      pga_asset_ver('assets/admin.css')
    );

    wp_register_style(
      'select2',
      'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
      [],
      '4.1.0'
    );
    wp_register_script(
      'select2',
      'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
      ['jquery'],
      '4.1.0',
      true
    );
    wp_enqueue_style('select2');
    wp_enqueue_script('select2');

    // SweetAlert2
    wp_enqueue_script(
      'sweetalert2',
      PGA_URL . 'assets/vendor/sweetalert2@11.js',
      [],
      '11',
      true
    );

    // JS principal
    wp_enqueue_script(
      'pga-admin',
      PGA_URL . 'assets/admin.js',
      ['jquery', 'wp-util', 'sweetalert2', 'wp-i18n'],
      pga_asset_ver('assets/admin.js'),
      true
    );

    wp_set_script_translations(
      'pga-admin',
      'plugins-alpha',
      plugin_dir_path(__FILE__) . 'languages'
    );

    wp_localize_script('pga-admin', 'PGA_CFG', [
      'rest'   => esc_url_raw(rest_url('pga/v1')),
      'nonce'  => wp_create_nonce('wp_rest'),
      'options' => class_exists('PluginsAlpha_Settings') ? PluginsAlpha_Settings::get() : [],
      'isCPT'  => (bool) $is_cpt_screen,
    ]);
  }
}
