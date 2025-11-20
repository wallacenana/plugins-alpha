<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_REST
{

    private static function set_opt($key, $val)
    {
        update_option($key, is_array($val) ? array_values($val) : $val, false);
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

    protected static function kw_get_pending()
    {
        $raw = get_option('pga_kw_pending', '');
        $lines = preg_split('/\r\n|\r|\n/', (string)$raw);
        $lines = array_values(array_filter(array_map('trim', $lines)));
        return $lines;
    }

    protected static function kw_get_done()
    {
        $raw = get_option('pga_kw_done', '');
        $lines = preg_split('/\r\n|\r|\n/', (string)$raw);
        $lines = array_values(array_filter(array_map('trim', $lines)));
        return $lines;
    }

    /**
     * Move UMA keyword da lista de pendentes para a lista de concluídas
     */
    protected static function kw_move_to_done_one(string $kw)
    {
        $kw = trim($kw);
        if ($kw === '') return;

        $pending = self::kw_get_pending();
        $done    = self::kw_get_done();

        // remove da pending
        $pending = array_values(array_filter($pending, function ($item) use ($kw) {
            return mb_strtolower($item) !== mb_strtolower($kw);
        }));

        // adiciona na done, se ainda não existir
        $exists = false;
        foreach ($done as $d) {
            if (mb_strtolower($d) === mb_strtolower($kw)) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $done[] = $kw;
        }

        update_option('pga_kw_pending', implode("\n", $pending));
        update_option('pga_kw_done',    implode("\n", $done));
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

    private static function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return new WP_Error('pga_exception', 'Exceção interna.', ['status' => 500]);
        }
    }

    // ---------------------- rotas ----------------------
    public static function register_routes()
    {
        register_rest_route('pga/v1', '/orion/outline', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_outline'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // 2) Gera UMA seção do esboço
        register_rest_route('pga/v1', '/orion/section', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_section'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

        // 3) Junta tudo e finaliza o post
        register_rest_route('pga/v1', '/orion/finalize', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_finalize'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            },
        ]);

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
    public static function permission()
    {
        return current_user_can('edit_posts');
    }
    /**
     * POST /wp-json/pga/v1/orion/outline
     * Body: { keywords: [...], length, template, locale, source_url, publish_time, category_id, post_type }
     */
    public static function handle_outline(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $params = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params(); // fallback pra form-urlencoded
        }

        // Gera rascunho + outline
        $res = PluginsAlpha_Pages_Generator::create_draft_and_outline($params);

        if (is_wp_error($res)) {
            return $res;
        }

        return rest_ensure_response($res);
    }

    /**
     * POST /wp-json/pga/v1/orion/section
     * Body: { post_id: 123, section_id: "1" }
     */
    public static function handle_section(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $params     = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params();
        }

        $post_id    = intval($params['post_id'] ?? 0);
        $section_id = (string)($params['section_id'] ?? '');

        if (!$post_id || $section_id === '') {
            return new WP_Error(
                'pga_bad_request',
                __('post_id ou section_id ausentes.', 'plugins-alpha'),
                ['status' => 400]
            );
        }

        $res = PluginsAlpha_Pages_Generator::generate_section_content($post_id, $section_id);
        if (is_wp_error($res)) {
            return $res;
        }

        return rest_ensure_response($res);
    }


    /**
     * POST /wp-json/pga/v1/orion/finalize
     * Body: { post_id: 123 }
     */
    public static function handle_finalize(WP_REST_Request $req)
    {
        $v = self::verify_nonce($req);
        if (is_wp_error($v)) return $v;

        $params = $req->get_json_params();
        if (empty($params)) {
            $params = $req->get_params();
        }

        $post_id = intval($params['post_id'] ?? 0);
        if (!$post_id) {
            return new WP_Error(
                'pga_bad_request',
                __('post_id ausente.', 'plugins-alpha'),
                ['status' => 400]
            );
        }

        // 1) Finaliza via Generator
        $res = PluginsAlpha_Pages_Generator::finalize_from_sections($post_id);

        if (is_wp_error($res)) {
            return $res;
        }

        // 2) Descobre a keyword usada nesse job
        $keyword = '';
        if (is_array($res) && !empty($res['keyword'])) {
            $keyword = (string)$res['keyword'];
        } else {
            $keyword = (string)get_post_meta($post_id, '_pga_outline_keyword', true);
        }

        // 3) Atualiza listas de palavras (pending → done), se os helpers existirem
        $state = null;

        if ($keyword !== '' && method_exists(__CLASS__, 'kw_move_to_done_one')) {
            self::kw_move_to_done_one($keyword);
        }

        if (method_exists(__CLASS__, 'kw_get_pending') && method_exists(__CLASS__, 'kw_get_done')) {
            $state = [
                'pending' => self::kw_get_pending(),
                'done'    => self::kw_get_done(),
            ];
        }

        if ($state !== null) {
            $res['state'] = $state;
        }

        // 4) Resposta final pro JS (mantém compatibilidade)
        return rest_ensure_response($res);
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
            $p     = $req->get_json_params();
            $mode  = (isset($p['mode']) && $p['mode'] === 'single') ? 'single' : 'multi';

            // keywords em linhas → array
            $kw_in = self::lines_to_array($p['keywords'] ?? '');
            $url   = esc_url_raw($p['source_url'] ?? '');

            // template / length / locale
            $locale = self::clean($p['locale'] ?? 'pt_BR');
            $tpl    = self::clean($p['template_key'] ?? 'article');
            $length = self::clean($p['length'] ?? 'short');

            $isModelar = ($tpl === 'modelar');

            /**
             * VALIDAÇÃO
             *  - modo normal: exige keywords (multi) ou keyword+url (single)
             *  - modo "modelar": aceita só URL, ou URL + keywords; se os dois vazios → erro
             */
            if (! $isModelar) {
                if (empty($url)) {
                    if ($mode === 'multi' && empty($kw_in)) {
                        return new WP_Error(
                            'pga_kw',
                            'Informe palavras-chave (modo múltiplo).',
                            ['status' => 400]
                        );
                    }
                    if ($mode === 'single' && empty($kw_in) && empty($url)) {
                        return new WP_Error(
                            'pga_kw',
                            'Informe ao menos 1 palavra ou uma URL (modo único).',
                            ['status' => 400]
                        );
                    }
                }
            } else {
                // modelar: precisa de URL OU de pelo menos 1 palavra-chave
                if (empty($url) && empty($kw_in)) {
                    return new WP_Error(
                        'pga_kw',
                        'Para modelar, informe uma URL ou pelo menos 1 palavra-chave.',
                        ['status' => 400]
                    );
                }
            }

            $total  = max(1, intval($p['total'] ?? ($mode === 'single' ? 1 : count($kw_in))));
            $perDay = max(1, intval($p['per_day'] ?? 3));
            $firstH = max(2, intval($p['first_delay_hours'] ?? 2));

            $transition = [
                'strict'    => !empty($p['transition']['strict']),
                'min_ratio' => floatval($p['transition']['min_ratio'] ?? 0.3),
                'words'     => is_array($p['transition']['words'] ?? null)
                    ? array_values(array_filter(array_map('trim', $p['transition']['words'])))
                    : [],
            ];

            // monta agenda leve
            $jobs   = [];
            $now    = time();
            $days   = (int) ceil($total / max(1, $perDay));
            $i      = 0;
            $cat_id = max(0, intval($p['category_id'] ?? 0));

            for ($d = 0; $d < $days; $d++) {
                $slotsToday = min($perDay, $total - count($jobs));
                $base       = [9 * 3600, 14 * 3600, 19 * 3600];

                for ($s = 0; $s < $slotsToday; $s++) {
                    $baseIdx = min($s, count($base) - 1);
                    $offset  = wp_rand(-40 * MINUTE_IN_SECONDS, 40 * MINUTE_IN_SECONDS);
                    $t       = strtotime('+' . $d . ' day', $now) + $base[$baseIdx] + $offset;

                    if ($i === 0) {
                        $min = $now + $firstH * HOUR_IN_SECONDS;
                        if ($t < $min) {
                            $t = $min + wp_rand(300, 2400);
                        }
                    }

                    // escolhe keyword para este job
                    $keywordValue = '';
                    if ($mode === 'single') {
                        $keywordValue = $kw_in[0] ?? '';
                    } else {
                        $keywordValue = $kw_in[$i] ?? '';
                    }

                    // no template "modelar", é permitido keyword vazia;
                    // o create_draft_and_outline depois deriva da URL se necessário.
                    $jobs[] = [
                        'keyword'      => $keywordValue,
                        'locale'       => $locale,
                        'length'       => $length,
                        'template_key' => $tpl,
                        'source_url'   => $url,
                        'publish_time' => $t,
                        'transition'   => $transition,
                        'category_id'  => $cat_id,
                    ];

                    $i++;
                    if (count($jobs) >= $total) {
                        break;
                    }
                }
            }

            // CORTE DE JOBS QUANDO FALTAM KEYWORDS
            // - fluxo antigo: se pediu 10 posts mas só mandou 3 keywords, reduzia para 3
            // - EXCETO no template "modelar" sem keywords, onde queremos manter os jobs
            if ($mode === 'multi' && count($kw_in) < $total && ! $isModelar) {
                $jobs = array_slice($jobs, 0, count($kw_in));
            }

            return [
                'ok'                 => true,
                'mode'               => $mode,
                'total_requested'    => $total,
                'jobs'               => $jobs,
                'available_keywords' => count($kw_in),
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
