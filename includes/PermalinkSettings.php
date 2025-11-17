<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_PermalinkSettings
{
  public static function init()
  {
    add_action('admin_init', [self::class, 'register_fields']);              // só renderiza os campos
    add_action('load-options-permalink.php', [self::class, 'handle_save']);  // salva de verdade
  }

  public static function register_fields()
  {
    add_settings_section(
      'plugins_alpha_permalinks_section',
      __('Plugins Alpha', 'plugins-alpha'),
      function () {
        echo '<p>Defina as bases (slugs) personalizadas para os tipos de conteúdo do Plugins Alpha.</p>';
      },
      'permalink'
    );

    add_settings_field(
      'pga_story_base',
      __('Base do Alpha Stories', 'plugins-alpha'),
      [self::class, 'render_story_field'],
      'permalink',
      'plugins_alpha_permalinks_section'
    );

    add_settings_field(
      'pga_posts_base',
      __('Base do GPT Posts', 'plugins-alpha'),
      [self::class, 'render_posts_field'],
      'permalink',
      'plugins_alpha_permalinks_section'
    );

    // OBS: register_setting não tem efeito nessa tela, pode remover sem problemas.
  }

  public static function render_story_field()
  {
    $value = get_option('pga_story_base', '');
    echo '<input name="pga_story_base" type="text" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">Slug base das Web Stories (ex: "story", "webstory"). Deixe em branco para usar o padrão.</p>';
  }

  public static function render_posts_field()
  {
    $value = get_option('pga_posts_base', '');
    echo '<input name="pga_posts_base" type="text" value="' . esc_attr($value) . '" class="regular-text"/>';
    echo '<p class="description">Slug base dos posts gerados (ex: "gpt", "ia-posts"). Deixe em branco para usar o padrão.</p>';
  }

  public static function handle_save()
  {
    // normaliza o método da requisição
    $method = isset($_SERVER['REQUEST_METHOD'])
      ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']))
      : '';

    if ('POST' !== $method) {
      return;
    }

    // mesmo nonce usado pela tela de links permanentes
    check_admin_referer('update-permalink');

    if (! current_user_can('manage_options')) {
      return;
    }

    $story = isset($_POST['pga_story_base'])
      ? sanitize_title_with_dashes(wp_unslash($_POST['pga_story_base']))
      : '';

    $posts = isset($_POST['pga_posts_base'])
      ? sanitize_title_with_dashes(wp_unslash($_POST['pga_posts_base']))
      : '';

    // defaults se vazio
    if ('' === $story) {
      $story = '';
    }
    if ('' === $posts) {
      $posts = '';
    }

    update_option('pga_story_base', $story, false);
    update_option('pga_posts_base', $posts, false);

    // garante que as novas regras valem imediatamente
    flush_rewrite_rules(false);
  }
}
