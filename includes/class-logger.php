<?php
namespace PodifyPodcast\Core;

class Logger {
    public static function log($m) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Podify] ' . $m);
        }
    }
}
