<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Keywords {
    const OPT_PENDING = 'pga_kw_pending';
    const OPT_DONE    = 'pga_kw_done';

    /** Bootstrap */
    public static function init(): void {
        add_action('admin_init',    [__CLASS__, 'register']);
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** Registra opções (para aparecer em settings API e ter defaults) */
    public static function register(): void {
        register_setting('plugins_alpha', self::OPT_PENDING, ['type'=>'array','default'=>[]]);
        register_setting('plugins_alpha', self::OPT_DONE,    ['type'=>'array','default'=>[]]);

        // garante opções criadas
        if (get_option(self::OPT_PENDING, null) === null) add_option(self::OPT_PENDING, [], '', false);
        if (get_option(self::OPT_DONE,    null) === null) add_option(self::OPT_DONE,    [], '', false);
    }

    /** ===== getters/setters utilitários ===== */
    public static function get_pending(): array {
        $a = get_option(self::OPT_PENDING, []);
        return is_array($a) ? array_values(array_unique(array_filter(array_map('trim', $a)))) : [];
    }

    public static function get_done(): array {
        $a = get_option(self::OPT_DONE, []);
        return is_array($a) ? array_values(array_unique(array_filter(array_map('trim', $a)))) : [];
    }

    public static function set_pending(array $list): void {
        $list = array_values(array_unique(array_filter(array_map('trim', $list))));
        update_option(self::OPT_PENDING, $list, false);
    }

    public static function set_done(array $list): void {
        $list = array_values(array_unique(array_filter(array_map('trim', $list))));
        update_option(self::OPT_DONE, $list, false);
    }

    public static function add_done(array $used): void {
        $done = self::get_done();
        $mix  = array_values(array_unique(array_merge($done, array_map('trim', $used))));
        update_option(self::OPT_DONE, $mix, false);
    }

    public static function move_to_done(array $used): void {
        if (empty($used)) return;

        // normaliza lista usada -> lookup
        $usedSet = array_flip(array_map(function($v){
            return trim((string)$v);
        }, $used));

        // remove dos pendentes tudo que está em $usedSet
        $pending = self::get_pending();
        $pending = array_values(array_filter($pending, function($kw) use ($usedSet) {
            $kw = trim((string)$kw);
            return ($kw !== '') && !isset($usedSet[$kw]);
        }));

        self::set_pending($pending);
        self::add_done($used);
    }

    public static function clear_pending(): void { delete_option(self::OPT_PENDING); add_option(self::OPT_PENDING, [], '', false); }
    public static function clear_done(): void    { delete_option(self::OPT_DONE);    add_option(self::OPT_DONE,    [], '', false); }

    /** ===== REST ===== */
    public static function register_routes(): void {
        $ns = 'plugins-alpha/v1';

        // GET /keywords/pending
        register_rest_route($ns, '/keywords/pending', [
            'methods'  => 'GET',
            'callback' => function () {
                return rest_ensure_response(['items' => self::get_pending()]);
            },
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);

        // GET /keywords/done
        register_rest_route($ns, '/keywords/done', [
            'methods'  => 'GET',
            'callback' => function () {
                return rest_ensure_response(['items' => self::get_done()]);
            },
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);

        // POST /keywords/pending  (substitui a lista inteira)
        register_rest_route($ns, '/keywords/pending', [
            'methods'  => 'POST',
            'callback' => function(\WP_REST_Request $req){
                $list = self::sanitize_list($req->get_param('items'));
                self::set_pending($list);
                return rest_ensure_response(['ok'=>true,'items'=> self::get_pending()]);
            },
            'args' => ['items' => ['required'=>true]],
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);

        // POST /keywords/done  (substitui a lista inteira)
        register_rest_route($ns, '/keywords/done', [
            'methods'  => 'POST',
            'callback' => function(\WP_REST_Request $req){
                $list = self::sanitize_list($req->get_param('items'));
                self::set_done($list);
                return rest_ensure_response(['ok'=>true,'items'=> self::get_done()]);
            },
            'args' => ['items' => ['required'=>true]],
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);

        // POST /keywords/move-to-done  (move itens usados)
        register_rest_route($ns, '/keywords/move-to-done', [
            'methods'  => 'POST',
            'callback' => function(\WP_REST_Request $req){
                $used = self::sanitize_list($req->get_param('items'));
                self::move_to_done($used);
                return rest_ensure_response([
                    'ok'      => true,
                    'pending' => self::get_pending(),
                    'done'    => self::get_done(),
                ]);
            },
            'args' => ['items' => ['required'=>true]],
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);

        // DELETE /keywords/pending
        register_rest_route($ns, '/keywords/pending', [
            'methods'  => 'DELETE',
            'callback' => function(){
                self::clear_pending();
                return rest_ensure_response(['ok'=>true,'items'=> self::get_pending()]);
            },
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);

        // DELETE /keywords/done
        register_rest_route($ns, '/keywords/done', [
            'methods'  => 'DELETE',
            'callback' => function(){
                self::clear_done();
                return rest_ensure_response(['ok'=>true,'items'=> self::get_done()]);
            },
            'permission_callback' => [__CLASS__, 'can_manage'],
        ]);
    }

    /** Permissão: só admins/editores com manage_options */
    public static function can_manage(): bool {
        return current_user_can('manage_options');
    }

    /** Sanitiza entrada “items” (array ou string separada por linhas) */
    private static function sanitize_list($raw): array {
        if (is_string($raw)) {
            $raw = preg_split('/\r\n|\r|\n/', $raw);
        }
        if (!is_array($raw)) $raw = [];
        return array_values(array_unique(array_filter(array_map('trim', $raw))));
    }
}
