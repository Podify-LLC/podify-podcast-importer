<?php
namespace PodifyPodcast\Core;

class Settings {
    const OPTION = 'podify_podcast_settings';
    public static function defaults() {
        return [
            'sticky_player_enabled' => 0,
            'sticky_player_position' => 'bottom',
            'read_more_text' => 'Read more',
            'load_more_text' => 'Load more',
            'card_bg_color' => '#ffffff',
            'card_bg_color_2' => '',
            'card_bg_gradient_direction' => 'to bottom',
            'button_bg_color' => '#0b5bd3',
            'button_text_color' => '#ffffff',
            'load_more_bg_color' => '#0b5bd3',
            'load_more_text_color' => '#ffffff',
            'load_more_bg_hover_color' => '#0948a8',
            'load_more_text_hover_color' => '#ffffff',
            'title_font' => '',
            'title_letter_spacing' => '',
            'title_line_height' => '',
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
