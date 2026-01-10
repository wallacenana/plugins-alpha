<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Adminbar
{
    public static function init(): void
    {
        add_action('admin_bar_menu', [self::class, 'add_orion_nodes'], 90);

        // flush via admin-post.php
        add_action('admin_post_pga_orion_flush_permalinks', [self::class, 'handle_flush_permalinks']);

        add_action('admin_notices', [self::class, 'maybe_notice_flushed']);
    }

    public static function add_orion_nodes(\WP_Admin_Bar $admin_bar): void
    {
        if (!is_user_logged_in() || !is_admin_bar_showing()) {
            return;
        }

        if (!is_singular('posts_orion')) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id || !current_user_can('edit_post', $post_id)) {
            return;
        }

        // NÓ PAI — ÍCONE + TEXTO
        $admin_bar->add_node([
            'id'    => 'pga-orion-root',
            'title' => '<span class="ab-icon pga-orion-icon"></span><span class="ab-label">
            <img src=' . PGA_URL . 'assets/images/favicon-plugins-alpha.png style="width: 17px;margin-right: 9px;float: left;margin-top: 7px;">Alpha Suite</span>',
            'href'  => get_edit_post_link($post_id, ''),
        ]);

        // EDITAR POST
        $admin_bar->add_node([
            'id'     => 'pga-orion-edit',
            'parent' => 'pga-orion-root',
            'title'  => __('Editar Órion Post', 'plugins-alpha'),
            'href'   => get_edit_post_link($post_id, ''),
        ]);

        // FUTUROS BOTÕES (deixados apenas comentados)
        /*
        $admin_bar->add_node([
            'id'     => 'pga-orion-regen',
            'parent' => 'pga-orion-root',
            'title'  => __('Regerar artigo com IA', 'plugins-alpha'),
            'href'   => '#',
        ]);

        $admin_bar->add_node([
            'id'     => 'pga-orion-story',
            'parent' => 'pga-orion-root',
            'title'  => __('Gerar Story agora', 'plugins-alpha'),
            'href'   => '#',
        ]);
        */

        // FLUSH PERMALINKS
        $redirect_to = rawurlencode(get_permalink($post_id));
        $flush_url   = wp_nonce_url(
            add_query_arg(
                [
                    'action'      => 'pga_orion_flush_permalinks',
                    'redirect_to' => $redirect_to,
                ],
                admin_url('admin-post.php')
            ),
            'pga_orion_flush_permalinks'
        );

        $admin_bar->add_node([
            'id'     => 'pga-orion-flush-permalinks',
            'parent' => 'pga-orion-root',
            'title'  => __('Recarregar links permanentes', 'plugins-alpha'),
            'href'   => $flush_url,
        ]);
    }

    public static function handle_flush_permalinks(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Sem permissão.', 'plugins-alpha'));
        }

        check_admin_referer('pga_orion_flush_permalinks');

        flush_rewrite_rules(false);

        $redirect = isset($_GET['redirect_to'])
            ? esc_url_raw(wp_unslash($_GET['redirect_to']))
            : wp_get_referer();

        if (!$redirect) {
            $redirect = admin_url();
        }

        $redirect = add_query_arg('pga_orion_flushed', '1', $redirect);

        wp_safe_redirect($redirect);
        exit;
    }

    public static function maybe_notice_flushed(): void
    {
        if (empty($_GET['pga_orion_flushed']) || !current_user_can('manage_options')) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>'
            . esc_html__('Links permanentes do Órion recarregados com sucesso.', 'plugins-alpha')
            . '</p></div>';
    }
}
