<?php
namespace PodifyPodcast\Core;

class Podify_Github_Updater {
    const HARDCODED_TOKEN = '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';
    private $plugin_file;
    private $plugin_basename;
    private $plugin_slug;
    private $installed_version;
    private $release;
    private $backup_dir;
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_slug = dirname($this->plugin_basename);
        $this->installed_version = $this->get_installed_version();
        $this->backup_dir = WP_CONTENT_DIR . '/upgrade/podify-backup';
        add_filter('pre_set_site_transient_update_plugins', [$this,'inject_update']);
        add_filter('plugins_api', [$this,'plugins_api'], 10, 3);
        add_filter('upgrader_pre_download', [$this,'pre_download'], 10, 3);
        add_filter('upgrader_source_selection', [$this,'source_selection'], 10, 4);
        add_action('upgrader_pre_install', [$this,'pre_install'], 10, 2);
        add_filter('upgrader_post_install', [$this,'post_install'], 10, 3);
        add_action('upgrader_process_complete', [$this,'process_complete'], 10, 2);
    }
    private function opt($k, $default = '') {
        if ($k === 'token') {
            // Hardcoded token per user request
            return self::HARDCODED_TOKEN;
        }
        if ($k === 'debug') return intval(get_option(Podify_Updater_Settings::OPT_DEBUG, 0)) ? 1 : 0;
        if ($k === 'branch') return (string)get_option(Podify_Updater_Settings::OPT_BRANCH, 'main');
        return $default;
    }
    private function log($m) {
        if ($this->opt('debug')) {
            error_log('[Podify Updater] '.$m);
        }
    }
    private function get_installed_version() {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $data = get_plugin_data($this->plugin_file, false, false);
        $v = isset($data['Version']) ? trim((string)$data['Version']) : '';
        return $v ?: (defined('PODIFY_PODCAST_VERSION') ? PODIFY_PODCAST_VERSION : '');
    }
    private function fetch_latest_release() {
        $token = $this->opt('token', '');
        $args = [
            'headers' => [
                'Accept' => 'application/vnd.github+json',
            ],
            'timeout' => 20,
        ];
        if ($token !== '') {
            $args['headers']['Authorization'] = 'Bearer '.$token;
        }
        // Updated to match the Pro repository name likely used for this plugin
        $url = 'https://api.github.com/repos/Podify-LLC/podify-podcast-importer-pro/releases/latest';
        $this->log('Requesting latest release from: '.$url);
        
        $resp = wp_remote_get($url, $args);
        
        if (is_wp_error($resp)) {
            $msg = $resp->get_error_message();
            $this->log('API error: '.$msg);
            update_option('podify_updater_status', [
                'time' => time(),
                'status' => 'error',
                'message' => 'API Error: '.$msg
            ]);
            return null;
        }
        $code = wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        
        if ($code !== 200 || !$body) {
            $this->log('API bad response: '.intval($code));
            update_option('podify_updater_status', [
                'time' => time(),
                'status' => 'error',
                'message' => 'GitHub API returned status '.intval($code)
            ]);
            return null;
        }
        
        $json = json_decode($body, true);
        if (!is_array($json) || empty($json['tag_name']) || empty($json['target_commitish'])) {
            $this->log('Invalid JSON payload');
            update_option('podify_updater_status', [
                'time' => time(),
                'status' => 'error',
                'message' => 'Invalid JSON response from GitHub'
            ]);
            return null;
        }
        
        $branch = (string)$json['target_commitish'];
        $locked = $this->opt('branch', 'main');
        if ($branch !== $locked) {
            $this->log('Branch mismatch: '.$branch.' != '.$locked);
            update_option('podify_updater_status', [
                'time' => time(),
                'status' => 'warning',
                'message' => 'Branch mismatch (Found: '.$branch.', Expected: '.$locked.')'
            ]);
            return null;
        }
        
        $tag = (string)$json['tag_name'];
        $ver = ltrim($tag, 'v');
        $zip = '';
        if (!empty($json['assets']) && is_array($json['assets'])) {
            foreach ($json['assets'] as $asset) {
                $name = isset($asset['name']) ? (string)$asset['name'] : '';
                $dl = isset($asset['browser_download_url']) ? (string)$asset['browser_download_url'] : '';
                if ($name && preg_match('/\.zip$/i', $name)) {
                    if (strpos($name, $this->plugin_slug) !== false) {
                        $zip = $dl;
                        break;
                    }
                    if ($zip === '' && $dl) {
                        $zip = $dl;
                    }
                }
            }
        }
        
        if ($zip === '') {
            if (!empty($json['zipball_url'])) {
                $zip = (string)$json['zipball_url'];
                $this->log('Using zipball_url as fallback');
            } else {
                $this->log('No ZIP asset found in release');
                update_option('podify_updater_status', [
                    'time' => time(),
                    'status' => 'error',
                    'message' => 'No ZIP asset found for v'.$ver
                ]);
                return null;
            }
        }
        
        // Update success status
        update_option('podify_updater_status', [
            'time' => time(),
            'status' => 'success',
            'message' => 'Found v'.$ver,
            'version' => $ver
        ]);
        
        $bodyTxt = isset($json['body']) ? (string)$json['body'] : '';
        $checksum = '';
        if ($bodyTxt) {
            if (preg_match('/SHA256:\s*([a-f0-9]{64})/i', $bodyTxt, $m)) {
                $checksum = strtolower($m[1]);
            }
        }
        $this->release = [
            'version' => $ver,
            'zip_url' => $zip,
            'branch' => $branch,
            'body' => $bodyTxt,
            'checksum' => $checksum,
        ];
        $this->log('Release version: '.$ver);
        return $this->release;
    }
    public function inject_update($transient) {
        if (!is_object($transient)) $transient = new \stdClass();
        $rel = $this->fetch_latest_release();
        if (!$rel || empty($rel['version'])) return $transient;
        if (!$this->installed_version || version_compare($rel['version'], $this->installed_version, '>')) {
            $obj = new \stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->plugin = $this->plugin_basename;
            $obj->new_version = $rel['version'];
            $obj->package = $rel['zip_url'];
            $obj->url = 'https://github.com/Podify-LLC/podify-podcast-importer';
            if (!isset($transient->response)) $transient->response = [];
            $transient->response[$this->plugin_basename] = $obj;
            $this->log('Update injected: '.$rel['version']);
        }
        return $transient;
    }
    public function plugins_api($result, $action, $args) {
        if ($action !== 'plugin_information') return $result;
        if (empty($args->slug) || $args->slug !== $this->plugin_slug) return $result;
        $rel = $this->release ?: $this->fetch_latest_release();
        if (!$rel) return $result;
        $res = new \stdClass();
        $res->name = 'Podify Podcast Importer Pro';
        $res->slug = $this->plugin_slug;
        $res->version = $rel['version'];
        $res->author = 'Podify';
        $res->homepage = 'https://github.com/Podify-LLC/podify-podcast-importer';
        $res->download_link = $rel['zip_url'];
        $res->sections = [
            'description' => 'Advanced podcast importer with private GitHub updates.',
            'changelog' => $rel['body'] ?: '',
        ];
        return $res;
    }
    public function pre_download($reply, $package, $upgrader) {
        $is_plugin = (isset($upgrader->skin) && isset($upgrader->skin->plugin) && $upgrader->skin->plugin === $this->plugin_basename);
        $matches_repo = (strpos($package, 'github.com/Podify-LLC/podify-podcast-importer') !== false) || (strpos($package, 'api.github.com/repos/Podify-LLC/podify-podcast-importer') !== false);
        if (!$is_plugin && !$matches_repo) return $reply;
        $rel = $this->release ?: $this->fetch_latest_release();
        if (!$rel || empty($rel['zip_url'])) return new \WP_Error('podify_updater', 'Invalid package');
        $token = $this->opt('token', '');
        $args = [
            'headers' => [
                'Accept' => 'application/octet-stream',
            ],
            'timeout' => 60,
        ];
        if ($token !== '') {
            $args['headers']['Authorization'] = 'Bearer '.$token;
        }
        $this->log('Downloading package for checksum');
        $resp = wp_remote_get($rel['zip_url'], $args);
        if (is_wp_error($resp)) {
            $this->log('Download error: '.$resp->get_error_message());
            return $resp;
        }
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            $this->log('Download bad code: '.intval($code));
            return new \WP_Error('podify_updater', 'Failed to download package');
        }
        $data = wp_remote_retrieve_body($resp);
        if (!$data) {
            $this->log('Empty package data');
            return new \WP_Error('podify_updater', 'Empty package');
        }
        $tmp = wp_tempnam($rel['zip_url']);
        if (!$tmp) {
            $this->log('Temp file error');
            return new \WP_Error('podify_updater', 'Temp file error');
        }
        file_put_contents($tmp, $data);
        $calc = hash_file('sha256', $tmp);
        $expected = isset($rel['checksum']) ? strtolower((string)$rel['checksum']) : '';
        
        if ($expected) {
            if (!preg_match('/^[a-f0-9]{64}$/', $expected)) {
                $this->log('Invalid checksum format in release body');
                // We proceed if format is invalid? No, if it was intended to be a checksum, we should probably fail or warn.
                // But let's assume if it was found by regex in fetch_latest_release, it matched the format.
            } else {
                if ($calc !== $expected) {
                    $this->log('Checksum mismatch');
                    @unlink($tmp);
                    return new \WP_Error('podify_updater', 'Checksum mismatch');
                }
                $this->log('Checksum validated');
            }
        } else {
            $this->log('No checksum provided in release body, skipping validation');
        }
        
        return $tmp;
    }
    public function pre_install($bool, $hook_extra) {
        $src = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
        if (!is_dir($src)) return $bool;
        wp_mkdir_p($this->backup_dir);
        $bak = trailingslashit($this->backup_dir) . $this->plugin_slug . '-backup-' . time();
        $ok = $this->copy_dir($src, $bak);
        if ($ok) {
            $this->log('Backup created: '.$bak);
            update_option('podify_updater_last_backup', $bak);
        } else {
            $this->log('Backup creation failed');
        }
        return $bool;
    }
    public function source_selection($source, $remote_source, $upgrader, $hook_extra = null) {
        // Fix: Only run for this specific plugin
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] !== $this->plugin_basename) {
            return $source;
        }

        // If hook_extra is missing/empty, try to detect if it's us by checking for the main file
        $main_file = trailingslashit($source) . basename($this->plugin_file);
        if (!file_exists($main_file)) {
            // It doesn't contain our main file, so it's likely not our plugin (or it's broken).
            // However, we shouldn't block other plugins if we are unsure.
            // But if we are sure it WAS meant to be us (checked above via hook_extra), then it's an error.
            if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
                return new \WP_Error('podify_updater', 'Main plugin file missing in ZIP');
            }
            return $source;
        }

        // It is our plugin. Now ensure the folder name is correct.
        $correct_slug = $this->plugin_slug;
        $current_slug = basename($source);

        if ($current_slug !== $correct_slug) {
            $new_source = trailingslashit(dirname($source)) . $correct_slug;
            
            // If the destination exists (rare in upgrade temp), try to clear it? 
            // WP usually handles unique temp dirs, but let's be safe.
            if (file_exists($new_source)) {
                 // If we can't rename because target exists, we might be in trouble, 
                 // but let's try to return the new source anyway if it's valid?
                 // Standard WP practice is to rename using $wp_filesystem->move usually, 
                 // but here we are in a filter returning a path. PHP rename works for local paths.
                 // We will rely on simple rename.
            }

            if (rename($source, $new_source)) {
                return $new_source;
            } else {
                return new \WP_Error('podify_updater', 'Unable to rename plugin folder');
            }
        }

        return $source;
    }
    public function post_install($bool, $hook_extra, $res) {
        if (is_wp_error($res)) {
            $this->rollback();
            return $res;
        }
        return $bool;
    }
    public function process_complete($upgrader, $hook_extra) {
        $bak = get_option('podify_updater_last_backup', '');
        if (!$bak) return;
        if (isset($upgrader->result) && is_wp_error($upgrader->result)) {
            $this->rollback();
            return;
        }
        $this->cleanup_backup();
        $this->log('Update success');
    }
    private function rollback() {
        $bak = get_option('podify_updater_last_backup', '');
        if (!$bak || !is_dir($bak)) return;
        $dst = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
        if (is_dir($dst)) {
            $this->rmdir_recursive($dst);
        }
        $ok = $this->copy_dir($bak, $dst);
        if ($ok) {
            delete_option('podify_updater_last_backup');
            $this->log('Rollback restored');
            $main = $dst . '/' . basename($this->plugin_file);
            if (file_exists($main)) {
                activate_plugin($this->plugin_basename);
            }
        } else {
            $this->log('Rollback failed to restore');
        }
    }
    private function cleanup_backup() {
        $bak = get_option('podify_updater_last_backup', '');
        if ($bak && is_dir($bak)) {
            $this->rmdir_recursive($bak);
        }
        delete_option('podify_updater_last_backup');
        $this->log('Backup removed');
    }
    private function copy_dir($src, $dst) {
        if (!is_dir($src)) return false;
        if (!is_dir($dst)) {
            if (!wp_mkdir_p($dst)) return false;
        }
        $items = scandir($src);
        if (!$items) return false;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $sp = $src . '/' . $item;
            $dp = $dst . '/' . $item;
            if (is_dir($sp)) {
                if (!$this->copy_dir($sp, $dp)) return false;
            } else {
                if (!copy($sp, $dp)) return false;
            }
        }
        return true;
    }
    private function rmdir_recursive($dir) {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = $dir . '/' . $item;
            if (is_dir($p)) {
                $this->rmdir_recursive($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }
}
