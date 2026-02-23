<?php
if (!defined('ABSPATH')) exit;
global $post;

// Metas e campos
$pages      = get_post_meta($post->ID, '_alpha_storys_pages', true);
$pages      = is_array($pages) ? $pages : [];

$alpha_storys_publisher   = get_post_meta($post->ID, '_alpha_storys_publisher', true) ?: (alpha_opt('publisher_name') ?: get_bloginfo('name'));

$alpha_logo_id  = (int) get_post_meta($post->ID, '_alpha_storys_logo_id', true);
$plugins_alpha_default_logo_id = (int) PluginsAlpha_Helpers::stories_logo_id();

$effective_logo_id = $alpha_logo_id ?: $plugins_alpha_default_logo_id;

$alpha_logo_src = $effective_logo_id
  ? (wp_get_attachment_image_url($effective_logo_id, 'thumbnail') ?: '')
  : (PluginsAlpha_Helpers::stories_logo_url() ?: '');


$alpha_ga_id      = PluginsAlpha_Helpers::alpha_get_ga4_id();
$alpha_ga_enable  = !empty($alpha_ga_id);

// Playback: meta do post > default das configurações > fallback hardcoded
$alpha_meta_autoplay = get_post_meta($post->ID, '_alpha_storys_autoplay', true);

// default global das configs de stories (1 = ligado)
$opt_autoplay = (int) PluginsAlpha_Helpers::alpha_opt('autoplay', 1);

if ($alpha_meta_autoplay === '' || $alpha_meta_autoplay === null) {
  // se o post não tiver meta, usa o global
  $plugins_alpha_autoplay = !empty($opt_autoplay);
} else {
  // se tiver meta, respeita o que está salvo no post
  $plugins_alpha_autoplay = (bool) $alpha_meta_autoplay;
}

// duração: meta > config stories > fallback
$meta_seconds = (int) get_post_meta($post->ID, '_alpha_storys_duration', true);
$opt_seconds  = (int) PluginsAlpha_Helpers::alpha_opt('duration', 7);

if ($meta_seconds > 0) {
  $seconds = $meta_seconds;
} elseif ($opt_seconds > 0) {
  $seconds = $opt_seconds;
} else {
  $seconds = 7;
}

$poster_id  = get_post_thumbnail_id($post->ID); // Poster obrigatório
$poster     = $poster_id ? wp_get_attachment_image_url($poster_id, 'storys_poster') : '';

if (!$poster) {
  foreach ($pages as $p) {
    $p = (array) $p;

    $img_id = !empty($p['image_id']) ? (int) $p['image_id'] : 0;
    $url    = '';

    if ($img_id) {
      // usa o size especial de poster (3:4)
      $url = wp_get_attachment_image_url($img_id, 'alpha_storys_poster');
    } elseif (!empty($p['image'])) {
      // legado: usa URL antiga
      $url = esc_url($p['image']);
    }

    if ($url) {
      $poster = $url;
      break;
    }
  }
}

if (!$poster) {
  $poster = get_stylesheet_directory_uri() . '/assets/story-poster-fallback.jpg';
}

// Ao menos 1 página
if (count($pages) === 0) {
  $pages[] = [
    'heading' => get_the_title($post),
    'body'   => '',
    'image'  => '',
    'cta_text' => '',
    'cta_url' => ''
  ];
}

// cores e estilo: meta do post > configs stories > fallback

$style = get_post_meta($post->ID, '_alpha_storys_style', true);
if (!$style) {
  $style = PluginsAlpha_Helpers::alpha_opt('default_style', 'clean');
}

$font = get_post_meta($post->ID, '_alpha_storys_font', true);
if (!$font) {
  $font = PluginsAlpha_Helpers::alpha_opt('default_font', 'inter');
}

$bg_color = (string) get_post_meta($post->ID, '_alpha_storys_background_color', true);
if ($bg_color === '') {
  $bg_color = (string) PluginsAlpha_Helpers::stories_opt('background_color', '#000000');
}

$txt_color = (string) get_post_meta($post->ID, '_alpha_storys_text_color', true);
if ($txt_color === '') {
  $txt_color = (string) PluginsAlpha_Helpers::stories_opt('text_color', '#ffffff');
}

