<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Utils {
    public static function clean($v){ return trim(wp_strip_all_tags((string)$v)); }

    public static function lines_to_array($txt){
        $a = preg_split('/\r\n|\r|\n/', (string)$txt);
        $a = array_filter(array_map('trim',$a));
        return array_values(array_unique($a));
    }

    /**
     * Gera timestamps futuros distribuídos aleatoriamente.
     * - $total: total de posts
     * - $per_day: quantos por dia
     * - $first_delay_hours: primeira publicação no mínimo após X horas
     */
    public static function random_schedule($total, $per_day, $first_delay_hours = 2){
        $out = [];
        $days = (int)ceil($total / max(1,$per_day));
        $now  = current_time('timestamp');
        $min_first = $now + max(2, intval($first_delay_hours)) * HOUR_IN_SECONDS;

        $made = 0;
        for ($d=0; $d<$days; $d++){
            $date_base = strtotime("+$d day", $now);
            $count_today = min($per_day, $total - $made);
            if ($count_today <= 0) break;

            $slots = [];
            for ($i=0; $i<$count_today; $i++){
                $h = wp_rand(9, 21);
                $m = wp_rand(0, 59);
                $slot = gmmktime($h, $m, 0, gmdate('m',$date_base), gmdate('d',$date_base), gmdate('Y',$date_base));
                $slot = $slot + ( get_option('gmt_offset') * HOUR_IN_SECONDS );
                $slots[] = $slot;
            }
            sort($slots, SORT_NUMERIC);

            foreach ($slots as $t){
                if (empty($out) && $t < $min_first) $t = $min_first;
                $out[] = $t;
                $made++;
            }
        }
        return array_slice($out, 0, $total);
    }
}
