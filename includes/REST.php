<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_REST
{

    // ---------------------- storage keywords ----------------------
    private static function get_opt($key, $default = [])
    {
        $opt = get_option($key, null);
        if ($opt === null) {
            update_option($key, $default, false);
            return $default;
        }
        return is_array($opt) ? $opt : $default;
    }
    private static function set_opt($key, $val)
    {
        update_option($key, is_array($val) ? array_values($val) : $val, false);
    }

    private static function kw_get_pending(): array
    {
        return self::get_opt('pga_keywords_pending', []);
    }
    private static function kw_get_done(): array
    {
        return self::get_opt('pga_keywords_done', []);
    }
    private static function kw_set_pending(array $a)
    {
        self::set_opt('pga_keywords_pending', self::unique_clean($a));
    }
    private static function kw_clear_pending()
    {
        self::set_opt('pga_keywords_pending', []);
    }
    private static function kw_clear_done()
    {
        self::set_opt('pga_keywords_done', []);
    }
    private static function kw_move_to_done_one(string $used)
    {
        if ($used === '') return;

        $pend = self::kw_get_pending();

        // helper compatível
        $lower = function ($s) {
            return function_exists('mb_strtolower')
                ? mb_strtolower($s, 'UTF-8')
                : strtolower($s);
        };

        // remove a keyword (case-insensitive)
        $usedL = $lower($used);
        $pend  = array_values(array_filter($pend, function ($k) use ($lower, $usedL) {
            return $lower($k) !== $usedL;
        }));
        self::kw_set_pending($pend);

        // adiciona em "done" se ainda não estiver
        $done = self::kw_get_done();
        $exists = false;
        foreach ($done as $d) {
            if ($lower($d) === $usedL) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $done[] = $used;
            self::set_opt('pga_keywords_done', $done);
        }
    }

    private static function unique_clean(array $arr): array
    {
        // 1) tira espaços
        $arr = array_map('trim', $arr);

        // 2) remove vazios (sem arrow function)
        $arr = array_filter($arr, function ($s) {
            return $s !== '';
        });

        // 3) de-duplicação case-insensitive
        $lower = function ($s) {
            return function_exists('mb_strtolower')
                ? mb_strtolower($s, 'UTF-8')
                : strtolower($s);
        };

        $seen = array();
        $out  = array();

        foreach ($arr as $s) {
            $k = $lower($s);
            if (!isset($seen[$k])) {
                $seen[$k] = 1;
                $out[] = $s;
            }
        }

        // 4) normaliza índices
        return array_values($out);
    }


    // ---------------------- utils ----------------------
    private static function verify_nonce($req)
    {
        $n = $req->get_header('X-WP-Nonce');
        if (!$n || !wp_verify_nonce($n, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Nonce inválido ou ausente.', ['status' => 403]);
        }
        return true;
    }
    private static function clean($s)
    {
        return sanitize_text_field((string)$s);
    }

    private static function lines_to_array($txt)
    {
        if (!is_string($txt) || $txt === '') return array();

        $lines = preg_split('/\r\n|\r|\n/', $txt);
        $lines = array_map('trim', $lines);

        // sem arrow function
        $lines = array_filter($lines, function ($s) {
            return $s !== '';
        });

        return self::unique_clean($lines);
    }


    private static function wp_error_to_string($err): string
    {
        if (!is_wp_error($err)) return '';
        $msg = $err->get_error_message();
        $code = $err->get_error_code();
        $data = $err->get_error_data();
        if (is_array($data) && isset($data['http_code'])) $msg .= sprintf(' (HTTP %s)', $data['http_code']);
        if ($code) $msg = "[$code] $msg";
        return $msg ?: 'Erro desconhecido';
    }

    private static function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log('[PluginsAlpha][REST] Exceção: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return new WP_Error('pga_exception', 'Exceção interna. Veja o error_log.', ['status' => 500]);
        }
    }

    // ---------------------- rotas ----------------------
    public static function register_routes()
    {
        register_rest_route('pga/v1', '/license/activate', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => [__CLASS__, 'activate'],
        ]);

        register_rest_route('pga/v1', '/license/status', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => [__CLASS__, 'status'],
        ]);

        register_rest_route('pga/v1', '/plan', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'plan'],
        ]);

        register_rest_route('pga/v1', '/generate', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'generate_single'],
        ]);

        register_rest_route('pga/v1', '/status', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'status_licence'],
        ]);

        register_rest_route('pga/v1', '/keywords', [
            'methods'  => 'GET',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'keywords_get'],
        ]);

        register_rest_route('pga/v1', '/keywords', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'keywords_save'],
        ]);

        register_rest_route('pga/v1', '/keywords/clear', [
            'methods'  => 'POST',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
            'callback' => [__CLASS__, 'keywords_clear'],
        ]);

        // --- self test da OpenAI (para tela de Configurações)
        register_rest_route('pga/v1', '/selftest', [
            'methods'  => ['GET', 'POST'], // mantém GET por compat, mas vamos usar POST
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
            'callback' => [__CLASS__, 'selftest'],
        ]);
    }

    public static function activate(WP_REST_Request $req)
    {
        $nonce = $req->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('rest_forbidden', 'Nonce inválido.', ['status' => 403]);
        }
        $p = $req->get_json_params();
        $email = sanitize_email($p['email'] ?? '');
        $pid   = sanitize_text_field($p['purchase_id'] ?? '');
        if (!$email || !$pid) return new WP_Error('pga_lic', 'Informe e-mail e ID da compra.', ['status' => 400]);

        $lic = PluginsAlpha_License::get_state($email, $pid);
        return ['ok' => PluginsAlpha_License::is_active($lic), 'license' => $lic];
    }

    public static function status_licence()
    {
        $lic = PluginsAlpha_License::get_state();
        return [
            'ok'      => PluginsAlpha_License::is_active(),
            'license' => $lic,
        ];
    }



    // ---------------------- PLAN: só planeja (rápido) ----------------------
    public static function plan($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
        return self::guard(function () use ($req) {
            $p       = $req->get_json_params();
            $mode    = (isset($p['mode']) && $p['mode'] === 'single') ? 'single' : 'multi';
            $kw_in   = self::lines_to_array($p['keywords'] ?? '');
            $url     = esc_url_raw($p['source_url'] ?? '');

            if ($mode === 'multi' && empty($kw_in)) {
                return new WP_Error('pga_kw', 'Informe palavras-chave (modo múltiplo).', ['status' => 400]);
            }
            if ($mode === 'single' && empty($kw_in) && empty($url)) {
                return new WP_Error('pga_kw', 'Informe ao menos 1 palavra ou uma URL (modo único).', ['status' => 400]);
            }

            $locale  = self::clean($p['locale'] ?? 'pt_BR');
            $tpl     = self::clean($p['template_key'] ?? 'article');
            $total   = max(1, intval($p['total'] ?? ($mode === 'single' ? 1 : count($kw_in))));
            $perDay  = max(1, intval($p['per_day'] ?? 3));
            $firstH  = max(2, intval($p['first_delay_hours'] ?? 2));

            $transition = [
                'strict'    => !empty($p['transition']['strict']),
                'min_ratio' => floatval($p['transition']['min_ratio'] ?? 0.3),
                'words'     => is_array($p['transition']['words'] ?? null)
                    ? array_values(array_filter(array_map('trim', $p['transition']['words'])))
                    : [],
            ];

            // monta agenda leve
            $jobs = [];
            $now  = time();
            $days = (int)ceil($total / max(1, $perDay));
            $i    = 0;
            $cat_id = max(0, intval($p['category_id'] ?? 0));
            for ($d = 0; $d < $days; $d++) {
                $slotsToday = min($perDay, $total - count($jobs));
                $base = [9 * 3600, 14 * 3600, 19 * 3600];
                for ($s = 0; $s < $slotsToday; $s++) {
                    $baseIdx = min($s, count($base) - 1);
                    $offset  = rand(-40 * 60, +40 * 60);
                    $t       = strtotime('+' . ($d) . ' day', $now) + $base[$baseIdx] + $offset;
                    if ($i === 0) {
                        $min = $now + $firstH * HOUR_IN_SECONDS;
                        if ($t < $min) $t = $min + rand(300, 2400);
                    }
                    $jobs[] = [
                        'keyword'      => ($mode === 'single' ? ($kw_in[0] ?? '') : ($kw_in[$i] ?? '')),
                        'locale'       => $locale,
                        'template_key' => $tpl,
                        'source_url'   => $url,
                        'publish_time' => $t,
                        'transition'   => $transition,
                        'category_id'  => $cat_id,
                    ];
                    $i++;
                    if (count($jobs) >= $total) break;
                }
            }

            if ($mode === 'multi' && count($kw_in) < $total) {
                $jobs = array_slice($jobs, 0, count($kw_in));
            }
            return [
                'ok'   => true,
                'mode' => $mode,
                'total_requested' => $total,
                'jobs' => $jobs,
                'available_keywords' => count($kw_in),
            ];
        });
    }

    // ---------------------- GENERATE: gera 1 por requisição -------------
    public static function generate_single($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        return self::guard(function () use ($req) {

            // 0) VERIFICA LICENÇA / MÓDULO ANTES DE GERAR
            if (class_exists('PluginsAlpha_License')) {
                $chk = PluginsAlpha_License::check('post-gpt');

                if (empty($chk['ok'])) {
                    return new WP_Error(
                        $chk['code'] ?: 'pga_lic',
                        $chk['message'] ?: __('Licença inválida ou módulo não disponível.', 'plugins-alpha'),
                        [
                            'status' => 403,
                            // se quiser, manda o code também nos dados:
                            'code'   => $chk['code'] ?? '',
                        ]
                    );
                }
            }

            // 1) PARAMS
            $p   = $req->get_json_params();
            $kw  = self::clean($p['keyword'] ?? '');
            $url = esc_url_raw($p['source_url'] ?? '');

            if ($kw === '' && $url === '') {
                return new WP_Error('pga_kw', 'Informe keyword ou URL.', ['status' => 400]);
            }

            $cat_id = max(0, intval($p['category_id'] ?? 0));

            if (!class_exists('PluginsAlpha_Pages_Generator')) {
                return new WP_Error('pga_no_generator', 'Classe Generator ausente.', ['status' => 500]);
            }

            $args = [
                'keywords'     => $kw ? [$kw] : [''],
                'locale'       => self::clean($p['locale'] ?? 'pt_BR'),
                'template'     => self::clean($p['template_key'] ?? 'discover_article'),
                'source_url'   => $url,
                'publish_time' => max(time() + 2 * HOUR_IN_SECONDS, intval($p['publish_time'] ?? time() + 2 * HOUR_IN_SECONDS)),
                'transition'   => [
                    'strict'    => !empty($p['transition']['strict']),
                    'min_ratio' => floatval($p['transition']['min_ratio'] ?? 0.3),
                    'words'     => is_array($p['transition']['words'] ?? null)
                        ? array_values(array_filter(array_map('trim', $p['transition']['words'])))
                        : [],
                ],
                'category_id'  => $cat_id,
                // força nosso CPT
                'post_type'    => 'posts_gpt',
            ];

            $res = PluginsAlpha_Pages_Generator::generate_and_insert(
                $args,
                class_exists('PluginsAlpha_Settings') ? PluginsAlpha_Settings::get() : []
            );

            if (is_wp_error($res)) {
                $edata  = $res->get_error_data();
                $status = is_array($edata) && isset($edata['status']) ? (int)$edata['status'] : 400;
                $msg    = $res->get_error_message() ?: self::wp_error_to_string($res);
                error_log('[PluginsAlpha][REST]/generate erro: ' . $msg . ' | status=' . $status);

                // devolve postId do “rascunho-inicial” se existir
                $pId = is_array($edata) && !empty($edata['post_id']) ? (int)$edata['post_id'] : 0;

                return new WP_Error(
                    $res->get_error_code() ?: 'pga_generate',
                    $msg,
                    ['status' => $status, 'postId' => $pId]
                );
            }

            if ($kw) self::kw_move_to_done_one($kw);

            return [
                'ok'      => true,
                'post_id' => $res['post_id'],
                'edit'    => get_edit_post_link($res['post_id'], ''),
                'view'    => $res['view_link'],
                'state'   => [
                    'pending' => self::kw_get_pending(),
                    'done'    => self::kw_get_done(),
                ],
            ];
        });
    }



    public static function status()
    {
        return ['ok' => true, 'time' => current_time('mysql')];
    }

    // ---------------------- keywords ----------------------
    public static function keywords_get($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
        return [
            'ok' => true,
            'pending' => self::kw_get_pending(),
            'done'   => self::kw_get_done(),
        ];
    }

    public static function keywords_save($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
        return self::guard(function () use ($req) {
            $p           = $req->get_json_params();
            $pending_txt = (string)($p['pending_text'] ?? '');
            $pending     = self::lines_to_array($pending_txt);
            self::kw_set_pending($pending);
            return [
                'ok' => true,
                'pending' => self::kw_get_pending(),
                'done'   => self::kw_get_done(),
            ];
        });
    }

    public static function keywords_clear($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;
        return self::guard(function () use ($req) {
            $p   = $req->get_json_params();
            $who = self::clean($p['who'] ?? 'pending');
            if ($who === 'done') self::kw_clear_done();
            else self::kw_clear_pending();
            return [
                'ok' => true,
                'pending' => self::kw_get_pending(),
                'done'   => self::kw_get_done(),
            ];
        });
    }

    // ---------------------- diagnóstico da OpenAI ----------------------
    public static function selftest($req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $p = $req->get_json_params();
        if (!is_array($p)) $p = [];

        $opt       = PluginsAlpha_Settings::get();
        $key       = trim($p['key'] ?? ($opt['apis']['openai']['key'] ?? ''));
        $model     = trim($p['model'] ?? ($opt['apis']['openai']['model_text'] ?? 'gpt-4o-mini'));
        $temp      = floatval($p['temperature'] ?? ($opt['apis']['openai']['temperature'] ?? 0.6));
        $maxTokens = intval($p['max_tokens'] ?? ($opt['apis']['openai']['max_tokens'] ?? 512));

        if (!$key) {
            return new WP_Error('agp_no_key', 'Nenhuma chave OpenAI informada.', ['status' => 400]);
        }

        $args = [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $key,
            ],
            'timeout' => 20,
            'body'    => json_encode([
                'model'       => $model,
                'messages'    => [['role' => 'user', 'content' => 'Responda apenas: pong']],
                'temperature' => $temp,
                'max_tokens'  => max(16, min($maxTokens, 256)), // limite baixo só pro teste
            ]),
        ];

        $t0 = microtime(true);
        $r  = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        $ms = round((microtime(true) - $t0) * 1000);

        if (is_wp_error($r)) {
            return new WP_Error('agp_http', $r->get_error_message(), ['status' => 500]);
        }

        $code = wp_remote_retrieve_response_code($r);
        $body = json_decode(wp_remote_retrieve_body($r), true);

        if ($code !== 200) {
            $msg = $body['error']['message'] ?? 'HTTP ' . $code;
            return new WP_Error('agp_api_fail', $msg, ['status' => $code]);
        }

        $text = (string)($body['choices'][0]['message']['content'] ?? '');
        $ok   = (stripos($text, 'pong') !== false);

        return [
            'ok'        => $ok,
            'latencyMs' => $ms,
            'model'     => $model,
            'sample'    => $text,
        ];
    }
}
