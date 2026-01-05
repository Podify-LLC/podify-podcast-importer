<?php
namespace PodifyPodcast\Core;

class Settings {
    const OPTION = 'podify_podcast_settings';
    public static function defaults() {
        return [
            'sticky_player_enabled' => 0,
            'sticky_player_position' => 'bottom',
            'custom_css' => '',
        ];
    }
    public static function get() {
        $opts = get_option(self::OPTION, []);
        if (!is_array($opts)) $opts = [];
        $defaults = self::defaults();
        foreach ($defaults as $k=>$v) {
            if (!array_key_exists($k, $opts)) {
                $opts[$k] = $v;
            }
        }
        return $opts;
    }
    public static function update($data) {
        $current = self::get();
        $merged = array_merge($current, is_array($data) ? $data : []);
        update_option(self::OPTION, $merged);
        return $merged;
    }
}
