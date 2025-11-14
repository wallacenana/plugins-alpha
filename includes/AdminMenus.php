<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_AdminMenus {
  public static function register(): void {
    $icon_url = PGA_URL.'assets/images/favicon-plugins-alpha.png?v='.pga_asset_ver('assets/images/favicon-plugins-alpha.png');

    // TOP LEVEL
    add_menu_page(
      __('Plugins Alpha','plugins-alpha'),
      'Plugins Alpha',
      'edit_posts',
      'plugins-alpha-dashboard',
      ['PluginsAlpha_AdminMenus','render_dashboard'],
      $icon_url,
      30
    );

    // 1) Dashboard (primeiro submenu = o que abre ao clicar no topo)
    add_submenu_page(
      'plugins-alpha-dashboard',
      __('Plugins Alpha','plugins-alpha'),
      __('Plugins Alpha','plugins-alpha'),
      'edit_posts',
      'plugins-alpha-dashboard',
      ['PluginsAlpha_AdminMenus','render_dashboard']
    );

    // 2) Posts GPT (lista do CPT)
    add_submenu_page(
      'plugins-alpha-dashboard',
      __('Posts GPT','plugins-alpha'),
      __('Posts GPT','plugins-alpha'),
      'edit_posts',
      'edit.php?post_type=posts_gpt',   // 👈 tela nativa do CPT
      null
    );
    
    // 4) Gerar Posts
    add_submenu_page(
      'plugins-alpha-dashboard',
      __('Gerar Posts','plugins-alpha'),
      __('Gerar Posts','plugins-alpha'),
      'edit_posts',
      'plugins-alpha-gpt-posts',
      ['PluginsAlpha_AdminMenus','render_generator']
    );

    // 3) Alpha Stories (lista do CPT)
    add_submenu_page(
      'plugins-alpha-dashboard',
      __('Alpha Stories','plugins-alpha'),
      __('Alpha Stories','plugins-alpha'),
      'edit_posts',
      'edit.php?post_type=alpha_storys', // 👈 tela nativa do CPT
      null
    );

    // 5) Configurações
    add_submenu_page(
      'plugins-alpha-dashboard',
      __('Configurações','plugins-alpha'),
      __('Configurações','plugins-alpha'),
      'manage_options',
      'plugins-alpha-settings',
      ['PluginsAlpha_AdminMenus','render_settings']
    );
  }

  public static function render_dashboard(): void {
    if (class_exists('PluginsAlpha_Dashboard')) {
      PluginsAlpha_Dashboard::render();
    } else {
      echo '<div class="wrap"><h1>Plugins Alpha — Dashboard</h1><p>Em breve…</p></div>';
    }
  }

  public static function render_generator(): void {
    if (class_exists('PluginsAlpha_Pages_Generator')) {
      PluginsAlpha_Pages_Generator::render();
    } else {
      echo '<div class="wrap"><h1>Gerar Posts</h1><p>Em breve…</p></div>';
    }
  }

  public static function render_settings(): void {
    if (class_exists('PluginsAlpha_Settings')) {
      PluginsAlpha_Settings::render();
    } else {
      echo '<div class="wrap"><h1>Configurações</h1><p>Em breve…</p></div>';
    }
  }
}
