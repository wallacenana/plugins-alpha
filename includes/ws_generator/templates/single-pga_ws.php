<?php
if (!defined('ABSPATH')) exit;

$story = get_post();
if (!$story) {
    status_header(404);
    exit;
}

$id = (int) $story->ID;

// teus metas
$meta_title = (string) get_post_meta($id, PluginsAlpha_REST_Ws_Generator::META_TITLE, true);
$meta_desc  = (string) get_post_meta($id, PluginsAlpha_REST_Ws_Generator::META_DESC, true);
$accent     = (string) get_post_meta($id, PluginsAlpha_REST_Ws_Generator::META_ACCENT, true);
$textc      = (string) get_post_meta($id, PluginsAlpha_REST_Ws_Generator::META_TEXT_COLOR, true);
$poster_id  = (int) get_post_meta($id, PluginsAlpha_REST_Ws_Generator::META_POSTER_ID, true);
$slides     = (array) get_post_meta($id, PluginsAlpha_REST_Ws_Generator::META_SLIDES, true);

$title = $meta_title !== '' ? $meta_title : get_the_title($id);
$desc  = $meta_desc !== '' ? $meta_desc : '';

$poster_url = $poster_id ? (wp_get_attachment_image_url($poster_id, 'full') ?: '') : '';
if (!$poster_url) {
    $thumb = get_the_post_thumbnail_url($id, 'full');
    if ($thumb) $poster_url = $thumb;
}

$builder_url = admin_url('admin.php?page=plugins-alpha-ws-generator&story_id=' . $id);
$can_edit = current_user_can('edit_post', $id);

header('Content-Type: text/html; charset=' . get_bloginfo('charset'), true);
?>
<!doctype html>
<html ⚡ lang="pt-BR">

<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">

    <title><?php echo esc_html($title); ?></title>
    <?php if ($desc) : ?>
        <meta name="description" content="<?php echo esc_attr($desc); ?>">
    <?php endif; ?>

    <link rel="canonical" href="<?php echo esc_url(get_permalink($id)); ?>">

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
            --accent: <?php echo esc_html($accent ?: '#3B82F6'); ?>;
            --text: <?php echo esc_html($textc ?: '#FFFFFF'); ?>;
        }

        .pga-cover-title {
            font-size: 38px;
            line-height: 1.1;
            color: var(--text);
        }

        .pga-cover-desc {
            font-size: 18px;
            line-height: 1.3;
            color: var(--text);
            opacity: .92;
            margin-top: 10px;
        }

        .pga-pill {
            display: inline-block;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(0, 0, 0, .45);
            color: #fff;
            font-size: 14px;
        }

        .pga-edit {
            position: absolute;
            top: 16px;
            right: 16px;
            z-index: 10;
            background: rgba(0, 0, 0, .55);
            color: #fff;
            padding: 10px 12px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 14px;
        }

        .pga-shadow {
            background: linear-gradient(180deg, rgba(0, 0, 0, .55), rgba(0, 0, 0, .10));
        }

        .pga-body {
            color: var(--text);
            font-size: 22px;
            line-height: 1.22;
        }

        .pga-cta {
            display: inline-block;
            margin-top: 14px;
            padding: 12px 16px;
            border-radius: 999px;
            background: var(--accent);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <amp-story
        standalone
        title="<?php echo esc_attr($title); ?>"
        publisher="Plugins Alpha"
        publisher-logo-src="<?php echo esc_url($poster_url ?: (get_site_icon_url(192) ?: '')); ?>"
        poster-portrait-src="<?php echo esc_url($poster_url ?: (get_site_icon_url(512) ?: '')); ?>">

        <!-- COVER -->
        <amp-story-page id="cover">
            <amp-story-grid-layer template="fill">
                <?php if ($poster_url) : ?>
                    <amp-img src="<?php echo esc_url($poster_url); ?>" width="720" height="1280" layout="responsive"></amp-img>
                <?php else : ?>
                    <div style="background:#111;width:100%;height:100%"></div>
                <?php endif; ?>
                <div class="pga-shadow" style="position:absolute;inset:0"></div>
            </amp-story-grid-layer>

            <amp-story-grid-layer template="vertical" style="padding: 28px">
                <?php if ($can_edit) : ?>
                    <a class="pga-edit" href="<?php echo esc_url($builder_url); ?>" target="_blank" rel="noopener">Editar</a>
                <?php endif; ?>

                <div>
                    <div class="pga-pill"><?php echo esc_html(get_bloginfo('name')); ?></div>
                    <h1 class="pga-cover-title"><?php echo esc_html($title); ?></h1>
                    <?php if ($desc) : ?><div class="pga-cover-desc"><?php echo esc_html($desc); ?></div><?php endif; ?>
                </div>
            </amp-story-grid-layer>
        </amp-story-page>

        <?php
        // PÁGINAS
        $i = 1;
        foreach ($slides as $pg) {
            if (!is_array($pg)) continue;

            $heading = isset($pg['heading']) ? (string) $pg['heading'] : '';
            $body    = isset($pg['body']) ? (string) $pg['body'] : '';
            $cta_t   = isset($pg['cta_text']) ? (string) $pg['cta_text'] : '';
            $cta_u   = isset($pg['cta_url']) ? (string) $pg['cta_url'] : '';
            $img_id  = isset($pg['image_id']) ? (int) $pg['image_id'] : 0;
            $img_url = $img_id ? (wp_get_attachment_image_url($img_id, 'full') ?: '') : '';

            $page_id = 'p' . $i++;
        ?>
            <amp-story-page id="<?php echo esc_attr($page_id); ?>">
                <amp-story-grid-layer template="fill">
                    <?php if ($img_url) : ?>
                        <amp-img src="<?php echo esc_url($img_url); ?>" width="720" height="1280" layout="responsive"></amp-img>
                    <?php else : ?>
                        <div style="background:#111;width:100%;height:100%"></div>
                    <?php endif; ?>
                    <div class="pga-shadow" style="position:absolute;inset:0"></div>
                </amp-story-grid-layer>

                <amp-story-grid-layer template="vertical" style="padding: 28px">
                    <?php if ($heading) : ?><h2 class="pga-cover-title" style="font-size:30px"><?php echo esc_html($heading); ?></h2><?php endif; ?>
                    <?php if ($body) : ?><div class="pga-body"><?php echo nl2br(esc_html($body)); ?></div><?php endif; ?>

                    <?php if ($cta_t && $cta_u) : ?>
                        <a class="pga-cta" href="<?php echo esc_url($cta_u); ?>" target="_blank" rel="noopener"><?php echo esc_html($cta_t); ?></a>
                    <?php endif; ?>
                </amp-story-grid-layer>
            </amp-story-page>
        <?php
        }
        ?>

    </amp-story>
</body>

</html>