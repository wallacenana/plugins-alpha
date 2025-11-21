<?php
// includes/License.php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_License
{
    const OPTION_KEY = 'pga_client_license';
    const CRON_HOOK  = 'plugins_alpha_license_daily_check';
    const PGA_LICENSE_API_BASE  = 'https://pluginsalpha.com/wp-json/pga-admin/v1';

    /**
     * Base da API do ADMIN (servidor central de licenças).
     * Pode ser sobrescrito com:
     *  - const PGA_LICENSE_API_BASE
     *  - filtro 'plugins_alpha/license_api_base'
     */
    public static function api_base(): string
    {
        $base = self::PGA_LICENSE_API_BASE
            ?? 'https://pluginsalpha.com/wp-json/pga-admin/v1';

        $base = rtrim($base, '/');
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound	
        return apply_filters('plugins_alpha/license_api_base', $base);
    }

    public static function init(): void
    {
        // submenu no dashboard Plugins Alpha
        add_action('admin_menu', [self::class, 'menu']);

        // ações de formulário
        add_action('admin_post_pga_activate_license', [self::class, 'handle_activate']);
        add_action('admin_post_pga_deactivate_license', [self::class, 'handle_deactivate']);

        // cron diário
        add_action(self::CRON_HOOK, [self::class, 'cron_check']);
    }

    public static function menu(): void
    {
        add_submenu_page(
            'plugins-alpha-dashboard',
            __('Licença', 'plugins-alpha'),
            __('Licença', 'plugins-alpha'),
            'manage_options',
            'plugins-alpha-license',
            [self::class, 'render_page']
        );
    }

    // ===================== Helpers de estado =====================

    public static function get_state(): array
    {
        $opt = get_option(self::OPTION_KEY, []);
        if (!is_array($opt)) $opt = [];

        return [
            'license_key' => (string)($opt['license_key'] ?? ''),
            'purchase_id' => (string)($opt['purchase_id'] ?? ''),
            'email'       => (string)($opt['email'] ?? ''),
            'status'      => (string)($opt['status'] ?? 'inactive'),
            'plan'        => (string)($opt['plan'] ?? ''),
            'modules'     => is_array($opt['modules'] ?? null) ? $opt['modules'] : [],
            'domains_used' => is_array($opt['domains_used'] ?? null) ? $opt['domains_used'] : [],
            'max_domains' => (int)($opt['max_domains'] ?? 1),
            'expires_at'  => $opt['expires_at'] ?? null,
            'last_check'  => $opt['last_check'] ?? null,
        ];
    }

    // ===================== API simplificada para outros módulos (Webhook etc.) =====================

    /**
     * Retorna o array CRU salvo na OPTION_KEY.
     * Útil para guardar metadados extras (buyer_email, product_id, webhook_token etc).
     */
    public static function get(): array
    {
        $opt = get_option(self::OPTION_KEY, []);
        return is_array($opt) ? $opt : [];
    }

    /**
     * Faz merge dos dados atuais com os novos e salva.
     * Não apaga chaves antigas que não foram enviadas.
     */
    public static function set(array $data): void
    {
        $current = self::get();
        $merged  = array_merge($current, $data);

        // Garantir formatos mínimos que o get_state() espera (defensivo)
        if (!isset($merged['modules']) || !is_array($merged['modules'])) {
            $merged['modules'] = [];
        }

        if (!isset($merged['domains_used']) || !is_array($merged['domains_used'])) {
            $merged['domains_used'] = [];
        }

        if (!isset($merged['max_domains'])) {
            $merged['max_domains'] = 1;
        }

        update_option(self::OPTION_KEY, $merged);
    }

    public static function is_active(?array $lic = null): bool
    {
        if ($lic === null) {
            $lic = self::get_state();
        }

        if (($lic['status'] ?? '') !== 'active') {
            return false;
        }

        if (!empty($lic['expires_at'])) {
            $ts = strtotime($lic['expires_at']);
            if ($ts && $ts < time()) {
                return false;
            }
        }

        return true;
    }


    /**
     * Verifica se o módulo (ex.: 'alpha_stories') está liberado para este site.
     */
    public static function has_module(string $module): bool
    {
        $lic = self::get_state();
        return self::is_active() && in_array($module, $lic['modules'], true);
    }

    /**
     * Domínio atual normalizado (sem http, sem path).
     */
    public static function current_domain(): string
    {
        $url = home_url('/');
        $url = preg_replace('#^https?://#i', '', $url);
        $slash = strpos($url, '/');
        if ($slash !== false) {
            $url = substr($url, 0, $slash);
        }
        return strtolower(trim($url));
    }

    /**
     * Revalida a licença 1x por dia com o servidor central.
     * Se der certo, atualiza dados (expiração, módulos, etc).
     * Se der erro, só loga – não derruba a licença local.
     */
    public static function cron_check(): void
    {
        $lic = self::get_state();

        // Se nem tem licença salva, não faz nada
        if (empty($lic['purchase_id']) && empty($lic['license_key'])) {
            return;
        }

        $domain = self::current_domain();

        $body = [
            'email'         => $lic['email'] ?? '',
            'purchase_id'   => $lic['purchase_id'] ?? '',
            'license_key'   => $lic['license_key'] ?? '',
            'domain'        => $domain,
            'site_url'      => home_url('/'),
            'site_name'     => get_bloginfo('name'),
            'wp_version'    => get_bloginfo('version'),
            'php_version'   => PHP_VERSION,
            'plugin'        => 'plugins-alpha',
            'plugin_version' => '1.0.1',
        ];

        $res = self::remote_call('/client/activate', $body);

        if (is_wp_error($res)) {
            return;
        }

        if (empty($res['ok']) || empty($res['license']) || !is_array($res['license'])) {
            return;
        }

        $l = $res['license'];

        $opt = [
            'license_key' => (string)($l['license_key'] ?? $lic['license_key']),
            'purchase_id' => (string)($l['purchase_id'] ?? $lic['purchase_id']),
            'email'       => (string)($l['email'] ?? $lic['email']),
            'status'      => (string)($l['status'] ?? $lic['status']),
            'plan'        => (string)($l['plan'] ?? ($lic['plan'] ?? '')),
            'modules'     => is_array($l['modules'] ?? null) ? $l['modules'] : ($lic['modules'] ?? []),
            'domains_used' => is_array($l['domains_used'] ?? null) ? $l['domains_used'] : ($lic['domains_used'] ?? []),
            'max_domains' => (int)($l['max_domains'] ?? ($lic['max_domains'] ?? 1)),
            'expires_at'  => $l['expires_at'] ?? ($lic['expires_at'] ?? null),
            'last_check'  => current_time('mysql', true),
        ];

        update_option(self::OPTION_KEY, $opt);
    }


    public static function schedule_cron(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // agenda para daqui 1 hora e depois todo dia
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function clear_cron(): void
    {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }

    // ===================== UI =====================

    public static function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Sem permissão.', 'plugins-alpha'));
        }

        // 1) Processa envio do formulário de licença (POST)
        if (isset($_POST['pga_license_submit'])) {

            // verifica nonce
            $nonce = isset($_POST['pga_license_nonce'])
                ? sanitize_text_field(wp_unslash($_POST['pga_license_nonce']))
                : '';

            if (! $nonce || ! wp_verify_nonce($nonce, 'pga_license_save')) {
                wp_die(
                    esc_html__(
                        'Falha na verificação de segurança. Recarregue a página e tente novamente.',
                        'plugins-alpha'
                    )
                );
            }

            // sanitiza campos
            $email       = isset($_POST['email'])
                ? sanitize_email(wp_unslash($_POST['email']))
                : '';

            $purchase_id = isset($_POST['purchase_id'])
                ? sanitize_text_field(wp_unslash($_POST['purchase_id']))
                : '';

            $license_key = isset($_POST['license_key'])
                ? sanitize_text_field(wp_unslash($_POST['license_key']))
                : '';

            // aqui você coloca a SUA lógica atual de salvar/validar licença
            // por exemplo:
            // self::save_state( $email, $purchase_id, $license_key );

            $error_msg = '';

            // se quiser uma validação mínima, pode fazer algo assim:
            if ('' === $email || '' === $license_key) {
                $error_msg = __('Informe e-mail e chave da licença.', 'plugins-alpha');
            }

            // se houve erro, redireciona com ?error=
            if ($error_msg) {
                $url = add_query_arg(
                    array(
                        'page'  => 'plugins-alpha-license',
                        'error' => rawurlencode($error_msg),
                    ),
                    admin_url('admin.php')
                );

                wp_safe_redirect(esc_url_raw($url));
                exit;
            }

            // se deu tudo certo, redireciona com ?updated=1
            $url = add_query_arg(
                array(
                    'page'    => 'plugins-alpha-license',
                    'updated' => 1,
                ),
                admin_url('admin.php')
            );

            wp_safe_redirect(esc_url_raw($url));
            exit;
        }

        // 2) Carrega estado atual
        $lic    = self::get_state();
        $domain = self::current_domain();
        $api    = self::api_base();

        $status_label = self::is_active()
            ? __('Ativa', 'plugins-alpha')
            : __('Inativa', 'plugins-alpha');

        $status_class = self::is_active()
            ? 'pga-badge-active'
            : 'pga-badge-inactive';

        // 3) Avisos via GET (já sanitizados)
        $error = isset($_GET['error'])
            ? sanitize_text_field(wp_unslash($_GET['error']))
            : '';

        $updated = isset($_GET['updated'])
            ? sanitize_text_field(wp_unslash($_GET['updated']))
            : '';

        if ($error) {
            echo '<div class="notice notice-error is-dismissible"><p>' .
                esc_html($error) .
                '</p></div>';
        }

        if ($updated) {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                esc_html__('Licença atualizada com sucesso.', 'plugins-alpha') .
                '</p></div>';
        }

        $expires_text = '—';
        if (! empty($lic['expires_at'])) {
            $expires_text = mysql2date('d/m/Y H:i', $lic['expires_at']);
        }
        if (isset($lic['plan']) && 'lifetime' === $lic['plan']) {
            $expires_text = __('Vitalício', 'plugins-alpha');
        }

        $used = is_array($lic['domains_used']) ? count($lic['domains_used']) : 0;
        $max  = ! empty($lic['max_domains']) ? (int) $lic['max_domains'] : 1;

        // === daqui pra baixo fica o teu HTML da tela de licença ===
?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Licença — Plugins Alpha', 'plugins-alpha'); ?></h1>

            <p style="margin-top:8px; color:#555;">
                <?php printf(
                    esc_html('Este site: %s (domínio usado para ativação)', 'plugins-alpha'),
                    '<code>' . esc_html($domain) . '</code>'
                ); ?>
            </p>

            <div style="display:flex; gap:24px; align-items:flex-start; margin-top:20px;">

                <!-- STATUS -->
                <div style="flex:1; min-width:260px;">
                    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px;">
                        <h2 style="margin-top:0;"><?php esc_html_e('Status da licença', 'plugins-alpha'); ?></h2>

                        <p>
                            <span class="<?php echo esc_attr($status_class); ?>" style="display:inline-block;padding:2px 8px;border-radius:999px;font-weight:600;font-size:12px;
                    <?php echo self::is_active()
                        ? 'background:#46b450;color:#fff;'
                        : 'background:#dc3232;color:#fff;'; ?>">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </p>

                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('E-mail', 'plugins-alpha'); ?></th>
                                <td><code><?php echo esc_html($lic['email'] ?: '—'); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('ID da compra', 'plugins-alpha'); ?></th>
                                <td><code><?php echo esc_html($lic['purchase_id'] ?: '—'); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Plano', 'plugins-alpha'); ?></th>
                                <td><code><?php echo esc_html($lic['plan'] ?: '—'); ?></code></td>
                            </tr>

                            <tr>
                                <th><?php esc_html_e('Expira em', 'plugins-alpha'); ?></th>
                                <td><code><?php echo esc_html($expires_text); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Domínios usados', 'plugins-alpha'); ?></th>
                                <td><code><?php echo esc_html("{$used} / {$max}"); ?></code></td>
                            </tr>
                        </table>



                        <?php if (!empty($lic['modules'])): ?>
                            <h3><?php esc_html_e('Módulos liberados para essa licença', 'plugins-alpha'); ?></h3>
                            <ul style="list-style:disc;margin-left:20px;">
                                <?php
                                $labels = self::module_labels();
                                foreach ($lic['modules'] as $m):
                                    $slug  = (string)$m;
                                    $label = $labels[$slug] ?? $slug;
                                ?>
                                    <li>
                                        <?php echo esc_html($label); ?>
                                        <span style="color:#777;font-size:11px;">(<?php echo esc_html($slug); ?>)</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- FORMULÁRIO -->
                <div style="flex:1; min-width:320px;">
                    <div style="background:#fff;border:1px solid #ccd0d4;border-radius:8px;padding:16px;">
                        <h2 style="margin-top:0;">
                            <?php echo self::is_active()
                                ? esc_html__('Reativar / trocar licença', 'plugins-alpha')
                                : esc_html__('Ativar licença', 'plugins-alpha'); ?>
                        </h2>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('pga_activate_license'); ?>
                            <input type="hidden" name="action" value="pga_activate_license">

                            <table class="form-table">
                                <tr>
                                    <th><label for="pga_email"><?php esc_html_e('E-mail da compra', 'plugins-alpha'); ?></label></th>
                                    <td>
                                        <input type="email" name="email" id="pga_email" class="regular-text"
                                            value="<?php echo esc_attr($lic['email'] ?? ''); ?>" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="pga_purchase_id"><?php esc_html_e('ID da compra / Transação', 'plugins-alpha'); ?></label></th>
                                    <td>
                                        <input type="text" name="purchase_id" id="pga_purchase_id" class="regular-text"
                                            value="<?php echo esc_attr($lic['purchase_id'] ?? ''); ?>" required>
                                        <p class="description">
                                            <?php esc_html_e('Use o código HP... ou outro identificador da compra enviado pela Hotmart.', 'plugins-alpha'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label for="pga_license_key"><?php esc_html_e('Chave de licença (opcional)', 'plugins-alpha'); ?></label></th>
                                    <td>
                                        <input type="text" name="license_key" id="pga_license_key" class="regular-text"
                                            value="<?php echo esc_attr($lic['license_key'] ?? ''); ?>">
                                        <p class="description">
                                            <?php esc_html_e('Se o painel Plugins Alpha gerar uma chave própria, use-a aqui. Senão, deixe em branco.', 'plugins-alpha'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <?php submit_button(
                                self::is_active()
                                    ? esc_html__('Revalidar licença neste domínio', 'plugins-alpha')
                                    : esc_html__('Ativar licença neste domínio', 'plugins-alpha'),
                                'primary'
                            ); ?>
                        </form>

                        <?php if (!empty($lic['status']) && $lic['status'] !== 'inactive'): ?>
                            <hr>
                            <h3><?php esc_html_e('Desativar neste site', 'plugins-alpha'); ?></h3>
                            <p><?php esc_html_e('Isso libera o slot deste domínio no painel do Plugins Alpha, permitindo ativar em outro site (se o plano permitir).', 'plugins-alpha'); ?></p>

                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                                onsubmit="return confirm('<?php echo esc_js(__('Desativar a licença neste site?', 'plugins-alpha')); ?>');">
                                <?php wp_nonce_field('pga_deactivate_license'); ?>
                                <input type="hidden" name="action" value="pga_deactivate_license">
                                <?php submit_button(
                                    esc_html__('Desativar neste site', 'plugins-alpha'),
                                    'secondary'
                                ); ?>
                            </form>
                        <?php endif; ?>

                    </div>
                </div>

            </div>
        </div>
<?php
    }

    /**
     * Valida a licença e (opcionalmente) um módulo específico.
     *
     * @param string $module_slug Ex.: 'alpha_stories', 'alpha_orion_posts' ou '' para só validar a licença.
     * @return array {
     *   ok      => bool,
     *   code    => string (ok|licenca_inativa|licenca_expirada|modulo_indisponivel),
     *   message => string (para exibir pro usuário, se quiser),
     * }
     */
    public static function check(string $module_slug = ''): array
    {
        $lic = self::get_state();

        // 1) licença inativa / sem dados
        if (empty($lic['status']) || $lic['status'] !== 'active') {
            return [
                'ok'      => false,
                'code'    => 'licenca_inativa',
                'message' => __('Licença inativa. Ative ou revalide a licença no painel Plugins Alpha.', 'plugins-alpha'),
            ];
        }

        // 2) expirada
        if (!empty($lic['expires_at'])) {
            $ts = strtotime($lic['expires_at']);
            if ($ts && $ts < time()) {
                return [
                    'ok'      => false,
                    'code'    => 'licenca_expirada',
                    'message' => __('Licença expirada. Renove ou atualize sua assinatura.', 'plugins-alpha'),
                ];
            }
        }

        // 3) módulo não disponível no plano
        if ($module_slug !== '') {
            $modules = is_array($lic['modules'] ?? null) ? $lic['modules'] : [];
            if (!in_array($module_slug, $modules, true)) {
                return [
                    'ok'      => false,
                    'code'    => 'modulo_indisponivel',
                    'message' => __('Este módulo não está disponível no seu plano.', 'plugins-alpha'),
                ];
            }
        }

        return [
            'ok'      => true,
            'code'    => 'ok',
            'message' => '',
        ];
    }


    /**
     * Traduz status do Hotmart para o status interno da licença.
     * - $purchaseStatus: status da compra (APPROVED, COMPLETED, CANCELLED, REFUNDED, etc.)
     * - $subscriptionStatus: status da assinatura (ACTIVE, CANCELLED, EXPIRED, etc.), se existir
     *
     * Importante: o resto do plugin só considera "active" como licença válida.
     */
    public static function map_hotmart_status(string $purchaseStatus, string $subscriptionStatus = ''): string
    {
        $p = strtoupper(trim($purchaseStatus));
        $s = strtoupper(trim($subscriptionStatus));

        // Casos que derrubam a licença de cara (cancelado, estornado, chargeback etc.)
        if (in_array($p, ['REFUNDED', 'CHARGEBACK', 'CANCELLED'], true)) {
            return 'inactive';
        }

        if (in_array($s, ['CANCELLED', 'EXPIRED', 'SUSPENDED', 'INACTIVE'], true)) {
            return 'inactive';
        }

        // Casos em que consideramos a licença ativa
        if (in_array($p, ['APPROVED', 'COMPLETED', 'CONFIRMED', 'ACTIVE'], true)) {
            return 'active';
        }

        // Pagamento em análise / aguardando → tratamos como "pending" (não é ativa ainda)
        if (in_array($p, ['PENDING', 'WAITING_PAYMENT', 'IN_ANALYSIS', 'IN_PROCESS'], true)) {
            return 'pending';
        }

        // Fallback: qualquer coisa desconhecida é inativa
        return 'inactive';
    }


    /**
     * Atalho simples: retorna só boolean.
     * Ex.: PluginsAlpha_License::is_module_available('alpha_stories')
     */
    public static function is_module_available(string $module_slug): bool
    {
        $r = self::check($module_slug);
        return !empty($r['ok']);
    }


    public static function module_labels(): array
    {
        return [
            'stories'   => __('Alpha Stories', 'plugins-alpha'),
            'orion' => __('Alpha Órion', 'plugins-alpha'),
        ];
    }

    // ===================== Handlers =====================

    public static function handle_activate(): void
    {
        if (!current_user_can('manage_options')) wp_die('Sem permissão.');
        check_admin_referer('pga_activate_license');

        $email = isset($_POST['email'])
            ? sanitize_email(wp_unslash($_POST['email']))
            : '';

        $purchase_id = isset($_POST['purchase_id'])
            ? sanitize_text_field(wp_unslash($_POST['purchase_id']))
            : '';

        $license_key = isset($_POST['license_key'])
            ? sanitize_text_field(wp_unslash($_POST['license_key']))
            : '';


        if (!$email || !$purchase_id) {
            $msg = __('E-mail e ID da compra são obrigatórios.', 'plugins-alpha');
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => $msg], 400);
            }
            self::redirect_with_error($msg);
        }

        $domain = self::current_domain();

        $body = [
            'email'         => $email,
            'purchase_id'   => $purchase_id,
            'license_key'   => $license_key,
            'domain'        => $domain,
            'site_url'      => home_url('/'),
            'site_name'     => get_bloginfo('name'),
            'wp_version'    => get_bloginfo('version'),
            'php_version'   => PHP_VERSION,
            'plugin'        => 'plugins-alpha',
            'plugin_version' => '1.0.1',
        ];

        $res = self::remote_call('/client/activate', $body);

        // ERRO
        if (is_wp_error($res)) {
            $msg  = $res->get_error_message();
            $data = $res->get_error_data();
            if (wp_doing_ajax()) {
                wp_send_json_error([
                    'message' => $msg,
                    'data'    => $data,
                ], 400);
            }

            self::redirect_with_error($msg);
        }

        // Esperamos algo tipo: { ok: true, license: {...}, message: '...' }
        if (empty($res['ok']) || empty($res['license']) || !is_array($res['license'])) {
            $msg = isset($res['message']) ? (string)$res['message'] : __('Erro ao ativar licença (resposta inesperada).', 'plugins-alpha');
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => $msg, 'data' => $res], 400);
            }

            self::redirect_with_error($msg);
        }

        $l = $res['license'];

        $opt = [
            'license_key' => (string)($l['license_key'] ?? $license_key),
            'purchase_id' => (string)($l['purchase_id'] ?? $purchase_id),
            'email'       => (string)($l['email'] ?? $email),
            'status'      => (string)($l['status'] ?? 'active'),
            'plan'        => (string)($l['plan'] ?? ''),
            'modules'     => is_array($l['modules'] ?? null) ? $l['modules'] : [],
            'domains_used' => is_array($l['domains_used'] ?? null) ? $l['domains_used'] : [],
            'max_domains' => (int)($l['max_domains'] ?? 1),
            'expires_at'  => $l['expires_at'] ?? null,
            'last_check'  => current_time('mysql', true),
        ];

        update_option(self::OPTION_KEY, $opt);

        if (wp_doing_ajax()) {
            wp_send_json_success([
                'message' => $res['message'] ?? __('Licença ativada com sucesso.', 'plugins-alpha'),
                'license' => $opt,
            ]);
        }

        $location = add_query_arg(
            [
                'page'    => 'plugins-alpha-license',
                'updated' => 1,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($location);
        exit;
    }


    public static function handle_deactivate(): void
    {
        if (!current_user_can('manage_options')) wp_die('Sem permissão.');
        check_admin_referer('pga_deactivate_license');

        $lic    = self::get_state();
        $domain = self::current_domain();

        $body = [
            'email'       => $lic['email'],
            'purchase_id' => $lic['purchase_id'],
            'license_key' => $lic['license_key'],
            'domain'      => $domain,
        ];

        $res = self::remote_call('/client/deactivate', $body);

        delete_option(self::OPTION_KEY);

        $location = add_query_arg(
            [
                'page'    => 'plugins-alpha-license',
                'updated' => 1,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($location);
        exit;
    }

    private static function remote_call(string $endpoint, array $body)
    {
        $base = self::api_base();
        $url  = $base . $endpoint;
        $args = [
            'timeout' => 15,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => wp_json_encode($body),
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return new WP_Error(
                'pga_license_http',
                sprintf(
                    esc_html('Erro ao conectar ao servidor de licença: %s', 'plugins-alpha'),
                    $response->get_error_message()
                )
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        // Se não veio JSON ou código não é 2xx, gera um erro mais informativo
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            $snippet = mb_substr($raw, 0, 300);
            $msg = __('Resposta inválida do servidor de licença.', 'plugins-alpha') . ' (HTTP ' . $code . ')';

            return new WP_Error(
                'pga_license_bad_response',
                $msg,
                [
                    'code'        => $code,
                    'body_snippet' => $snippet,
                    'raw'         => $raw,
                ]
            );
        }
        return $json;
    }


    private static function redirect_with_error(string $msg): void
    {
        $location = add_query_arg(
            [
                'page'    => 'plugins-alpha-license',
                'updated' => 1,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($location);
        exit;
    }
}
