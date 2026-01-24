<?php

/**
 * Template: AMP Web Story (ws_generator)
 * Arquivo: single-ws_generator.php (ajuste conforme seu post_type)
 */
if (!defined('ABSPATH')) exit;

global $post;
if (!($post instanceof WP_Post)) exit;

$post_id = (int) $post->ID;

$opts = PluginsAlpha_Settings::get();
$st = $opts['stories'] ?? [];

// ====== metas que você já tem ======
$meta_title = (string) get_post_meta($post_id, PluginsAlpha_REST_Ws_Generator::META_TITLE, true); // ajuste para sua constante real (META_TITLE)
$meta_desc  = (string) get_post_meta($post_id, PluginsAlpha_REST_Ws_Generator::META_DESC, true);  // ajuste para sua constante real (META_DESC)
$accent     = (string) get_post_meta($post_id, PluginsAlpha_REST_Ws_Generator::META_ACCENT, true);     // META_ACCENT
$textc      = (string) get_post_meta($post_id, PluginsAlpha_REST_Ws_Generator::META_TEXT_COLOR, true); // META_TEXT_COLOR
$theme      = (string) get_post_meta($post_id, PluginsAlpha_REST_Ws_Generator::META_THEME, true);      // META_THEME
$slides     = (array)  get_post_meta($post_id, PluginsAlpha_REST_Ws_Generator::META_SLIDES, true);     // META_SLIDES (array)
$logo_id    = (int)    get_post_meta($post_id, PluginsAlpha_REST_Ws_Generator::META_LOGO_ID, true);    // META_LOGO_ID
$poster_id  = (int)    get_post_meta($post_id, PluginsAlpha_REST_Ws_Generator::META_POSTER_ID, true);  // META_POSTER_ID

// ====== novos settings (se já tiver, ótimo; se não, ficam defaults) ======
$publisher_name = $st['publisher_name'] ?? [];
$bg_color       = $st['background_color'] ?? [];
$autoplay_on    = $st['autoplay'] ?? [];
$page_duration  = $st['duration'] ?? [];
$font_family    = $st['default_font'] ?? [];
$style_preset   = $st['default_style'] ?? [];

// ====== defaults ======
$title = trim($meta_title) !== '' ? $meta_title : (get_the_title($post_id) ?: 'Web Story');
$desc  = trim($meta_desc) !== '' ? $meta_desc : '';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) ? $accent : '#3B82F6';
$textc  = preg_match('/^#[0-9a-fA-F]{6}$/', $textc) ? $textc : '#FFFFFF';
$bg_color = preg_match('/^#[0-9a-fA-F]{6}$/', $bg_color) ? $bg_color : '#0b1220';

$theme = $theme !== '' ? $theme : 'theme-normal'; // theme-normal/news/dark/soft/pop
$publisher_name = trim($publisher_name) !== '' ? $publisher_name : get_bloginfo('name');

$autoplay_on = $autoplay_on ? 1 : 0;
$page_duration = $page_duration > 0 ? $page_duration : 7; // segundos

// ====== urls de mídia ======
$logo_url = $logo_id ? (wp_get_attachment_image_url($logo_id, 'full') ?: '') : '';
$poster_url = $poster_id ? (wp_get_attachment_image_url($poster_id, 'full') ?: '') : '';

if ($poster_url === '') {
    // fallback: thumbnail do post
    $thumb_id = (int) get_post_thumbnail_id($post_id);
    if ($thumb_id) $poster_url = wp_get_attachment_image_url($thumb_id, 'full') ?: '';
}

$alpha_ga_id      = PluginsAlpha_Helpers::alpha_get_ga4_id();
$alpha_ga_enable  = !empty($alpha_ga_id);

// ====== slides: garantir array ======
if (!is_array($slides)) $slides = [];
$slides = array_values(array_filter($slides, fn($s) => is_array($s)));

