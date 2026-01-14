<?php
namespace PodifyPodcast\Core\Admin;

class AdminInit {
    const SLUG = 'podify-podcast-importer';
    public static function register() {
        add_action('admin_menu', [self::class,'menu']);
        add_action('admin_enqueue_scripts', [self::class,'enqueue']);
    }
    public static function menu() {
        add_menu_page('Podcast Importer','Podcast Importer','manage_options',self::SLUG,[self::class,'page'], 'dashicons-microphone');
        add_submenu_page(self::SLUG,'Podcast Importer','Dashboard','manage_options',self::SLUG,[self::class,'page']);
    }
    public static function enqueue($hook) {
        if (isset($_GET['page']) && $_GET['page'] === self::SLUG) {
            wp_enqueue_style('podify_admin', \PODIFY_PODCAST_URL . 'assets/css/admin.css', [], \PODIFY_PODCAST_VERSION);
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
        $authors = get_users(['who'=>'authors']);
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'import';
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
        $feeds = \PodifyPodcast\Core\Database::get_feeds();
        $episodes = \PodifyPodcast\Core\Database::get_episodes(null, 10, 0);
        $sync_url = esc_url_raw( rest_url('podify/v1/sync') );
        $resync_url = esc_url_raw( rest_url('podify/v1/resync') );
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="wrap">';
        echo '<h1>Podcast Importer</h1>';
        $msg = isset($_GET['podify_msg']) ? sanitize_key($_GET['podify_msg']) : '';
        if ($msg === 'added') $notice = 'Feed added';
        if ($msg === 'removed') $notice = 'Feed removed';
        if ($msg === 'cat_added') $notice = 'Category added';
        if ($notice) echo '<div class="updated"><p>'.esc_html($notice).'</p></div>';
        echo '<div class="podify-admin">';
        $base = admin_url('admin.php?page='.self::SLUG);
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="'.$base.'&tab=import" class="nav-tab'.($tab==='import'?' nav-tab-active':'').'">Import Feed</a>';
        echo '<a href="'.$base.'&tab=scheduled" class="nav-tab'.($tab==='scheduled'?' nav-tab-active':'').'">Scheduled Imports</a>';
        echo '<a href="'.$base.'&tab=episodes" class="nav-tab'.($tab==='episodes'?' nav-tab-active':'').'">Podcast Episodes</a>';
        echo '<a href="'.$base.'&tab=categories" class="nav-tab'.($tab==='categories'?' nav-tab-active':'').'">Categories</a>';
        echo '<a href="'.$base.'&tab=shortcodes" class="nav-tab'.($tab==='shortcodes'?' nav-tab-active':'').'">Shortcodes & Settings</a>';
        echo '</h2>';
        if ($tab === 'import') {
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
            echo '</div>';
            echo '<div class="podify-actions"><button class="button button-primary">Add Import</button></div></form>';
        } elseif ($tab === 'scheduled') {
            echo '<table class="widefat"><thead><tr><th>Title</th><th>Feed Link</th><th>Interval</th><th>Actions</th></tr></thead><tbody>';
            if ($feeds) {
                foreach ($feeds as $f) {
                    $id = intval($f['id']);
                    $url = esc_html($f['feed_url']);
                    $full = \PodifyPodcast\Core\Database::get_feed($id);
                    $opts = [];
                    if (!empty($full['options'])) { $opts = json_decode($full['options'], true) ?: []; }
                    $interval_cur = isset($opts['interval']) ? $opts['interval'] : 'hourly';
                    echo '<tr><td>Feed '.$id.'</td><td>'.$url.'</td><td>';
                    echo '<form method="post" style="display:inline;margin-right:8px"><input type="hidden" name="podify_action" value="update_feed_options"><input type="hidden" name="feed_id" value="'.$id.'">';
                    wp_nonce_field('podify_update_feed');
                    echo '<select name="interval" style="width:180px">';
                    foreach ($intervals as $key=>$label) {
                        $sel = $interval_cur===$key ? ' selected' : '';
                        echo '<option value="'.esc_attr($key).'"'.$sel.'>'.esc_html($label).'</option>';
                    }
                    echo '</select> ';
                    echo '<button class="button">Save</button></form>';
                    echo '</td><td>';
                    echo '<a class="button" href="'.$base.'&tab=episodes&feed_id='.$id.'">View Episodes</a> ';
                    echo '<button class="button podify-sync" data-id="'.$id.'">Sync now</button> ';
                    echo '<button class="button podify-resync" data-id="'.$id.'">Force Re-Sync</button> ';
                    echo '<form method="post" style="display:inline"><input type="hidden" name="podify_action" value="remove_feed"><input type="hidden" name="feed_id" value="'.$id.'">';
                    wp_nonce_field('podify_remove_feed');
                    echo '<button class="button button-link-delete">Remove</button></form>';
                    echo '</td></tr>';
                }
            } else {
                echo '<tr><td colspan="4">No scheduled imports</td></tr>';
            }
            echo '</tbody></table>';
            echo '<script>(function(){';
            echo 'const SYNC_URL = '.wp_json_encode($sync_url).';';
            echo 'const RESYNC_URL = '.wp_json_encode($resync_url).';';
            echo 'const NONCE = '.wp_json_encode($nonce).';';
            echo 'document.addEventListener("click",function(e){';
            echo 'var b=e.target.closest(".podify-sync");';
            echo 'if(b){var id=b.getAttribute("data-id");b.disabled=true;fetch(SYNC_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({feed_id:parseInt(id)})}).then(function(r){return r.json()}).then(function(d){b.disabled=false;alert(d.message||"Done")}).catch(function(){b.disabled=false;alert("Failed")});return}';
            echo 'var r=e.target.closest(".podify-resync");';
            echo 'if(r){var id=r.getAttribute("data-id");r.disabled=true;fetch(RESYNC_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({feed_id:parseInt(id)})}).then(function(resp){return resp.json()}).then(function(d){r.disabled=false;alert(d.message||"Done")}).catch(function(){r.disabled=false;alert("Failed")})}';
            echo '});';
            echo '})();</script>';
        } elseif ($tab === 'episodes') {
            $feed_filter = isset($_GET['feed_id']) ? intval($_GET['feed_id']) : 0;
            $limit_ep = $feed_filter ? (isset($_GET['limit']) ? max(25, min(500, intval($_GET['limit']))) : 50) : 10;
            $search_q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $orderby_q = isset($_GET['orderby']) && in_array($_GET['orderby'], ['published','title'], true) ? $_GET['orderby'] : 'published';
            $order_q = isset($_GET['order']) && in_array(strtolower($_GET['order']), ['asc','desc'], true) ? strtolower($_GET['order']) : 'desc';
            $has_audio_q = !empty($_GET['has_audio']) ? 1 : 0;
            if ($feed_filter) {
                $episodes = \PodifyPodcast\Core\Database::get_episodes_advanced([
                    'feed_id' => $feed_filter,
                    'limit' => $limit_ep,
                    'offset' => 0,
                    'q' => $search_q,
                    'has_audio' => $has_audio_q,
                    'orderby' => $orderby_q,
                    'order' => $order_q
                ]);
            } else {
                $episodes = \PodifyPodcast\Core\Database::get_episodes(null, $limit_ep, 0);
            }
            if ($feed_filter) {
                $has_limit_param = isset($_GET['limit']);
                $total_episodes = \PodifyPodcast\Core\Database::count_episodes($feed_filter, null);
                $total_pages = $limit_ep > 0 ? max(1, (int)ceil($total_episodes / $limit_ep)) : 1;
                echo '<div class="podify-field" style="margin-top:0"><strong>Feed '.$feed_filter.':</strong> showing episodes for this feed ('.intval($total_episodes).' total episodes). <a href="'.$base.'&tab=episodes&feed_id='.$feed_filter.'&limit=500">Show all for this feed</a></div>';
                echo '<div class="podify-filter-bar">';
                echo '<div class="podify-field"><label>Items per page</label><select id="podify-ep-limit"><option value=""'.(!$has_limit_param?' selected':'').'>Select items per page</option><option value="25"'.($has_limit_param && $limit_ep===25?' selected':'').'>25</option><option value="50"'.($has_limit_param && $limit_ep===50?' selected':'').'>50</option><option value="100"'.($has_limit_param && $limit_ep===100?' selected':'').'>100</option><option value="200"'.($has_limit_param && $limit_ep===200?' selected':'').'>200</option><option value="500"'.($has_limit_param && $limit_ep===500?' selected':'').'>500</option></select></div>';
                echo '<div class="podify-field"><label>Search</label><input type="text" id="podify-ep-search" value="'.esc_attr($search_q).'" placeholder="Search title or description"></div>';
                echo '<div class="podify-field"><label>Order by</label><select id="podify-ep-orderby"><option value="published"'.($orderby_q==='published'?' selected':'').'>Published</option><option value="title"'.($orderby_q==='title'?' selected':'').'>Title</option></select></div>';
                echo '<div class="podify-field"><label>Order</label><select id="podify-ep-order"><option value="desc"'.($order_q==='desc'?' selected':'').'>Desc</option><option value="asc"'.($order_q==='asc'?' selected':'').'>Asc</option></select></div>';
                echo '<div class="podify-field"><label><input type="checkbox" id="podify-ep-audio" value="1"'.($has_audio_q? ' checked':'').'> Has audio only</label></div>';
                echo '<div class="podify-actions"><button class="button" id="podify-ep-apply">Apply</button></div>';
                echo '</div>';
            }
            $offset_cur = is_array($episodes) ? count($episodes) : 0;
            echo '<div class="podify-table-wrap">';
            echo '<table id="podify-admin-episodes" class="widefat" data-feed="'.$feed_filter.'" data-offset="'.$offset_cur.'" data-limit="'.$limit_ep.'" data-page="1"'.($feed_filter ? ' data-total-episodes="'.intval($total_episodes).'" data-total-pages="'.intval($total_pages).'"' : '').'><thead><tr><th style="width:32px"><input type="checkbox" id="podify-ep-select-all"></th><th>Title</th><th>Feed</th><th>Published</th><th>Audio URL</th><th>Image URL</th><th>Category</th></tr></thead><tbody>';
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
                    echo '<tr><td><input type="checkbox" class="podify-ep-select" value="'.intval($e['id']).'"></td><td>'.$title.'</td><td>'.$feed_id.'</td><td>'.$pub.'</td><td>'.$audioCell.'</td><td>'.$imageCell.'</td><td>'.$select.'</td></tr>';
                }
            } else {
                echo '<tr><td colspan="7">No episodes</td></tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
            if ($feed_filter) {
                echo '<div class="podify-actions"><button class="button" id="podify-admin-prev" disabled>Prev</button> <span id="podify-admin-page">Page 1 of '.intval($total_pages).'</span> <button class="button" id="podify-admin-next"'.(($limit_ep >= $total_episodes)?' disabled':'').'>Next</button></div>';
            }
            $assign_url = esc_url_raw( rest_url('podify/v1/assign-category') );
            echo '<script>(function(){';
            echo 'const ASSIGN_URL = '.wp_json_encode($assign_url).';';
            echo 'const NONCE = '.wp_json_encode($nonce).';';
            echo 'document.addEventListener("change", function(e){ var sel = e.target.closest(".podify-assign-cat"); if(!sel) return; var eid = parseInt(sel.getAttribute("data-episode")); var cid = parseInt(sel.value); if(!eid || !cid) return; sel.disabled = true; fetch(ASSIGN_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({episode_id:eid,category_id:cid})}).then(function(r){return r.json()}).then(function(d){ sel.disabled=false; if(d && d.ok){ sel.nextElementSibling && (sel.nextElementSibling.textContent = "Assigned: " + sel.options[sel.selectedIndex].text); } else { alert("Failed to assign"); } }).catch(function(){ sel.disabled=false; alert("Failed"); }); });';
            echo 'document.addEventListener("change", function(e){ var master = e.target.closest("#podify-ep-select-all"); if(!master) return; var table = document.getElementById("podify-admin-episodes"); if(!table) return; var boxes = table.querySelectorAll(".podify-ep-select"); boxes.forEach(function(b){ b.checked = master.checked; }); });';
            if ($feed_filter) {
                $episodes_url = esc_url_raw( rest_url('podify/v1/episodes') );
                $cats_url = esc_url_raw( rest_url('podify/v1/categories') );
                echo 'const EP_URL = '.wp_json_encode($episodes_url).';';
                echo 'const CATS_URL = '.wp_json_encode($cats_url).';';
                echo 'let PODIFY_CATS = [];';
                echo 'function fetchCats(){ return fetch(CATS_URL + "?feed_id='.intval($feed_filter).'", {headers:{"X-WP-Nonce":NONCE}}).then(function(r){return r.json()}).then(function(d){ PODIFY_CATS = (d && d.items) ? d.items : []; }).catch(function(){ PODIFY_CATS = []; }); }';
                echo 'function makeCatSelect(epId){ var html = \'<select class="podify-assign-cat" data-episode="\'+epId+\'"><option value="">Select category</option>\'; PODIFY_CATS.forEach(function(c){ html += \'<option value="\'+(c.id||0)+\'">\'+(c.name||"")+\'</option>\'; }); html += \'</select><div class="podify-assigned">Assigned: —</div>\'; return html; }';
                echo 'function ensureEpisodeCheckboxes(){ var table=document.getElementById("podify-admin-episodes"); if(!table) return; var rows=table.querySelectorAll("tbody tr"); rows.forEach(function(tr){ if(tr.querySelector(".podify-ep-select")) return; var sel=tr.querySelector(".podify-assign-cat"); var eid=sel?parseInt(sel.getAttribute("data-episode"))||0:0; var firstCell=tr.querySelector("td"); if(!firstCell) return; var td=document.createElement("td"); var val=eid?String(eid):""; td.innerHTML = \'<input type="checkbox" class="podify-ep-select" value="\'+val+\'">\'; tr.insertBefore(td, firstCell); }); }';
                echo 'function initEpisodeCheckboxObserver(){ var table=document.getElementById("podify-admin-episodes"); if(!table || !window.MutationObserver) return; var tbody=table.querySelector("tbody"); if(!tbody) return; var obs=new MutationObserver(function(){ ensureEpisodeCheckboxes(); }); obs.observe(tbody,{childList:true}); }';
                echo 'ensureEpisodeCheckboxes(); initEpisodeCheckboxObserver();';
                echo 'function setPageLabel(p){ var el=document.getElementById("podify-admin-page"); if(!el) return; var table=document.getElementById("podify-admin-episodes"); var totalPages=1; var totalEpisodes=0; if(table){ var tp=parseInt(table.getAttribute("data-total-pages"))||0; var te=parseInt(table.getAttribute("data-total-episodes"))||0; if(tp>0) totalPages=tp; if(te>0) totalEpisodes=te; } var label="Page "+String(p)+" of "+String(totalPages); if(totalEpisodes>0){ label += " ("+String(totalEpisodes)+" episodes)"; } el.textContent=label; }';
                echo 'function setPrevNextDisabled(prevDis,nextDis){ var p=document.getElementById("podify-admin-prev"); var n=document.getElementById("podify-admin-next"); if(p) p.disabled=!!prevDis; if(n) n.disabled=!!nextDis; }';
                echo 'function buildParams(limit, offset){ var q=(document.getElementById("podify-ep-search")||{value:""}).value||""; var ob=(document.getElementById("podify-ep-orderby")||{value:"published"}).value||"published"; var or=(document.getElementById("podify-ep-order")||{value:"desc"}).value||"desc"; var ha=(document.getElementById("podify-ep-audio")||{checked:false}).checked?1:0; var s="&limit="+encodeURIComponent(limit)+"&offset="+encodeURIComponent(offset); if(q.length){ s += "&q="+encodeURIComponent(q); } if(ob){ s += "&orderby="+encodeURIComponent(ob); } if(or){ s += "&order="+encodeURIComponent(or); } if(ha){ s += "&has_audio=1"; } return s; }';
                echo 'document.addEventListener("click", function(e){ var btnPrev = e.target.closest("#podify-admin-prev"); var btnNext = e.target.closest("#podify-admin-next"); var btnApply = e.target.closest("#podify-ep-apply"); if(!btnPrev && !btnNext && !btnApply) return; var table = document.getElementById("podify-admin-episodes"); if(!table) return; var feed = parseInt(table.getAttribute("data-feed"))||0; var limit = parseInt(table.getAttribute("data-limit"))||50; var page = parseInt(table.getAttribute("data-page"))||1; var nextPage = btnApply ? 1 : (page + (btnNext?1:-1)); if(nextPage<1) nextPage=1; var offset = (nextPage-1)*limit; var url = EP_URL + "?feed_id=" + encodeURIComponent(feed) + buildParams(limit, offset); btnPrev && (btnPrev.disabled = true); btnNext && (btnNext.disabled = true); fetchCats().then(function(){ return fetch(url).then(function(r){ return r.json(); }); }).then(function(d){ var tbody = table.querySelector("tbody"); var rowsHtml = ""; if(d && d.items){ d.items.forEach(function(it){ var title = it.title || ""; var pub = it.published || ""; var audio = it.audio_url || ""; var image = it.image_url || ""; var feedId = it.feed_id || 0; var audioCell = audio ? (\'<a href="\'+audio+\'" target="_blank" rel="noopener">Open</a>\') : "—"; var imageCell = image ? (\'<a href="\'+image+\'" target="_blank" rel="noopener">Open</a>\') : "—"; rowsHtml += \'<tr><td>\'+title+\'</td><td>\'+feedId+\'</td><td>\'+pub+\'</td><td>\'+audioCell+\'</td><td>\'+imageCell+\'</td><td>\'+makeCatSelect(it.id)+\'</td></tr>\'; }); } tbody.innerHTML = rowsHtml; var totalCount = d && typeof d.total_count !== "undefined" ? parseInt(d.total_count) || 0 : 0; var totalPages = limit > 0 ? Math.max(1, Math.ceil(totalCount/limit)) : 1; if(totalCount>0){ table.setAttribute("data-total-episodes", String(totalCount)); table.setAttribute("data-total-pages", String(totalPages)); } table.setAttribute("data-page", String(nextPage)); table.setAttribute("data-offset", String(offset + (d && d.items ? d.items.length : 0))); setPageLabel(nextPage); var isFirst = nextPage<=1; var isLast = nextPage>=totalPages; setPrevNextDisabled(isFirst, isLast); }).catch(function(){ alert("Failed to load page"); setPrevNextDisabled(false, false); }); });';
                echo 'var PODIFY_SEARCH_TIMER; document.addEventListener("input", function(e){ var s = e.target.closest("#podify-ep-search"); if(!s) return; clearTimeout(PODIFY_SEARCH_TIMER); PODIFY_SEARCH_TIMER = setTimeout(function(){ var lbl=document.getElementById("podify-admin-page"); if(lbl){ lbl.textContent="Searching episodes..."; } var btn = document.getElementById("podify-ep-apply"); if(btn){ btn.click(); } }, 300); }); document.addEventListener("change", function(e){ var sel = e.target.closest("#podify-ep-limit"); if(!sel) return; var v = parseInt(sel.value)||50; var url = new URL(window.location.href); url.searchParams.set("feed_id", "'.intval($feed_filter).'"); url.searchParams.set("limit", String(v)); window.location.href = url.toString(); });';
            }
            echo '})();</script>';
        } elseif ($tab === 'categories') {
            echo '<div class="podify-grid">';
            echo '<div class="podify-card"><h3>Add Category</h3>';
            echo '<form method="post"><input type="hidden" name="podify_action" value="add_category">';
            wp_nonce_field('podify_add_category');
            echo '<div class="podify-field"><label>Feed</label><select name="feed_id">';
            if ($feeds) {
                foreach ($feeds as $f) {
                    echo '<option value="'.intval($f['id']).'">Feed '.intval($f['id']).'</option>';
                }
            }
            echo '</select></div>';
            echo '<div class="podify-field"><label>Category Name</label><input type="text" name="category_name" required></div>';
            echo '<div class="podify-actions"><button class="button button-primary">Add Category</button></div></form>';
            echo '</div>';
            echo '<div class="podify-card"><h3>Existing Categories</h3>';
            $allCats = \PodifyPodcast\Core\Database::get_categories(null);
            if ($allCats) {
                echo '<table class="widefat"><thead><tr><th>Feed</th><th>ID</th><th>Name</th><th>Slug</th><th>Actions</th></tr></thead><tbody>';
                foreach ($allCats as $c) {
                    $rowId = intval($c['id']);
                    $name = esc_html($c['name']);
                    $slug = esc_html($c['slug']);
                    echo '<tr><td>'.intval($c['feed_id']).'</td><td>'.$rowId.'</td><td><input type="text" value="'.$name.'" class="podify-cat-name" data-id="'.$rowId.'"></td><td class="podify-cat-slug">'.$slug.'</td><td><button class="button podify-cat-save" data-id="'.$rowId.'">Save</button> <button class="button button-link-delete podify-cat-delete" data-id="'.$rowId.'">Delete</button></td></tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>No categories</p>';
            }
            echo '</div>';
            $upd_url = esc_url_raw( rest_url('podify/v1/update-category') );
            $del_url = esc_url_raw( rest_url('podify/v1/delete-category') );
            echo '<script>(function(){';
            echo 'const NONCE = '.wp_json_encode($nonce).';';
            echo 'const UPD_URL = '.wp_json_encode($upd_url).';';
            echo 'const DEL_URL = '.wp_json_encode($del_url).';';
            echo 'document.addEventListener("click",function(e){var s=e.target.closest(".podify-cat-save");if(s){var id=parseInt(s.getAttribute("data-id"));var input=document.querySelector(".podify-cat-name[data-id=\'"+id+"\']");if(!input)return;var name=input.value.trim();if(!name)return;s.disabled=true;fetch(UPD_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({id:id,name:name})}).then(function(r){return r.json()}).then(function(d){s.disabled=false;if(d&&d.ok){var slugCell=s.closest("tr").querySelector(".podify-cat-slug");if(slugCell){slugCell.textContent=name.toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"");}}else{alert("Failed to update")}}).catch(function(){s.disabled=false;alert("Failed")});}});';
            echo 'document.addEventListener("click",function(e){var b=e.target.closest(".podify-cat-delete");if(b){var id=parseInt(b.getAttribute("data-id"));if(!id)return;if(!confirm("Delete this category?"))return;b.disabled=true;fetch(DEL_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({id:id})}).then(function(r){return r.json()}).then(function(d){b.disabled=false;if(d&&d.ok){var tr=b.closest("tr");if(tr){tr.parentNode.removeChild(tr);}}else{alert("Failed to delete")}}).catch(function(){b.disabled=false;alert("Failed")});}});';
            echo '})();</script>';
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
            echo '<div class="podify-actions"><button class="button button-primary">Save Settings</button></div></form>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }
}
