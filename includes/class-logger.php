<?php
namespace PodifyPodcast\Core;

class Logger {
    public static function log($m) {
        // Only log info messages if explicitly enabled via constant
        if (defined('PODIFY_DEBUG_MODE') && PODIFY_DEBUG_MODE) {
            error_log('[Podify Info] ' . $m);
        }
    }

    public static function error($m) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Podify Error] ' . $m);
        }
    }
}
