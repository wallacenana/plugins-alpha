<?php
if (!defined('ABSPATH')) exit;

class AlphaSuite_CRON
{
    // =========================
    // DISPATCHER (cron principal)
    // =========================
    public static function dispatch()
    {
        global $wpdb;

        $now  = wp_date('Y-m-d H:i:s');
        $hour = (int) current_time('H');

        $table_g = esc_sql($wpdb->prefix . 'pga_generators');
        $table_r = esc_sql($wpdb->prefix . 'pga_generator_runtime');

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $sql = $wpdb->prepare(
            "
        SELECT g.id, g.start_hour, g.end_hour, r.next_run
        FROM {$table_g} g
        INNER JOIN {$table_r} r
            ON r.generator_id = g.id
        WHERE g.active = %d
        AND r.next_run <= %s
        ",
            1,
            $now
        );
        $rows = $wpdb->get_results($sql);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

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

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "
        SELECT g.*, c.config_json
        FROM {$wpdb->prefix}pga_generators g
        JOIN {$wpdb->prefix}pga_generator_config c
          ON c.generator_id = g.id
        WHERE g.id = %d
        ",
                $generator_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if (!$row) {
            return;
        }

        $config = json_decode($row->config_json, true);

        if (!$config) {
            return;
        }

        $feedUrl = trim($config['keywords'] ?? '');

        if (!$feedUrl) {
            self::update_runtime($generator_id, 'feed_url_missing');
            return;
        }

        // 🔥 Chama o pipeline único
        AlphaSuite_RESTRSS::process_feed($feedUrl, $generator_id);

        // 🔥 Atualiza runtime como executado
        self::update_runtime($generator_id, 'executed');
    }

    private static function update_runtime($generator_id, $status)
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $generator = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT interval_hours 
             FROM {$wpdb->prefix}pga_generators 
             WHERE id = %d",
                $generator_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        $interval = intval($generator->interval_hours ?? 1);
        if ($interval < 1) {
            $interval = 1;
        }

        // 🔥 Se não gerou nada, tenta de novo em 30 min
        if (in_array($status, ['feed_empty', 'no_new_items'])) {
            $seconds = 30 * MINUTE_IN_SECONDS;
        } else {
            $seconds = $interval * HOUR_IN_SECONDS;
        }

        $next = wp_date(
            'Y-m-d H:i:s',
            current_time('timestamp') + $seconds
        );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            "{$wpdb->prefix}pga_generator_runtime",
            [
                'last_run'    => current_time('mysql'),
                'next_run'    => $next,
                'last_status' => $status,
            ],
            ['generator_id' => $generator_id]
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
