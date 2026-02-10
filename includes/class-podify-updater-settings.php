<?php
namespace PodifyPodcast\Core;

class Podify_Updater_Settings {
    const OPT_TOKEN = 'podify_updater_token';
    const OPT_DEBUG = 'podify_updater_debug';
    const OPT_BRANCH = 'podify_updater_branch';
    public static function register() {
        add_action('admin_menu', [self::class,'menu']);
        add_action('admin_init', [self::class,'save']);
    }
    public static function menu() {
        add_options_page('Podify Updater','Podify Updater','manage_options','podify-updater',[self::class,'page']);
    }
    private static function get_opt($k, $default = '') {
        $v = get_option($k, null);
        if ($v === null) {
            if ($k === self::OPT_TOKEN) add_option($k, '', '', 'no');
            if ($k === self::OPT_DEBUG) add_option($k, 0, '', 'no');
            if ($k === self::OPT_BRANCH) add_option($k, 'main', '', 'no');
            $v = get_option($k, $default);
        }
        return $v !== null ? $v : $default;
    }
    public static function save() {
        if (!is_admin() || !current_user_can('manage_options')) return;
        if (!empty($_POST['podify_updater_action']) && $_POST['podify_updater_action'] === 'save') {
            check_admin_referer('podify_updater_save');
            $token = isset($_POST['podify_updater_token']) ? (string)$_POST['podify_updater_token'] : '';
            $debug = !empty($_POST['podify_updater_debug']) ? 1 : 0;
            $branch = isset($_POST['podify_updater_branch']) ? sanitize_text_field((string)$_POST['podify_updater_branch']) : 'main';
            $token = trim($token);
            update_option(self::OPT_DEBUG, $debug);
            update_option(self::OPT_BRANCH, $branch);
            if ($token !== '') {
                update_option(self::OPT_TOKEN, $token);
            } else {
                if (isset($_POST['podify_updater_token'])) {
                    update_option(self::OPT_TOKEN, '');
                }
            }
            wp_safe_redirect( admin_url('options-general.php?page=podify-updater&updated=1') );
            exit;
        }
    }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        $token = self::get_opt(self::OPT_TOKEN, '');
        $debug = intval(self::get_opt(self::OPT_DEBUG, 0)) ? 1 : 0;
        $branch = (string)self::get_opt(self::OPT_BRANCH, 'main');
        echo '<div class="wrap"><h1>Podify Updater</h1>';
        if (!empty($_GET['updated'])) {
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="podify_updater_action" value="save">';
        wp_nonce_field('podify_updater_save');
        echo '<table class="form-table" role="presentation"><tbody>';
        
        // Token Field
        $token_val = $token;
        $is_constant = defined('PODIFY_GITHUB_TOKEN') && PODIFY_GITHUB_TOKEN;
        if ($is_constant) {
            $token_val = 'Set in wp-config.php (Hidden)';
        }
        
        echo '<tr>
            <th scope="row"><label for="podify_updater_token">GitHub Access Token</label></th>
            <td>';
        
        if ($is_constant) {
            echo '<input type="text" value="'.esc_attr($token_val).'" class="regular-text" disabled>';
            echo '<p class="description">Token is defined via <code>PODIFY_GITHUB_TOKEN</code> constant.</p>';
        } else {
            echo '<input type="password" id="podify_updater_token" name="podify_updater_token" value="'.esc_attr($token_val).'" class="regular-text">';
            echo '<p class="description">Enter a GitHub Personal Access Token (Fine-grained) with <code>contents:read</code> access to the repository.</p>';
        }
        
        echo '</td></tr>';

        echo '<tr><th scope="row">Enable Debug Logging</th><td><label><input type="checkbox" name="podify_updater_debug" value="1"'.($debug?' checked':'').'> Enable</label></td></tr>';
        echo '<tr><th scope="row"><label for="podify_updater_branch">Locked Release Branch</label></th><td><input type="text" id="podify_updater_branch" name="podify_updater_branch" value="'.esc_attr($branch ?: 'main').'" class="regular-text"></td></tr>';
        echo '</tbody></table>';
        echo '<p class="submit"><button type="submit" class="button button-primary">Save Changes</button></p>';
        echo '</form></div>';
    }
}
