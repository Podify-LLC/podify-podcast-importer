<?php
namespace PodifyPodcast\Core;

class Database {
    private static $last_error = '';
    public static function install() {
        global $wpdb;
        if (!is_object($wpdb)) {
            Logger::log('Database not available during activation');
            return;
        }
        $upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (file_exists($upgrade)) {
            require_once $upgrade;
        }
        if (!function_exists('dbDelta')) {
            Logger::log('dbDelta not available');
            return;
        }
        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';

        dbDelta("CREATE TABLE {$wpdb->prefix}podify_podcast_feeds (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            feed_url TEXT NOT NULL,
            last_sync DATETIME NULL,
            options LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}podify_podcast_episodes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT NULL,
            feed_id BIGINT NULL,
            title VARCHAR(255),
            description LONGTEXT,
            audio_url TEXT,
            image_url TEXT,
            duration VARCHAR(32),
            tags TEXT,
            published DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset;");
        
        dbDelta("CREATE TABLE {$wpdb->prefix}podify_podcast_categories (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            feed_id BIGINT NOT NULL,
            name VARCHAR(128) NOT NULL,
            slug VARCHAR(128) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_feed_slug (feed_id, slug)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}podify_podcast_episode_categories (
            episode_id BIGINT NOT NULL,
            category_id BIGINT NOT NULL,
            PRIMARY KEY (episode_id, category_id)
        ) $charset;");
    }
    public static function ensure_installed() {
        global $wpdb;
        if (!is_object($wpdb)) return false;
        $feeds = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'podify_podcast_feeds'));
        $episodes = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'podify_podcast_episodes'));
        if (!$feeds || !$episodes) {
            self::install();
            $feeds = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'podify_podcast_feeds'));
            $episodes = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'podify_podcast_episodes'));
        }
        // Migrate columns if needed
        if ($feeds) {
            $has_options = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}podify_podcast_feeds LIKE 'options'");
            if (!$has_options) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}podify_podcast_feeds ADD COLUMN options LONGTEXT NULL");
            }
        }
        if ($episodes) {
            $has_post_id = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}podify_podcast_episodes LIKE 'post_id'");
            if (!$has_post_id) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}podify_podcast_episodes ADD COLUMN post_id BIGINT NULL AFTER id");
            }
            $has_duration = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}podify_podcast_episodes LIKE 'duration'");
            if (!$has_duration) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}podify_podcast_episodes ADD COLUMN duration VARCHAR(32) NULL AFTER image_url");
            }
            $has_tags = $wpdb->get_var("SHOW COLUMNS FROM {$wpdb->prefix}podify_podcast_episodes LIKE 'tags'");
            if (!$has_tags) {
                $wpdb->query("ALTER TABLE {$wpdb->prefix}podify_podcast_episodes ADD COLUMN tags TEXT NULL AFTER duration");
            }
        }
        // Ensure category tables exist
        $cats = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'podify_podcast_categories'));
        if (!$cats) {
            $this_file = __FILE__; // trigger dbDelta via install()
            self::install();
            $cats = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'podify_podcast_categories'));
        }
        $epcats = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'podify_podcast_episode_categories'));
        if (!$epcats) {
            self::install();
            $epcats = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'podify_podcast_episode_categories'));
        }
        return (bool)($feeds && $episodes);
    }
    public static function get_feeds() {
        global $wpdb;
        self::ensure_installed();
        return $wpdb->get_results("SELECT id, feed_url, last_sync, created_at FROM {$wpdb->prefix}podify_podcast_feeds ORDER BY id DESC", ARRAY_A);
    }
    public static function add_feed($url, $options = []) {
        global $wpdb;
        if (!$url) return false;
        $opt_json = !empty($options) ? wp_json_encode($options) : null;
        self::ensure_installed();
        $ok = $wpdb->insert("{$wpdb->prefix}podify_podcast_feeds", ['feed_url' => $url, 'last_sync' => null, 'options' => $opt_json]);
        if (!$ok) {
            self::$last_error = $wpdb->last_error ?: 'Unknown database error';
        }
        return $ok ? intval($wpdb->insert_id) : false;
    }
    public static function remove_feed($id) {
        global $wpdb;
        if (!$id) return false;
        $wpdb->delete("{$wpdb->prefix}podify_podcast_feeds", ['id' => intval($id)]);
        $wpdb->delete("{$wpdb->prefix}podify_podcast_episodes", ['feed_id' => intval($id)]);
        return true;
    }
    public static function update_feed_options($id, $options) {
        global $wpdb;
        $id = intval($id);
        if (!$id) return false;
        $opt_json = wp_json_encode($options ?: []);
        return $wpdb->update("{$wpdb->prefix}podify_podcast_feeds", ['options' => $opt_json], ['id' => $id]);
    }
    public static function get_feed($id) {
        global $wpdb;
        $id = intval($id);
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}podify_podcast_feeds WHERE id=%d", $id), ARRAY_A);
    }
    public static function get_episodes($feed_id = null, $limit = 10, $offset = 0, $category_id = null) {
        global $wpdb;
        $limit = intval($limit);
        $offset = intval($offset);
        $tbl = "{$wpdb->prefix}podify_podcast_episodes";
        $sql = "SELECT e.id, e.post_id, e.feed_id, e.title, e.description, e.audio_url, e.image_url, e.duration, e.tags, e.published
                FROM $tbl e";
        $params = [];
        $wheres = [];
        if ($category_id) {
            $sql .= " INNER JOIN {$wpdb->prefix}podify_podcast_episode_categories ec ON ec.episode_id = e.id";
            $wheres[] = "ec.category_id = %d";
            $params[] = intval($category_id);
        }
        if ($feed_id) {
            $wheres[] = "e.feed_id = %d";
            $params[] = intval($feed_id);
        }
        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(" AND ", $wheres);
        }
        $sql .= " ORDER BY e.published DESC, e.id DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        $sql = $wpdb->prepare($sql, $params);
        return $wpdb->get_results($sql, ARRAY_A);
    }
    public static function get_episodes_advanced($opts = []) {
        global $wpdb;
        $feed_id = isset($opts['feed_id']) ? intval($opts['feed_id']) : null;
        $limit = isset($opts['limit']) ? max(1, min(200, intval($opts['limit']))) : 10;
        $offset = isset($opts['offset']) ? max(0, intval($opts['offset'])) : 0;
        $category_id = isset($opts['category_id']) ? intval($opts['category_id']) : null;
        $q = isset($opts['q']) ? trim((string)$opts['q']) : '';
        $has_audio = !empty($opts['has_audio']);
        $orderby = isset($opts['orderby']) && in_array($opts['orderby'], ['published','title'], true) ? $opts['orderby'] : 'published';
        $order = isset($opts['order']) && in_array(strtolower($opts['order']), ['asc','desc'], true) ? strtolower($opts['order']) : 'desc';
        $tbl = "{$wpdb->prefix}podify_podcast_episodes";
        $sql = "SELECT e.id, e.post_id, e.feed_id, e.title, e.description, e.audio_url, e.image_url, e.duration, e.tags, e.published FROM $tbl e";
        $params = [];
        $wheres = [];
        if ($category_id) {
            $sql .= " INNER JOIN {$wpdb->prefix}podify_podcast_episode_categories ec ON ec.episode_id = e.id";
            $wheres[] = "ec.category_id = %d";
            $params[] = intval($category_id);
        }
        if ($feed_id) {
            $wheres[] = "e.feed_id = %d";
            $params[] = intval($feed_id);
        }
        if ($has_audio) {
            $wheres[] = "e.audio_url IS NOT NULL AND e.audio_url <> ''";
        }
        if ($q !== '') {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $wheres[] = "(e.title LIKE %s OR e.description LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(" AND ", $wheres);
        }
        $sql .= " ORDER BY e.".$orderby." ".strtoupper($order).", e.id ".strtoupper($order)." LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        $sql = $wpdb->prepare($sql, $params);
        return $wpdb->get_results($sql, ARRAY_A);
    }
    public static function get_categories($feed_id = null) {
        global $wpdb;
        self::ensure_installed();
        if ($feed_id) {
            return $wpdb->get_results($wpdb->prepare("SELECT id, feed_id, name, slug FROM {$wpdb->prefix}podify_podcast_categories WHERE feed_id=%d ORDER BY name ASC", intval($feed_id)), ARRAY_A);
        }
        return $wpdb->get_results("SELECT id, feed_id, name, slug FROM {$wpdb->prefix}podify_podcast_categories ORDER BY feed_id ASC, name ASC", ARRAY_A);
    }
    public static function add_category($feed_id, $name) {
        global $wpdb;
        $feed_id = intval($feed_id);
        $name = trim((string)$name);
        if (!$feed_id || $name === '') return false;
        $slug = sanitize_title($name);
        self::ensure_installed();
        $ok = $wpdb->insert("{$wpdb->prefix}podify_podcast_categories", ['feed_id' => $feed_id, 'name' => $name, 'slug' => $slug]);
        return $ok ? intval($wpdb->insert_id) : false;
    }
    public static function assign_episode_category($episode_id, $category_id) {
        global $wpdb;
        $episode_id = intval($episode_id);
        $category_id = intval($category_id);
        if (!$episode_id || !$category_id) return false;
        self::ensure_installed();
        // Upsert
        $exists = $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->prefix}podify_podcast_episode_categories WHERE episode_id=%d AND category_id=%d", $episode_id, $category_id));
        if ($exists) return true;
        return (bool)$wpdb->insert("{$wpdb->prefix}podify_podcast_episode_categories", ['episode_id' => $episode_id, 'category_id' => $category_id]);
    }
    public static function get_episode_categories($episode_id) {
        global $wpdb;
        $episode_id = intval($episode_id);
        if (!$episode_id) return [];
        return $wpdb->get_results($wpdb->prepare("SELECT c.id, c.name, c.slug FROM {$wpdb->prefix}podify_podcast_categories c INNER JOIN {$wpdb->prefix}podify_podcast_episode_categories ec ON ec.category_id=c.id WHERE ec.episode_id=%d ORDER BY c.name ASC", $episode_id), ARRAY_A);
    }
    public static function count_episodes($feed_id = null, $category_id = null) {
        global $wpdb;
        $tbl = "{$wpdb->prefix}podify_podcast_episodes";
        $sql = "SELECT COUNT(*) FROM $tbl e";
        $params = [];
        $wheres = [];
        if ($category_id) {
            $sql .= " INNER JOIN {$wpdb->prefix}podify_podcast_episode_categories ec ON ec.episode_id = e.id";
            $wheres[] = "ec.category_id = %d";
            $params[] = intval($category_id);
        }
        if ($feed_id) {
            $wheres[] = "e.feed_id = %d";
            $params[] = intval($feed_id);
        }
        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(" AND ", $wheres);
        }
        $sql = $wpdb->prepare($sql, $params);
        return intval($wpdb->get_var($sql));
    }
    public static function update_category($id, $name) {
        global $wpdb;
        $id = intval($id);
        $name = trim((string)$name);
        if (!$id || $name === '') return false;
        $slug = sanitize_title($name);
        self::ensure_installed();
        return (bool)$wpdb->update("{$wpdb->prefix}podify_podcast_categories", ['name' => $name, 'slug' => $slug], ['id' => $id]);
    }
    public static function delete_category($id) {
        global $wpdb;
        $id = intval($id);
        if (!$id) return false;
        self::ensure_installed();
        $wpdb->delete("{$wpdb->prefix}podify_podcast_episode_categories", ['category_id' => $id]);
        return (bool)$wpdb->delete("{$wpdb->prefix}podify_podcast_categories", ['id' => $id]);
    }
    public static function set_feed_last_sync($id) {
        global $wpdb;
        $id = intval($id);
        if (!$id) return false;
        return $wpdb->update("{$wpdb->prefix}podify_podcast_feeds", ['last_sync' => current_time('mysql')], ['id' => $id]);
    }
    public static function last_error() {
        return self::$last_error;
    }
}
