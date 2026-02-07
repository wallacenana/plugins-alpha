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
    add_action('admin_enqueue_scripts', function ($hook) {
      // Ajuste a condição pra bater na sua página.
      // Ex.: admin.php?page=plugins-alpha-settings
      if (empty($_GET['page']) || $_GET['page'] !== 'plugins-alpha-settings') return;

      // ESSENCIAL: carrega o wp.media
      wp_enqueue_media();

      // Seu JS
      wp_enqueue_script(
        'pga-admin-media',
        plugins_url('../assets/admin-media.js', __FILE__), // ajuste o caminho
        ['jquery'],
        '1.0.0',
        true
      );
    });
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
      $allowed_providers = ['pollinations', 'openai', 'pexels', 'unsplash', 'none'];
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

      /**
       * Pexels – banco de imagens
       */
      $pex = $in['apis']['pexels'] ?? [];
      $out['apis']['pexels'] = [
        'key' => sanitize_text_field($pex['key'] ?? ''),
      ];

      /**
       * Unsplash – banco de imagens
       */
      $uns = $in['apis']['unsplash'] ?? [];
      $out['apis']['unsplash'] = [
        'access_key' => sanitize_text_field($uns['access_key'] ?? ''),
      ];

      /**
       * Gemini – credenciais para textos
       */
      $gem = $in['apis']['gemini'] ?? [];
      $out['apis']['gemini'] = [
        'key'        => sanitize_text_field($gem['key'] ?? ''),
        'model_text' => sanitize_text_field($gem['model_text'] ?? 'gemini-1.5-pro'),
      ];

      /**
       * YouTube API
       */
      $yt = $in['apis']['youtube'] ?? [];
      $out['apis']['youtube'] = [
        'key' => sanitize_text_field($yt['key'] ?? ''),
      ];
    }

    /*
     * ORION POSTS ======================
     */
    if ($tab === 'orion-posts') {
      $gp = $in['orion_posts'] ?? [];

      // provider de TEXTO para Órion
      $text_prov = isset($gp['text_provider'])
        ? sanitize_text_field($gp['text_provider'])
        : 'openai'; // default

      $allowed_text_prov = ['openai', 'gemini'];
      if (!in_array($text_prov, $allowed_text_prov, true)) {
        $text_prov = 'openai';
      }

      // provider de IMAGEM para Órion
      $img_prov = isset($gp['images_provider'])
        ? sanitize_text_field($gp['images_provider'])
        : 'pollinations'; // default imagem

      $allowed_img_prov = ['pollinations', 'openai', 'pexels', 'unsplash', 'none'];
      if (!in_array($img_prov, $allowed_img_prov, true)) {
        $img_prov = 'pollinations';
      }

      $out['orion_posts'] = [
        'defaults' => [
          'locale' => sanitize_text_field($gp['defaults']['locale'] ?? 'pt_BR'),
        ],
        'text_provider'   => $text_prov,
        'images_provider' => $img_prov,
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
      $text_prov = isset($st['text_provider'])
        ? sanitize_text_field($st['text_provider'])
        : 'openai';

      $allowed_text_prov = ['openai', 'gemini'];
      if (!in_array($text_prov, $allowed_text_prov, true)) {
        $text_prov = 'openai';
      }

      // provider de IMAGEM para stories
      $img_prov = isset($st['images_provider'])
        ? sanitize_text_field($st['images_provider'])
        : 'pollinations';

      $allowed_img_prov = ['pollinations', 'openai', 'pexels', 'unsplash', 'none'];
      if (!in_array($img_prov, $allowed_img_prov, true)) {
        $img_prov = 'pollinations';
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

        'text_provider'   => $text_prov,
        'images_provider' => $img_prov,
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
      'core'      => __('Integrações', 'plugins-alpha'),
      'orion-posts' => __('Órion Posts', 'plugins-alpha'),
      'stories'   => __('Stories', 'plugins-alpha'),
    ];
    $opts = self::get();
?>
    <div class="wrap">
      <h1><?php esc_html_e('Plugins Alpha — Configurações', 'plugins-alpha'); ?></h1>

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
    $pex  = $o['apis']['pexels'] ?? [];
    $coh  = $o['apis']['cohere'] ?? [];
    $mis  = $o['apis']['mistral'] ?? [];
    $per  = $o['apis']['perplexity'] ?? [];
    $cla  = $o['apis']['claude'] ?? [];
    $uns  = $o['apis']['unsplash'] ?? [];
    $gem  = $o['apis']['gemini'] ?? [];
    $yt   = $o['apis']['youtube'] ?? [];
  ?>
    <h2 class="title">OpenAI</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">
          <label for="pga_openai_key">API Key</label>
        </th>
        <td>
          <p class="description">
            <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e('Gerar chave.', 'plugins-alpha'); ?>
            </a>
          </p>
          <input
            name="pga_settings[apis][openai][key]"
            id="pga_openai_key"
            type="text"
            class="regular-text"
            placeholder="sk-..."
            value="<?php echo esc_attr($apis['key'] ?? ''); ?>">
        </td>
      </tr>
      <tr>
        <th scope="row">
          <label for="pga_openai_model">Modelo</label>
        </th>
        <td>

          <input
            name="pga_settings[apis][openai][model_text]"
            id="pga_openai_model"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($apis['model_text'] ?? 'gpt-4o-mini'); ?>">
          <p class="description">
            <?php esc_html_e('Ex.: gpt-4.1-mini, gpt-4.1, o3-mini, etc.', 'plugins-alpha'); ?>
          </p>
          <a href="https://platform.openai.com/docs/models" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('Ver modelos.', 'plugins-alpha'); ?>
          </a>
        </td>
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

    <h2 class="title"><?php esc_html_e('Gemini', 'plugins-alpha'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">
          <label for="pga_gemini_key"><?php esc_html_e('API Key', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <p class="description">
            <a href="https://aistudio.google.com/api-keys" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e('Gerar chave.', 'plugins-alpha'); ?>
            </a>
          </p>
          <input
            name="pga_settings[apis][gemini][key]"
            id="pga_gemini_key"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($gem['key'] ?? ''); ?>">
          <!-- <button type="button" class="button button-secondary pga-selftest-btn" data-provider="gemini">
            <?php // esc_html_e('Testar conexão', 'plugins-alpha'); 
            ?>
          </button> -->
        </td>
      </tr>
      <tr>
        <th scope="row">
          <label for="pga_gemini_model"><?php esc_html_e('Modelo de texto', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <input
            name="pga_settings[apis][gemini][model_text]"
            id="pga_gemini_model"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($gem['model_text'] ?? 'gemini-2.5-flash-lite'); ?>">
          <p class="description">
            <?php esc_html_e('Ex. (recomendado): gemini-2.5-flash-lite.', 'plugins-alpha'); ?>
            <br>
            <a href="https://ai.google.dev/gemini-api/docs/models/gemini" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e('Ver modelos.', 'plugins-alpha'); ?>
            </a>
          </p>
        </td>
      </tr>
    </table>

    <h2 class="title">Perplexity</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_perplexity_key">API Key</label></th>
        <td>
          <p class="description">
            <a href="https://www.perplexity.ai/settings/api" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e('Gerar chave.', 'plugins-alpha'); ?>
            </a>
          </p>
          <input
            name="pga_settings[apis][perplexity][key]"
            id="pga_perplexity_key"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($per['key'] ?? ''); ?>">
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_perplexity_model">Modelo</label></th>
        <td>
          <input
            name="pga_settings[apis][perplexity][model_text]"
            id="pga_perplexity_model"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($per['model_text'] ?? 'llama-3.1-sonar-large-128k-online'); ?>">
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_perplexity_temp">Temperatura</label></th>
        <td><input name="pga_settings[apis][perplexity][temperature]" id="pga_perplexity_temp" type="number" min="0" max="1" step="0.1" value="<?php echo esc_attr($per['temperature'] ?? 0.6); ?>"></td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_perplexity_maxtok">Max tokens</label></th>
        <td><input name="pga_settings[apis][perplexity][max_tokens]" id="pga_perplexity_maxtok" type="number" class="small-text" value="<?php echo esc_attr($per['max_tokens'] ?? 8000); ?>"></td>
      </tr>
    </table>


    <h2 class="title">Claude (Anthropic)</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_claude_key">API Key</label></th>
        <td>
          <p class="description">
            <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e('Gerar chave.', 'plugins-alpha'); ?>
            </a>
          </p>
          <input
            name="pga_settings[apis][claude][key]"
            id="pga_claude_key"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($cla['key'] ?? ''); ?>">
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_claude_model">Modelo</label></th>
        <td>
          <input
            name="pga_settings[apis][claude][model_text]"
            id="pga_claude_model"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($cla['model_text'] ?? 'claude-3-5-sonnet-20240620'); ?>">
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_claude_temp">Temperatura</label></th>
        <td><input name="pga_settings[apis][claude][temperature]" id="pga_claude_temp" type="number" min="0" max="1" step="0.1" value="<?php echo esc_attr($cla['temperature'] ?? 0.6); ?>"></td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_claude_maxtok">Max tokens</label></th>
        <td><input name="pga_settings[apis][claude][max_tokens]" id="pga_claude_maxtok" type="number" class="small-text" value="<?php echo esc_attr($cla['max_tokens'] ?? 8000); ?>"></td>
      </tr>
    </table>

    <h2 class="title">Mistral</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_mistral_key">API Key</label></th>
        <td>
          <p class="description">
            <a href="https://console.mistral.ai/api-keys/" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e('Gerar chave.', 'plugins-alpha'); ?>
            </a>
          </p>
          <input
            name="pga_settings[apis][mistral][key]"
            id="pga_mistral_key"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($mis['key'] ?? ''); ?>">
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_mistral_model">Modelo</label></th>
        <td>
          <input
            name="pga_settings[apis][mistral][model_text]"
            id="pga_mistral_model"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($mis['model_text'] ?? 'mistral-large-latest'); ?>">
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_mistral_temp">Temperatura</label></th>
        <td><input name="pga_settings[apis][mistral][temperature]" id="pga_mistral_temp" type="number" min="0" max="1" step="0.1" value="<?php echo esc_attr($mis['temperature'] ?? 0.6); ?>"></td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_mistral_maxtok">Max tokens</label></th>
        <td><input name="pga_settings[apis][mistral][max_tokens]" id="pga_mistral_maxtok" type="number" class="small-text" value="<?php echo esc_attr($mis['max_tokens'] ?? 8000); ?>"></td>
      </tr>
    </table>

    <h2 class="title">Cohere</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_cohere_key">API Key</label></th>
        <td>
          <p class="description">
            <a href="https://dashboard.cohere.com/api-keys" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e('Gerar chave.', 'plugins-alpha'); ?>
            </a>
          </p>
          <input
            name="pga_settings[apis][cohere][key]"
            id="pga_cohere_key"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($coh['key'] ?? ''); ?>">
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_cohere_model">Modelo</label></th>
        <td>
          <input
            name="pga_settings[apis][cohere][model_text]"
            id="pga_cohere_model"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($coh['model_text'] ?? 'command-r-plus'); ?>">
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_cohere_temp">Temperatura</label></th>
        <td><input name="pga_settings[apis][cohere][temperature]" id="pga_cohere_temp" type="number" min="0" max="1" step="0.1" value="<?php echo esc_attr($coh['temperature'] ?? 0.6); ?>"></td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_cohere_maxtok">Max tokens</label></th>
        <td><input name="pga_settings[apis][cohere][max_tokens]" id="pga_cohere_maxtok" type="number" class="small-text" value="<?php echo esc_attr($coh['max_tokens'] ?? 8000); ?>"></td>
      </tr>
    </table>


    <h2 class="title"><?php esc_html_e('Pexels (banco de imagens)', 'plugins-alpha'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">
          <label for="pga_pexels_key"><?php esc_html_e('API Key Pexels', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <p class="description">
            <a href="https://www.pexels.com/api/" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e('Gerar chave.', 'plugins-alpha'); ?>
            </a>
          </p>
          <input
            name="pga_settings[apis][pexels][key]"
            id="pga_pexels_key"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($pex['key'] ?? ''); ?>">
          <!-- <button type="button" class="button button-secondary pga-selftest-btn" data-provider="pexels">
            <?php // esc_html_e('Testar conexão', 'plugins-alpha'); 
            ?>
          </button> -->
        </td>
      </tr>
    </table>

    <h2 class="title"><?php esc_html_e('Unsplash (banco de imagens)', 'plugins-alpha'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">
          <label for="pga_unsplash_key"><?php esc_html_e('Access Key Unsplash', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <p class="description">
            <a href="https://unsplash.com/developers" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e('Gerar chave.', 'plugins-alpha'); ?>
            </a>
          </p>
          <input
            name="pga_settings[apis][unsplash][access_key]"
            id="pga_unsplash_key"
            type="text"
            class="regular-text"
            value="<?php echo esc_attr($uns['access_key'] ?? ''); ?>">
          <!-- <button type="button" class="button button-secondary pga-selftest-btn" data-provider="unsplash">
            <?php // esc_html_e('Testar conexão', 'plugins-alpha'); 
            ?>
          </button> -->
        </td>
      </tr>
    </table>

    <h2 class="title"><?php esc_html_e('YouTube API', 'plugins-alpha'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">
          <label for="pga_youtube_key"><?php esc_html_e('API Key do YouTube', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <p class="description">
            <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">
              <?php esc_html_e('Gerar chave.', 'plugins-alpha'); ?>
            </a>
          </p>
          <input
            name="pga_settings[apis][youtube][key]"
            id="pga_youtube_key"
            type="text"
            class="regular-text"
            placeholder="AIza..."
            value="<?php echo esc_attr($yt['key'] ?? ''); ?>">
          <!-- <button type="button" class="button button-secondary pga-selftest-btn" data-provider="youtube">
            <?php //esc_html_e('Testar conexão ', 'plugins-alpha'); 
            ?>
          </button> -->
        </td>
      </tr>
    </table>
  <?php
  }


  private static function render_tab_orion_posts(array $o): void
  {
    $gp_text = $o['orion_posts']['text_provider'] ?? 'openai';
    $gp_img  = $o['orion_posts']['images_provider'] ?? 'pollinations';
  ?>
    <h2 class="title"><?php esc_html_e('Padrões de geração', 'plugins-alpha'); ?></h2>
    <table class="form-table" role="presentation">

      <tr>
        <th scope="row">
          <label for="pga_gp_locale"><?php esc_html_e('Idioma padrão', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <!-- select de locale existente -->
        </td>
      </tr>

      <tr>
        <th scope="row">
          <label for="pga_gp_text_provider">
            <?php esc_html_e('IA para geração de TEXTO', 'plugins-alpha'); ?>
          </label>
        </th>
        <td>
          <select name="pga_settings[orion_posts][text_provider]" id="pga_gp_text_provider">
            <option value="openai" <?php selected($gp_text, 'openai'); ?>>OpenAI</option>
            <option value="claude" <?php selected($gp_text, 'claude'); ?>>Claude (Anthropic)</option>
            <option value="mistral" <?php selected($gp_text, 'mistral'); ?>>Mistral</option>
            <option value="perplexity" <?php selected($gp_text, 'perplexity'); ?>>Perplexity</option>
            <option value="cohere" <?php selected($gp_text, 'cohere'); ?>>Cohere</option>
            <option value="gemini" <?php selected($gp_text, 'gemini'); ?>>Gemini</option>
          </select>

          <p class="description">
            <?php esc_html_e(
              'Usada para gerar títulos, keywords, outlines, sections e artigos.',
              'plugins-alpha'
            ); ?>
          </p>
        </td>
      </tr>

      <tr>
        <th scope="row">
          <label for="pga_gp_img_provider">
            <?php esc_html_e('IA / Fonte para IMAGEM', 'plugins-alpha'); ?>
          </label>
        </th>
        <td>
          <select name="pga_settings[orion_posts][images_provider]" id="pga_gp_img_provider">
            <option value="pollinations" <?php selected($gp_img, 'pollinations'); ?>>
              Pollinations (IA grátis)
            </option>
            <option value="openai" <?php selected($gp_img, 'openai'); ?>>
              OpenAI (DALL·E)
            </option>
            <option value="pexels" <?php selected($gp_img, 'pexels'); ?>>
              Pexels
            </option>
            <option value="unsplash" <?php selected($gp_img, 'unsplash'); ?>>
              Unsplash
            </option>
            <option value="none" <?php selected($gp_img, 'none'); ?>>
              <?php esc_html_e('Não gerar imagens automaticamente', 'plugins-alpha'); ?>
            </option>
          </select>

          <p class="description">
            <?php esc_html_e(
              'Usada para thumbnails e imagens geradas automaticamente pelo módulo Órion.',
              'plugins-alpha'
            ); ?>
          </p>
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
    $bg_color   = $st['background_color'] ?? '#000000';
    $text_color = $st['text_color'] ?? '#ffffff';
    $images_provider = $st['images_provider'] ?? 'pollinations';
    $text_provider   = $st['text_provider'] ?? 'openai';

  ?>
    <h2 class="title"><?php esc_html_e('Publisher', 'plugins-alpha'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_st_pub_name"><?php esc_html_e('Nome do blog', 'plugins-alpha'); ?></label></th>
        <td><input name="pga_settings[stories][publisher_name]" id="pga_st_pub_name" type="text" class="regular-text" value="<?php echo esc_attr($st['publisher_name'] ?? get_bloginfo('name')); ?>"></td>
      </tr>
      <tr>
        <th scope="row">Logo</th>
        <td>
          <div style="margin-bottom:8px;">
            <img id="pga_st_logo_prev" src="<?php echo esc_url($logo_url ?: ''); ?>" style="max-width:96px;height:auto;<?php echo $logo_url ? '' : 'display:none'; ?>">
          </div>
          <input type="hidden" id="pga_st_logo_id" name="pga_settings[stories][publisher_logo_id]" value="<?php echo (int)$logo_id; ?>">
          <button type="button" class="button" data-pga-media-target="pga_st_logo_id" data-pga-preview="pga_st_logo_prev"><?php esc_html_e('Selecionar imagem', 'plugins-alpha'); ?></button>
          <button type="button" class="button" data-pga-media-clear="pga_st_logo_id" style="margin-left:8px;"><?php esc_html_e('Remover', 'plugins-alpha'); ?></button>
        </td>
      </tr>
    </table>

    <h2 class="title"><?php esc_html_e('Estilo & Playback (padrão)', 'plugins-alpha'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row"><label for="pga_st_style"><?php esc_html_e('Preset de estilo', 'plugins-alpha'); ?></label></th>
        <td>
          <select name="pga_settings[stories][default_style]" id="pga_st_style">
            <?php foreach (['clean' => 'Clean', 'dark-left' => 'Dark Left', 'card' => 'Card', 'split' => 'Split', 'top' => 'Image top'] as $v => $lab): ?>
              <option value="<?php echo esc_attr($v); ?>" <?php selected(($st['default_style'] ?? 'clean'), $v); ?>><?php echo esc_html($lab); ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_st_font"><?php esc_html_e('Fonte', 'plugins-alpha'); ?></label></th>
        <td>
          <select name="pga_settings[stories][default_font]" id="pga_st_font">
            <?php foreach (['system' => 'System UI', 'inter' => 'Inter', 'poppins' => 'Poppins', 'merriweather' => 'Merriweather', 'plusjakarta' => 'Plus Jakarta Sans'] as $v => $lab): ?>
              <option value="<?php echo esc_attr($v); ?>" <?php selected(($st['default_font'] ?? 'plusjakarta'), $v); ?>><?php echo esc_html($lab); ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_st_accent"><?php esc_html_e('Cor de destaque', 'plugins-alpha'); ?></label></th>
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
          <label for="pga_st_duration"><?php esc_html_e('Tempo por página (s)', 'plugins-alpha'); ?></label>
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
          <label><input type="radio" name="pga_settings[stories][ga_mode]" value="auto" <?php checked($mode, 'auto');   ?>> <?php esc_html_e('Auto', 'plugins-alpha'); ?></label><br>
          <label><input type="radio" name="pga_settings[stories][ga_mode]" value="manual" <?php checked($mode, 'manual'); ?>> <?php esc_html_e('Manual', 'plugins-alpha'); ?></label><br>
          <label><input type="radio" name="pga_settings[stories][ga_mode]" value="off" <?php checked($mode, 'off');    ?>> <?php esc_html_e('Desativado', 'plugins-alpha'); ?></label>
        </td>
      </tr>
      <tr>
        <th scope="row"><label for="pga_ga_manual_id"><?php esc_html_e('GA4 Measurement ID (Manual)', 'plugins-alpha'); ?></label></th>
        <td>
          <input name="pga_settings[stories][ga_manual_id]" id="pga_ga_manual_id" type="text" class="regular-text" placeholder="G-XXXXXXXXXX" value="<?php echo esc_attr($st['ga_manual_id'] ?? ''); ?>">
          <p class="description"><?php esc_html_e('Usado apenas se “Manual” estiver selecionado.', 'plugins-alpha'); ?></p>
        </td>
      </tr>
    </table>

    <h2 class="title"><?php esc_html_e('Imagens / IA para Stories', 'plugins-alpha'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th scope="row">
          <label for="pga_st_text_provider"><?php esc_html_e('IA para geração de TEXTO dos stories', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <select id="pga_st_text_provider" name="pga_settings[stories][text_provider]">
            <option value="openai" <?php selected($text_provider, 'openai'); ?>>OpenAI</option>
            <option value="gemini" <?php selected($text_provider, 'gemini'); ?>>Gemini</option>
            <option value="claude" <?php selected($text_provider, 'claude'); ?>>Claude</option>
            <option value="mistral" <?php selected($text_provider, 'mistral'); ?>>Mistral</option>
            <option value="cohere" <?php selected($text_provider, 'cohere'); ?>>Cohere</option>
            <option value="perplexity" <?php selected($text_provider, 'perplexity'); ?>>Perplexity</option>
          </select>
          <p class="description">
            <?php esc_html_e('Usada para gerar as páginas de Web Stories (texto).', 'plugins-alpha'); ?>
          </p>
        </td>
      </tr>

      <tr>
        <th scope="row">
          <label for="pga_st_img_provider"><?php esc_html_e('Provedor para IMAGENS dos stories', 'plugins-alpha'); ?></label>
        </th>
        <td>
          <select id="pga_st_img_provider" name="pga_settings[stories][images_provider]">
            <option value="pollinations" <?php selected($images_provider, 'pollinations'); ?>>Pollinations (IA grátis)</option>
            <option value="openai" <?php selected($images_provider, 'openai'); ?>>OpenAI (DALL·E)</option>
            <option value="pexels" <?php selected($images_provider, 'pexels'); ?>>Pexels</option>
            <option value="unsplash" <?php selected($images_provider, 'unsplash'); ?>>Unsplash</option>
            <option value="none" <?php selected($images_provider, 'none'); ?>><?php esc_html_e('Não gerar imagens automáticas', 'plugins-alpha'); ?></option>
          </select>
        </td>
      </tr>
    </table>
<?php
  }
}