// Se não tem slide, evita erro
if (empty($slides)) {
    $slides[] = [
        'index' => 0,
        'heading' => $title,
        'body' => $desc,
        'cta_text' => '',
        'cta_url' => '',
        'template' => 'template-1',
        'image_id' => $poster_id,
        'image_url' => $poster_url,
    ];
}

// ====== helpers ======
function pga_ws_esc($s)
{
    return esc_html((string)$s);
}
function pga_ws_url($s)
{
    return esc_url((string)$s);
}

function pga_ws_image_url_from_slide(array $pg): string
{
    $img = '';
    if (!empty($pg['image_url'])) $img = (string)$pg['image_url'];
    if ($img === '' && !empty($pg['image_id'])) {
        $img = wp_get_attachment_image_url((int)$pg['image_id'], 'full') ?: '';
    }
    return $img;
}

function pga_ws_page_auto_advance_attr(int $i, int $autoplay_on, int $page_duration): string
{
    // Se autoplay ligado, coloca auto-advance-after em TODOS os pages.
    // Se desligado, retorna vazio.
    if (!$autoplay_on) return '';
    // Pode variar por página se quiser, aqui é fixo
    return ' auto-advance-after="' . (int)$page_duration . 's"';
}

function pga_ws_animate_attrs(int $i, string $role = 'title'): string
{
    // Slide 1: capa => deixar mais “limpo” (sem animação agressiva)
    if ($i === 0) {
        return '';
    }
    // Slides 2+: animações
    if ($role === 'title') return ' animate-in="fly-in-bottom" animate-in-duration="0.6s"';
    if ($role === 'body')  return ' animate-in="fade-in" animate-in-delay="0.15s" animate-in-duration="0.7s"';
    if ($role === 'cta')   return ' animate-in="fly-in-bottom" animate-in-delay="0.25s" animate-in-duration="0.6s"';
    return ' animate-in="fade-in" animate-in-duration="0.7s"';
}

// fonte segura (AMP): você pode travar em poucas opções
$font_family_css = 'system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
if (stripos($font_family, 'jakarta') !== false) {
    $font_family_css = '"Plus Jakarta Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
}

// canonical
$canonical = get_permalink($post_id);