$accent = (string) get_post_meta($post->ID, '_alpha_storys_accent_color', true);
if ($accent === '') {
  $accent = (string) PluginsAlpha_Helpers::stories_opt('accent_color', '#ffffff');
}


// Mapeia Google Fonts
function alpha_font_href($font)
{
  switch ($font) {
    case 'inter':
      return 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap';
    case 'poppins':
      return 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;800&display=swap';
    case 'merriweather':
      return 'https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700;900&display=swap';
    case 'plusjakarta':
      return 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;900&display=swap';
    default:
      return '';
  }
}
$font_href = alpha_font_href($font);

// Classe do estilo
$style_class = 'style-' . preg_replace('/[^a-z0-9\-]/i', '', $style);
// Família CSS
$font_family = $font === 'system'
  ? "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu,'Helvetica Neue',Arial,'Noto Sans',sans-serif"
  : ($font === 'merriweather'
    ? "'Merriweather',serif"
    : ($font === 'poppins' ? "'Poppins',sans-serif" : "'Inter',sans-serif"));
?>
<!doctype html>
<html amp lang="<?php echo esc_attr(get_bloginfo('language')); ?>">

<head>
  <meta charset="utf-8">
  <title><?php echo esc_html(get_the_title($post)); ?></title>
  <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
  <?php
  // ===== JSON-LD para Web Stories (Article + AmpStory) =====
  $permalink     = get_permalink($post);
  $headline      = get_the_title($post);
  $description   = has_excerpt($post)
    ? wp_strip_all_tags(get_the_excerpt($post))
    : wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post)), 35, '…');
  $datePublished = get_post_time('c', true, $post);
  $dateModified  = get_post_modified_time('c', true, $post);

  // Imagens (poster + primeiras imagens das páginas)
  $images = [];
  if (!empty($poster_id)) {
    if ($src = wp_get_attachment_image_src($poster_id, 'full')) {
      $images[] = [
        '@type'  => 'ImageObject',
        'url'    => $src[0],
        'width'  => (int) $src[1],
        'height' => (int) $src[2],
      ];
    }
  } elseif (!empty($poster)) {
    $images[] = $poster; // fallback simples
  }

  if (!empty($pages) && is_array($pages)) {
    foreach ($pages as $p) {
      if (!empty($p['image'])) {
        $images[] = ['@type' => 'ImageObject', 'url' => esc_url($p['image'])];
      }
    }
  }
  // Remove duplicadas mantendo estrutura
  $images = array_values(array_unique($images, SORT_REGULAR));

  // Autor
  $author = [
    '@type' => 'Person',
    'name'  => get_the_author_meta('display_name', $post->post_author),
    'url'   => get_author_posts_url($post->post_author),
  ];

  // Publisher + logo
  $publisher_logo = null;
  if (!empty($alpha_logo_id) && ($lsrc = wp_get_attachment_image_src($alpha_logo_id, 'full'))) {
    $publisher_logo = [
      '@type'  => 'ImageObject',
      'url'    => $lsrc[0],
      'width'  => (int) $lsrc[1],
      'height' => (int) $lsrc[2],
    ];
  } elseif (!empty($alpha_logo_src)) {
    $publisher_logo = ['@type' => 'ImageObject', 'url' => $alpha_logo_src];
  }
  
  $publisher_data = [
    '@type' => 'Organization',
    'name'  => $alpha_storys_publisher,
  ];
  if ($publisher_logo) $publisher_data['logo'] = $publisher_logo;

  // Monta o Article (+ AmpStory opcional)
  $schema = [
    '@context'          => 'https://schema.org',
    '@type'             => ['Article', 'AmpStory'],
    'mainEntityOfPage'  => ['@type' => 'WebPage', '@id' => $permalink],
    'headline'          => wp_strip_all_tags($headline),
    'description'       => $description,
    'image'             => $images,
    'datePublished'     => $datePublished,
    'dateModified'      => $dateModified,
    'author'            => $author,
    'publisher'         => $publisher_data,
  ];
  ?>
  <script type="application/ld+json">
    <?php echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
  </script>

  <link rel="canonical" href="<?php echo esc_url(get_permalink($post)); ?>">

  <?php if ($font_href) :
    // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
  ?>
    <link rel="stylesheet" href="<?php echo esc_url($font_href); ?>">
  <?php
  // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
  endif; ?>


  <style amp-boilerplate>
    body {
      -webkit-animation: -amp-start 8s steps(1, end) 0s 1 normal both;
      -moz-animation: -amp-start 8s steps(1, end) 0s 1 normal both;
      -ms-animation: -amp-start 8s steps(1, end) 0s 1 normal both;
      animation: -amp-start 8s steps(1, end) 0s 1 normal both
    }

    @-webkit-keyframes -amp-start {
      from {
        visibility: hidden
      }

      to {
        visibility: visible
      }
    }

    @-moz-keyframes -amp-start {
      from {
        visibility: hidden
      }

      to {
        visibility: visible
      }
    }

    @-ms-keyframes -amp-start {
      from {
        visibility: hidden
      }

      to {
        visibility: visible
      }
    }

    @-o-keyframes -amp-start {
      from {
        visibility: hidden
      }

      to {
        visibility: visible
      }
    }

    @keyframes -amp-start {
      from {
        visibility: hidden
      }

      to {
        visibility: visible
      }
    }
  </style>
  <noscript>
    <style amp-boilerplate>
      body {
        -webkit-animation: none;
        -moz-animation: none;
        -ms-animation: none;
        animation: none
      }
    </style>
  </noscript>

  <!-- AMP scripts: apenas UMA vez cada -->
  <?php // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript	
  ?>
  <script async src="https://cdn.ampproject.org/v0.js"></script>
  <script async custom-element="amp-story" src="https://cdn.ampproject.org/v0/amp-story-1.0.js"></script>

  <?php if ($alpha_ga_enable) : ?>
    <script async custom-element="amp-analytics" src="https://cdn.ampproject.org/v0/amp-analytics-0.1.js"></script>
    <script async custom-element="amp-story-auto-analytics" src="https://cdn.ampproject.org/v0/amp-story-auto-analytics-0.1.js"></script>
  <?php endif; ?>
  <?php // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript	
  ?>

  <style amp-custom>
    /* Fonte e estilos base */
    <?php // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>amp-story {
      font-family: <?php echo $font_family; ?>;
    }

    <?php // phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped 
    ?>.pad {
      padding: 24px
    }

    .h2 {
      font-size: 26px;
      line-height: 1.1;
      color: #fff;
      margin: 0 0 10px;
      padding-left: 15px;
      border-left: 3px solid <?php echo esc_html($accent); ?>;
    }

    .p {
      font-size: 18px;
      color: #fff;
      margin: 0;
    }

    .btn {
      display: inline-block;
      padding: 12px 20px;
      color: #000;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 700;
      box-shadow: 0 4px 24px rgba(0, 0, 0, .35)
    }

    .bg {
      width: 100%;
      height: 100%;
      background: <?php echo esc_html($bg_color); ?> center / cover no-repeat;
    }

    .overlay {
      position: absolute;
      top: 0;
      right: 0;
      bottom: 0;
      left: 0;
      background: linear-gradient(180deg, rgba(0, 0, 0, .35), rgba(0, 0, 0, .55))
    }

    /* CLEAN - texto central com imagem de fundo */
    .style-clean .layer-content {
      align-content: end;
      justify-content: center;
      text-align: left;
      padding-bottom: 120px;
    }

    /* garante que a layer possa receber o pseudo-elemento */
    .style-clean .layer-content {
      position: relative;
    }

    /* overlay de gradiente: transparente no topo e preto no rodapé */
    .style-clean .layer-content::before {
      content: "";
      position: absolute;
      inset: 0;
      pointer-events: none;
      background: linear-gradient(to bottom, rgba(0, 0, 0, 0) 46%, rgba(0, 0, 0, 0.5) 64%, rgba(0, 0, 0, .8) 90%);
      z-index: -1;
    }

    /* DARK-LEFT - overlay e texto à esquerda */
    .style-dark-left .layer-content {
      align-content: center;
      justify-content: center;
      text-align: left;
      padding: 40px;
    }

    .style-dark-left .overlay {
      background: linear-gradient(120deg, rgba(0, 0, 0, .65), rgba(0, 0, 0, .2) 60%);
    }

    .style-dark-left .h2,
    .style-dark-left .p {
      text-shadow: 0 4px 30px rgba(0, 0, 0, .8);
    }

    /* CARD - imagem em cartão, texto abaixo */
    .style-card .card {
      width: 78%;
      max-width: 820px;
      height: 260px;
      border-radius: 24px;
      overflow: hidden;
      background: #111 center / cover no-repeat;
      box-shadow: 0 4px 24px rgba(0, 0, 0, .35);
      margin: 0 auto 18px auto;
    }

    amp-story-grid-layer {
      border-bottom: 5px solid <?php echo esc_html($accent); ?>;
    }

    .style-card .layer-content {
      align-content: end;
      justify-content: end;
      text-align: center;
      padding: 24px;
    }

    /* SPLIT - imagem esquerda, texto direita */
    .style-split .split {
      display: flex;
      align-items: center;
      height: 100%;
      padding: 24px;
    }

    .style-split .split .left {
      width: 45%;
      height: 80%;
      border-radius: 20px;
      background: #111 center / cover no-repeat;
      box-shadow: 0 4px 24px rgba(0, 0, 0, .35);
      margin-right: 24px;
    }

    .style-split .split .right {
      flex: 1;
      color: #fff;
    }

    .style-split .right .h2 {
      margin-bottom: 12px;
    }

    .h2,
    .p {
      color: <?php echo esc_html($txt_color); ?>;
    }

    /* Fundo desfocado tipo Web Stories plugin */
    .bg-blur {
      filter: blur(22px) saturate(1.1);
      transform: scale(1.08);
    }

    /* TOP — imagem no topo (full width), borda arredondada embaixo; textos abaixo centralizados (container), alinhados à esquerda */
    .style-top .bg-solid {
      position: absolute;
      inset: 0;
      background: <?php echo esc_html($bg_color); ?>;
    }

    .style-top .layer-content-top {
      align-content: start;
      justify-content: start;
      padding-top: 0;
    }

    .style-top .hero img {
      object-fit: cover;
    }

    .style-top .hero {
      position: relative;
      width: 100%;
      height: 56vh;
      /* altura “razoável” */
      max-height: 65%;
      overflow: hidden;
      object-fit: cover;
      border-radius: 0 0 12px 12px;
    }

    .style-top .content {
      width: 100%;
      padding: 18px 0 0;
    }

    .style-top .content-inner {
      width: 86%;
      max-width: 820px;
      margin: 0 auto;
      /* centraliza o container */
      text-align: left;
      /* mas textos alinhados à esquerda */
    }

    /* opcional: você já tem .overlay; ela escurece por cima do blur */
    .brand {
      position: absolute;
      z-index: 10;
    }

    .brand .logo {
      width: 36px;
      height: 36px;
      overflow: hidden;
    }

    .brand .logo amp-img,
    .brand .logo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .brand .name {
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .2px;
      color: <?php echo esc_html($txt_color); ?>;
      text-shadow: 0 6px 24px rgba(0, 0, 0, .45);
      max-width: 210px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  </style>
</head>

<body>
  <amp-story
    standalone
    class="<?php echo esc_attr($style_class); ?>"
    title="<?php echo esc_attr(get_the_title($post)); ?>"
    publisher="<?php echo esc_attr($alpha_storys_publisher); ?>"
    publisher-logo-src="<?php echo esc_url($alpha_logo_src); ?>"
    poster-portrait-src="<?php echo esc_url($poster); ?>">
    <?php if ($alpha_ga_enable): ?>
      <amp-story-auto-analytics gtag-id="<?php echo esc_attr($alpha_ga_id); ?>"></amp-story-auto-analytics>
    <?php endif;



    $i = 1;
    foreach ($pages as $p):
      $p = array_merge([
        'image'     => '',
        'image_id'  => 0,
        'heading'   => '',
        'body'      => '',
        'cta_url'   => '',
        'cta_text'  => '',
        'cta_type'  => '',
        'cta_icon'  => '',
        'duration'  => null,
      ], (array) $p);

      // === IMAGEM DO SLIDE (usa sizes especiais) ==========================
      $img_id = (int) ($p['image_id'] ?? 0);
      $img    = '';

      // define o size default por estilo
      $size = 'alpha_storys_slide'; // vertical 9:16 padrão

      switch ($style) {
        case 'top':
          // hero no topo, pode ser 3:4 sem problema
          $size = 'alpha_storys_poster';
          break;

        case 'card':
        case 'split':
        case 'dark-left':
        default:
          $size = 'alpha_storys_slide';
          break;
      }

      // 1) Se tiver image_id, usa attachment + size
      if ($img_id) {
        $img = wp_get_attachment_image_url($img_id, $size);

        // fallback se precisar
        if (!$img) {
          $img = wp_get_attachment_image_url($img_id, 'alpha_storys_slide')
            ?: wp_get_attachment_image_url($img_id, 'alpha_storys_poster');
        }
      }

      // 2) Compat com stories antigos que só tinham 'image' (URL pura)
      if (!$img && !empty($p['image'])) {
        $att_id = attachment_url_to_postid($p['image']);
        if ($att_id) {
          $img = wp_get_attachment_image_url($att_id, $size)
            ?: wp_get_attachment_image_url($att_id, 'alpha_storys_slide')
            ?: wp_get_attachment_image_url($att_id, 'alpha_storys_poster');
        }

        // último fallback: se ainda não achou attachment, aí sim usa a URL original
        if (!$img) {
          $img = esc_url($p['image']);
        }
      }

      $dur = $p['duration'] ? (int)$p['duration'] : (int)$seconds;

      // CTA (fallback = swipe)
      $cta_url  = !empty($p['cta_url'])  ? esc_url($p['cta_url']) : '';
      $cta_text = !empty($p['cta_text']) ? esc_html($p['cta_text']) : 'Saiba mais';
      $cta_type = !empty($p['cta_type']) ? $p['cta_type'] : ($cta_url ? 'swipe' : '');
      $cta_icon = !empty($p['cta_icon']) ? esc_url($p['cta_icon']) : '';
      $is_first = ($i === 1);
      if ($is_first && $cta_type === 'button') $cta_type = 'swipe';

      // Animações só a partir do 2º slide
      $anim = ($i > 1);
      // presets por estilo:
      $anim_card_div = $anim ? ' animate-in="fly-in-right" animate-in-delay="0s" animate-in-duration="350ms" animate-in-timing-function="ease-out"' : '';
      $anim_h2_clean = $anim ? ' animate-in="fly-in-bottom" animate-in-delay="0.08s" animate-in-duration="360ms" animate-in-timing-function="ease-out"' : '';
      $anim_p_clean  = $anim ? ' animate-in="fade-in"      animate-in-delay="0.20s" animate-in-duration="360ms" animate-in-timing-function="ease-out"' : '';

      $anim_left_split = $anim ? ' animate-in="fly-in-left"  animate-in-delay="0s"    animate-in-duration="360ms" animate-in-timing-function="ease-out"' : '';
      $anim_h2_split   = $anim ? ' animate-in="fade-in"       animate-in-delay="0.12s" animate-in-duration="360ms" animate-in-timing-function="ease-out"' : '';
      $anim_p_split    = $anim ? ' animate-in="fly-in-bottom" animate-in-delay="0.22s" animate-in-duration="360ms" animate-in-timing-function="ease-out"' : '';
    ?>

      <amp-story-page
        id="p<?php echo (int)$i; ?>"
        <?php if ($plugins_alpha_autoplay): ?>auto-advance-after="<?php echo (int)$dur; ?>s" <?php endif; ?>>

        <?php if ($style === 'card'): ?>
          <amp-story-grid-layer template="fill">
            <?php if ($img): ?>
              <amp-img layout="fill" src="<?php echo esc_attr($img); ?>" alt=""></amp-img>
            <?php else: ?>
              <div class="bg"></div>
            <?php endif; ?>
            <div class="overlay"></div>
          </amp-story-grid-layer>

          <amp-story-grid-layer template="vertical" class="layer-content">
            <div class="card"
              <?php if ($img): ?>style="background-image:url('<?php echo esc_url($img); ?>');" <?php endif; ?>
              <?php echo esc_attr($anim_card_div); ?>></div>

            <?php if (!empty($p['heading'])): ?>
              <h2 class="h2"><?php echo esc_html($p['heading']); ?></h2>
            <?php endif; ?>
            <?php if (!empty($p['body'])): ?>
              <p class="p"><?php echo esc_html($p['body']); ?></p>
            <?php endif; ?>
          </amp-story-grid-layer>

        <?php elseif ($style === 'top'): ?>
          <amp-story-grid-layer template="fill">
            <div class="bg-solid"></div>
          </amp-story-grid-layer>

          <amp-story-grid-layer template="vertical" class="layer-content-top" style="padding:0;display:block">
            <div class="hero" <?php echo esc_attr($anim_card_div); ?>>
              <?php if ($img): ?>
                <amp-img layout="fill" src="<?php echo esc_url($img); ?>" alt=""></amp-img>
              <?php endif; ?>
            </div>

            <div class="content">
              <div class="content-inner">
                <?php if (!empty($p['heading'])): ?>
                  <h2 class="h2"><?php echo esc_html($p['heading']); ?></h2>
                <?php endif; ?>
                <?php if (!empty($p['body'])): ?>
                  <p class="p"><?php echo esc_html($p['body']); ?></p>
                <?php endif; ?>
              </div>
            </div>
          </amp-story-grid-layer>

        <?php elseif ($style === 'split'): ?>
          <amp-story-grid-layer template="fill">
            <?php if ($img): ?>
              <amp-img layout="fill" src="<?php echo esc_url($img); ?>" alt=""></amp-img>
            <?php else: ?>
              <div class="bg"></div>
            <?php endif; ?>
            <div class="overlay"></div>
          </amp-story-grid-layer>

          <amp-story-grid-layer template="vertical">
            <div class="split">
              <div class="left"
                <?php if ($img): ?>style="background-image:url('<?php echo esc_url($img); ?>');" <?php endif; ?>
                <?php echo esc_attr($anim_left_split); ?>></div>
              <div class="right">
                <?php if (!empty($p['heading'])): ?>
                  <h2 class="h2"><?php echo esc_html($p['heading']); ?></h2>
                <?php endif; ?>
                <?php if (!empty($p['body'])): ?>
                  <p class="p"><?php echo esc_html($p['body']); ?></p>
                <?php endif; ?>
              </div>
            </div>
          </amp-story-grid-layer>

        <?php else: ?>
          <amp-story-grid-layer template="fill">
            <?php if ($img): ?>
              <amp-img layout="fill" src="<?php echo esc_url($img); ?>" alt=""></amp-img>
            <?php else: ?>
              <div class="bg"></div>
            <?php endif; ?>
            <?php if ($style === 'dark-left'): ?><div class="overlay"></div><?php endif; ?>
          </amp-story-grid-layer>

          <amp-story-grid-layer template="vertical" class="layer-content pad">
            <?php if (!empty($p['heading'])): ?>
              <h2 class="h2"><?php echo esc_html($p['heading']); ?></h2>
            <?php endif; ?>
            <?php if (!empty($p['body'])): ?>
              <p class="p"><?php echo esc_html($p['body']); ?></p>
            <?php endif; ?>
          </amp-story-grid-layer>
        <?php endif; ?>
        <?php if ($alpha_logo_src): ?>
          <amp-story-grid-layer template="fill">
            <div class="brand" style="padding-top: 30px; padding-left: 18px;">
              <div class="logo">
                <amp-img
                  src="<?php echo esc_url($alpha_logo_src); ?>"
                  width="36"
                  height="36"
                  layout="responsive"
                  alt="<?php echo esc_attr($alpha_storys_publisher); ?>">
                </amp-img>
              </div>
            </div>
          </amp-story-grid-layer>
        <?php endif; ?>
        <!-- CTA fica igual -->
        <?php if ($cta_url): ?>
          <?php if ($cta_type === 'button' && !$is_first): ?>
            <amp-story-cta-layer>
              <a class="btn"
                href="<?php echo esc_url($cta_url); ?>"
                target="_blank"
                rel="noreferrer">
                <?php echo esc_html($cta_text); ?>
              </a>
            </amp-story-cta-layer>
          <?php elseif ($cta_type === 'swipe'): ?>
            <amp-story-page-outlink
              layout="nodisplay"
              theme="dark"
              <?php if ($cta_icon): ?>cta-image="<?php echo esc_attr($cta_icon); ?>" <?php endif; ?>>
              <a href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noreferrer"><?php echo esc_html($cta_text); ?></a>
            </amp-story-page-outlink>
          <?php endif; ?>
        <?php endif; ?>
      </amp-story-page>

    <?php
      $i++;
    endforeach;
    // phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

    ?>
  </amp-story>
</body>

</html>