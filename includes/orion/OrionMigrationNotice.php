<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Orion_Migration_Notice
{
    const DISMISS_META_KEY = 'pga_orion_migrate_notice_dismissed';
    const ACTION_MIGRATE   = 'pga_orion_migrate_old_posts';
    const ACTION_DISMISS   = 'pga_orion_migrate_notice_dismiss';
    const NONCE_ACTION     = 'pga_orion_migrate_notice';

    // marque posts já migrados (pra não contar de novo)
    const MIGRATED_META_KEY = '_pga_migrated_to_post';

    // ajuste para teu CPT real
    const CPT = 'posts_orion';

    public static function init(): void
    {
        add_action('admin_notices', [__CLASS__, 'render_notice']);
        add_action('admin_post_' . self::ACTION_MIGRATE, [__CLASS__, 'handle_migrate']);
        add_action('admin_post_' . self::ACTION_DISMISS, [__CLASS__, 'handle_dismiss']);
    }

    /** Decide se mostra notice */
    protected static function should_show(): bool
    {
        if (!is_admin()) return false;
        if (!current_user_can('manage_options')) return false;

        $user_id = get_current_user_id();
        if ($user_id <= 0) return false;

        // já dispensou
        $dismissed = get_user_meta($user_id, self::DISMISS_META_KEY, true);
        if ($dismissed) return false;

        // só mostra se EXISTIR “legado” para migrar:
        // - posts_orion publicados
        // - ainda não marcados como migrados
        $q = new WP_Query([
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::MIGRATED_META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        return ($q->have_posts());
    }

    public static function render_notice(): void
    {
        if (!self::should_show()) return;

        $user_id = get_current_user_id();

        $count = (int) (new WP_Query([
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::MIGRATED_META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]))->found_posts;

        $migrate_url = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_MIGRATE),
            self::NONCE_ACTION
        );

        $dismiss_url = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_DISMISS),
            self::NONCE_ACTION
        );

        // mensagem pós-redirect (sucesso/progresso)
        $done  = isset($_GET['pga_mig_done']) ? (int) $_GET['pga_mig_done'] : 0;
        $left  = isset($_GET['pga_mig_left']) ? (int) $_GET['pga_mig_left'] : 0;
        $batch = isset($_GET['pga_mig_batch']) ? (int) $_GET['pga_mig_batch'] : 0;

        $statusLine = '';
        if ($batch > 0) {
            $statusLine = sprintf(
                '<p style="margin:.5em 0 0;color:#1d2327;"><strong>Progresso:</strong> %d migrados agora. %s</p>',
                $batch,
                ($left > 0 ? ('Restam ' . $left . '.') : 'Tudo migrado ✅')
            );
        }

?>
        <div class="notice notice-warning" style="border-left-color:#1C5CF4;">
            <p style="margin:0.6em 0;">
                <strong>Atualização do Alpha Órion:</strong>
                a partir de agora, quando um conteúdo for marcado como <strong>Publicado</strong>, ele pode ser convertido para <strong>Post padrão do WordPress</strong>.
            </p>

            <p style="margin:0.6em 0;">
                Detectamos <strong><?php echo esc_html($count); ?></strong> posts antigos publicados no formato antigo (<code><?php echo esc_html(self::CPT); ?></code>).
                Você quer migrar esses posts antigos agora?
            </p>

            <details style="margin:0.6em 0;">
                <summary style="cursor:pointer;">Detalhes da atualização</summary>
                <div style="margin-top:8px;color:#50575e;">
                    <p style="margin:.5em 0;">
                        - A migração <strong>não altera</strong> o conteúdo do post, só converte o tipo para <code>post</code> (nativo).
                        <br>- Isso melhora compatibilidade com temas, SEO e plugins.
                        <br>- Se você tiver automações que dependem do CPT antigo, você pode escolher “Não agora”.
                    </p>
                </div>
            </details>

            <p style="display:flex; gap:10px; align-items:center; margin:.8em 0 .6em;">
                <a href="<?php echo esc_url($migrate_url); ?>" class="button button-primary" style="background:#1C5CF4;border-color:#1C5CF4;">
                    Migrar posts antigos agora
                </a>

                <a href="<?php echo esc_url($dismiss_url); ?>" class="button">
                    Não agora
                </a>

                <span style="color:#6c7781;">
                    (essa mensagem não vai aparecer novamente após “Não agora”.)
                </span>
            </p>

            <?php echo $statusLine; ?>
        </div>
<?php
    }

    public static function handle_dismiss(): void
    {
        if (!current_user_can('manage_options')) wp_die('Sem permissão.');
        check_admin_referer(self::NONCE_ACTION);

        update_user_meta(get_current_user_id(), self::DISMISS_META_KEY, 1);

        wp_safe_redirect(remove_query_arg(['pga_mig_done', 'pga_mig_left', 'pga_mig_batch']));
        exit;
    }

    public static function handle_migrate(): void
    {
        if (!current_user_can('manage_options')) wp_die('Sem permissão.');
        check_admin_referer(self::NONCE_ACTION);

        // processa em lotes para não estourar timeout
        $limit = 50;

        $ids = get_posts([
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'numberposts'    => $limit,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::MIGRATED_META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]);

        $migrated_now = 0;

        if ($ids) {
            foreach ($ids as $id) {
                $id = (int) $id;

                // converte post_type pra post
                $r = wp_update_post([
                    'ID'        => $id,
                    'post_type' => 'post',
                ], true);

                if (!is_wp_error($r)) {
                    update_post_meta($id, self::MIGRATED_META_KEY, 1);
                    $migrated_now++;
                }
            }
        }

        // calcula quantos restam
        $left = (int) (new WP_Query([
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::MIGRATED_META_KEY,
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ]))->found_posts;

        // se terminou, pode dar dismiss automático
        if ($left <= 0) {
            update_user_meta(get_current_user_id(), self::DISMISS_META_KEY, 1);
        }

        $redirect = add_query_arg([
            'pga_mig_batch' => $migrated_now,
            'pga_mig_left'  => max(0, $left),
        ], remove_query_arg(['pga_mig_done', 'pga_mig_left', 'pga_mig_batch']));

        wp_safe_redirect($redirect);
        exit;
    }
}
