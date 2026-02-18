<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_CRON
{
    // =========================
    // DISPATCHER (cron principal)
    // =========================
    public static function dispatch()
    {
        error_log('DISPATCH rodando...');

        global $wpdb;

        $now  = current_time('mysql');
        $hour = (int) current_time('H');

        $sql = $wpdb->prepare(
            "
        SELECT g.id, g.start_hour, g.end_hour, r.next_run
        FROM {$wpdb->prefix}pga_generators g
        INNER JOIN {$wpdb->prefix}pga_generator_runtime r
            ON r.generator_id = g.id
        WHERE g.active = %d
        AND r.next_run <= %s
        ",
            1,
            $now
        );

        $rows = $wpdb->get_results($sql);
        if (empty($rows)) {
            return;
        }

        foreach ($rows as $row) {

            $start = (int) $row->start_hour;
            $end   = (int) $row->end_hour;

            if ($hour < $start || $hour > $end) {
                continue;
            }

            self::run_generator((int) $row->id);
        }
    }

    // =========================
    // EXECUTA 1 GERADOR
    // =========================
    public static function run_generator($generator_id)
    {
        global $wpdb;

        // 1️⃣ Buscar config
        $row = $wpdb->get_row(
            $wpdb->prepare("
            SELECT g.*, c.config_json
            FROM {$wpdb->prefix}pga_generators g
            JOIN {$wpdb->prefix}pga_generator_config c
              ON c.generator_id = g.id
            WHERE g.id = %d
        ", $generator_id)
        );

        if (!$row) return;

        $config = json_decode($row->config_json, true);
        if (!$config) return;

        $feedUrl = trim($config['keywords'] ?? '');
        if (!$feedUrl) {
            self::update_runtime($generator_id, 'feed_url_missing');
            return;
        }

        // 🔥 2️⃣ Busca os 20 mais recentes do feed
        $feedItems = PluginsAlpha_RESTRSS::fetch_feed_items($feedUrl, 20);

        if (empty($feedItems)) {
            self::update_runtime($generator_id, 'feed_empty');
            return;
        }

        // 🔥 3️⃣ Percorre do mais recente para o mais antigo
        foreach ($feedItems as $item) {

            // Verifica se já foi gerado
            $exists = $wpdb->get_var(
                $wpdb->prepare("
                SELECT id FROM {$wpdb->prefix}pga_generator_items
                WHERE generator_id = %d
                AND keyword = %s
                AND status = 'done'
                LIMIT 1
            ", $generator_id, $item['hash'])
            );

            if ($exists) {
                continue; // já gerado
            }

            // 🔥 GERAR ESSE
            $postId = PluginsAlpha_RESTRSS::create_base_post($item);

            PluginsAlpha_RESTRSS::generate_title($postId);
            PluginsAlpha_RESTRSS::generate_slug($postId);
            PluginsAlpha_RESTRSS::generate_meta($postId);

            $outline = PluginsAlpha_RESTRSS::generate_outline($postId);

            if (!is_wp_error($outline)) {

                $sections = $outline['sections'] ?? [];

                foreach ($sections as $sec) {
                    if (!empty($sec['id'])) {
                        PluginsAlpha_RESTRSS::generate_section($postId, (string)$sec['id']);
                    }
                }
            }

            PluginsAlpha_RESTRSS::finalize($postId);

            if (!empty($item['link'])) {
                PluginsAlpha_RESTRSS::extract_image($postId, $item['link']);
            }

            // 🔥 Salva como done
            $wpdb->insert(
                "{$wpdb->prefix}pga_generator_items",
                [
                    'generator_id' => $generator_id,
                    'keyword'      => $item['hash'],
                    'status'       => 'done',
                    'post_id'      => $postId,
                    'created_at'   => current_time('mysql'),
                    'generated_at' => current_time('mysql')
                ]
            );

            self::update_runtime($generator_id, 'generated');

            return; // 👈 GERA SÓ 1 POR CRON
        }

        // Se chegou aqui, todos já foram gerados
        self::update_runtime($generator_id, 'no_new_items');
    }

    private static function update_runtime($generator_id, $status)
    {
        global $wpdb;

        $generator = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT interval_hours 
             FROM {$wpdb->prefix}pga_generators 
             WHERE id = %d",
                $generator_id
            )
        );

        $interval = intval($generator->interval_hours ?? 1);
        if ($interval < 1) {
            $interval = 1;
        }

        $next = date(
            'Y-m-d H:i:s',
            current_time('timestamp') + ($interval * HOUR_IN_SECONDS)
        );

        $wpdb->update(
            "{$wpdb->prefix}pga_generator_runtime",
            [
                'last_run'    => current_time('mysql'),
                'next_run'    => $next,
                'last_status' => $status
            ],
            ['generator_id' => $generator_id]
        );
    }
}
