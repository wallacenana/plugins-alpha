<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_AdminMenus
{
  public static function register(): void
  {
    $icon_url = PGA_URL . 'assets/images/favicon-alpha-suite.png?v=' . pga_asset_ver('assets/images/favicon-alpha-suite.png');

    // TOP LEVEL
    add_menu_page(
      __('Alpha Suite', 'alpha-suite'),
      'Alpha Suite',
      'edit_posts',
      'alpha-suite-dashboard',
      ['PluginsAlpha_AdminMenus', 'render_dashboard'],
      $icon_url,
      30
    );

    // 1) Dashboard (primeiro submenu = o que abre ao clicar no topo)
    add_submenu_page(
      'alpha-suite-dashboard',
      __('Dashboard', 'alpha-suite'),
      __('Dashboard', 'alpha-suite'),
      'edit_posts',
      'alpha-suite-dashboard',
      ['PluginsAlpha_AdminMenus', 'render_dashboard']
    );

    // 2) Posts Órion Posts (lista do CPT)
    add_submenu_page(
      'alpha-suite-dashboard',
      __('Órion Posts', 'alpha-suite'),
      __('Órion Posts', 'alpha-suite'),
      'edit_posts',
      'edit.php?post_type=posts_orion',
      null
    );

    // 4) Gerar Posts
    add_submenu_page(
      'alpha-suite-dashboard',
      __('Gerar Posts', 'alpha-suite'),
      __('Gerar Posts', 'alpha-suite'),
      'edit_posts',
      'alpha-suite-orion-posts',
      ['PluginsAlpha_AdminMenus', 'render_generator']
    );

    add_submenu_page(
      'alpha-suite-dashboard',
      __('WS Generator (beta)', 'alpha-suite'),
      __('WS Generator (beta)', 'alpha-suite'),
      'edit_posts',
      'alpha-suite-ws-generator',
      ['PluginsAlpha_AdminMenus', 'render_ws_generator']
    );

    add_submenu_page(
      'alpha-suite-dashboard',
      __('WS Lista', 'alpha-suite'),
      __('WS Lista', 'alpha-suite'),
      'edit_posts',
      'edit.php?post_type=' . PluginsAlpha_WS_CPT::POST_TYPE
    );

    add_submenu_page(
      'alpha-suite-dashboard',
      __('RSS', 'alpha-suite'),
      __('RSS', 'alpha-suite'),
      'edit_posts',
      'alpha-suite-rss',
      ['PluginsAlpha_AdminMenus', 'render_rss']
    );

    // 3) Alpha Stories (lista do CPT)
    add_submenu_page(
      'alpha-suite-dashboard',
      __('Alpha Stories', 'alpha-suite'),
      __('Alpha Stories', 'alpha-suite'),
      'edit_posts',
      'edit.php?post_type=alpha_storys',
      null
    );

    // 5) Configurações
    add_submenu_page(
      'alpha-suite-dashboard',
      __('Configurações', 'alpha-suite'),
      __('Configurações', 'alpha-suite'),
      'manage_options',
      'alpha-suite-settings',
      ['PluginsAlpha_AdminMenus', 'render_settings']
    );

    add_submenu_page(
      'alpha-suite-dashboard',
      __('Prompts', 'alpha-suite'),
      __('Prompts', 'alpha-suite'),
      'manage_options',
      'alpha-suite-orion-prompts',
      ['PluginsAlpha_AdminMenus', 'render_prompts']
    );
  }

  public static function render_dashboard(): void
  {
    if (class_exists('PluginsAlpha_Dashboard')) {
      PluginsAlpha_Dashboard::render();
    } else {
      echo '<div class="wrap"><h1>Alpha Suite — Dashboard</h1><p>Em breve…</p></div>';
    }
  }

  public static function render_ws_generator(): void
  {
    if (class_exists('PluginsAlpha_WS_CPT')) {
      PluginsAlpha_WS_CPT::render();
    } else {
      echo '<div class="wrap"><h1>Web Story Generator</h1><p>Em breve…</p></div>';
    }
  }
  public static function render_rss(): void
  {
    if (class_exists('PluginsAlpha_RSS')) {
      PluginsAlpha_RSS::render();
    } else {
      echo '<div class="wrap"><h1>RSS Feed Settings</h1><p>Em breve…</p></div>';
    }
  }

  public static function render_generator(): void
  {
    if (class_exists('PluginsAlpha_Pages_Generator')) {
      PluginsAlpha_Pages_Generator::render();
    } else {
      echo '<div class="wrap"><h1>Gerar Posts</h1><p>Em breve…</p></div>';
    }
  }

  public static function render_settings(): void
  {
    if (class_exists('PluginsAlpha_Settings')) {
      PluginsAlpha_Settings::render();
    } else {
      echo '<div class="wrap"><h1>Configurações</h1><p>Em breve…</p></div>';
    }
  }
  public static function render_prompts(): void
  {
    if (class_exists('PluginsAlpha_Prompts')) {
      PluginsAlpha_Prompts::render_page();
    } else {
      echo '<div class="wrap"><h1>Configurações</h1><p>Em breve…</p></div>';
    }
  }
}
