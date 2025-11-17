<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Settings
{
  const OPTION = 'pga_settings';
  const NONCE  = 'pga_settings_nonce';

  public static function init(): void
  {
    // registra option + sanitização única
    add_action('admin_init', [self::class, 'register']);
  }

  public static function register(): void
  {
    register_setting(self::OPTION, self::OPTION, [
      'type' => 'array',
      'sanitize_callback' => [self::class, 'sanitize_all'],
      'default' => []
    ]);
  }

  /** Sanitização única (merge de sub-árvores) */
  public static function sanitize_all($in)
  {
    $in = is_array($in) ? $in : [];

    // apis.openai (global)
    $api = $in['apis']['openai'] ?? [];
    $out['apis']['openai'] = [
      'key'          => sanitize_text_field($api['key'] ?? ''),
      'model_text'   => sanitize_text_field($api['model_text'] ?? 'gpt-4o-mini'),
      'temperature'  => is_numeric($api['temperature'] ?? null) ? (float)$api['temperature'] : 0.6,
      'max_tokens'   => max(1, (int)($api['max_tokens'] ?? 6000)),
    ];

    // GPT Posts (ex.: defaults)
    $gp = $in['gpt_posts'] ?? [];
    $out['gpt_posts'] = [
      'defaults' => [
        'locale' => sanitize_text_field($gp['defaults']['locale'] ?? 'pt_BR'),
      ],
      // acrescente aqui outras chaves do GPT Posts quando precisar
    ];

    // Stories (migração do alpha_storys_options)
    $st = $in['stories'] ?? [];
    $allowed_styles = ['clean', 'dark-left', 'card', 'split', 'top'];
    $allowed_fonts  = ['system', 'inter', 'poppins', 'merriweather', 'plusjakarta'];

    $out['stories'] = [
      'publisher_name'   => sanitize_text_field($st['publisher_name'] ?? get_bloginfo('name')),
      'publisher_logo_id' => (int)($st['publisher_logo_id'] ?? 0),

      'default_style'    => in_array(($st['default_style'] ?? 'clean'), $allowed_styles, true) ? $st['default_style'] : 'clean',
      'default_font'     => in_array(($st['default_font'] ?? 'plusjakarta'), $allowed_fonts, true) ? $st['default_font'] : 'plusjakarta',
      'accent_color'     => preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', ($st['accent_color'] ?? '')) ? $st['accent_color'] : '#ffffff',
      'autoplay'         => !empty($st['autoplay']) ? 1 : 0,
      'duration'         => in_array(($st['duration'] ?? '7'), ['5', '7', '10', '12'], true) ? $st['duration'] : '7',

      'ga_mode'          => in_array(($st['ga_mode'] ?? 'auto'), ['auto', 'manual', 'off'], true) ? $st['ga_mode'] : 'auto',
      'ga_manual_id'     => (function ($id) {
        $id = trim((string)$id);
        return preg_match('/^G-[A-Z0-9\-]{4,}$/i', $id) ? $id : '';
      })($st['ga_manual_id'] ?? ''),

      // prompts/IA só se quiser separar do global:
      'ai_brief_default' => wp_kses_post($st['ai_brief_default'] ?? ''),
    ];

    return $out;
  }

  /** Helper para obter settings */
  public static function get(): array
  {
    return get_option(self::OPTION, []);
  }

  /** Render da página + abas */
  public static function render(): void
  {
    if (! current_user_can('manage_options')) {
      return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $tab = isset($_GET['tab'])
      ? sanitize_key(wp_unslash($_GET['tab']))
      : 'core';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    $tabs = [
      'core'      => __('Geral', 'plugins-alpha'),
      'gpt-posts' => __('GPT Posts', 'plugins-alpha'),
      'stories'   => __('Stories', 'plugins-alpha'),
    ];
    $opts = self::get();
?>
    <div class="wrap">
      <h1>Plugins Alpha — Configurações</h1>

      <h2 class="nav-tab-wrapper" style="margin-top:12px;">
        <?php foreach ($tabs as $slug => $label):
          $cls = $slug === $tab ? ' nav-tab nav-tab-active' : ' nav-tab';
          $url = admin_url('admin.php?page=plugins-alpha-settings&tab=' . $slug);
        ?>
          <a class="<?php echo esc_attr($cls); ?>" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
      </h2>

      <form method="post" action="options.php" id="pga-settings-form">
        <?php settings_fields(self::OPTION); ?>

        <?php
        switch ($tab) {
          case 'gpt-posts':
            self::render_tab_gpt_posts($opts);
            break;
          case 'stories':
            self::render_tab_stories($opts);
            break;
          default:
            self::render_tab_core($opts);
            break;
        }
        ?>

        <?php submit_button(); ?>
      </form>
    </div>
  <?php
  }

  private static function render_tab_core(array $o): void
  {
    $apis  = $o['apis']['openai'] ?? [];
  ?>
    <h2 class="title">OpenAI (global)</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_openai_key">API Key</label></th>
        <td><input name="pga_settings[apis][openai][key]" id="pga_openai_key" type="password" class="regular-text" placeholder="sk-..." value="<?php echo esc_attr($apis['key'] ?? ''); ?>"></td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_openai_model">Modelo</label></th>
        <td><input name="pga_settings[apis][openai][model_text]" id="pga_openai_model" type="text" class="regular-text" value="<?php echo esc_attr($apis['model_text'] ?? 'gpt-4o-mini'); ?>"></td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_openai_temp">Temperatura</label></th>
        <td><input name="pga_settings[apis][openai][temperature]" id="pga_openai_temp" type="number" min="0" max="1" step="0.1" value="<?php echo esc_attr($apis['temperature'] ?? 0.6); ?>"></td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_openai_maxtok">Max tokens</label></th>
        <td><input name="pga_settings[apis][openai][max_tokens]" id="pga_openai_maxtok" type="number" class="small-text" value="<?php echo esc_attr($apis['max_tokens'] ?? 6000); ?>"></td>
      </tr>
    </table>
  <?php
  }

  private static function render_tab_gpt_posts(array $o): void
  {
    $gp = $o['gpt_posts']['defaults'] ?? [];
  ?>
    <h2 class="title">Padrões de geração</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_gp_locale">Locale padrão</label></th>
        <td>
          <select name="pga_settings[gpt_posts][defaults][locale]" id="pga_gp_locale">
            <?php foreach (['pt_BR' => 'Português (Brasil)', 'en_US' => 'English (US)', 'es_ES' => 'Español', 'fr_FR' => 'Français'] as $v => $lab): ?>
              <option value="<?php echo esc_attr($v); ?>" <?php selected(($gp['locale'] ?? 'pt_BR'), $v); ?>>
                <?php echo esc_html($lab); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
    </table>
  <?php
  }

  private static function render_tab_stories(array $o): void
  {
    $st = $o['stories'] ?? [];
    $logo_id = (int)($st['publisher_logo_id'] ?? 0);
    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
  ?>
    <h2 class="title">Publisher</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_st_pub_name">Nome do publisher</label></th>
        <td><input name="pga_settings[stories][publisher_name]" id="pga_st_pub_name" type="text" class="regular-text" value="<?php echo esc_attr($st['publisher_name'] ?? get_bloginfo('name')); ?>"></td>
      </tr>
      <tr>
        <th scope="row">Logo (96x96)</th>
        <td>
          <div style="margin-bottom:8px;">
            <img id="pga_st_logo_prev" src="<?php echo esc_url($logo_url ?: ''); ?>" style="max-width:96px;height:auto;<?php echo $logo_url ? '' : 'display:none'; ?>">
          </div>
          <input type="hidden" id="pga_st_logo_id" name="pga_settings[stories][publisher_logo_id]" value="<?php echo (int)$logo_id; ?>">
          <button type="button" class="button" data-pga-media-target="pga_st_logo_id" data-pga-preview="pga_st_logo_prev">Selecionar imagem</button>
          <button type="button" class="button" data-pga-media-clear="pga_st_logo_id" style="margin-left:8px;">Remover</button>
        </td>
      </tr>
    </table>

    <h2 class="title">Estilo & Playback (padrão)</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_st_style">Preset de estilo</label></th>
        <td>
          <select name="pga_settings[stories][default_style]" id="pga_st_style">
            <?php foreach (['clean' => 'Clean', 'dark-left' => 'Dark Left', 'card' => 'Card', 'split' => 'Split', 'top' => 'Image top'] as $v => $lab): ?>
              <option value="<?php echo esc_attr($v); ?>" <?php selected(($st['default_style'] ?? 'clean'), $v); ?>><?php echo esc_html($lab); ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_st_font">Fonte</label></th>
        <td>
          <select name="pga_settings[stories][default_font]" id="pga_st_font">
            <?php foreach (['system' => 'System UI', 'inter' => 'Inter', 'poppins' => 'Poppins', 'merriweather' => 'Merriweather', 'plusjakarta' => 'Plus Jakarta Sans'] as $v => $lab): ?>
              <option value="<?php echo esc_attr($v); ?>" <?php selected(($st['default_font'] ?? 'plusjakarta'), $v); ?>><?php echo esc_html($lab); ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_st_accent">Cor de destaque</label></th>
        <td><input name="pga_settings[stories][accent_color]" id="pga_st_accent" type="text" class="regular-text pga-color" value="<?php echo esc_attr($st['accent_color'] ?? '#ffffff'); ?>"></td>
      </tr>
      <tr>
        <th scope="row">Autoplay</th>
        <td>
          <label><input type="checkbox" name="pga_settings[stories][autoplay]" value="1" <?php checked(!empty($st['autoplay'])); ?>> Ativar</label>
          &nbsp;&nbsp;
          <label for="pga_st_duration">Tempo por página (s)</label>
          <select name="pga_settings[stories][duration]" id="pga_st_duration">
            <?php foreach (['5', '7', '10', '12'] as $d): ?>
              <option value="<?php echo esc_attr($d); ?>" <?php selected(($st['duration'] ?? '7'), $d); ?>><?php echo esc_html($d) ?>s</option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
    </table>

    <h2 class="title">Analytics</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">Modo</th>
        <td>
          <?php $mode = $st['ga_mode'] ?? 'auto'; ?>
          <label><input type="radio" name="pga_settings[stories][ga_mode]" value="auto" <?php checked($mode, 'auto');   ?>> Auto</label><br>
          <label><input type="radio" name="pga_settings[stories][ga_mode]" value="manual" <?php checked($mode, 'manual'); ?>> Manual</label><br>
          <label><input type="radio" name="pga_settings[stories][ga_mode]" value="off" <?php checked($mode, 'off');    ?>> Desativado</label>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_ga_manual_id">GA4 Measurement ID (Manual)</label></th>
        <td>
          <input name="pga_settings[stories][ga_manual_id]" id="pga_ga_manual_id" type="text" class="regular-text" placeholder="G-XXXXXXXXXX" value="<?php echo esc_attr($st['ga_manual_id'] ?? ''); ?>">
          <p class="description">Usado apenas se “Manual” estiver selecionado.</p>
        </td>
      </tr>
    </table>

    <h2 class="title">Prompt / IA (opcional)</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_st_brief">Brief padrão</label></th>
        <td>
          <textarea name="pga_settings[stories][ai_brief_default]" id="pga_st_brief" class="large-text" rows="4" placeholder="tom, público, CTA padrão, nº ideal de slides etc."><?php echo esc_textarea($st['ai_brief_default'] ?? ''); ?></textarea>
        </td>
      </tr>
    </table>
<?php
  }
}
