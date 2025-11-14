<?php
// includes/stories/MetaBox.php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Stories_MetaBox
{
  const NONCE = 'alpha_storys_meta_nonce';

  public static function init(): void
  {
    add_action('add_meta_boxes',        [self::class, 'add_box']);
    add_action('save_post_alpha_storys',[self::class, 'save'], 10, 2);
  }

  public static function add_box(): void
  {
    add_meta_box(
      'alpha_storys_sidebar_meta',
      __('Web Story deste conteúdo', 'plugins-alpha'),
      [self::class, 'render'],
      'alpha_storys',
      'side',
      'default'
    );
  }

  public static function render(\WP_Post $post): void
  {
    wp_nonce_field(self::NONCE, self::NONCE);

    // metas (com defaults)
    $enabled     = true; // na tela de alpha_storys SEMPRE habilitado
    $autoplay    = (int)  get_post_meta($post->ID, '_storys_autoplay', true);
    $duration    = (string) get_post_meta($post->ID, '_storys_duration', true) ?: '7';
    $show_ctrl   = (int)  get_post_meta($post->ID, '_storys_show_controls', true);

    $style       = (string) get_post_meta($post->ID, '_storys_style', true) ?: 'clean';
    $font        = (string) get_post_meta($post->ID, '_storys_font',  true) ?: 'plusjakarta';

    $bg_color    = (string) get_post_meta($post->ID, '_storys_background_color', true) ?: '#ffffff';
    $text_color  = (string) get_post_meta($post->ID, '_storys_text_color',       true) ?: '#ffffff';
    $accent      = (string) get_post_meta($post->ID, '_storys_accent_color',     true) ?: '#cc0000';

    $poster_id   = (int) get_post_meta($post->ID, '_storys_poster',          true);
    $logo_id     = (int) get_post_meta($post->ID, '_storys_publisher_logo',  true);

    $poster_url  = $poster_id ? wp_get_attachment_image_url($poster_id, 'full') : '';
    $logo_url    = $logo_id   ? wp_get_attachment_image_url($logo_id,   'full') : '';

    ?>
    <style>
      .alpha-field      { margin-bottom:10px; }
      .alpha-field label{ font-weight:600; display:block; margin-bottom:4px; }
      .alpha-thumb      { display:block; width:100%; max-width:100%; height:auto; margin:6px 0;
                          border:1px solid #eee; border-radius:6px; }
      .alpha-row        { display:flex; gap:8px; align-items:center; }
      .alpha-row > *    { flex:1; }
      .alpha-help       { color:#666; font-size:11px; margin-top:2px; }
      .alpha-muted      { color:#888; font-size:12px; }
      .alpha-sep        { border-top:1px solid #eee; margin:10px 0; }
    </style>

    <!-- Habilitar: não faz sentido nessa tela, então usamos hidden sempre = 1 -->
    <input type="hidden" name="storys_enable" value="1" />

    <div class="alpha-field">
      <label>
        <input type="checkbox" name="storys_autoplay" value="1" checked <?php checked($autoplay, 1); ?>>
        <?php _e('Autoplay', 'plugins-alpha'); ?>
      </label>
    </div>

    <div class="alpha-field">
      <label for="storys_duration"><?php _e('Tempo por página (s)', 'plugins-alpha'); ?></label>
      <select name="storys_duration" id="storys_duration">
        <?php foreach (['5','7','10','12'] as $d): ?>
          <option value="<?php echo esc_attr($d); ?>" <?php selected($duration, $d); ?>>
            <?php echo esc_html($d) ?>s
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="alpha-field">
      <label>
        <input type="checkbox" name="storys_show_controls" value="1" checked <?php checked($show_ctrl, 1); ?>>
        <?php _e('Mostrar botão Play/Pause', 'plugins-alpha'); ?>
      </label>
    </div>

    <div class="alpha-field">
      <label for="storys_style"><?php _e('Preset de estilo', 'plugins-alpha'); ?></label>
      <select name="storys_style" id="storys_style">
        <?php
        $choices = [
          'top'       => 'Image top (imagem no topo)',
          'clean'     => 'Clean (fundo com imagem, texto central)',
          'dark-left' => 'Dark Left (overlay escuro, texto à esquerda)',
          'card'      => 'Card (imagem em cartão, texto abaixo)',
          'split'     => 'Split (imagem esquerda, texto direita)',
        ];
        foreach ($choices as $val => $lab) {
          printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($val),
            selected($style, $val, false),
            esc_html($lab)
          );
        }
        ?>
      </select>
    </div>

    <div class="alpha-field">
      <label for="storys_font"><?php _e('Fonte', 'plugins-alpha'); ?></label>
      <select name="storys_font" id="storys_font">
        <?php
        $fonts = [
          'system'       => 'System UI',
          'inter'        => 'Inter',
          'poppins'      => 'Poppins',
          'merriweather' => 'Merriweather',
          'plusjakarta'  => 'Plus Jakarta Sans',
        ];
        foreach ($fonts as $val => $lab) {
          printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($val),
            selected($font, $val, false),
            esc_html($lab)
          );
        }
        ?>
      </select>
    </div>

    <div class="alpha-row">
      <div class="alpha-field">
        <label><?php _e('Cor de fundo', 'plugins-alpha'); ?></label>
        <input type="color" class="alpha-color" name="storys_background_color"
               value="<?php echo esc_attr($bg_color); ?>">
      </div>
      <div class="alpha-field">
        <label><?php _e('Cor do texto', 'plugins-alpha'); ?></label>
        <input type="color" class="alpha-color" name="storys_text_color"
               value="<?php echo esc_attr($text_color); ?>">
      </div>
    </div>

    <div class="alpha-field">
      <label><?php _e('Cor de destaque', 'plugins-alpha'); ?></label>
      <input type="color" class="alpha-color" name="storys_accent_color"
             value="<?php echo esc_attr($accent); ?>">
    </div>

    <div class="alpha-sep"></div>

    <div class="alpha-field">
      <label><?php _e('Capa (1080x1920)', 'plugins-alpha'); ?></label>
      <img id="alpha_storys_poster_preview" class="alpha-thumb"
           src="<?php echo esc_url($poster_url ?: ''); ?>"
           style="<?php echo $poster_url ? '' : 'display:none'; ?>">
      <input type="hidden" id="storys_poster" name="storys_poster" value="<?php echo (int)$poster_id; ?>">
      <button type="button" class="button" data-alpha-media-target="storys_poster">
        <?php _e('Selecionar imagem', 'plugins-alpha'); ?>
      </button>
      <button type="button" class="button" data-alpha-media-clear="storys_poster" style="margin-left:6px;">
        <?php _e('Remover', 'plugins-alpha'); ?>
      </button>
      <p class="alpha-help"><?php _e('Dica: use imagem vertical 1080x1920', 'plugins-alpha'); ?></p>
    </div>

    <div class="alpha-field">
      <label><?php _e('Logo do Publisher (96x96)', 'plugins-alpha'); ?></label>
      <img id="alpha_storys_logo_preview" class="alpha-thumb"
           src="<?php echo esc_url($logo_url ?: ''); ?>"
           style="<?php echo $logo_url ? '' : 'display:none'; ?>">
      <input type="hidden" id="storys_publisher_logo" name="storys_publisher_logo"
             value="<?php echo (int)$logo_id; ?>">
      <button type="button" class="button" data-alpha-media-target="storys_publisher_logo">
        <?php _e('Selecionar imagem', 'plugins-alpha'); ?>
      </button>
      <button type="button" class="button" data-alpha-media-clear="storys_publisher_logo" style="margin-left:6px;">
        <?php _e('Remover', 'plugins-alpha'); ?>
      </button>
    </div>

    <p class="alpha-muted">
      <?php _e('Ao salvar/atualizar, as páginas da story são geradas a partir do conteúdo (H2 e separadores &lt;hr&gt;).', 'plugins-alpha'); ?>
    </p>
    <?php
  }

  public static function save(int $post_id, \WP_Post $post): void
  {
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
    if ($post->post_type !== 'alpha_storys') return;
    if (!isset($_POST[self::NONCE]) || !wp_verify_nonce($_POST[self::NONCE], self::NONCE)) return;

    // sempre habilitado nessa tela
    $enabled   = 1;
    $autoplay  = !empty($_POST['storys_autoplay']) ? 1 : 0;
    $duration  = isset($_POST['storys_duration']) ? (string) $_POST['storys_duration'] : '7';
    $show_ctrl = !empty($_POST['storys_show_controls']) ? 1 : 0;

    $style     = isset($_POST['storys_style']) ? sanitize_text_field($_POST['storys_style']) : 'clean';
    $font      = isset($_POST['storys_font'])  ? sanitize_text_field($_POST['storys_font'])  : 'plusjakarta';

    $bg        = isset($_POST['storys_background_color']) ? sanitize_text_field($_POST['storys_background_color']) : '#ffffff';
    $txt       = isset($_POST['storys_text_color'])       ? sanitize_text_field($_POST['storys_text_color'])       : '#ffffff';
    $accent    = isset($_POST['storys_accent_color'])     ? sanitize_text_field($_POST['storys_accent_color'])     : '#cc0000';

    $poster_id = isset($_POST['storys_poster']) ? (int) $_POST['storys_poster'] : 0;
    $logo_id   = isset($_POST['storys_publisher_logo']) ? (int) $_POST['storys_publisher_logo'] : 0;

    update_post_meta($post_id, '_storys_enable',           $enabled);
    update_post_meta($post_id, '_storys_autoplay',         $autoplay);
    update_post_meta($post_id, '_storys_duration',         in_array($duration, ['5','7','10','12'], true) ? $duration : '7');
    update_post_meta($post_id, '_storys_show_controls',    $show_ctrl);

    update_post_meta($post_id, '_storys_style',            $style);
    update_post_meta($post_id, '_storys_font',             $font);

    update_post_meta($post_id, '_storys_background_color', $bg);
    update_post_meta($post_id, '_storys_text_color',       $txt);
    update_post_meta($post_id, '_storys_accent_color',     $accent);

    update_post_meta($post_id, '_storys_poster',           $poster_id);
    update_post_meta($post_id, '_storys_publisher_logo',   $logo_id);

    // gera/atualiza páginas a partir do próprio conteúdo
    if ($enabled) {
      $pages = alpha_build_storys_pages_from_content($post->post_content);
      if (!empty($pages)) {
        $publisher = get_bloginfo('name');

        // neste fluxo, o próprio $post_id é a story final
        $storys_id = $post_id;

        if ($poster_id) {
          set_post_thumbnail($storys_id, $poster_id);
        }

        update_post_meta($storys_id, '_alpha_storys_source_post', $post_id);
        update_post_meta($storys_id, '_alpha_storys_pages',       $pages);
        update_post_meta($storys_id, '_alpha_storys_publisher',   sanitize_text_field($publisher));
        update_post_meta($storys_id, '_alpha_storys_logo_id',     $logo_id);

        // estilos / cores / playback replicados com prefixo _alpha_
        update_post_meta($storys_id, '_alpha_storys_background_color', $bg);
        update_post_meta($storys_id, '_alpha_storys_text_color',       $txt);
        update_post_meta($storys_id, '_alpha_storys_accent_color',     $accent);
        update_post_meta($storys_id, '_alpha_storys_style',            $style);
        update_post_meta($storys_id, '_alpha_storys_font',             $font);
        update_post_meta($storys_id, '_alpha_storys_autoplay',         $autoplay);
        update_post_meta($storys_id, '_alpha_storys_duration',         in_array($duration, ['5','7','10','12'], true) ? $duration : '7');
      }
    }
  }
}
