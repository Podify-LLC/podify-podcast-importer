<?php
namespace PodifyPodcast\Core\Admin;

class AdminInit {
    const SLUG = 'podify-podcast-importer';
    public static function register() {
        add_action('admin_menu', [self::class,'menu']);
        add_action('admin_enqueue_scripts', [self::class,'enqueue']);
    }
    public static function menu() {
        // Position 11 places it right after 'Media' (10) and before 'Links' (15) or 'Pages' (20)
        // This moves it away from the bottom and likely near content-related items like Image Carousel
        add_menu_page('Podcast Importer','Podcast Importer','manage_options',self::SLUG,[self::class,'page'], 'dashicons-microphone', 11);
        add_submenu_page(self::SLUG,'Podcast Importer','Dashboard','manage_options',self::SLUG,[self::class,'page']);
    }
    public static function enqueue($hook) {
        if (isset($_GET['page']) && $_GET['page'] === self::SLUG) {
            wp_enqueue_style('podify_admin', \PODIFY_PODCAST_URL . 'assets/css/admin.css', [], \PODIFY_PODCAST_VERSION);
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_script('jquery');
            wp_enqueue_script('wp-api');
        }
    }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        $notice = '';
        $intervals = [
            'every_15_minutes' => 'Every 15 Minutes',
            'every_30_minutes' => 'Every 30 Minutes',
            'hourly' => 'Hourly',
            'twice_daily' => 'Twice Daily',
            'daily' => 'Daily'
        ];
        $post_types = get_post_types(['public' => true], 'objects');
        $post_statuses = ['publish'=>'Publish','draft'=>'Draft','private'=>'Private','pending'=>'Pending'];
        $authors = get_users(['capability' => ['edit_posts']]);
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'add_feed') {
            check_admin_referer('podify_add_feed');
            $url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
            if ($url) {
                $options = [
                    'post_type' => sanitize_text_field($_POST['post_type'] ?? 'post'),
                    'post_status' => sanitize_text_field($_POST['post_status'] ?? 'publish'),
                    'post_author' => intval($_POST['post_author'] ?? 0),
                    'interval' => sanitize_text_field($_POST['interval'] ?? 'hourly'),
                    'transcript_tag' => sanitize_text_field($_POST['transcript_tag'] ?? ''),
                    'audio_field' => sanitize_text_field($_POST['audio_field'] ?? ''),
                    'import_categories' => !empty($_POST['import_categories']) ? 1 : 0,
                    'import_tags' => !empty($_POST['import_tags']) ? 1 : 0,
                    'featured_image' => esc_url_raw($_POST['featured_image'] ?? ''),
                    'append_episode_number' => !empty($_POST['append_episode_number']) ? 1 : 0,
                    'read_more_text' => sanitize_text_field($_POST['read_more_text'] ?? ''),
                    'load_more_text' => sanitize_text_field($_POST['load_more_text'] ?? ''),
                    'title_font' => sanitize_text_field($_POST['title_font'] ?? ''),
                    'title_color' => sanitize_hex_color($_POST['title_color'] ?? ''),
                    'desc_font' => sanitize_text_field($_POST['desc_font'] ?? ''),
                    'desc_color' => sanitize_hex_color($_POST['desc_color'] ?? ''),
                    'meta_font' => sanitize_text_field($_POST['meta_font'] ?? ''),
                    'meta_color' => sanitize_hex_color($_POST['meta_color'] ?? ''),
                    'lm_bg_hover' => sanitize_hex_color($_POST['lm_bg_hover'] ?? ''),
                    'lm_text_hover' => sanitize_hex_color($_POST['lm_text_hover'] ?? ''),
                ];
                $new_id = \PodifyPodcast\Core\Database::add_feed($url, $options);
                if ($new_id) {
                    \PodifyPodcast\Core\Cron\CronInit::schedule_feed($new_id, $options['interval']);
                    wp_safe_redirect( admin_url('admin.php?page='.self::SLUG.'&tab=scheduled&podify_msg=added') );
                    exit;
                } else {
                    $err = \PodifyPodcast\Core\Database::last_error();
                    $notice = $err ? ('Error adding feed: '.$err) : 'Unable to add feed';
                }
                $notice = 'Feed added';
            }
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'remove_feed') {
            check_admin_referer('podify_remove_feed');
            $id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
            if ($id) {
                \PodifyPodcast\Core\Database::remove_feed($id);
                $notice = 'Feed removed';
                wp_safe_redirect( admin_url('admin.php?page='.self::SLUG.'&tab=scheduled&podify_msg=removed') );
                exit;
            }
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'update_feed_options') {
            check_admin_referer('podify_update_feed');
            $id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
            if ($id) {
                $options = [
                    'interval' => sanitize_text_field($_POST['interval'] ?? 'hourly'),
                    'featured_image' => esc_url_raw($_POST['featured_image'] ?? ''),
                    'append_episode_number' => !empty($_POST['append_episode_number']) ? 1 : 0,
                    'import_categories' => !empty($_POST['import_categories']) ? 1 : 0,
                    'import_tags' => !empty($_POST['import_tags']) ? 1 : 0,
                    'read_more_text' => sanitize_text_field($_POST['read_more_text'] ?? ''),
                    'load_more_text' => sanitize_text_field($_POST['load_more_text'] ?? ''),
                    'title_font' => sanitize_text_field($_POST['title_font'] ?? ''),
                    'title_color' => sanitize_hex_color($_POST['title_color'] ?? ''),
                    'desc_font' => sanitize_text_field($_POST['desc_font'] ?? ''),
                    'desc_color' => sanitize_hex_color($_POST['desc_color'] ?? ''),
                    'meta_font' => sanitize_text_field($_POST['meta_font'] ?? ''),
                    'meta_color' => sanitize_hex_color($_POST['meta_color'] ?? ''),
                    'lm_bg_hover' => sanitize_hex_color($_POST['lm_bg_hover'] ?? ''),
                    'lm_text_hover' => sanitize_hex_color($_POST['lm_text_hover'] ?? ''),
                ];
                \PodifyPodcast\Core\Database::update_feed_options($id, $options);
                \PodifyPodcast\Core\Cron\CronInit::clear_feed($id);
                \PodifyPodcast\Core\Cron\CronInit::schedule_feed($id, $options['interval']);
                $notice = 'Feed options saved';
            }
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'save_settings') {
            check_admin_referer('podify_save_settings');
            $data = [
                'sticky_player_enabled' => !empty($_POST['sticky_player_enabled']) ? 1 : 0,
                'sticky_player_position' => sanitize_text_field($_POST['sticky_player_position'] ?? 'bottom'),
                'custom_css' => isset($_POST['custom_css']) ? sanitize_textarea_field($_POST['custom_css']) : '',
            ];
            \PodifyPodcast\Core\Settings::update($data);
            $notice = 'Settings saved';
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'add_category') {
            check_admin_referer('podify_add_category');
            $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
            $name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
            if ($feed_id && $name !== '') {
                $cid = \PodifyPodcast\Core\Database::add_category($feed_id, $name);
                if ($cid) {
                    wp_safe_redirect( admin_url('admin.php?page='.self::SLUG.'&tab=categories&podify_msg=cat_added') );
                    exit;
                } else {
                    $notice = 'Unable to add category';
                }
            }
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'clear_cache') {
            check_admin_referer('podify_clear_cache');
            delete_site_transient('update_plugins');
            delete_transient('podify_updater_check');
            $notice = 'Cache cleared';
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'refresh_updater') {
            check_admin_referer('podify_refresh_updater');
            wp_clean_plugins_cache();
            wp_update_plugins();
            // Removed global notice to show in widget instead
        }
        if (isset($_GET['podify_action']) && $_GET['podify_action'] === 'refresh_updater_ajax') {
            wp_clean_plugins_cache();
            wp_update_plugins();
            
            // Re-calculate status for the response
            $local_ver = \PODIFY_PODCAST_VERSION;
            $updater_status = get_option('podify_updater_status', []);
            $st = 'unknown';
            $st_color = '#f0b849';
            
            if (!empty($updater_status) && is_array($updater_status)) {
                $st = $updater_status['status'] ?? 'unknown';
                $remote_ver = $updater_status['version'] ?? '';
                if ($st === 'success' && $remote_ver) {
                    if (version_compare($local_ver, $remote_ver, '>')) {
                        $st = 'Dev'; $st_color = '#10b981';
                    } elseif (version_compare($local_ver, $remote_ver, '<')) {
                        $st = 'Update Available'; $st_color = '#f59e0b';
                    } else {
                        $st = 'Up to Date'; $st_color = '#10b981';
                    }
                } else {
                    $st_color = ($st === 'success') ? '#10b981' : (($st === 'error') ? '#dc3232' : '#f0b849');
                }
            }
            
            wp_send_json_success([
                'status_label' => $st,
                'status_color' => $st_color
            ]);
        }
        $feeds = \PodifyPodcast\Core\Database::get_feeds();
        $episodes = \PodifyPodcast\Core\Database::get_episodes(null, 10, 0);
        $sync_url = esc_url_raw( rest_url('podify/v1/sync') );
        $resync_url = esc_url_raw( rest_url('podify/v1/resync') );
        $progress_url = esc_url_raw( rest_url('podify/v1/progress') );
        $nonce = wp_create_nonce('wp_rest');
        echo '<style>
        .podify-progress-wrap{display:none; width:140px; height:26px; background:#f0f0f1; border:1px solid #8c8f94; border-radius:3px; position:relative; vertical-align:middle; float:right; overflow:hidden;}
        .podify-progress-bar{height:100%; background:#2271b1; width:0%; transition:width 0.2s linear;}
        .podify-progress-text{position:absolute; top:0; left:0; right:0; bottom:0; line-height:24px; font-size:11px; color:#fff; text-shadow:0 0 2px #000; text-align:center; font-weight:600; white-space:nowrap; z-index:2;}
        
        /* Layout & Cards */
        .podify-admin-layout { display: flex; gap: 24px; margin-top: 20px; align-items: flex-start; }
        .podify-sidebar { width: 220px; flex-shrink: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; position: sticky; top: 50px; }
        .podify-content { flex-grow: 1; min-width: 0; }
        .podify-grid { display: grid; gap: 24px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .podify-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .podify-card h3 { margin-top: 0; margin-bottom: 20px; font-size: 16px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        
        /* Categories Specific Layout */
        .podify-categories-grid { display: grid; grid-template-columns: 350px 1fr; gap: 24px; align-items: flex-start; }
        @media (max-width: 1100px) { .podify-categories-grid { grid-template-columns: 1fr; } }
        
        /* Modern Table Styles */
        .podify-table-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
        .podify-modern-table { width:100%; border-collapse:collapse; text-align:left; }
        .podify-modern-table th { background:#f8fafc; padding:14px 16px; font-weight:600; color:#64748b; border-bottom:1px solid #e2e8f0; font-size:11px; text-transform:uppercase; letter-spacing:0.05em; }
        .podify-modern-table td { padding:14px 16px; border-bottom:1px solid #f1f5f9; vertical-align:middle; color:#334155; font-size:13px; }
        .podify-modern-table tr:last-child td { border-bottom:none; }
        .podify-modern-table tr:hover { background:#fcfdfe; }
        
        /* Forms & Inputs */
        .podify-field { margin-bottom: 18px; }
        .podify-field label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #334155; }
        .podify-field input[type="text"], .podify-field input[type="url"], .podify-field select, .podify-field textarea { 
            width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 13px; transition: border-color 0.2s; 
        }
        .podify-field input:focus { border-color: #2271b1; outline: none; box-shadow: 0 0 0 1px #2271b1; }
        
        /* Buttons */
        .podify-button-modern { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; border: 1px solid #cbd5e1; background: #fff; color: #334155; transition: all 0.2s; }
        .podify-button-modern:hover { background: #f8fafc; border-color: #94a3b8; }
        .podify-button-primary { background: #2271b1; color: #fff; border-color: #2271b1; }
        .podify-button-primary:hover { background: #135e96; border-color: #135e96; }
        .podify-button-danger { color: #ef4444; border-color: #fca5a5; }
        .podify-button-danger:hover { background: #fef2f2; border-color: #ef4444; }
        .podify-button-small { padding: 4px 10px; font-size: 11px; height: auto; line-height: 1.4; }
        
        .podify-actions-cell { display:flex; align-items:center; gap:8px; }
        .podify-badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; background:#f1f5f9; color:#475569; border: 1px solid #e2e8f0; }
        .podify-url-text { font-family: ui-monospace, monospace; color:#0f172a; background:#f8fafc; padding:4px 8px; border-radius:4px; font-size:12px; max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; border:1px solid #e2e8f0; }
        
        /* Color Picker Overrides */
        .wp-picker-container { display: flex; align-items: center; }
        .wp-color-result.button { height: 28px; min-height: 28px; margin: 0; }
        
        </style>';
        echo '<div class="wrap podify-admin-wrap-main">';
        
        // Tab specific headers
        if ($tab === 'episodes') {
            echo '<div class="podify-page-header">';
            echo '<h1><span class="dashicons dashicons-playlist-audio"></span> Episodes Management</h1>';
            echo '<p>View and manage all imported podcast episodes across your feeds.</p>';
            echo '</div>';
        }
        
        $msg = isset($_GET['podify_msg']) ? sanitize_key($_GET['podify_msg']) : '';
        if ($msg === 'added') $notice = 'Feed added';
        if ($msg === 'removed') $notice = 'Feed removed';
        if ($msg === 'cat_added') $notice = 'Category added';
        if ($notice) echo '<div class="updated" style="border-left-color:#46b450;"><p style="color:#1d2327; font-weight:600;">'.esc_html($notice).'</p></div>';
        
        $base = admin_url('admin.php?page='.self::SLUG);
        
        echo '<div class="podify-admin-layout">';
        
        // Sidebar
        echo '<div class="podify-sidebar">';
            echo '<div class="podify-sidebar-header">';
                echo '<div class="podify-sidebar-logo-container">';
                    echo '<div class="podify-sidebar-logo"><img src="' . \PODIFY_PODCAST_URL . 'assets/images/logo_cropped.png" alt="Podify"></div>';
                    echo '<div class="podify-version-container">';
                        echo '<div class="podify-plugin-version">v'.\PODIFY_PODCAST_VERSION.'</div>';
                        echo '<span class="podify-pro-badge">PRO</span>';
                    echo '</div>';
                echo '</div>';
            echo '</div>';
            echo '<div class="podify-nav-links">';
                $tabs = [
                    'dashboard' => ['icon' => 'dashicons-dashboard', 'label' => 'Dashboard'],
                    'import' => ['icon' => 'dashicons-plus-alt2', 'label' => 'Import Feed'],
                    'scheduled' => ['icon' => 'dashicons-calendar-alt', 'label' => 'Schedules'],
                    'episodes' => ['icon' => 'dashicons-playlist-audio', 'label' => 'Episodes'],
                    'categories' => ['icon' => 'dashicons-category', 'label' => 'Categories'],
                    'settings' => ['icon' => 'dashicons-admin-settings', 'label' => 'Settings'],
                    'changelog' => ['icon' => 'dashicons-list-view', 'label' => 'Changelog'],
                ];
                foreach ($tabs as $k => $t) {
                    $active = $tab === $k ? ' active' : '';
                    echo '<a href="'.$base.'&tab='.$k.'" class="podify-nav-item'.$active.'">';
                    echo '<span class="dashicons '.$t['icon'].'"></span> <span class="podify-nav-text">'.$t['label'].'</span>';
                    echo '</a>';
                }
            echo '</div>';
        echo '</div>'; // End sidebar

        // Main Content Area
        echo '<div class="podify-content">';
        if ($tab === 'dashboard') {
              echo '<div class="podify-dashboard-hero">';
              echo '<div class="podify-hero-content">';
              echo '<h2>Welcome to Podify Podcast Importer Pro <div class="podify-hero-badges"><span class="podify-version-badge">v'.\PODIFY_PODCAST_VERSION.'</span> <span class="podify-pro-badge hero-pro">PRO</span></div></h2>';
              echo '<p>The ultimate solution for importing and managing podcasts in WordPress. Automated imports, modern players, and seamless integration.</p>';
              echo '<a href="'.$base.'&tab=import" class="podify-button-modern podify-button-primary" style="padding:12px 24px; font-size:15px;">Import a Podcast</a>';
              echo '</div>';
              echo '</div>';
            
            echo '<div class="podify-dashboard-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:24px;">';
            
            echo '<div class="podify-card podify-updater-card">';
            echo '<h3><span class="dashicons dashicons-update"></span> Updater Status</h3>';
            $updater_status = get_option('podify_updater_status', []);
            
            $st = 'unknown';
            $st_color = '#f0b849';
            $context_msg = 'No update activity recorded yet.';
            $local_ver = \PODIFY_PODCAST_VERSION;

            if (!empty($updater_status) && is_array($updater_status)) {
                $st = $updater_status['status'] ?? 'unknown';
                $remote_ver = $updater_status['version'] ?? '';
                
                if ($st === 'success' && $remote_ver) {
                    if (version_compare($local_ver, $remote_ver, '>')) {
                        $st = 'Dev';
                        $st_color = '#10b981';
                    } elseif (version_compare($local_ver, $remote_ver, '<')) {
                        $st = 'Update Available';
                        $st_color = '#f59e0b';
                    } else {
                        $st = 'Up to Date';
                        $st_color = '#10b981'; // Match green in reference
                    }
                } else {
                     $st_color = ($st === 'success') ? '#10b981' : (($st === 'error') ? '#dc3232' : '#f0b849');
                }
            }

            echo '<div class="podify-updater-body">';
                echo '<div class="podify-status-badge-wrap"><span class="podify-status-badge" id="podify-updater-status-label" style="background-color:'.esc_attr($st_color).'15; color:'.esc_attr($st_color).'; border:1px solid '.esc_attr($st_color).'30;">'.esc_html(strtoupper($st)).'</span></div>';
                echo '<p class="podify-running-ver">Currently running v' . esc_html($local_ver) . '</p>';
                
                // Success indicator relocated
                echo '<div id="podify-updater-success-msg" class="podify-check-success" style="display:none; margin-bottom:15px;"><span class="dashicons dashicons-yes"></span> Updated!</div>';

                echo '<div class="podify-updater-actions">';
                    echo '<button type="button" id="podify-check-updates-btn" class="podify-button-modern">';
                        echo '<span class="dashicons dashicons-update"></span>';
                        echo '<span>Check Now</span>';
                    echo '</button>';
                echo '</div>';
            echo '</div>';

            echo '<script>
            (function(){
                const btn = document.getElementById("podify-check-updates-btn");
                const successMsg = document.getElementById("podify-updater-success-msg");
                const statusLabel = document.getElementById("podify-updater-status-label");
                if(!btn) return;

                btn.addEventListener("click", function(){
                    if(btn.classList.contains("is-loading")) return;
                    
                    btn.classList.add("is-loading");
                    successMsg.style.display = "none";
                    
                    // Call the REST API to refresh updater
                    fetch(window.location.pathname + "?page=podify-podcast-importer&podify_action=refresh_updater_ajax", {
                        method: "POST",
                        headers: {
                            "X-WP-Nonce": "' . wp_create_nonce('wp_rest') . '",
                            "Content-Type": "application/json"
                        }
                    }).then(r => r.json()).then(data => {
                        btn.classList.remove("is-loading");
                        if(data.success) {
                            successMsg.style.display = "inline-flex";
                            if(data.status_label) {
                                statusLabel.textContent = data.status_label.toUpperCase();
                                statusLabel.style.color = data.status_color;
                                statusLabel.style.backgroundColor = data.status_color + "15";
                                statusLabel.style.borderColor = data.status_color + "30";
                            }
                            setTimeout(() => { successMsg.style.display = "none"; }, 5000);
                        }
                    }).catch(() => {
                        btn.classList.remove("is-loading");
                    });
                });
            })();
            </script>';

            echo '</div>'; // End card
            

            
            echo '<div class="podify-card">';
            echo '<h3><span class="dashicons dashicons-yes-alt"></span> Key Features</h3>';
            echo '<ul style="margin:0; padding:0; list-style:none;">';
            echo '<li style="margin-bottom:12px; display:flex; align-items:center; gap:8px; color:#475569;"><span class="dashicons dashicons-yes" style="color:#10b981; font-size:18px;"></span> Automated Background Imports</li>';
            echo '<li style="margin-bottom:12px; display:flex; align-items:center; gap:8px; color:#475569;"><span class="dashicons dashicons-yes" style="color:#10b981; font-size:18px;"></span> Modern Sticky Audio Player</li>';
            echo '<li style="margin-bottom:12px; display:flex; align-items:center; gap:8px; color:#475569;"><span class="dashicons dashicons-yes" style="color:#10b981; font-size:18px;"></span> SEO-Friendly Episode Pages</li>';
            echo '<li style="margin-bottom:12px; display:flex; align-items:center; gap:8px; color:#475569;"><span class="dashicons dashicons-yes" style="color:#10b981; font-size:18px;"></span> Advanced Category Mapping</li>';
            echo '<li style="margin-bottom:0; display:flex; align-items:center; gap:8px; color:#475569;"><span class="dashicons dashicons-yes" style="color:#10b981; font-size:18px;"></span> Bulk Episode Management</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="podify-card">';
            echo '<h3><span class="dashicons dashicons-admin-settings"></span> Quick Actions</h3>';
            echo '<p style="margin-bottom:20px; color:#64748b; font-size:13px;">Get started quickly with these common tasks:</p>';
            echo '<div class="podify-action-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">';
            echo '<a href="'.$base.'&tab=import" class="podify-button-modern" style="justify-content:center; flex-direction:column; padding:15px; height:auto;"><span class="dashicons dashicons-plus-alt2"></span> <span style="margin-top:5px;">Import Feed</span></a>';
            echo '<a href="'.$base.'&tab=scheduled" class="podify-button-modern" style="justify-content:center; flex-direction:column; padding:15px; height:auto;"><span class="dashicons dashicons-calendar-alt"></span> <span style="margin-top:5px;">Schedules</span></a>';
            echo '<a href="'.$base.'&tab=episodes" class="podify-button-modern" style="justify-content:center; flex-direction:column; padding:15px; height:auto;"><span class="dashicons dashicons-playlist-audio"></span> <span style="margin-top:5px;">Episodes</span></a>';
            echo '<a href="'.$base.'&tab=settings" class="podify-button-modern" style="justify-content:center; flex-direction:column; padding:15px; height:auto;"><span class="dashicons dashicons-admin-settings"></span> <span style="margin-top:5px;">Settings</span></a>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="podify-card">';
            echo '<h3><span class="dashicons dashicons-info"></span> Plugin Details</h3>';
            echo '<div style="display:flex; flex-direction:column; gap:12px;">';
            echo '<p style="margin:0; font-size:13px; color:#475569;"><strong>Version:</strong> <span class="podify-badge">'.\PODIFY_PODCAST_VERSION.'</span></p>';
            echo '<p style="margin:0; font-size:13px; color:#475569;"><strong>Author:</strong> Podify LLC</p>';
            echo '<p style="margin:0; font-size:13px; color:#475569;"><strong>License:</strong> <span class="podify-badge" style="background:#fef9c3; color:#854d0e; border-color:#fef08a;">PRO</span></p>';
            echo '<p style="margin:20px 0 0; font-size:13px; color:#64748b;">Need help? Check the <a href="'.$base.'&tab=changelog" style="color:#2271b1; text-decoration:none; font-weight:600;">Changelog</a> or contact support.</p>';
            echo '</div>';
            echo '</div>';
            
            echo '</div>'; // End grid
            
        } elseif ($tab === 'import') {
            echo '<form method="post"><input type="hidden" name="podify_action" value="add_feed">';
            wp_nonce_field('podify_add_feed');
            echo '<div class="podify-grid">';
            echo '<div class="podify-card">';
            echo '<h3>Source & Publishing</h3>';
            echo '<div class="podify-field"><label>Podcast Feed URL</label><input type="url" name="feed_url" placeholder="https://example.com/feed/podcast" required></div>';
            echo '<div class="podify-field"><label>Post Type</label><select name="post_type">';
            foreach ($post_types as $pt) { echo '<option value="'.esc_attr($pt->name).'">'.esc_html($pt->labels->singular_name).'</option>'; }
            echo '</select></div>';
            echo '<div class="podify-field"><label>Post Status</label><select name="post_status">';
            foreach ($post_statuses as $k=>$v) { echo '<option value="'.esc_attr($k).'">'.esc_html($v).'</option>'; }
            echo '</select></div>';
            echo '<div class="podify-field"><label>Post Author</label><select name="post_author"><option value="0">— System —</option>';
            foreach ($authors as $u) { echo '<option value="'.esc_attr($u->ID).'">'.esc_html($u->display_name).'</option>'; }
            echo '</select></div>';
            echo '<div class="podify-field"><label>Import Interval</label><select name="interval">';
            foreach ($intervals as $key=>$label) { echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>'; }
            echo '</select></div>';
            echo '</div>';
            echo '<div class="podify-card">';
            echo '<h3>Content & Metadata</h3>';
            echo '<div class="podify-field"><label>Import transcripts from RSS (tag)</label><input type="text" name="transcript_tag" placeholder="content:encoded"></div>';
            echo '<div class="podify-field"><label>Audio player custom field key</label><input type="text" name="audio_field" placeholder="podcast_audio_player"></div>';
            echo '<div class="podify-field"><label><input type="checkbox" name="import_categories" value="1"> Import categories from feed</label></div>';
            echo '<div class="podify-field"><label><input type="checkbox" name="import_tags" value="1"> Import tags from feed</label></div>';
            echo '<div class="podify-field"><label>Global featured image URL</label><input type="url" name="featured_image" placeholder="https://example.com/image.jpg"></div>';
            echo '<div class="podify-field"><label><input type="checkbox" name="append_episode_number" value="1"> Append episode number to post title</label></div>';
            echo '</div>';
            echo '<div class="podify-card">';
            echo '<h3>Customization (Feed Specific)</h3>';
            echo '<div class="podify-field"><label>Read More Button Text</label><input type="text" name="read_more_text" placeholder="Read more"></div>';
            echo '<div class="podify-field"><label>Load More Button Text</label><input type="text" name="load_more_text" placeholder="Load more"></div>';
            
            echo '<h4 style="margin-top:20px; border-bottom:1px solid #eee; padding-bottom:5px;">Text Styling</h4>';
            echo '<div class="podify-field"><label>Title Font Family</label><input type="text" name="title_font" placeholder="e.g. \'Inter\', sans-serif"></div>';
            echo '<div class="podify-field"><label>Title Text Color</label><input type="text" name="title_color" class="podify-color-picker" value=""></div>';
            
            echo '<div class="podify-field"><label>Description Font Family</label><input type="text" name="desc_font" placeholder="e.g. system-ui, sans-serif"></div>';
            echo '<div class="podify-field"><label>Description Text Color</label><input type="text" name="desc_color" class="podify-color-picker" value=""></div>';
            
            echo '<div class="podify-field"><label>Meta Font Family</label><input type="text" name="meta_font" placeholder="e.g. sans-serif"></div>';
            echo '<div class="podify-field"><label>Meta Text Color</label><input type="text" name="meta_color" class="podify-color-picker" value=""></div>';
            
            echo '<h4 style="margin-top:20px; border-bottom:1px solid #eee; padding-bottom:5px;">Load More Hover Colors</h4>';
            echo '<div class="podify-field"><label>Hover Background Color</label><input type="text" name="lm_bg_hover" class="podify-color-picker" value=""></div>';
            echo '<div class="podify-field"><label>Hover Text Color</label><input type="text" name="lm_text_hover" class="podify-color-picker" value=""></div>';
            
            echo '</div>';
            echo '</div>';
            echo '<div class="podify-actions" style="margin-top:24px; text-align:right;"><button class="podify-button-modern podify-button-primary" style="padding:12px 24px;">Add Import</button></div></form>';
            echo '<script>(function($){ $(function(){ $(".podify-color-picker").wpColorPicker(); }); })(jQuery);</script>';
        } elseif ($tab === 'scheduled') {
            echo '<div class="podify-table-card">';
            echo '<table class="podify-modern-table">';
            echo '<thead><tr><th style="width:80px">ID</th><th>Feed Source</th><th style="width:380px">Actions</th></tr></thead>';
            echo '<tbody>';
            if ($feeds) {
                foreach ($feeds as $f) {
                    $id = intval($f['id']);
                    $url = esc_html($f['feed_url']);
                    $full = \PodifyPodcast\Core\Database::get_feed($id);
                    $opts = [];
                    if (!empty($full['options'])) { $opts = json_decode($full['options'], true) ?: []; }
                    $interval_cur = isset($opts['interval']) ? $opts['interval'] : 'hourly';
                    $read_more = $opts['read_more_text'] ?? '';
                    $load_more = $opts['load_more_text'] ?? '';
                    $t_font = $opts['title_font'] ?? '';
                    $t_color = $opts['title_color'] ?? '';
                    $d_font = $opts['desc_font'] ?? '';
                    $d_color = $opts['desc_color'] ?? '';
                    $m_font = $opts['meta_font'] ?? '';
                    $m_color = $opts['meta_color'] ?? '';
                    $lm_bg_h = $opts['lm_bg_hover'] ?? '';
                    $lm_txt_h = $opts['lm_text_hover'] ?? '';
                    
                    echo '<tr>';
                    echo '<td><span class="podify-badge">#'.$id.'</span></td>';
                    echo '<td><div class="podify-url-text" title="'.$url.'">'.$url.'</div></td>';
                    
                    echo '<td>';
                    echo '<div class="podify-actions-cell">';
                    echo '<a class="podify-button-modern podify-button-small" href="'.$base.'&tab=episodes&feed_id='.$id.'">Episodes</a> ';
                    echo '<button type="button" class="podify-button-modern podify-button-small podify-toggle-customization" data-id="'.$id.'">Customize</button> ';
                    echo '<button type="button" class="podify-button-modern podify-button-small podify-sync" data-id="'.$id.'"><span class="dashicons dashicons-update" style="font-size:14px;margin-right:2px"></span> Sync</button> ';
                    echo '<button type="button" class="podify-button-modern podify-button-small podify-resync" data-id="'.$id.'">Force</button> ';
                    echo '<form method="post" style="display:inline"><input type="hidden" name="podify_action" value="remove_feed"><input type="hidden" name="feed_id" value="'.$id.'">';
                    wp_nonce_field('podify_remove_feed');
                    echo '<button class="podify-button-modern podify-button-small podify-button-danger">Remove</button></form>';
                    echo '<div class="podify-progress-wrap" data-id="'.$id.'" style="width:100px; height:20px; margin-left:5px;"><div class="podify-progress-bar"></div><div class="podify-progress-text" style="font-size:9px; line-height:20px;"></div></div>';
                    echo '</div>'; 
                    echo '</td></tr>';

                    // Customization Row
                    echo '<tr id="podify-customization-'.$id.'" class="podify-customization-row" style="display:none; background:#f8fafc;">';
                    echo '<td colspan="3" style="padding:20px; border-bottom:2px solid #e2e8f0;">';
                    echo '<form method="post" class="podify-feed-options-form">';
                    echo '<input type="hidden" name="podify_action" value="update_feed_options"><input type="hidden" name="feed_id" value="'.$id.'">';
                    wp_nonce_field('podify_update_feed');
                    
                    echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:20px;">';
                    
                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Import Interval</label>';
                    echo '<select name="interval" style="width:100%;">';
                    foreach ($intervals as $key=>$label) {
                        $sel = $interval_cur===$key ? ' selected' : '';
                        echo '<option value="'.esc_attr($key).'"'.$sel.'>'.esc_html($label).'</option>';
                    }
                    echo '</select>';
                    echo '</div>';

                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Read More Text</label>';
                    echo '<input type="text" name="read_more_text" value="'.esc_attr($read_more).'" placeholder="Default" style="width:100%;">';
                    echo '</div>';

                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Load More Text</label>';
                    echo '<input type="text" name="load_more_text" value="'.esc_attr($load_more).'" placeholder="Default" style="width:100%;">';
                    echo '</div>';

                    echo '</div>'; // End grid

                    echo '<h4 style="margin-top:20px; border-bottom:1px solid #e2e8f0; padding-bottom:5px;">Text Styling</h4>';
                    echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:20px; margin-top:15px;">';
                    
                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Title Font Family</label>';
                    echo '<input type="text" name="title_font" value="'.esc_attr($t_font).'" placeholder="Default" style="width:100%;">';
                    echo '</div>';
                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Title Color</label>';
                    echo '<input type="text" name="title_color" class="podify-color-picker" value="'.esc_attr($t_color).'">';
                    echo '</div>';
                    
                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Description Font Family</label>';
                    echo '<input type="text" name="desc_font" value="'.esc_attr($d_font).'" placeholder="Default" style="width:100%;">';
                    echo '</div>';
                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Description Color</label>';
                    echo '<input type="text" name="desc_color" class="podify-color-picker" value="'.esc_attr($d_color).'">';
                    echo '</div>';
                    
                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Meta Font Family</label>';
                    echo '<input type="text" name="meta_font" value="'.esc_attr($m_font).'" placeholder="Default" style="width:100%;">';
                    echo '</div>';
                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Meta Color</label>';
                    echo '<input type="text" name="meta_color" class="podify-color-picker" value="'.esc_attr($m_color).'">';
                    echo '</div>';
                    
                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Load More Hover BG</label>';
                    echo '<input type="text" name="lm_bg_hover" class="podify-color-picker" value="'.esc_attr($lm_bg_h).'">';
                    echo '</div>';
                    echo '<div>';
                    echo '<label style="display:block; margin-bottom:5px; font-weight:600;">Load More Hover Text</label>';
                    echo '<input type="text" name="lm_text_hover" class="podify-color-picker" value="'.esc_attr($lm_txt_h).'">';
                    echo '</div>';
                    
                    echo '</div>'; // End styling grid

                    echo '<div style="margin-top:20px; text-align:right;">';
                    echo '<button class="button button-primary">Save Changes</button>';
                    echo '</div>';
                    
                    echo '</form></td></tr>';
                }
            } else {
                echo '<tr><td colspan="3" style="text-align:center; padding:40px; color:#64748b;">No scheduled imports found. <a href="'.$base.'&tab=import">Add one now</a>.</td></tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
            echo '<script>(function($){';
            echo '$(function(){ $(".podify-color-picker").wpColorPicker(); });';
            echo 'document.addEventListener("click",function(e){';
            echo 'var t=e.target.closest(".podify-toggle-customization");';
            echo 'if(t){ var id=t.getAttribute("data-id"); var row=document.getElementById("podify-customization-"+id); if(row) { ';
            echo '  var isHidden = (row.style.display==="none"); ';
            echo '  row.style.display=(isHidden?"table-row":"none"); ';
            echo '  if(isHidden) { $(row).find(".podify-color-picker").each(function(){ if(!$(this).next().hasClass("wp-picker-container")) $(this).wpColorPicker(); }); }';
            echo '} }';
            echo '});';
            echo '})(jQuery);</script>';
            echo '<script>(function(){';
            echo 'const SYNC_URL = '.wp_json_encode($sync_url).';';
            echo 'const RESYNC_URL = '.wp_json_encode($resync_url).';';
            echo 'const PROGRESS_URL = '.wp_json_encode($progress_url).';';
            echo 'const NONCE = '.wp_json_encode($nonce).';';
            echo 'function poll(id, btn, origText) {';
            echo '  var wrap = document.querySelector(".podify-progress-wrap[data-id=\'"+id+"\']");';
            echo '  var bar = wrap ? wrap.querySelector(".podify-progress-bar") : null;';
            echo '  var txt = wrap ? wrap.querySelector(".podify-progress-text") : null;';
            echo '  if(wrap) wrap.style.display="inline-block";';
            echo '  if(bar) bar.style.width="0%";';
            echo '  if(txt) txt.textContent="Starting...";';
            echo '  return setInterval(function(){';
            echo '    fetch(PROGRESS_URL+"?feed_id="+id, {headers:{"X-WP-Nonce":NONCE}}).then(function(r){';
            echo '      if(!r.ok) throw new Error("Status "+r.status);';
            echo '      return r.json();';
            echo '    }).then(function(d){';
            echo '      if(d.ok && d.status!=="idle"){';
            echo '        if(bar) {';
            echo '          bar.style.width=d.percentage+"%";';
            echo '          if(d.status.indexOf("Phase 2")!==-1){bar.style.backgroundColor="#46b450";}else{bar.style.backgroundColor="#2271b1";}';
            echo '        }';
            echo '        if(txt) txt.textContent=d.percentage+"% ("+d.current+"/"+d.total+") "+(d.status||"");';
            echo '      }';
            echo '    }).catch(function(e){});';
            echo '  }, 3000);';
            echo '}';
            echo 'function stopPoll(t, id, msg) {';
            echo '  clearInterval(t);';
            echo '  var wrap = document.querySelector(".podify-progress-wrap[data-id=\'"+id+"\']");';
            echo '  if(wrap) setTimeout(function(){ wrap.style.display="none"; }, 3000);'; // keep visible for 3s
            echo '  if(msg) alert(msg);';
            echo '}';
            echo 'document.addEventListener("click",function(e){';
            echo 'var b=e.target.closest(".podify-sync");';
            echo 'if(b){var id=b.getAttribute("data-id");b.disabled=true;var ot=b.textContent;var t=poll(id,b,ot);fetch(SYNC_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({feed_id:parseInt(id)})}).then(function(r){return r.json()}).then(function(d){b.disabled=false;b.textContent=ot;stopPoll(t,id,d.message||"Done")}).catch(function(){b.disabled=false;b.textContent=ot;stopPoll(t,id,"Failed")});return}';
            echo 'var r=e.target.closest(".podify-resync");';
            echo 'if(r){var id=r.getAttribute("data-id");r.disabled=true;var ot=r.textContent;var t=poll(id,r,ot);fetch(RESYNC_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({feed_id:parseInt(id)})}).then(function(resp){return resp.json()}).then(function(d){r.disabled=false;r.textContent=ot;stopPoll(t,id,d.message||"Done")}).catch(function(){r.disabled=false;r.textContent=ot;stopPoll(t,id,"Failed")})}';
            echo '});';
            echo '})();</script>';
        } elseif ($tab === 'episodes') {
            $feed_filter = isset($_GET['feed_id']) ? intval($_GET['feed_id']) : 0;
            $limit_ep = isset($_GET['limit']) ? max(25, min(500, intval($_GET['limit']))) : ($feed_filter ? 50 : 25);
            $category_filter_raw = isset($_GET['category_id']) ? sanitize_text_field($_GET['category_id']) : '';
            $category_filter_id = is_numeric($category_filter_raw) ? intval($category_filter_raw) : 0;
            $uncategorized_filter = ($category_filter_raw === 'uncategorized');

            $search_q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $orderby_q = isset($_GET['orderby']) && in_array($_GET['orderby'], ['published','title'], true) ? $_GET['orderby'] : 'published';
            $order_q = isset($_GET['order']) && in_array(strtolower($_GET['order']), ['asc','desc'], true) ? strtolower($_GET['order']) : 'desc';
            $has_audio_q = !empty($_GET['has_audio']) ? 1 : 0;

            $query_opts = [
                'feed_id' => $feed_filter ?: null,
                'limit' => $limit_ep,
                'offset' => 0, // Admin always starts at 0 (page 1) for PHP render? 
                // Wait, the table has data-offset logic but the initial render is always page 1?
                // The JS handles pagination via AJAX. But the initial render needs to match params.
                // The previous code had offset => 0.
                'category_id' => $category_filter_id ?: null,
                'uncategorized' => $uncategorized_filter,
                'q' => $search_q,
                'has_audio' => $has_audio_q,
                'orderby' => $orderby_q,
                'order' => $order_q
            ];
            $episodes = \PodifyPodcast\Core\Database::get_episodes_advanced($query_opts);
            $total_episodes = \PodifyPodcast\Core\Database::count_episodes_advanced($query_opts);
            $total_pages = $limit_ep > 0 ? max(1, (int)ceil($total_episodes / $limit_ep)) : 1;

            if ($feed_filter) {
                echo '<div class="podify-field" style="margin-top:0"><strong>Feed '.$feed_filter.':</strong> showing episodes for this feed ('.intval($total_episodes).' found). <a href="'.$base.'&tab=episodes&feed_id='.$feed_filter.'&limit=500">Show all for this feed</a></div>';
            }
            $has_limit_param = isset($_GET['limit']);

            echo '<div class="podify-admin-header">';
            
            // Bulk Actions
            echo '<div class="podify-bulk-group">';
            echo '<select id="podify-bulk-action-top"><option value="">Bulk Actions</option><option value="assign_category">Assign Category</option></select>';
            echo '<select id="podify-bulk-category-top" style="display:none; max-width:200px;"><option value="">Select Category</option>';
            $bulk_cats = \PodifyPodcast\Core\Database::get_categories($feed_filter ?: null);
            if ($bulk_cats) {
                foreach ($bulk_cats as $bc) {
                    $lbl = (!$feed_filter && !empty($bc['feed_id'])) ? ('Feed '.$bc['feed_id'].': ') : '';
                    echo '<option value="'.intval($bc['id']).'">'.esc_html($lbl . $bc['name']).'</option>';
                }
            }
            echo '</select>';
            echo '<button class="button" id="podify-bulk-apply-top">Apply</button>';
            echo '</div>'; // End bulk group

            // Items per page
            echo '<div class="podify-limit-group">';
            echo '<div class="podify-limit-field">';
            echo '<label for="podify-ep-limit">Items per page</label>';
            echo '<select id="podify-ep-limit"><option value="25"'.($limit_ep===25?' selected':'').'>25</option><option value="50"'.($limit_ep===50?' selected':'').'>50</option><option value="100"'.($limit_ep===100?' selected':'').'>100</option><option value="200"'.($limit_ep===200?' selected':'').'>200</option><option value="500"'.($limit_ep===500?' selected':'').'>500</option></select>';
            echo '</div>';
            echo '</div>';

            echo '</div>'; // End header

            echo '<div class="podify-filters-bar">';
            echo '<div class="podify-filters-grid">';

            echo '<div class="podify-filter-item podify-field"><label>Search Episodes</label><input type="text" id="podify-ep-search" value="'.esc_attr($search_q).'" placeholder="Title or description..."></div>';

            echo '<div class="podify-filter-item podify-field"><label>Category</label><select id="podify-ep-category">';
            echo '<option value=""'.(($category_filter_id===0 && !$uncategorized_filter)?' selected':'').'>All Categories</option>';
            if ($bulk_cats) {
                foreach ($bulk_cats as $bc) {
                    $lbl = (!$feed_filter && !empty($bc['feed_id'])) ? ('Feed '.$bc['feed_id'].': ') : '';
                    $cid = intval($bc['id']);
                    $sel = ($cid === $category_filter_id && !$uncategorized_filter) ? ' selected' : '';
                    echo '<option value="'.$cid.'"'.$sel.'>'.esc_html($lbl . $bc['name']).'</option>';
                }
            }
            echo '<option value="uncategorized"'.($uncategorized_filter?' selected':'').'>Uncategorized</option>';
            echo '</select></div>';

            echo '<div class="podify-filter-item podify-field"><label>Sort By</label><select id="podify-ep-orderby"><option value="published"'.($orderby_q==='published'?' selected':'').'>Published Date</option><option value="title"'.($orderby_q==='title'?' selected':'').'>Title</option></select></div>';

            echo '<div class="podify-filter-item podify-field"><label>Order</label><select id="podify-ep-order"><option value="desc"'.($order_q==='desc'?' selected':'').'>Desc</option><option value="asc"'.($order_q==='asc'?' selected':'').'>Asc</option></select></div>';

            echo '<div class="podify-filter-item podify-field"><div class="podify-checkbox-field"><label><input type="checkbox" id="podify-ep-audio" value="1"'.($has_audio_q? ' checked':'').'> Only episodes with audio</label></div></div>';

            echo '<div class="podify-filter-item podify-field align-self-end"><button type="button" class="podify-button-modern" id="podify-ep-apply"><span class="dashicons dashicons-filter"></span> Apply Filters</button></div>';

            echo '</div>'; // End filter grid
            echo '</div>'; // End filters bar

            $offset_cur = 0;
            echo '<div class="podify-table-card">';
            echo '<div class="podify-table-wrap">';
            echo '<div id="podify-table-loader" class="podify-loader-overlay"><div class="podify-loader-spinner"></div></div>';
            echo '<table id="podify-admin-episodes" class="podify-modern-table" data-feed="'.$feed_filter.'" data-offset="'.$offset_cur.'" data-limit="'.$limit_ep.'" data-page="1" data-total-episodes="'.intval($total_episodes).'" data-total-pages="'.intval($total_pages).'"><thead><tr><th class="check-column"><input type="checkbox" id="podify-ep-select-all"></th><th>Title</th><th>Feed</th><th>Published</th><th>Audio URL</th><th>Image URL</th><th>Category</th></tr></thead><tbody>';
            if ($episodes) {
                foreach ($episodes as $e) {
                    $title = esc_html($e['title']);
                    $feed_id = intval($e['feed_id']);
                    $pub = esc_html($e['published']);
                    $audio = !empty($e['audio_url']) && wp_http_validate_url($e['audio_url']) ? esc_url($e['audio_url']) : '';
                    $image = !empty($e['image_url']) && wp_http_validate_url($e['image_url']) ? esc_url($e['image_url']) : '';
                    $audioCell = $audio ? ('<a href="'.$audio.'" target="_blank" rel="noopener">Open</a>') : '—';
                    $imageCell = $image ? ('<a href="'.$image.'" target="_blank" rel="noopener">Open</a>') : '—';
                    $cats = \PodifyPodcast\Core\Database::get_categories($feed_id);
                    $assigned = \PodifyPodcast\Core\Database::get_episode_categories(intval($e['id']));
                    $assigned_names = $assigned ? implode(', ', array_map(function($c){ return esc_html($c['name']); }, $assigned)) : '—';
                    $select = '<select class="podify-assign-cat" data-episode="'.intval($e['id']).'"><option value="">Select category</option>';
                    if ($cats) {
                        foreach ($cats as $c) {
                            $select .= '<option value="'.intval($c['id']).'">'.esc_html($c['name']).'</option>';
                        }
                    }
                    $select .= '</select><div class="podify-assigned">Assigned: '.$assigned_names.'</div>';
                    echo '<tr class="podify-episode-row"><td><input type="checkbox" class="podify-ep-select" value="'.intval($e['id']).'"></td><td>'.$title.'</td><td>'.$feed_id.'</td><td>'.$pub.'</td><td>'.$audioCell.'</td><td>'.$imageCell.'</td><td>'.$select.'</td></tr>';
                }
            } else {
                echo '<tr><td colspan="7">No episodes</td></tr>';
            }
            echo '</tbody></table>';
            echo '</div>'; // End table wrap
            echo '</div>'; // End table card

            // 3. Pagination
            echo '<div class="podify-pagination">';
            echo '<span id="podify-admin-page">Page 1 of '.intval($total_pages).' ('.intval($total_episodes).' episodes)</span>';
            echo '<button class="button" id="podify-admin-prev" disabled><span class="dashicons dashicons-arrow-left-alt2" style="line-height:28px"></span></button>';
            echo '<button class="button" id="podify-admin-next"'.(($limit_ep >= $total_episodes)?' disabled':'').'><span class="dashicons dashicons-arrow-right-alt2" style="line-height:28px"></span></button>';
            echo '</div>';
            
            $assign_url = esc_url_raw( rest_url('podify/v1/assign-category') );
            $bulk_assign_url = esc_url_raw( rest_url('podify/v1/bulk-assign-category') );
            echo '<script>(function(){';
            echo 'const ASSIGN_URL = '.wp_json_encode($assign_url).';';
            echo 'const BULK_ASSIGN_URL = '.wp_json_encode($bulk_assign_url).';';
            echo 'const NONCE = '.wp_json_encode($nonce).';';
            echo 'document.addEventListener("change", function(e){ var sel = e.target.closest(".podify-assign-cat"); if(!sel) return; var eid = parseInt(sel.getAttribute("data-episode")); var cid = parseInt(sel.value); if(!eid || !cid) return; sel.disabled = true; fetch(ASSIGN_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({episode_id:eid,category_id:cid})}).then(function(r){return r.json()}).then(function(d){ sel.disabled=false; if(d && d.ok){ sel.nextElementSibling && (sel.nextElementSibling.textContent = "Assigned: " + sel.options[sel.selectedIndex].text); } else { alert("Failed to assign"); } }).catch(function(){ sel.disabled=false; alert("Failed"); }); });';
            echo 'document.addEventListener("change", function(e){ var master = e.target.closest("#podify-ep-select-all"); if(!master) return; var table = document.getElementById("podify-admin-episodes"); if(!table) return; var boxes = table.querySelectorAll(".podify-ep-select"); boxes.forEach(function(b){ b.checked = master.checked; }); });';
            // Pagination JS
            $episodes_url = esc_url_raw( rest_url('podify/v1/episodes') );
                $cats_url = esc_url_raw( rest_url('podify/v1/categories') );
                echo 'const EP_URL = '.wp_json_encode($episodes_url).';';
                echo 'const CATS_URL = '.wp_json_encode($cats_url).';';
                echo 'let PODIFY_CATS = [];';
                echo 'function fetchCats(){ return fetch(CATS_URL + "?feed_id='.intval($feed_filter).'", {headers:{"X-WP-Nonce":NONCE}}).then(function(r){return r.json()}).then(function(d){ PODIFY_CATS = (d && d.items) ? d.items : []; }).catch(function(){ PODIFY_CATS = []; }); }';
                echo 'function makeCatSelect(epId, assignedCats){ var html = \'<select class="podify-assign-cat" data-episode="\'+epId+\'"><option value="">Select category</option>\'; PODIFY_CATS.forEach(function(c){ var label = (c.feed_id ? "Feed "+c.feed_id+": " : "") + (c.name||""); html += \'<option value="\'+(c.id||0)+\'">\'+label+\'</option>\'; }); var assignedNames = (assignedCats && assignedCats.length) ? assignedCats.map(function(c){ return c.name; }).join(", ") : "—"; html += \'</select><div class="podify-assigned">Assigned: \'+assignedNames+\'</div>\'; return html; }';
                echo 'function ensureEpisodeCheckboxes(){ var table=document.getElementById("podify-admin-episodes"); if(!table) return; var rows=table.querySelectorAll("tbody tr"); rows.forEach(function(tr){ if(tr.querySelector(".podify-ep-select")) return; var sel=tr.querySelector(".podify-assign-cat"); var eid=sel?parseInt(sel.getAttribute("data-episode"))||0:0; var firstCell=tr.querySelector("td"); if(!firstCell) return; var td=document.createElement("td"); var val=eid?String(eid):""; td.innerHTML = \'<input type="checkbox" class="podify-ep-select" value="\'+val+\'">\'; tr.insertBefore(td, firstCell); }); }';
                echo 'function initEpisodeCheckboxObserver(){ var table=document.getElementById("podify-admin-episodes"); if(!table || !window.MutationObserver) return; var tbody=table.querySelector("tbody"); if(!tbody) return; var obs=new MutationObserver(function(){ ensureEpisodeCheckboxes(); }); obs.observe(tbody,{childList:true}); }';
                echo 'ensureEpisodeCheckboxes(); initEpisodeCheckboxObserver();';
                echo 'function setPageLabel(p){ var el=document.getElementById("podify-admin-page"); if(!el) return; var table=document.getElementById("podify-admin-episodes"); var totalPages=1; var totalEpisodes=0; if(table){ var tp=parseInt(table.getAttribute("data-total-pages"))||0; var te=parseInt(table.getAttribute("data-total-episodes"))||0; if(tp>0) totalPages=tp; if(te>0) totalEpisodes=te; } var label="Page "+String(p)+" of "+String(totalPages); if(totalEpisodes>0){ label += " ("+String(totalEpisodes)+" episodes)"; } el.textContent=label; }';
                echo 'function setPrevNextDisabled(prevDis,nextDis){ var p=document.getElementById("podify-admin-prev"); var n=document.getElementById("podify-admin-next"); if(p) p.disabled=!!prevDis; if(n) n.disabled=!!nextDis; }';
                echo 'function showLoader(show){ var l=document.getElementById("podify-table-loader"); if(l) l.style.display = show ? "flex" : "none"; }';
                echo 'function buildParams(limit, offset){ var q=(document.getElementById("podify-ep-search")||{value:""}).value||""; var cat=(document.getElementById("podify-ep-category")||{value:""}).value||""; var ob=(document.getElementById("podify-ep-orderby")||{value:"published"}).value||"published"; var or=(document.getElementById("podify-ep-order")||{value:"desc"}).value||"desc"; var ha=(document.getElementById("podify-ep-audio")||{checked:false}).checked?1:0; var s="&limit="+encodeURIComponent(limit)+"&offset="+encodeURIComponent(offset); if(q.length){ s += "&q="+encodeURIComponent(q); } if(cat){ s += "&category_id="+encodeURIComponent(cat); } if(ob){ s += "&orderby="+encodeURIComponent(ob); } if(or){ s += "&order="+encodeURIComponent(or); } if(ha){ s += "&has_audio=1"; } return s; }';
                echo 'document.addEventListener("click", function(e){ var btnPrev = e.target.closest("#podify-admin-prev"); var btnNext = e.target.closest("#podify-admin-next"); var btnApply = e.target.closest("#podify-ep-apply"); if(!btnPrev && !btnNext && !btnApply) return; var table = document.getElementById("podify-admin-episodes"); if(!table) return; var feed = parseInt(table.getAttribute("data-feed"))||0; var limit = parseInt(table.getAttribute("data-limit"))||50; var page = parseInt(table.getAttribute("data-page"))||1; var nextPage = btnApply ? 1 : (page + (btnNext?1:-1)); if(nextPage<1) nextPage=1; var offset = (nextPage-1)*limit; var url = EP_URL + "?feed_id=" + encodeURIComponent(feed) + buildParams(limit, offset); btnPrev && (btnPrev.disabled = true); btnNext && (btnNext.disabled = true); showLoader(true); fetchCats().then(function(){ return fetch(url).then(function(r){ return r.json(); }); }).then(function(d){ var tbody = table.querySelector("tbody"); var rowsHtml = ""; if(d && d.items){ d.items.forEach(function(it){ var title = it.title || ""; var pub = it.published || ""; var audio = it.audio_url || ""; var image = it.image_url || ""; var feedId = it.feed_id || 0; var audioCell = audio ? (\'<a href="\'+audio+\'" target="_blank" rel="noopener">Open</a>\') : "—"; var imageCell = image ? (\'<a href="\'+image+\'" target="_blank" rel="noopener">Open</a>\') : "—"; rowsHtml += \'<tr class="podify-episode-row"><td><input type="checkbox" class="podify-ep-select" value="\'+it.id+\'"></td><td>\'+title+\'</td><td>\'+feedId+\'</td><td>\'+pub+\'</td><td>\'+audioCell+\'</td><td>\'+imageCell+\'</td><td>\'+makeCatSelect(it.id, it.categories)+\'</td></tr>\'; }); } tbody.innerHTML = rowsHtml; var totalCount = d && typeof d.total_count !== "undefined" ? parseInt(d.total_count) || 0 : 0; var totalPages = limit > 0 ? Math.max(1, Math.ceil(totalCount/limit)) : 1; if(totalCount>0){ table.setAttribute("data-total-episodes", String(totalCount)); table.setAttribute("data-total-pages", String(totalPages)); } table.setAttribute("data-page", String(nextPage)); table.setAttribute("data-offset", String(offset)); setPageLabel(nextPage); setPrevNextDisabled(nextPage<=1, nextPage>=totalPages); ensureEpisodeCheckboxes(); showLoader(false); }).catch(function(){ alert("Failed to load episodes"); btnPrev && (btnPrev.disabled = false); btnNext && (btnNext.disabled = false); showLoader(false); }); });';
                echo 'var PODIFY_SEARCH_TIMER; document.addEventListener("input", function(e){ var s = e.target.closest("#podify-ep-search"); if(!s) return; clearTimeout(PODIFY_SEARCH_TIMER); PODIFY_SEARCH_TIMER = setTimeout(function(){ var btn = document.getElementById("podify-ep-apply"); if(btn){ btn.click(); } }, 300); }); document.addEventListener("change", function(e){ var sel = e.target.closest("#podify-ep-limit"); if(!sel) return; var v = parseInt(sel.value)||50; var url = new URL(window.location.href); url.searchParams.set("feed_id", "'.intval($feed_filter).'"); url.searchParams.set("limit", String(v)); window.location.href = url.toString(); });';
        
        echo 'document.addEventListener("change", function(e){ var sel=e.target.closest("#podify-bulk-action-top"); if(!sel) return; var cat=document.getElementById("podify-bulk-category-top"); if(cat) cat.style.display=(sel.value==="assign_category"?"inline-block":"none"); });';
            echo 'document.addEventListener("click", function(e){ var btn=e.target.closest("#podify-bulk-apply-top"); if(!btn) return; var sel=document.getElementById("podify-bulk-action-top"); var act=sel?sel.value:""; if(!act){alert("Select an action");return;} var cbs=document.querySelectorAll(".podify-ep-select:checked"); if(!cbs.length){alert("No episodes selected");return;} var ids=[]; cbs.forEach(function(c){ids.push(parseInt(c.value))}); if(act==="assign_category"){ var catSel=document.getElementById("podify-bulk-category-top"); var catId=catSel?parseInt(catSel.value):0; if(!catId){alert("Select a category");return;} btn.disabled=true; btn.textContent="Applying..."; fetch(BULK_ASSIGN_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({episode_ids:ids,category_id:catId})}).then(function(r){return r.json()}).then(function(d){ btn.disabled=false; btn.textContent="Apply"; if(d&&d.ok){ alert("Assigned to "+(d.count||0)+" episodes"); window.location.reload(); }else{ alert("Failed: "+(d.message||"Error")); } }).catch(function(e){ btn.disabled=false; btn.textContent="Apply"; alert("Error: "+e); }); } });';
            echo '})();</script>';
        } elseif ($tab === 'categories') {
            echo '<div class="podify-categories-grid">';
            echo '<div class="podify-card"><h3><span class="dashicons dashicons-plus-alt2"></span> Add Category</h3>';
            echo '<form method="post"><input type="hidden" name="podify_action" value="add_category">';
            wp_nonce_field('podify_add_category');
            echo '<div class="podify-field"><label>Feed</label><select name="feed_id">';
            if ($feeds) {
                foreach ($feeds as $f) {
                    echo '<option value="'.intval($f['id']).'">Feed '.intval($f['id']).'</option>';
                }
            }
            echo '</select></div>';
            echo '<div class="podify-field"><label>Category Name</label><input type="text" name="category_name" required placeholder="e.g. Health & Fitness"></div>';
            echo '<div class="podify-actions" style="margin-top:24px;"><button class="podify-button-modern podify-button-primary" style="width:100%; justify-content:center;">Add Category</button></div></form>';
            echo '</div>';
            
            echo '<div class="podify-table-card"><h3><span class="dashicons dashicons-category"></span> Existing Categories</h3>';
            $allCats = \PodifyPodcast\Core\Database::get_categories(null);
            if ($allCats) {
                echo '<table class="podify-modern-table"><thead><tr><th style="width:100px">Feed</th><th style="width:60px">ID</th><th>Name</th><th>Actions</th></tr></thead><tbody>';
                foreach ($allCats as $c) {
                    $rowId = intval($c['id']);
                    $currentFeedId = intval($c['feed_id']);
                    $name = esc_html($c['name']);
                    $slug = esc_html($c['slug']);
                    
                    // Color values
                    $c1 = $c['card_bg_color'] ?: '';
                    $bbg = $c['button_bg_color'] ?: '';
                    $btxt = $c['button_text_color'] ?: '';
                    $lmbg = $c['load_more_bg_color'] ?: '';
                    $lmtxt = $c['load_more_text_color'] ?: '';
                    $lmbgh = $c['load_more_bg_hover_color'] ?: '';
                    $lmtxth = $c['load_more_text_hover_color'] ?: '';

                    $feedSelect = '<select class="podify-cat-feed" data-id="'.$rowId.'" style="width:auto; padding:4px 8px; height:auto; font-size:12px;"><option value="0"'.($currentFeedId===0?' selected':'').'>— Unassigned —</option>';
                    if ($feeds) {
                        foreach ($feeds as $f) {
                            $fid = intval($f['id']);
                            $sel = ($fid === $currentFeedId) ? ' selected' : '';
                            $feedSelect .= '<option value="'.$fid.'"'.$sel.'>Feed '.$fid.'</option>';
                        }
                    }
                    $feedSelect .= '</select>';

                    echo '<tr>';
                    echo '<td>'.$feedSelect.'</td>';
                    echo '<td><span class="podify-badge">#'.$rowId.'</span></td>';
                    echo '<td><input type="text" value="'.$name.'" class="podify-cat-name" data-id="'.$rowId.'" style="padding:4px 8px; height:auto; font-size:12px;"></td>';
                    echo '<td>';
                    echo '<div class="podify-actions-cell">';
                    echo '<button class="podify-button-modern podify-button-small podify-cat-customize-toggle" data-id="'.$rowId.'"><span class="dashicons dashicons-art" style="font-size:14px; margin-right:2px;"></span> Colors</button>';
                    echo '<button class="podify-button-modern podify-button-small podify-cat-save" data-id="'.$rowId.'">Save</button>';
                    echo '<button class="podify-button-modern podify-button-small podify-button-danger podify-cat-delete" data-id="'.$rowId.'">Delete</button>';
                    echo '</div></td></tr>';
                    
                    // Customization Row
                    echo '<tr id="podify-cat-customization-'.$rowId.'" style="display:none; background:#f8fafc;"><td colspan="4" style="padding:20px; border-bottom:1px solid #e2e8f0; border-top:1px solid #e2e8f0;">';
                    echo '<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(160px, 1fr)); gap:20px;">';
                    
                    echo '<div><label style="display:block;margin-bottom:6px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Card Background</label><input type="text" class="podify-color-picker podify-cat-c1" data-id="'.$rowId.'" value="'.$c1.'"></div>';
                    echo '<div><label style="display:block;margin-bottom:6px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Read More BG</label><input type="text" class="podify-color-picker podify-cat-bbg" data-id="'.$rowId.'" value="'.$bbg.'"></div>';
                    echo '<div><label style="display:block;margin-bottom:6px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Read More Text</label><input type="text" class="podify-color-picker podify-cat-btxt" data-id="'.$rowId.'" value="'.$btxt.'"></div>';
                    
                    echo '<div><label style="display:block;margin-bottom:6px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Load More BG</label><input type="text" class="podify-color-picker podify-cat-lmbg" data-id="'.$rowId.'" value="'.$lmbg.'"></div>';
                    echo '<div><label style="display:block;margin-bottom:6px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Load More Text</label><input type="text" class="podify-color-picker podify-cat-lmtxt" data-id="'.$rowId.'" value="'.$lmtxt.'"></div>';
                    echo '<div><label style="display:block;margin-bottom:6px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Hover Background</label><input type="text" class="podify-color-picker podify-cat-lmbgh" data-id="'.$rowId.'" value="'.$lmbgh.'"></div>';
                    echo '<div><label style="display:block;margin-bottom:6px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;">Hover Text</label><input type="text" class="podify-color-picker podify-cat-lmtxth" data-id="'.$rowId.'" value="'.$lmtxth.'"></div>';
                    
                    echo '</div></td></tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<div style="padding:40px; text-align:center; color:#64748b;">No categories found. Add your first category to get started.</div>';
            }
            echo '</div>';
            echo '</div>'; // End categories grid
            $nonce = wp_create_nonce('wp_rest');
            $upd_url = esc_url_raw( rest_url('podify/v1/update-category') );
            $del_url = esc_url_raw( rest_url('podify/v1/delete-category') );
            echo '<script>(function(){';
            echo 'const NONCE = '.wp_json_encode($nonce).';';
            echo 'const UPD_URL = '.wp_json_encode($upd_url).';';
            echo 'const DEL_URL = '.wp_json_encode($del_url).';';
            echo 'document.addEventListener("click",function(e){var s=e.target.closest(".podify-cat-save");if(s){var id=parseInt(s.getAttribute("data-id"));var input=document.querySelector(".podify-cat-name[data-id=\'"+id+"\']");var feedSel=document.querySelector(".podify-cat-feed[data-id=\'"+id+"\']");if(!input)return;var name=input.value.trim();if(!name)return;var feedId=feedSel?parseInt(feedSel.value):0;';
            echo 'var colors={';
            echo 'card_bg_color:document.querySelector(".podify-cat-c1[data-id=\'"+id+"\']").value,';
            echo 'button_bg_color:document.querySelector(".podify-cat-bbg[data-id=\'"+id+"\']").value,';
            echo 'button_text_color:document.querySelector(".podify-cat-btxt[data-id=\'"+id+"\']").value,';
            echo 'load_more_bg_color:document.querySelector(".podify-cat-lmbg[data-id=\'"+id+"\']").value,';
            echo 'load_more_text_color:document.querySelector(".podify-cat-lmtxt[data-id=\'"+id+"\']").value,';
            echo 'load_more_bg_hover_color:document.querySelector(".podify-cat-lmbgh[data-id=\'"+id+"\']").value,';
            echo 'load_more_text_hover_color:document.querySelector(".podify-cat-lmtxth[data-id=\'"+id+"\']").value';
            echo '};';
            echo 's.disabled=true;fetch(UPD_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({id:id,name:name,feed_id:feedId,colors:colors})}).then(function(r){return r.json()}).then(function(d){s.disabled=false;if(d&&d.ok){var slugCell=s.closest("tr").querySelector(".podify-cat-slug");if(slugCell){slugCell.textContent=name.toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"");}}else{alert("Failed to update: "+(d.message||"Unknown error"))}}).catch(function(){s.disabled=false;alert("Failed")});}});';
            echo 'document.addEventListener("click",function(e){var t=e.target.closest(".podify-cat-customize-toggle");if(t){var id=t.getAttribute("data-id");var row=document.getElementById("podify-cat-customization-"+id);if(row){var isH=(row.style.display==="none");row.style.display=(isH?"table-row":"none");if(isH){jQuery(row).find(".podify-color-picker").each(function(){if(!jQuery(this).next().hasClass("wp-picker-container"))jQuery(this).wpColorPicker();});}}}});';
            echo 'document.addEventListener("click",function(e){var b=e.target.closest(".podify-cat-delete");if(b){var id=parseInt(b.getAttribute("data-id"));if(!id)return;if(!confirm("Delete this category?"))return;b.disabled=true;fetch(DEL_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({id:id})}).then(function(r){return r.json()}).then(function(d){b.disabled=false;if(d&&d.ok){var tr=b.closest("tr");if(tr){tr.parentNode.removeChild(tr);var cRow=document.getElementById("podify-cat-customization-"+id);if(cRow)cRow.parentNode.removeChild(cRow);}}else{alert("Failed to delete")}}).catch(function(){b.disabled=false;alert("Failed")});}});';
            echo '})();</script>';
        } elseif ($tab === 'changelog') {
            echo '<div class="podify-changelog">';
            echo '<h1>Changelog</h1>';
            $file = \PODIFY_PODCAST_PATH . 'changelog.md';
            if (file_exists($file)) {
                $raw = file_get_contents($file);
                $lines = explode("\n", $raw);
                $in_list = false;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        if ($in_list) { echo '</ul>'; $in_list = false; }
                        continue;
                    }
                    if (strpos($line, '## ') === 0) {
                        if ($in_list) { echo '</ul>'; $in_list = false; }
                        echo '<h2>'.esc_html(substr($line, 3)).'</h2>';
                    } elseif (strpos($line, '### ') === 0) {
                        if ($in_list) { echo '</ul>'; $in_list = false; }
                        echo '<h3>'.esc_html(substr($line, 4)).'</h3>';
                    } elseif (strpos($line, '- ') === 0) {
                        if (!$in_list) { echo '<ul>'; $in_list = true; }
                        $content = substr($line, 2);
                        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
                        $content = preg_replace('/`(.+?)`/', '<code>$1</code>', $content);
                        echo '<li>'.$content.'</li>';
                    } else {
                        if ($in_list) { echo '</ul>'; $in_list = false; }
                        $content = $line;
                        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
                        echo '<p>'.$content.'</p>';
                    }
                }
                if ($in_list) { echo '</ul>'; }
            } else {
                echo '<p>Changelog not found.</p>';
            }
            echo '</div>';
        } else {
            $settings = \PodifyPodcast\Core\Settings::get();
            echo '<div class="podify-grid">';
            echo '<div class="podify-card"><h3>Shortcodes</h3>';
            echo '<div class="podify-field"><label>Episodes List</label><input type="text" readonly value="[podify_podcast_list]" /></div>';
            echo '<div class="podify-field"><label>By Feed</label><input type="text" readonly value="[podify_podcast_list feed_id=&quot;1&quot;]" /></div>';
            echo '<div class="podify-field"><label>By Feed + Category (optional)</label><input type="text" readonly value="[podify_podcast_list feed_id=&quot;1&quot; category_id=&quot;10&quot;]" /></div>';
            echo '<div class="podify-field"><label>Custom Limit & Columns</label><input type="text" readonly value="[podify_podcast_list limit=&quot;12&quot; cols=&quot;4&quot;]" /></div>';
            echo '<div class="podify-field"><label>Card Layout (via shortcode)</label><input type="text" readonly value="[podify_podcast_list layout=&quot;modern&quot;]" /><p class="description">Accepted values: <code>modern</code> or <code>classic</code>. Example with feed: [podify_podcast_list feed_id=&quot;1&quot; layout=&quot;modern&quot;]</p></div>';
            echo '<p style="margin-top:8px">Tip: Omit category_id to display all episodes for the chosen feed. Use the Categories tab to find the exact Category ID.</p>';
            echo '</div>';
            echo '<div class="podify-card"><h3>Sticky Player & Custom CSS</h3>';
            echo '<form method="post"><input type="hidden" name="podify_action" value="save_settings">';
            wp_nonce_field('podify_save_settings');
            $enabled = !empty($settings['sticky_player_enabled']);
            $position = !empty($settings['sticky_player_position']) ? $settings['sticky_player_position'] : 'bottom';

            echo '<div class="podify-field"><label><input type="checkbox" name="sticky_player_enabled" value="1"'.($enabled?' checked':'').'> Enable sticky player</label></div>';
            echo '<div class="podify-field"><label>Position</label><select name="sticky_player_position"><option value="bottom"'.($position==='bottom'?' selected':'').'>Bottom</option><option value="top"'.($position==='top'?' selected':'').'>Top</option></select></div>';
            
            $custom_css = !empty($settings['custom_css']) ? $settings['custom_css'] : '';
            echo '<div class="podify-field"><label>Custom CSS for Episode Cards</label><textarea name="custom_css" rows="12" placeholder="/* Add CSS to style the episode cards and category pills */" style="height: 125px;">'.esc_textarea($custom_css).'</textarea><p class="description">Control layout per shortcode: [podify_podcast_list layout="classic|modern"]. Target elements like .podify-episode-card, .podify-episode-title, .podify-category-pill. Your CSS is injected sitewide.</p></div>';
            echo '<div class="podify-actions" style="text-align:right;"><button class="podify-button-modern podify-button-primary">Save Settings</button></div></form>';
            echo '</div>';
            echo '<script>(function($){ $(function(){ $(".podify-color-picker").wpColorPicker(); }); })(jQuery);</script>';
            echo '<div class="podify-card"><h3><span class="dashicons dashicons-admin-tools"></span> Tools</h3>';
            echo '<form method="post"><input type="hidden" name="podify_action" value="clear_cache">';
            wp_nonce_field('podify_clear_cache');
            echo '<p style="color:#64748b; margin-bottom:20px;">Clear plugin caches and force update checks.</p>';
            echo '<div class="podify-actions" style="text-align:right;"><button class="podify-button-modern">Clear Cache</button></div></form>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>'; // End podify-content
        echo '</div>'; // End podify-admin-layout
        echo '</div>'; // End wrap
    }
}
