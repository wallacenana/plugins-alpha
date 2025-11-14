<?php
if (!defined('ABSPATH')) exit;

class PluginsAlpha_Server {
    // TROQUE pela sua URL (https + domínio seu)
    public static function base(): string {
        return apply_filters('pga_server_base', 'https://pluginsalpha.com');
    }
    public static function license_activate_endpoint(): string {
        return trailingslashit(self::base()).'api/license/activate';
    }
    public static function license_status_endpoint(): string {
        return trailingslashit(self::base()).'api/license/status';
    }
    public static function updates_endpoint(): string {
        return trailingslashit(self::base()).'api/updates';
    }
}
