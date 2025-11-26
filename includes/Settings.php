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

    // pega o que já existe
    $current = get_option(self::OPTION, []);
    $out     = is_array($current) ? $current : [];

    // descobre qual aba está sendo salva
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $tab = isset($_POST['pga_settings_tab'])
      ? sanitize_key(wp_unslash($_POST['pga_settings_tab']))
      : '';
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    // se vier vazio, trata como "todas" (caso raro)
    if ($tab === '') {
      $tab = 'core';
    }

    /*
     * CORE =============================
     * (apis.openai + apis.images)
     */
    if ($tab === 'core') {
      // --- apis.openai ---
      $api = $in['apis']['openai'] ?? [];

      $out['apis']['openai'] = [
        'key'         => sanitize_text_field($api['key'] ?? ''),
        'model_text'  => sanitize_text_field($api['model_text'] ?? 'gpt-4o-mini'),
        'temperature' => is_numeric($api['temperature'] ?? null) ? (float) $api['temperature'] : 0.6,
        'max_tokens'  => max(1, (int) ($api['max_tokens'] ?? 6000)),
      ];

      // --- apis.images ---
      $img      = $in['apis']['images'] ?? [];
      $provider = isset($img['provider']) ? sanitize_text_field($img['provider']) : 'pollinations';
      $allowed_providers = ['pollinations', 'openai', 'none'];
      if (!in_array($provider, $allowed_providers, true)) {
        $provider = 'pollinations';
      }

      $model = isset($img['model']) ? sanitize_text_field($img['model']) : 'dall-e-3';
      $allowed_models = ['dall-e-3', 'gpt-image-1'];
      if (!in_array($model, $allowed_models, true)) {
        $model = 'dall-e-3';
      }

      $size = isset($img['size']) ? sanitize_text_field($img['size']) : '1792x1024';
      $allowed_sizes = ['1024x1792', '1024x1024', '1792x1024'];
      if (!in_array($size, $allowed_sizes, true)) {
        $size = '1792x1024';
      }

      $quality = isset($img['quality']) ? sanitize_text_field($img['quality']) : 'standard';
      if (!in_array($quality, ['standard', 'hd', 'auto'], true)) {
        $quality = 'auto';
      }

      $out['apis']['images'] = [
        'provider' => $provider,
        'model'    => $model,
        'size'     => $size,
        'quality'  => $quality,
      ];
    }

    /*
     * ORION POSTS ======================
     */
    if ($tab === 'orion-posts') {
      $gp = $in['orion_posts'] ?? [];
      $out['orion_posts'] = [
        'defaults' => [
          'locale' => sanitize_text_field($gp['defaults']['locale'] ?? 'pt_BR'),
        ],
      ];
    }

    /*
     * STORIES ==========================
     */
    if ($tab === 'stories') {
      $st = $in['stories'] ?? [];
      $allowed_styles = ['clean', 'dark-left', 'card', 'split', 'top'];
      $allowed_fonts  = ['system', 'inter', 'poppins', 'merriweather', 'plusjakarta'];

      // provider específico dos stories
      $prov = isset($st['images_provider'])
        ? sanitize_text_field($st['images_provider'])
        : 'inherit';

      $allowed_providers = ['inherit', 'pollinations', 'openai', 'none'];
      if (!in_array($prov, $allowed_providers, true)) {
        $prov = 'inherit';
      }

      // cores com fallback
      $accent = isset($st['accent_color']) ? trim((string)$st['accent_color']) : '';
      $accent = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $accent) ? $accent : '#ffffff';

      $bg = isset($st['background_color']) ? trim((string)$st['background_color']) : '';
      $bg = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $bg) ? $bg : '#000000';

      $txt = isset($st['text_color']) ? trim((string)$st['text_color']) : '';
      $txt = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $txt) ? $txt : '#ffffff';

      $out['stories'] = [
        'publisher_name'    => sanitize_text_field($st['publisher_name'] ?? get_bloginfo('name')),
        'publisher_logo_id' => (int)($st['publisher_logo_id'] ?? 0),

        'default_style'     => in_array(($st['default_style'] ?? 'clean'), $allowed_styles, true) ? $st['default_style'] : 'clean',
        'default_font'      => in_array(($st['default_font'] ?? 'plusjakarta'), $allowed_fonts, true) ? $st['default_font'] : 'plusjakarta',

        // cores
        'accent_color'      => $accent,
        'background_color'  => $bg,
        'text_color'        => $txt,

        // autoplay/duração padrão para stories
        'autoplay'          => !empty($st['autoplay']) ? 1 : 0,
        'duration'          => in_array(($st['duration'] ?? '7'), ['5', '7', '10', '12'], true) ? $st['duration'] : '7',

        'ga_mode'           => in_array(($st['ga_mode'] ?? 'auto'), ['auto', 'manual', 'off'], true) ? $st['ga_mode'] : 'auto',
        'ga_manual_id'      => (function ($id) {
          $id = trim((string)$id);
          return preg_match('/^G-[A-Z0-9\-]{4,}$/i', $id) ? $id : '';
        })($st['ga_manual_id'] ?? ''),

        'ai_brief_default'  => wp_kses_post($st['ai_brief_default'] ?? ''),

        'images_provider'   => $prov,
      ];
    }


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
      'orion-posts' => __('Órion Posts', 'plugins-alpha'),
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

        <input type="hidden" name="pga_settings_tab" value="<?php echo esc_attr($tab); ?>">

        <?php
        switch ($tab) {
          case 'orion-posts':
            self::render_tab_orion_posts($opts);
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
    $apis = $o['apis']['openai'] ?? [];
    $img  = $o['apis']['images'] ?? [];

    $prov    = $img['provider'] ?? 'pollinations';
    $img_model   = $img['model'] ?? 'dall-e-3';
    $img_size    = $img['size'] ?? '1024x576';
    $img_quality = $img['quality'] ?? 'auto';
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
    <h2 class="title"><?php esc_html_e('OpenAI (global)', 'plugins-alpha'); ?></h2>
    <table class="form-table" role="presentation">
      <!-- seus campos já existentes de OpenAI aqui -->
    </table>

    <h2 class="title"><?php esc_html_e('Imagens (thumbnails automáticas)', 'plugins-alpha'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">
          <label for="pga_img_provider"><?php esc_html_e('Provedor de imagem', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <select name="pga_settings[apis][images][provider]"
            id="pga_img_provider">
            <option value="pollinations" <?php selected($prov, 'pollinations'); ?>>
              <?php esc_html_e('Pollinations (grátis, qualidade variável)', 'plugins-alpha'); ?>
            </option>
            <option value="openai" <?php selected($prov, 'openai'); ?>>
              <?php esc_html_e('OpenAI / DALL·E (pago, melhor qualidade)', 'plugins-alpha'); ?>
            </option>
            <option value="none" <?php selected($prov, 'none'); ?>>
              <?php esc_html_e('Não gerar thumbnails automaticamente', 'plugins-alpha'); ?>
            </option>
          </select>
          <p class="description">
            <?php esc_html_e('Escolha se as imagens serão geradas pelo Pollinations, OpenAI ou se serão desativadas.', 'plugins-alpha'); ?>
          </p>
        </td>
      </tr>

      <tr class="pga-img-openai-row">
        <th scope="row">
          <label for="pga_img_model"><?php esc_html_e('Modelo OpenAI', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <select name="pga_settings[apis][images][model]"
            id="pga_img_model">
            <option value="dall-e-3" <?php selected($img_model, 'dall-e-3'); ?>>
              dall-e-3
            </option>
            <!-- <option value="gpt-image-1" <?php selected($img_model, 'gpt-image-1'); ?>>
              gpt-image-1
            </option> -->
          </select>
          <p class="description">
            <?php esc_html_e('Escolha o modelo de imagem da OpenAI compatível com a sua conta.', 'plugins-alpha'); ?>
          </p>
        </td>
      </tr>

      <tr class="pga-img-openai-row">
        <th scope="row">
          <label for="pga_img_size"><?php esc_html_e('Tamanho da imagem', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <select name="pga_settings[apis][images][size]"
            id="pga_img_size">
            <option value="1024x1024" <?php selected($img_size, '1024x1024'); ?>>1024x1024 (quadrado)</option>
            <option value="1024x1792" <?php selected($img_size, '1024x1792'); ?>>1024x1792 (Vertical)</option>
            <option value="1792x1024" <?php selected($img_size, '1792x1024'); ?>>1792x1024 (wide)</option>
          </select>
          <p class="description">
            <?php esc_html_e('Use 16:9 para thumbnails de posts e 1024x1024 para usos genéricos.', 'plugins-alpha'); ?>
          </p>
        </td>
      </tr>

      <tr class="pga-img-openai-row">
        <th scope="row">
          <label for="pga_img_quality"><?php esc_html_e('Qualidade', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <select name="pga_settings[apis][images][quality]"
            id="pga_img_quality">
            <option value="standard" <?php selected($img_quality, 'standard'); ?>>
              <?php esc_html_e('Standard (mais barato)', 'plugins-alpha'); ?>
            </option>
            <option value="hd" <?php selected($img_quality, 'hd'); ?>>
              <?php esc_html_e('HD (mais caro, melhor)', 'plugins-alpha'); ?>
            </option>
          </select>
          <p class="description">
            <?php esc_html_e('HD consome mais créditos, use apenas quando precisar de máxima qualidade.', 'plugins-alpha'); ?>
          </p>
        </td>
      </tr>
    </table>

    <script>
      (function($) {
        function pgaToggleImageRows() {
          var prov = $('#pga_img_provider').val();
          if (prov === 'openai') {
            $('.pga-img-openai-row').show();
          } else {
            $('.pga-img-openai-row').hide();
          }
        }
        $(document).on('change', '#pga_img_provider', pgaToggleImageRows);
        $(pgaToggleImageRows);
      })(jQuery);
    </script>
  <?php
  }

  private static function render_tab_orion_posts(array $o): void
  {
    $gp = $o['orion_posts']['defaults'] ?? [];
  ?>
    <h2 class="title">Padrões de geração</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_gp_locale">Locale padrão</label></th>
        <td>
          <select name="pga_settings[orion_posts][defaults][locale]" id="pga_gp_locale">
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
    $images_provider = $st['images_provider'] ?? 'inherit';
    $bg_color   = $st['background_color'] ?? '#000000';
    $text_color = $st['text_color'] ?? '#ffffff';

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
        <td><input name="pga_settings[stories][accent_color]" id="pga_st_accent" type="color" class="regular-text pga-color" value="<?php echo esc_attr($st['accent_color'] ?? '#ffffff'); ?>"></td>
      </tr>
      <tr>
        <th scope="row">
          <label for="pga_st_background_color">
            <?php esc_html_e('Cor de fundo padrão', 'plugins-alpha'); ?>
          </label>
        </th>
        <td>
          <input
            type="color"
            id="pga_st_background_color"
            name="pga_settings[stories][background_color]"
            value="<?php echo esc_attr($bg_color); ?>"
            class="regular-text pga-color-field"
            data-default-color="#000000" />
          <p class="description">
            <?php esc_html_e('Cor de fundo usada por padrão nas Web Stories (caso o post não tenha uma cor própria).', 'plugins-alpha'); ?>
          </p>
        </td>
      </tr>

      <tr>
        <th scope="row">
          <label for="pga_st_text_color">
            <?php esc_html_e('Cor do texto padrão', 'plugins-alpha'); ?>
          </label>
        </th>
        <td>
          <input
            type="color"
            id="pga_st_text_color"
            name="pga_settings[stories][text_color]"
            value="<?php echo esc_attr($text_color); ?>"
            class="regular-text pga-color-field"
            data-default-color="#ffffff" />
          <p class="description">
            <?php esc_html_e('Cor do texto usada por padrão nas Web Stories (caso o post não tenha uma cor própria).', 'plugins-alpha'); ?>
          </p>
        </td>
      </tr>

      <tr>
        <th scope="row">Autoplay</th>
        <td>
          <?php
          $st = $opts['stories'] ?? [];
          $autoplay = isset($st['autoplay']) ? (int)$st['autoplay'] : 1; // default 1
          ?>
          <label>
            <input type="checkbox"
              name="pga_settings[stories][autoplay]"
              value="1"
              <?php checked($autoplay, 1); ?>>
            <?php esc_html_e('Ativar autoplay por padrão', 'plugins-alpha'); ?>
          </label>
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

    <h2 class="title">Imagens / IA para Stories</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">
          <label for="pga_st_img_provider">
            <?php esc_html_e('Provedor de imagens para Stories', 'plugins-alpha'); ?>
          </label>
        </th>
        <td>
          <select
            id="pga_st_img_provider"
            name="pga_settings[stories][images_provider]">
            <option value="inherit" <?php selected($images_provider, 'inherit'); ?>>
              <?php esc_html_e('Usar provedor global (Imagens)', 'plugins-alpha'); ?>
            </option>
            <option value="pollinations" <?php selected($images_provider, 'pollinations'); ?>>
              <?php esc_html_e('Pollinations (grátis, qualidade variável)', 'plugins-alpha'); ?>
            </option>
            <option value="openai" <?php selected($images_provider, 'openai'); ?>>
              <?php esc_html_e('OpenAI / DALL·E (pago, melhor qualidade)', 'plugins-alpha'); ?>
            </option>
            <option value="none" <?php selected($images_provider, 'none'); ?>>
              <?php esc_html_e('Não gerar imagens automaticamente para Stories', 'plugins-alpha'); ?>
            </option>
          </select>
          <p class="description">
            <?php esc_html_e(
              'Se escolher "Usar provedor global", os Stories usam o mesmo provedor configurado em Geral › Imagens. Caso contrário, essa escolha vale só para as imagens de Web Stories.',
              'plugins-alpha'
            ); ?>
          </p>
        </td>
      </tr>
    </table>
<?php
  }
}