// Top bar edit link (admin)
$edit_url = '';
if (is_user_logged_in() && current_user_can('edit_post', $post_id)) {
    $edit_url = admin_url('admin.php?page=plugins-alpha-ws-generator&story_id=' . $post_id);
}
// AMP requires full HTML
?>
<!DOCTYPE html>
<html amp lang="<?php echo esc_attr(str_replace('_', '-', get_locale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    <link rel="canonical" href="<?php echo esc_url($canonical); ?>">
    <title><?php echo esc_html($title); ?></title>

    <?php if ($desc !== ''): ?>
        <meta name="description" content="<?php echo esc_attr($desc); ?>">
    <?php endif; ?>

    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <script async custom-element="amp-story" src="https://cdn.ampproject.org/v0/amp-story-1.0.js"></script>

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

    <style amp-custom>
        :root {
            --pga-accent: <?php echo esc_html($accent); ?>;
            --pga-text: <?php echo esc_html($textc); ?>;
            --pga-bg: <?php echo esc_html($bg_color); ?>;
        }

        body {
            font-family: <?php echo $font_family_css; ?>;
            background: var(--pga-bg);
            margin: 0;
            padding: 0;
        }


        /* =========================================================
   TEMPLATE-1
   transparente em cima -> preto em baixo (no final do height)
   ========================================================= */
        .template-1 .pga-overlay-fundo {
            background: linear-gradient(to bottom,
                    rgba(0, 0, 0, 0) 0%,
                    rgba(0, 0, 0, 0) 35%,
                    rgba(0, 0, 0, .85) 100%);
            z-index: 99;
        }

        /* =========================================================
   TEMPLATE-2
   transparente em cima -> preto no centro -> transparente em baixo
   (preto no centro do height)
   ========================================================= */
        .template-2.pga-overlay-fundo,
        .template-2 .pga-overlay-fundo {
            background: linear-gradient(to bottom,
                    rgba(0, 0, 0, 0) 0%,
                    rgba(0, 0, 0, 0) 25%,
                    rgba(0, 0, 0, .78) 50%,
                    rgba(0, 0, 0, 0) 75%,
                    rgba(0, 0, 0, 0) 100%);
            z-index: 99;
        }

        /* =========================================================
   TEMPLATE-3
   preto no topo -> transparente em baixo (no topo da página)
   ========================================================= */
        .template-3.pga-overlay-fundo,
        .template-3 .pga-overlay-fundo {
            background: linear-gradient(to bottom,
                    rgba(0, 0, 0, .85) 0%,
                    rgba(0, 0, 0, .35) 30%,
                    rgba(0, 0, 0, 0) 65%,
                    rgba(0, 0, 0, 0) 100%);
            z-index: 99;
        }

        /* ===== Top bar overlay (não depende do amp-story UI) ===== */
        .pga-topbar {
            position: absolute;
            bottom: 10px;
            left: 10px;
            right: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 9999;
        }

        .pga-pill {
            background: rgba(0, 0, 0, .55);
            color: #fff;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 16px;
            text-decoration: none;
        }

        .pga-pill {
            pointer-events: auto;
            background: rgba(0, 0, 0, .55);
            color: #fff;
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            line-height: 1;
            display: flex;
            gap: 8px;
            align-items: center;
            text-decoration: none;
            backdrop-filter: blur(8px);
        }

        .pga-pill .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--pga-accent);
            display: inline-block;
        }

        .pga-pill:hover {
            background: rgba(0, 0, 0, .7);
        }

        /* ------------------------- */
        /* template igual ao arquivo */
        /* ------------------------- */

        /* frame */
        .pga-ws-story-frame {
            position: relative;
            width: 100%;
            aspect-ratio: 9 / 16;
            border-radius: 10px;
            overflow: hidden;
            padding: 40px 15px;
            box-sizing: border-box;
            display: flex;
            height: 100vh;
            min-height: 100vh;
        }

        /* imagem */
        .pga-frame-img {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            filter: saturate(1.05);
            transform: scale(1.01);
            background-size: cover;
        }

        /* overlay base (muda por skin) */
        .pga-overlay-fundo {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            background: linear-gradient(to top, rgba(0, 0, 0, .55), rgba(0, 0, 0, .12) 55%, rgba(0, 0, 0, 0));
        }

        /* conteúdo (muda por template e skin) */
        .pga-ws-frame-content {
            width: 100%;
            box-sizing: border-box;
            color: #fff;
            position: absolute;
            z-index: 99;
            padding: 35px;
            display: flex;
            flex-direction: column;
            align-items: start;
            gap: 8px;
        }

        .template-1 .pga-ws-frame-content {
            bottom: 100px;
        }

        .pga-ws-frame-title {
            margin: 0 0 8px 0;
            line-height: 1.1;
            font-weight: 600;
            text-wrap: balance;
        }

        .pga-ws-frame-text {
            margin: 0;
            line-height: 1.35;
            font-size: 1rem;
            opacity: .95;
        }

        /* =========================================================
   TEMPLATES (layout/estrutura)
   - template-1: conteúdo em baixo (clássico)
   - template-2: bloco central com card
   - template-3: topo com headline (editorial)
   ========================================================= */

        .template-1 {
            align-items: flex-end;
        }

        .template-2 {
            align-items: center;
        }

        .template-3 {
            align-items: start;
        }

        .pga-overlay-fundo {
            position: absolute;
            height: 100%;
            top: 0;
            left: 0;
            width: 100%;
        }

        .template-2 .pga-overlay-fundo {
            /* overlay mais suave no meio */
            background: radial-gradient(circle at 50% 65%, rgba(0, 0, 0, .55), rgba(0, 0, 0, .12) 55%, rgba(0, 0, 0, 0));
        }

        .template-3 .pga-overlay-fundo {
            background: linear-gradient(to bottom, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.1) 77%, rgba(0, 0, 0, 0));
        }

        .template-3 .pga-ws-frame-content {
            top: 100px;
        }

        .theme-dark .pga-ws-frame-content,
        .theme-soft .pga-ws-frame-content
         {
            transform: translateX(-50%);
            left: 50%;
            width: 90%;
        }

        .template-3 .pga-ws-frame-text {
            opacity: .9;
        }

        /* =========================================================
   SKINS (theme-*)
   A skin altera tipografia, overlays, badge, divider, etc.
   Aplica via: .theme-news ...
   ========================================================= */

        /* -------------------------
   THEME: NORMAL (clean, neutro)
   ------------------------- */
        .theme-normal .pga-ws-story-frame {
            border: 1px solid rgba(0, 0, 0, .08);
        }

        /* -------------------------
   THEME: NEWSROOM (editorial, jornal)
   - mais contraste, "faixa" editorial, título mais sério
   ------------------------- */
        .theme-news .pga-frame-img {
            filter: grayscale(.12) contrast(1.12) saturate(.85);
        }

        .theme-news .pga-overlay-fundo {
            background:
                linear-gradient(to top, rgba(0, 0, 0, .72), rgba(0, 0, 0, .22) 55%, rgba(0, 0, 0, 0)),
                linear-gradient(to bottom, rgba(0, 0, 0, .55), rgba(0, 0, 0, 0) 45%);
        }

        .theme-news .pga-ws-frame-title {
            font-weight: 700;
            letter-spacing: -0.03em;
            text-transform: none;
            color: <? echo $textc ?>;
        }

        .theme-news .pga-ws-frame-text {
            font-size: 1rem;
            opacity: .92;
        }

        .pga-ws-frame-divider {
            width: 74px;
            height: 2px;
            background: #fff;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .35);
        }

        /* -------------------------
   THEME: DARK NEON (tech, glow)
   - neon, borda luminosa, overlay colorido
   ------------------------- */
        .theme-dark .pga-ws-story-frame {
            border: 1px solid rgba(255, 255, 255, .08);
        }

        .theme-dark .pga-overlay-fundo {
            background:
                radial-gradient(circle at 30% 20%, rgba(0, 255, 255, .22), rgba(0, 0, 0, 0) 45%),
                radial-gradient(circle at 70% 30%, rgba(168, 85, 247, .18), rgba(0, 0, 0, 0) 45%),
                linear-gradient(to top, rgba(0, 0, 0, .78), rgba(0, 0, 0, .22) 55%, rgba(0, 0, 0, 0));
        }

        .theme-dark .pga-frame-img {
            filter: contrast(1.18) saturate(1.15);
        }

        .theme-dark .pga-ws-frame-title {
            font-weight: 900;
            text-shadow: 0 10px 30px rgba(0, 0, 0, .55);
        }

        /* template-2 no dark vira vidro neon */
        .theme-dark .pga-ws-frame-content h3 {
            color: var(--pga-branco);
        }

        .theme-dark .pga-ws-frame-content {
            background: rgba(0, 0, 0, .30);
            border: 1px solid rgba(0, 255, 255, .14);
            box-shadow: 0 0 0 1px rgba(168, 85, 247, .08), 0 18px 60px rgba(0, 0, 0, .35);
            padding: 15px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        /* -------------------------
   THEME: SOFT CLEAN (claro, minimal, “instagram clean”)
   - overlay bem leve, texto escuro em “painel” claro
   ------------------------- */
        .theme-soft .pga-ws-story-frame {
            border: 1px solid rgba(0, 0, 0, .06);
        }

        .theme-soft .pga-overlay-fundo {
            background: linear-gradient(to top, rgba(255, 255, 255, .88), rgba(255, 255, 255, .34) 55%, rgba(255, 255, 255, 0));
        }

        .theme-soft .pga-ws-frame-content {
            color: #0f172a;
        }

        .theme-soft .pga-ws-frame-title {
            font-weight: 900;
        }

        .theme-soft .pga-ws-frame-text {
            opacity: .88;
        }

        /* no soft, template-1 e 3 ganham “painel” branco */
        .theme-soft .pga-ws-frame-content,
        .theme-soft .pga-ws-frame-content {
            background: rgba(255, 255, 255, .78);
            border: 1px solid rgba(15, 23, 42, .10);
            border-radius: 16px;
            padding: 14px 14px;
            backdrop-filter: blur(10px);
        }

        /* template-2 no soft vira card ainda mais claro */
        .theme-soft .pga-ws-frame-content {
            background: rgba(255, 255, 255, .80);
            border: 1px solid rgba(15, 23, 42, .10);
        }

        /* -------------------------
   THEME: POP BOLD (vibrante, “cartaz”, alto impacto)
   - cores fortes, stroke, badge grande
   ------------------------- */
        .theme-pop .pga-frame-img {
            filter: saturate(1.35) contrast(1.08);
        }

        .theme-pop .pga-overlay-fundo {
            background:
                linear-gradient(135deg, rgba(255, 0, 122, .20), rgba(0, 212, 255, .14) 50%, rgba(255, 214, 0, .12)),
                linear-gradient(to top, rgba(0, 0, 0, .62), rgba(0, 0, 0, .12) 60%, rgba(0, 0, 0, 0));
        }

        .theme-pop .pga-ws-frame-title {
            font-weight: 900;
            letter-spacing: -0.03em;
            text-shadow: 0 12px 30px rgba(0, 0, 0, .45);
            color: #fff;
        }

        .theme-pop .pga-ws-frame-text {
            font-size: 1rem;
            opacity: .95;
        }


        /* CTA base */
        .pga-ws-story-frame .pga-ws-cta {
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 27px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 16px;
            letter-spacing: .01em;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease, border-color .15s ease, color .15s ease;
            user-select: none;
            margin-top: 10px;
        }

        .pga-ws-cta.ws-cta-active {
            display: inline-block
        }

        .pga-ws-story-frame .pga-ws-cta:hover {
            transform: translateY(-1px);
        }

        .theme-normal .pga-ws-cta {
            background: rgba(255, 255, 255, .92);
            color: rgba(15, 23, 42, .92);
            border: 1px solid rgba(15, 23, 42, .14);
            box-shadow: 0 12px 26px rgba(0, 0, 0, .18);
        }

        .theme-normal .pga-ws-cta:hover {
            box-shadow: 0 16px 34px rgba(0, 0, 0, .22);
        }

        /* template-1 normal: botão “padrão” */
        .theme-normal .template-1 .pga-ws-cta {
            padding: 10px 14px;
        }

        /* base newsroom: “tarja editorial” */
        .theme-news .pga-ws-cta {
            background: rgba(255, 255, 255, .96);
            color: rgba(2, 6, 23, .95);
            border: 1px solid rgba(255, 255, 255, .35);
            box-shadow: 0 16px 34px rgba(0, 0, 0, .28);
        }

        /* template-1 newsroom: botão tipo “selo” com borda forte */
        .theme-news .template-1 .pga-ws-cta {
            border-radius: 10px;
            padding: 10px 12px;
            letter-spacing: .02em;
            border: 1px solid rgba(2, 6, 23, .22);
        }

        .theme-dark .pga-ws-cta {
            background: rgba(0, 0, 0, .35);
            color: rgba(255, 255, 255, .96);
            border: 1px solid rgba(0, 255, 255, .22);
            box-shadow: 0 0 0 1px rgba(168, 85, 247, .10), 0 18px 50px rgba(0, 0, 0, .40);
            backdrop-filter: blur(10px);
        }

        .theme-dark .pga-ws-cta:hover {
            border-color: rgba(0, 255, 255, .34);
            box-shadow: 0 0 0 1px rgba(168, 85, 247, .16), 0 0 26px rgba(0, 255, 255, .16), 0 18px 55px rgba(0, 0, 0, .45);
        }

        /* template-1 dark: botão “neon pill” */
        .theme-dark .template-1 .pga-ws-cta {
            padding: 10px 14px;
        }

        /* template-3 dark: tag pequena com glow */
        .theme-dark .template-3 .pga-ws-cta {
            padding: 13px 30px;
            font-size: 16px;
            border-radius: 999px;
            box-shadow: 0 0 20px rgba(0, 255, 255, .14), 0 12px 30px rgba(0, 0, 0, .35);
        }

        .theme-soft .pga-ws-cta {
            background: rgba(255, 255, 255, .92);
            color: rgba(15, 23, 42, .92);
            border: 1px solid rgba(15, 23, 42, .12);
            box-shadow: 0 14px 30px rgba(0, 0, 0, .12);
        }

        /* template-1 soft: botão bem clean */
        .theme-soft .template-1 .pga-ws-cta {
            background: rgba(255, 255, 255, .95);
        }

        /* template-3 soft: tag discreta clara */
        .theme-soft .template-3 .pga-ws-cta {
            background: rgba(255, 255, 255, .78);
            color: rgba(15, 23, 42, .92);
            border-color: rgba(15, 23, 42, .10);
            box-shadow: 0 10px 22px rgba(0, 0, 0, .10);
        }

        .theme-pop .pga-ws-cta {
            background: rgba(255, 255, 255, .95);
            color: rgba(2, 6, 23, .96);
            border: 2px solid rgba(0, 0, 0, .22);
            box-shadow: 0 18px 38px rgba(0, 0, 0, .26);
        }

        /* template-1 pop: “sticker” */
        .theme-pop .template-1 .pga-ws-cta {
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 950;
        }


        /* template-3 pop: tag preta no topo (alto contraste) */
        .theme-pop .template-3 .pga-ws-cta {
            background: rgba(0, 0, 0, .62);
            color: rgba(255, 255, 255, .98);
            border-color: rgba(255, 255, 255, .18);
            box-shadow: 0 14px 34px rgba(0, 0, 0, .30);
        }

        .pga-wrap {
            display: flex;
        }

        .pga-logo {
            position: absolute;
            top: 25px;
            width: 35px;
            height: 35px;
            left: 25px;
            z-index: 99;
        }

        /* AMP: page TEM que ocupar a tela toda (não pode ser card) */
        amp-story-page.pga-ws-story-frame {
            width: 100%;
            height: 100%;
            aspect-ratio: auto;
            padding: 0;
            border-radius: 0;
            box-shadow: none;
            overflow: hidden;
            display: block;
        }

        /* Se teu CSS colocou flex no page, remove */
        amp-story-page.pga-ws-story-frame {
            align-items: initial;
            justify-content: initial;
        }
    </style>

    <?php
    $img = $poster_url ?: '';
    $published = get_the_date('c', $post_id);
    $modified  = get_the_modified_date('c', $post_id);

    $ld = [
        '@context' => 'https://schema.org',
        '@type' => ['Article', 'AmpStory'],
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
        'headline' => $title,
        'description' => $desc,
        'datePublished' => $published,
        'dateModified' => $modified,
        'image' => $img ? [$img] : [],
        'author' => [
            '@type' => 'Person',
            'name' => get_the_author_meta('display_name', $post->post_author),
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => $publisher_name,
            'logo' => $logo_url ? [
                '@type' => 'ImageObject',
                'url' => $logo_url
            ] : null,
        ],
    ];
    ?>
    <script type="application/ld+json">
        <?php echo wp_json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>

    <?php if ($alpha_ga_enable) : ?>
        <script async custom-element="amp-analytics" src="https://cdn.ampproject.org/v0/amp-analytics-0.1.js"></script>
        <script async custom-element="amp-story-auto-analytics" src="https://cdn.ampproject.org/v0/amp-story-auto-analytics-0.1.js"></script>
    <?php endif; ?>
</head>

<body class="<?php echo esc_attr($theme); ?>">

    <amp-story
        standalone
        title="<?php echo esc_attr($title); ?>"
        publisher="<?php echo esc_attr($publisher_name); ?>"
        publisher-logo-src="<?php echo esc_url($logo_url ?: $poster_url); ?>"
        poster-portrait-src="<?php echo esc_url($poster_url); ?>"
        <?php /* opcional: poster-square-src / poster-landscape-src */ ?>>
        <?php if ($alpha_ga_enable): ?>
            <amp-story-auto-analytics gtag-id="<?php echo esc_attr($alpha_ga_id); ?>"></amp-story-auto-analytics>
        <?php endif;

        // Render pages
        foreach ($slides as $i => $pg):
            $heading = trim((string)($pg['heading'] ?? ''));
            $body    = trim((string)($pg['body'] ?? ''));
            $cta_t   = trim((string)($pg['cta_text'] ?? ''));
            $cta_u   = trim((string)($pg['cta_url'] ?? ''));

            $tpl     = (string)($pg['template'] ?? 'template-1');

            $img_url = pga_ws_image_url_from_slide($pg);
            if ($img_url === '' && $poster_url !== '') $img_url = $poster_url;

            $page_id = 'p' . ($i + 1);
            $autoAttr = pga_ws_page_auto_advance_attr((int)$i, (int)$autoplay_on, (int)$page_duration);
        ?>
            <amp-story-page id="<?php echo esc_attr($page_id); ?>" <?php echo $autoAttr; ?> class="pga-ws-story-frame">
                <amp-story-grid-layer template="fill">
                    <?php if ($img_url): ?>
                        <amp-img
                            src="<?php echo esc_url($img_url); ?>"
                            width="720" height="1280"
                            layout="responsive"
                            alt="<?php echo esc_attr($heading ?: $title); ?>"
                            class="pga-frame-img">
                        </amp-img>
                        <div class="pga-overlay"></div>
                    <?php else: ?>
                        <div style="width:100%;height:100%;background:var(--pga-bg)"></div>
                    <?php endif; ?>
                </amp-story-grid-layer>

                <amp-story-grid-layer template="vertical" class="pga-wrap <?php echo $tpl; ?>">
                    <div class="pga-overlay-fundo"></div>
                    <?php if ($logo_url): ?>
                        <div class="pga-logo" aria-hidden="true">
                            <amp-img src="<?php echo esc_url($logo_url); ?>" width="44" height="44" layout="fixed" alt="Logo"></amp-img>
                        </div>
                    <?php endif; ?>

                    <div class="pga-ws-frame-content">
                        <?php if ($heading !== ''): ?>
                            <h2 class="pga-ws-frame-title" <?php echo pga_ws_animate_attrs((int)$i, 'title'); ?>>
                                <?php echo pga_ws_esc($heading); ?>
                            </h2>
                        <?php endif; ?>
                        <div class="pga-ws-frame-divider" aria-hidden="true"></div>

                        <?php if ($body !== ''): ?>
                            <p class="pga-ws-frame-text" <?php echo pga_ws_animate_attrs((int)$i, 'body'); ?>>
                                <?php echo nl2br(pga_ws_esc($body)); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($cta_t !== '' && $cta_u !== ''): ?>
                            <a class="pga-ws-cta" href="<?php echo esc_url($cta_u); ?>" target="_blank" rel="noopener"
                                <?php echo pga_ws_animate_attrs((int)$i, 'cta'); ?>>
                                <?php echo pga_ws_esc($cta_t); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </amp-story-grid-layer>
                <!-- Topbar: tem que estar dentro de um layer -->
                <?php if ($edit_url && $i === 0): ?>
                    <amp-story-grid-layer template="vertical">
                        <div class="pga-topbar">
                            <div class="left">
                                <a class="pga-pill" href="<?php echo esc_url($edit_url); ?>">
                                    <span class="dot"></span>
                                    Editar no Builder
                                </a>
                            </div>
                        </div>
                    </amp-story-grid-layer>
                <?php endif; ?>
            </amp-story-page>

        <?php endforeach; ?>

    </amp-story>
</body>

</html>