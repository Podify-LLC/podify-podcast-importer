<?php
namespace PodifyPodcast\Core\API;

class RestInit {
    public static function register() {
        add_action('rest_api_init',[self::class,'routes']);
    }
    public static function routes() {
        register_rest_route('podify/v1','/sync',[
            'methods' => 'POST',
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                if (!$feed_id) {
                    return ['ok' => false, 'message' => 'Missing feed_id'];
                }
                $res = \PodifyPodcast\Core\Importer::import_feed($feed_id);
                return $res;
            }
        ]);
        register_rest_route('podify/v1','/resync',[
            'methods' => 'POST',
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                if (!$feed_id) {
                    return ['ok' => false, 'message' => 'Missing feed_id'];
                }
                $res = \PodifyPodcast\Core\Importer::resync_feed($feed_id);
                return $res;
            }
        ]);
        register_rest_route('podify/v1','/episodes',[
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                $limit = intval($req->get_param('limit'));
                $offset = intval($req->get_param('offset'));
                $category_id = intval($req->get_param('category_id'));
                $q = (string)$req->get_param('q');
                $has_audio = (bool)$req->get_param('has_audio');
                $orderby = (string)$req->get_param('orderby');
                $order = (string)$req->get_param('order');
                if ($limit <= 0 || $limit > 500) $limit = 9;
                if ($offset < 0) $offset = 0;
                $use_adv = ($q !== '') || $has_audio || ($category_id > 0) || ($orderby !== '') || ($order !== '');
                if ($use_adv) {
                    $opts = [
                        'feed_id' => $feed_id ?: null,
                        'limit' => $limit,
                        'offset' => $offset,
                        'category_id' => $category_id ?: null,
                        'q' => $q,
                        'has_audio' => $has_audio,
                        'orderby' => $orderby,
                        'order' => $order
                    ];
                    $rows = \PodifyPodcast\Core\Database::get_episodes_advanced($opts);
                    $total = \PodifyPodcast\Core\Database::count_episodes_advanced($opts);
                } else {
                    $rows = \PodifyPodcast\Core\Database::get_episodes($feed_id ?: null, $limit, $offset, null);
                    $total = \PodifyPodcast\Core\Database::count_episodes($feed_id ?: null, $category_id ?: null);
                }
                $items = [];
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        $pid = !empty($r['post_id']) ? intval($r['post_id']) : 0;
                        $audio = !empty($r['audio_url']) && wp_http_validate_url($r['audio_url']) ? esc_url($r['audio_url']) : '';
                        $image = !empty($r['image_url']) && wp_http_validate_url($r['image_url']) ? esc_url($r['image_url']) : '';
                        if ($pid > 0) {
                            $maudio = get_post_meta($pid, '_podify_audio_url', true);
                            $mimage = get_post_meta($pid, '_podify_episode_image', true);
                            if (!empty($maudio) && wp_http_validate_url($maudio)) { $audio = esc_url($maudio); }
                            if (!empty($mimage) && wp_http_validate_url($mimage)) { $image = esc_url($mimage); }
                        }
                        $items[] = [
                            'id' => intval($r['id']),
                            'post_id' => $pid,
                            'feed_id' => intval($r['feed_id']),
                            'title' => is_string($r['title']) ? $r['title'] : '',
                            'description' => is_string($r['description']) ? $r['description'] : '',
                            'audio_url' => $audio,
                            'image_url' => $image,
                            'duration' => is_string($r['duration']) ? $r['duration'] : '',
                            'tags' => is_string($r['tags']) ? $r['tags'] : '',
                            'published' => is_string($r['published']) ? $r['published'] : '',
                            'permalink' => $pid > 0 ? get_permalink($pid) : home_url('/'.sanitize_title(is_string($r['title']) ? $r['title'] : '').'/'),
                            'categories' => array_map(function($c){ return ['id'=>intval($c['id']),'name'=>is_string($c['name'])?$c['name']:'','slug'=>is_string($c['slug'])?$c['slug']:'']; }, \PodifyPodcast\Core\Database::get_episode_categories(intval($r['id']))),
                        ];
                    }
                }
                return [
                    'ok' => true,
                    'items' => $items,
                    'next_offset' => $offset + (is_array($rows) ? count($rows) : 0),
                    'total_count' => intval($total)
                ];
            }
        ]);
        register_rest_route('podify/v1','/categories',[
            'methods' => 'GET',
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                $rows = \PodifyPodcast\Core\Database::get_categories($feed_id ?: null);
                return ['ok' => true, 'items' => is_array($rows)?$rows:[]];
            }
        ]);
        register_rest_route('podify/v1','/update-category',[
            'methods' => 'POST',
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'callback' => function(\WP_REST_Request $req) {
                $id = intval($req->get_param('id'));
                $name = (string)$req->get_param('name');
                if (!$id || trim($name) === '') return ['ok'=>false,'message'=>'Invalid payload'];
                $ok = \PodifyPodcast\Core\Database::update_category($id, $name);
                return ['ok' => (bool)$ok];
            }
        ]);
        register_rest_route('podify/v1','/delete-category',[
            'methods' => 'POST',
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'callback' => function(\WP_REST_Request $req) {
                $id = intval($req->get_param('id'));
                if (!$id) return ['ok'=>false,'message'=>'Invalid payload'];
                $ok = \PodifyPodcast\Core\Database::delete_category($id);
                return ['ok' => (bool)$ok];
            }
        ]);
        register_rest_route('podify/v1','/assign-category',[
            'methods' => 'POST',
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'callback' => function(\WP_REST_Request $req) {
                $episode_id = intval($req->get_param('episode_id'));
                $category_id = intval($req->get_param('category_id'));
                if (!$episode_id || !$category_id) return ['ok'=>false,'message'=>'Invalid payload'];
                $ok = \PodifyPodcast\Core\Database::assign_episode_category($episode_id, $category_id);
                return ['ok' => (bool)$ok];
            }
        ]);
        register_rest_route('podify/v1','/feed-options',[
            'methods' => 'POST',
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                $options = $req->get_param('options');
                if (!$feed_id || !is_array($options)) {
                    return ['ok' => false, 'message' => 'Invalid payload'];
                }
                \PodifyPodcast\Core\Database::update_feed_options($feed_id, $options);
                $interval = isset($options['interval']) ? $options['interval'] : 'hourly';
                \PodifyPodcast\Core\Cron\CronInit::clear_feed($feed_id);
                \PodifyPodcast\Core\Cron\CronInit::schedule_feed($feed_id, $interval);
                return ['ok' => true];
            }
        ]);
    }
}
