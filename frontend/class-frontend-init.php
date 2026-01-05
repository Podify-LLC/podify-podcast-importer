<?php
namespace PodifyPodcast\Core\Frontend;

class FrontendInit {
    public static function register() {
        add_shortcode('podify_podcast_list',[self::class,'render_list']);
        add_action('wp_footer', [self::class, 'inject_sticky_player'], 20);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets_global']);
    }
    private static function duration_seconds($d) {
        $s = trim((string)$d);
        if ($s === '') return 0;
        if (preg_match('/^\d+$/', $s)) {
            return intval($s);
        }
        $parts = array_map('intval', explode(':', $s));
        $sec = 0;
        foreach ($parts as $p) { $sec = $sec*60 + $p; }
        return $sec;
    }
    public static function enqueue_assets_global() {
        $settings = \PodifyPodcast\Core\Settings::get();
        if (!empty($settings['sticky_player_enabled'])) {
            self::enqueue_assets();
        }
    }
    private static function format_duration($d) {
        $s = trim((string)$d);
        if ($s === '') return '';
        if (preg_match('/^\d+$/', $s)) {
            $sec = intval($s);
        } else {
            $parts = array_map('intval', explode(':', $s));
            $sec = 0;
            foreach ($parts as $p) { $sec = $sec*60 + $p; }
        }
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $se = $sec % 60;
        if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $se);
        return sprintf('%d:%02d', $m, $se);
    }
    private static function enqueue_assets() {
        wp_enqueue_style('podify_frontend', \PODIFY_PODCAST_URL . 'assets/css/frontend.css', [], \PODIFY_PODCAST_VERSION);
    }
    public static function render_list($atts = []) {
        self::enqueue_assets();
        $limit = isset($atts['limit']) ? intval($atts['limit']) : 9;
        $cols = isset($atts['cols']) ? max(1, min(4, intval($atts['cols']))) : 3;
        $feed_id = isset($atts['feed_id']) ? intval($atts['feed_id']) : null;
        $category_id = isset($atts['category_id']) ? intval($atts['category_id']) : null;
        $episodes = \PodifyPodcast\Core\Database::get_episodes($feed_id ?: null, $limit, 0, $category_id ?: null);
        if (!$episodes) {
            return '<div class="podify-episodes-grid">No episodes</div>';
        }
        $container_id = 'podify-ep-'.wp_generate_uuid4();
        $html = '<div id="'.$container_id.'" class="podify-episodes-grid podify-cols-'.$cols.'" data-limit="'.$limit.'"'.($feed_id?' data-feed="'.$feed_id.'"':'').($category_id?' data-category="'.$category_id.'"':'').' data-offset="'.count($episodes).'">';
        foreach ($episodes as $e) {
            $title = esc_html($e['title']);
            $date = !empty($e['published']) ? esc_html( date_i18n(get_option('date_format'), strtotime($e['published'])) ) : '';
            $duration = !empty($e['duration']) ? esc_html(self::format_duration($e['duration'])) : '';
            $dur_raw = !empty($e['duration']) ? $e['duration'] : '';
            $tags = !empty($e['tags']) ? array_map('trim', explode(',', $e['tags'])) : [];
            $tags_str = $tags ? esc_html(implode(', ', array_slice($tags, 0, 3))) : '';
            $img = !empty($e['image_url']) ? esc_url($e['image_url']) : '';
            $audio = !empty($e['audio_url']) ? esc_url($e['audio_url']) : '';
            if (!empty($e['post_id'])) {
                $pid = intval($e['post_id']);
                if ($pid > 0) {
                    $maudio = get_post_meta($pid, '_podify_audio_url', true);
                    if (!empty($maudio) && wp_http_validate_url($maudio)) { $audio = esc_url($maudio); }
                    $mimage = get_post_meta($pid, '_podify_episode_image', true);
                    if (!empty($mimage) && wp_http_validate_url($mimage)) { $img = esc_url($mimage); }
                    $mdur = get_post_meta($pid, '_podify_duration', true);
                    if (!empty($mdur)) { 
                        $duration = esc_html(self::format_duration($mdur)); 
                        $dur_raw = $mdur;
                    }
                }
            }
            $desc_raw = !empty($e['description']) ? wp_strip_all_tags($e['description']) : '';
            $desc = $desc_raw ? esc_html( wp_trim_words($desc_raw, 18) ) : '';
            $meta_parts = array_filter([$date, $tags_str], function($x){ return !empty($x); });
            $meta_line = $meta_parts ? implode(' · ', $meta_parts) : '';
            $data_attrs = ' data-title="'.esc_attr($title).'"';
            if ($audio) { $data_attrs .= ' data-audio="'.$audio.'"'; }
            if ($img) { $data_attrs .= ' data-image="'.$img.'"'; }
            $data_attrs .= ' data-duration="'.esc_attr(self::format_duration($dur_raw)).'"';
            $data_attrs .= ' data-duration-seconds="'.esc_attr(self::duration_seconds($dur_raw)).'"';
            $html .= '<div class="podify-episode-card podify-row"'.$data_attrs.'>';
            $html .= '<div class="podify-episode-media">'.($img ? '<img src="'.$img.'" alt="'.$title.'" loading="lazy">' : '<div class="podify-episode-placeholder"></div>');
            $html .= '</div>';
            $html .= '<div class="podify-episode-body">';
            $html .= '<div class="podify-episode-top"><h3 class="podify-episode-title">'.$title.'</h3></div>';
            $cats = \PodifyPodcast\Core\Database::get_episode_categories(intval($e['id']));
            if (is_array($cats) && !empty($cats)) {
                $html .= '<div class="podify-category-pills">';
                foreach ($cats as $cat) {
                    $html .= '<span class="podify-category-pill">'.esc_html($cat['name']).'</span>';
                }
                $html .= '</div>';
            }
            if ($desc) $html .= '<div class="podify-episode-desc podify-clamp-2">'.$desc.'</div>';
            if ($meta_line) $html .= '<div class="podify-episode-meta">'.$meta_line.'</div>';
            $html .= '<div class="podify-episode-actions">';
            if ($audio) { $html .= '<button class="podify-play-overlay" aria-label="Play">▶</button>'; }
            if ($duration) { $html .= '<span class="podify-episode-duration">'.$duration.'</span>'; }
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $episodes_url = esc_url_raw(rest_url('podify/v1/episodes'));
        $total_count = \PodifyPodcast\Core\Database::count_episodes($feed_id ?: null, $category_id ?: null);
        $remaining = max(0, intval($total_count) - count($episodes));
        if ($remaining > 6) {
            $html .= '<div class="podify-load-more-wrap" style="text-align:center;margin-top:16px;"><button class="podify-load-more button" data-target="'.$container_id.'">Load more</button></div>';
        }
        $html .= '<script>(function(){';
        $html .= 'var EP_URL='.wp_json_encode($episodes_url).';';
        $html .= 'var TOTAL_COUNT='.wp_json_encode(intval($total_count)).';';
        // Debug: Log episode data to check for missing audio
        $html .= 'console.log("Podify Debug: Loaded '.count($episodes).' episodes");';
        $html .= 'var debugEps = '.wp_json_encode(array_map(function($e){ return ['title'=>$e['title'], 'audio'=>$e['audio_url']]; }, $episodes)).';';
        $html .= 'console.log("Podify Debug: Episodes Data", debugEps);';
        $html .= 'debugEps.forEach(function(ep){ if(!ep.audio) console.warn("Podify Warning: Episode \\""+ ep.title + "\\" has no audio URL. Check Importer settings or Feed."); });';
        $html .= 'function setCardMediaAspect(root){var imgs=(root?root.querySelectorAll(".podify-episode-media img"):document.querySelectorAll(".podify-episode-media img"));imgs.forEach(function(img){function apply(){var w=img.naturalWidth||0,h=img.naturalHeight||0;if(w>0&&h>0){var p=img.parentElement;if(p){p.style.aspectRatio=w+" / "+h;img.style.width="100%";img.style.height="100%";img.style.objectFit="contain";}}}if(img.complete){apply();}else{img.addEventListener("load",apply,{once:true});}});}setCardMediaAspect();';
        
        $html .= 'document.addEventListener("click",function(e){var btn=e.target.closest(".podify-play-overlay");if(!btn)return;var card=btn.closest(".podify-episode-card");if(!card)return;var src=card.getAttribute("data-audio");if(!src)return;e.preventDefault();try{var player=document.getElementById("podify-sticky-player");var stickyAudio=document.getElementById("podify-sticky-audio");var titleEl=document.getElementById("podify-sticky-title");var imgEl=document.getElementById("podify-sticky-img");var playBtn=document.getElementById("podify-sticky-play");var volBtn=document.getElementById("podify-sticky-volume");if(stickyAudio&&player){stickyAudio.src=src;stickyAudio.setAttribute("data-duration",card.getAttribute("data-duration")||"");stickyAudio.setAttribute("data-duration-seconds",card.getAttribute("data-duration-seconds")||"");document.body.classList.add("podify-player-active");player.style.setProperty("display","block","important");if(titleEl)titleEl.textContent=card.getAttribute("data-title")||titleEl.textContent;if(imgEl)imgEl.src=card.getAttribute("data-image")||imgEl.src;if(playBtn)playBtn.innerHTML=\'<svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><circle cx="12" cy="12" r="12" fill="white"/><path d="M9 8h2v8H9V8zm4 0h2v8h-2V8z" fill="black"/></svg>\';try{stickyAudio.load()}catch(_e){}document.querySelectorAll(".podify-episode-card.podify-playing").forEach(function(x){x.classList.remove("podify-playing")});card.classList.add("podify-playing");stickyAudio.play().catch(function(err){console.error("Podify: Sticky play failed from overlay click",err)})}}catch(err){console.error("Podify: Overlay click error",err)}});';
        $html .= 'function fmtDur(s){if(!s)return"";var sec=0;if(/^[0-9]+$/.test(s)){sec=parseInt(s,10)}else{var parts=s.split(":").map(function(x){return parseInt(x,10)||0});for(var i=0;i<parts.length;i++){sec=sec*60+parts[i]}}var h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),se=sec%60;return h>0?(h+":"+(m<10?"0":"")+m+":"+(se<10?"0":"")+se):(m+":"+(se<10?"0":"")+se)}';
        $html .= 'document.addEventListener("click",function(e){var btn=e.target.closest(".podify-load-more");if(!btn)return;var id=btn.getAttribute("data-target");var grid=document.getElementById(id);if(!grid)return;var limit=parseInt(grid.getAttribute("data-limit"))||9;var offset=parseInt(grid.getAttribute("data-offset"))||0;var feed=grid.getAttribute("data-feed")||"";var cat=grid.getAttribute("data-category")||"";btn.disabled=true;var url=EP_URL+"?limit="+limit+"&offset="+offset+(feed?("&feed_id="+encodeURIComponent(feed)):"")+(cat?("&category_id="+encodeURIComponent(cat)):"");fetch(url).then(function(r){return r.json()}).then(function(d){btn.disabled=false;if(!d||!d.items||!d.items.length){btn.textContent="No more";btn.disabled=true;return}var html="";d.items.forEach(function(ei){var title=ei.title||"";var date=ei.published?new Date(ei.published):null;var dateStr=date?date.toLocaleDateString():"";var duration=fmtDur(ei.duration||"");var tags=(ei.tags||"").split(",").filter(function(x){return x.trim().length});var tagsStr=tags.slice(0,3).join(", ");var img=ei.image_url||"";var audio=ei.audio_url||"";var desc=ei.description||"";if(desc.length>0){desc=desc.replace(/<[^>]+>/g,"");if(desc.length>180){desc=desc.slice(0,180)+"…"}}var metaParts=[];if(dateStr)metaParts.push(dateStr);if(tagsStr)metaParts.push(tagsStr);var metaLine=metaParts.join(" · ");html+=\'<div class="podify-episode-card podify-row">\'+\'<div class="podify-episode-media">\'+(img?\'<img src="\'+img+\'" alt="" loading="lazy">\':\'<div class="podify-episode-placeholder"></div>\')+(audio?\'<button class="podify-play-overlay" aria-label="Play">▶</button>\':\'\')+\'</div>\'+\'<div class="podify-episode-body">\'+\'<div class="podify-episode-top"><h3 class="podify-episode-title">\'+title+\'</h3></div>\'+(ei.categories&&ei.categories.length?(function(){var s=\'<div class="podify-category-pills">\';ei.categories.forEach(function(c){s+=\'<span class="podify-category-pill">\'+c.name+\'</span>\';});return s+\'</div>\';})():\'\')+(desc?\'<div class="podify-episode-desc podify-clamp-2">\'+desc+\'</div>\':\'\')+\'\'+(metaLine?\'<div class="podify-episode-meta">\'+metaLine+\'</div>\':\'\')+\'<div class="podify-episode-actions">\'+(audio?\'<audio class="podify-episode-audio" controls preload="none" src="\'+audio+\'"></audio>\':\'\')+\'</div>\'+\'</div>\'+\'</div>\';});grid.insertAdjacentHTML("beforeend",html);var newOffset=offset + d.items.length;grid.setAttribute("data-offset",newOffset);var tot=d.total_count||TOTAL_COUNT||0;var remain=tot-newOffset;if(remain<=6){var wrap=btn.closest(".podify-load-more-wrap");if(wrap){wrap.parentNode.removeChild(wrap);}}}).catch(function(){btn.disabled=false;});});';
        $html .= 'document.addEventListener("click",function(e){var btn=e.target.closest(".podify-load-more");if(!btn)return;e.preventDefault();e.stopImmediatePropagation();var id=btn.getAttribute("data-target");var grid=document.getElementById(id);if(!grid)return;var limit=parseInt(grid.getAttribute("data-limit"))||9;var offset=parseInt(grid.getAttribute("data-offset"))||0;var feed=grid.getAttribute("data-feed")||"";var cat=grid.getAttribute("data-category")||"";btn.disabled=true;var url=EP_URL+"?limit="+limit+"&offset="+offset+(feed?("&feed_id="+encodeURIComponent(feed)):"")+(cat?("&category_id="+encodeURIComponent(cat)):"");fetch(url).then(function(r){return r.json()}).then(function(d){btn.disabled=false;if(!d||!d.items||!d.items.length){btn.textContent="No more";btn.disabled=true;return}var html="";d.items.forEach(function(ei){var title=ei.title||"";var date=ei.published?new Date(ei.published):null;var dateStr=date?date.toLocaleDateString():"";var duration=fmtDur(ei.duration||"");var tags=(ei.tags||"").split(",").filter(function(x){return x.trim().length});var tagsStr=tags.slice(0,3).join(", ");var img=ei.image_url||"";var audio=ei.audio_url||"";var desc=ei.description||"";if(desc.length>0){desc=desc.replace(/<[^>]+>/g,"");if(desc.length>180){desc=desc.slice(0,180)+"…"}}var metaParts=[];if(dateStr)metaParts.push(dateStr);if(tagsStr)metaParts.push(tagsStr);var metaLine=metaParts.join(" · ");var dsCalc=(function(){var s=ei.duration||"";var sec=0;if(/^[0-9]+$/.test(s)){sec=parseInt(s,10)}else{var parts=s.split(":").map(function(x){return parseInt(x,10)||0});for(var i=0;i<parts.length;i++){sec=sec*60+parts[i]}}return sec;})();html+=\'<div class="podify-episode-card podify-row\'+(audio?\'" data-audio="\'+audio+\'"\':\'"\')+\' data-title="\'+title.replace(/"/g,\'&quot;\')+\'"\'+(img?\' data-image="\'+img+\'"\':\'\')+\' data-duration="\'+duration+\'" data-duration-seconds="\'+dsCalc+\'">\'+\'<div class="podify-episode-media">\'+(img?\'<img src="\'+img+\'" alt="" loading="lazy">\':\'<div class="podify-episode-placeholder"></div>\')+\'</div>\'+\'<div class="podify-episode-body">\'+\'<div class="podify-episode-top"><h3 class="podify-episode-title">\'+title+\'</h3></div>\'+(ei.categories&&ei.categories.length?(function(){var s=\'<div class="podify-category-pills">\';ei.categories.forEach(function(c){s+=\'<span class="podify-category-pill">\'+c.name+\'</span>\';});return s+\'</div>\';})():\'\')+(desc?\'<div class="podify-episode-desc podify-clamp-2">\'+desc+\'</div>\':\'\')+\'\'+(metaLine?\'<div class="podify-episode-meta">\'+metaLine+\'</div>\':\'\')+\'<div class="podify-episode-actions">\'+(audio?\'<button class="podify-play-overlay" aria-label="Play">▶</button>\':\'\')+(duration?\'<span class="podify-episode-duration">\'+duration+\'</span>\':\'\')+\'</div>\'+\'</div>\'+\'</div>\';});grid.insertAdjacentHTML("beforeend",html);setCardMediaAspect(grid);var newOffset=parseInt(offset)+d.items.length;grid.setAttribute("data-offset",String(newOffset));var tot=d.total_count||TOTAL_COUNT||0;var remain=tot-newOffset;if(remain<=6){var wrap=btn.closest(".podify-load-more-wrap");if(wrap){wrap.parentNode.removeChild(wrap);}}}).catch(function(err){btn.disabled=false;console.error("Podify: Load more (override) failed",err)});},true);})();</script>';
        return $html;
    }
    public static function inject_sticky_player() {
        echo self::render_sticky();
    }
    public static function render_sticky($atts = []) {
        $settings = \PodifyPodcast\Core\Settings::get();
        $enabled = !empty($settings['sticky_player_enabled']);
        $position = !empty($settings['sticky_player_position']) ? $settings['sticky_player_position'] : 'bottom';
        if (!$enabled) {
            return '';
        }
        $posClass = $position === 'top' ? 'podify-pos-top' : 'podify-pos-bottom';
        $feed_id = isset($atts['feed_id']) ? intval($atts['feed_id']) : null;
        $latest = \PodifyPodcast\Core\Database::get_episodes($feed_id ?: null, 50, 0);
        $ep = null;
        if (is_array($latest) && !empty($latest)) {
            foreach ($latest as $row) {
                if (!empty($row['audio_url'])) { $ep = $row; break; }
            }
            if (!$ep) { $ep = $latest[0]; }
        }
        $ep_title = $ep && !empty($ep['title']) ? esc_html($ep['title']) : '';
        $ep_img = $ep && !empty($ep['image_url']) ? esc_url($ep['image_url']) : '';
        $ep_audio = $ep && !empty($ep['audio_url']) ? esc_url($ep['audio_url']) : '';
        if ($ep && !empty($ep['post_id'])) {
            $pid = intval($ep['post_id']);
            if ($pid > 0) {
                $maudio = get_post_meta($pid, '_podify_audio_url', true);
                $mimage = get_post_meta($pid, '_podify_episode_image', true);
                if (!empty($maudio) && wp_http_validate_url($maudio)) { $ep_audio = esc_url($maudio); }
                if (!empty($mimage) && wp_http_validate_url($mimage)) { $ep_img = esc_url($mimage); }
            }
        }
        $ep_sub = '';
        if ($ep && !empty($ep['published'])) {
            $ep_sub = esc_html( date_i18n(get_option('date_format'), strtotime($ep['published'])) );
        }
        
        $html = '<div id="podify-sticky-player" class="podify-sticky-player '.$posClass.'">';
        
        // Progress bar (top)
        $html .= '<div class="podify-sticky-progress-container">';
        $html .= '<input type="range" id="podify-sticky-range" min="0" max="100" value="0">';
        $html .= '</div>';

        $html .= '<div class="podify-sticky-inner">';
        $html .= '<div class="podify-sticky-left">';
        $html .= '<div class="podify-sticky-thumb"><img id="podify-sticky-img" src="'.($ep_img ?: '').'" alt=""></div>';
        $html .= '<div class="podify-sticky-meta"><div id="podify-sticky-title">'.($ep_title ?: '').'</div><div id="podify-sticky-subtitle">'.($ep_sub ?: '').'</div></div>';
        $html .= '</div>';
        $html .= '<audio id="podify-sticky-audio" preload="metadata" crossorigin="anonymous" '.($ep_audio ? ('src="'.$ep_audio.'"') : '').' data-duration="'.esc_attr(self::format_duration($ep['duration'] ?? '')).'" data-duration-seconds="'.esc_attr(self::duration_seconds($ep['duration'] ?? '')).'" style="display:none"></audio>';
        $html .= '<div class="podify-sticky-right">';
        $html .= '<button id="podify-sticky-volume" aria-label="Mute/Unmute" class="podify-volume-btn"><svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M3 9v6h4l5 4V5L7 9H3z"></path></svg></button>';
        $html .= '<span id="podify-sticky-time">0:00 / 0:00</span>';
        $html .= '<button id="podify-sticky-play" class="podify-play-btn-large"><svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><circle cx="12" cy="12" r="12" fill="white"/><path d="M9.5 8l6 4-6 4V8z" fill="black"/></svg></button>';
        $html .= '</div>';

        $html .= '</div></div>'; // End inner, End player

        // Inline JS for sticky player logic
        $html .= '<script>
        (function(){
            console.log("Podify: Sticky Player Script Loaded");
            var EP_URL = '.wp_json_encode( esc_url_raw( rest_url('podify/v1/episodes') ) ).';
            var FEED_ID = '.wp_json_encode( $feed_id ? intval($feed_id) : null ).';
            var FEED_ID_JS = (FEED_ID !== null && FEED_ID !== undefined) ? FEED_ID : (function(){ var el = document.querySelector(".podify-episodes-grid[data-feed]"); if(!el) return null; var v = parseInt(el.getAttribute("data-feed")); return isNaN(v)?null:v; })();
            var player = document.getElementById("podify-sticky-player");
            if(!player) { console.error("Podify: Sticky player element not found in DOM"); return; }
            else { 
                console.log("Podify: Sticky player found", player); 
                // Move player to body to ensure fixed positioning works relative to viewport
                // This fixes issues where the player is trapped inside a container with transform/filter
                if (player.parentNode !== document.body) {
                    document.body.appendChild(player);
                    console.log("Podify: Moved sticky player to body root");
                }
            }

            var imgEl = document.getElementById("podify-sticky-img");
            var titleEl = document.getElementById("podify-sticky-title");
            var subEl = document.getElementById("podify-sticky-subtitle");
            var playBtn = document.getElementById("podify-sticky-play");
            var rewindBtn = document.getElementById("podify-sticky-rewind");
            var volBtn = document.getElementById("podify-sticky-volume");
            var range = document.getElementById("podify-sticky-range");
            var timeEl = document.getElementById("podify-sticky-time");
            var wave = document.getElementById("podify-wave");
            var waveProg = document.getElementById("podify-wave-progress");
            var stickyAudio = document.getElementById("podify-sticky-audio"); if(stickyAudio){stickyAudio.crossOrigin="anonymous";}
            var currentAudio = null;

            // SVGs
            var SVG_PLAY = \'<svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><circle cx="12" cy="12" r="12" fill="white"/><path d="M9.5 8l6 4-6 4V8z" fill="black"/></svg>\';
            var SVG_PAUSE = \'<svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><circle cx="12" cy="12" r="12" fill="white"/><path d="M9 8h2v8H9V8zm4 0h2v8h-2V8z" fill="black"/></svg>\';
            var SVG_VOL_ON = \'<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M3 9v6h4l5 4V5L7 9H3z"></path><path d="M14 8.5a4.5 4.5 0 010 7" fill="none" stroke="currentColor" stroke-width="2"></path></svg>\';
            var SVG_VOL_OFF = \'<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M3 9v6h4l5 4V5L7 9H3z"></path><path d="M16 8l4 4-4 4M12 8l-4 4 4 4" fill="none" stroke="currentColor" stroke-width="2"></path></svg>\';
            
            function fmtTime(s) {
                var m = Math.floor(s / 60);
                var se = Math.floor(s % 60);
                var h = Math.floor(m / 60);
                m = m % 60;
                if(h > 0) return h + ":" + (m < 10 ? "0" : "") + m + ":" + (se < 10 ? "0" : "") + se;
                return m + ":" + (se < 10 ? "0" : "") + se;
            }

            if(range) {
                range.style.background = "linear-gradient(to right, #f59e0b 0%, transparent 0%)";
            }

            function setInitialTimeFromSticky(){
                if(!timeEl || !stickyAudio) return;
                var dsAttr = stickyAudio.getAttribute("data-duration");
                var dsSec = parseFloat(stickyAudio.getAttribute("data-duration-seconds")) || 0;
                var durStr = dsAttr && dsAttr.length ? dsAttr : (dsSec ? fmtTime(dsSec) : "0:00");
                timeEl.textContent = "0:00 / " + durStr;
            }
            setInitialTimeFromSticky();

            // Bootstrap metadata if empty (no local episode rendered)
            (function bootstrapMeta(){
                var hasTitle = titleEl && titleEl.textContent && titleEl.textContent.trim().length;
                var hasImg = imgEl && imgEl.getAttribute("src") && imgEl.getAttribute("src").trim().length;
                if (hasTitle || hasImg) return;
                var url = EP_URL + "?limit=1&offset=0" + (FEED_ID ? ("&feed_id="+encodeURIComponent(FEED_ID)) : "");
                fetch(url).then(function(r){ return r.json(); }).then(function(d){
                    if(d && d.items && d.items.length){
                        var it = d.items[0];
                        if(titleEl) titleEl.textContent = it.title || titleEl.textContent;
                        if(imgEl && it.image_url) imgEl.src = it.image_url;
                        if(subEl && it.published){ try{ var dt = new Date(it.published); subEl.textContent = dt.toLocaleDateString(); }catch(_e){} }
                        if(stickyAudio){
                            if(it.duration){
                                var s = it.duration;
                                var dsFmt = /^[0-9]+$/.test(s) ? (function(x){ var m=Math.floor(x/60), se=Math.floor(x%60), h=Math.floor(m/60); m=m%60; return h>0?(h+":"+(m<10?"0":"")+m+":"+(se<10?"0":"")+se):(m+":"+(se<10?"0":"")+se); })(parseInt(s,10)) : s;
                                stickyAudio.setAttribute("data-duration", dsFmt);
                            }
                            var ds = 0; var s = it.duration || ""; if(/^[0-9]+$/.test(s)){ ds=parseInt(s,10);} else { var parts=s.split(":").map(function(x){ return parseInt(x,10)||0 }); for(var i=0;i<parts.length;i++){ ds=ds*60+parts[i]; } }
                            if(ds>0) stickyAudio.setAttribute("data-duration-seconds", ds);
                        }
                        updateProgress();
                    }
                }).catch(function(err){ console.error("Podify: Bootstrap metadata failed", err); });
            })();

            document.addEventListener("play", function(e){
                if(e.target && e.target.tagName === "AUDIO" && e.target.classList.contains("podify-episode-audio")) {
                    try {
                        e.target.pause();
                        if(stickyAudio){stickyAudio.src=e.target.src;stickyAudio.setAttribute("data-duration",e.target.getAttribute("data-duration")||"");stickyAudio.setAttribute("data-duration-seconds",e.target.getAttribute("data-duration-seconds")||"");currentAudio=stickyAudio;document.body.classList.add("podify-player-active");player.style.setProperty("display","block","important");if(titleEl)titleEl.textContent=e.target.getAttribute("data-title")||titleEl.textContent;if(imgEl)imgEl.src=e.target.getAttribute("data-image")||imgEl.src;if(playBtn)playBtn.innerHTML=SVG_PAUSE;if(volBtn)volBtn.innerHTML=currentAudio.muted?SVG_VOL_OFF:SVG_VOL_ON;stickyAudio.play().then(function(){updateProgress()}).catch(function(err){console.error("Podify: Sticky play failed from episode play",err)})}
                    } catch(err){}
                }
            }, true);
            document.addEventListener("play", function(e){
                if(e.target === stickyAudio || e.target === currentAudio) {
                    if(playBtn) playBtn.innerHTML = SVG_PAUSE;
                }
            }, true);

            document.addEventListener("pause", function(e){
                if(e.target === currentAudio) {
                    if(playBtn) playBtn.innerHTML = SVG_PLAY;
                }
            }, true);

            document.addEventListener("ended", function(e){
                if(e.target === currentAudio) {
                    if(playBtn) playBtn.innerHTML = SVG_PLAY;
                }
            }, true);

            document.addEventListener("timeupdate", function(e){
                if(e.target === currentAudio) {
                    updateProgress();
                }
            }, true);
            document.addEventListener("loadedmetadata", function(e){
                if (!currentAudio && e.target === stickyAudio) {
                    currentAudio = stickyAudio;
                }
                if (e.target === currentAudio) {
                    updateProgress();
                }
            }, true);

            function updateProgress() {
                if(!currentAudio) return;
                var cur = currentAudio.currentTime;
                var dur = currentAudio.duration;
                if(!dur || isNaN(dur)) {
                    var ds = parseFloat(currentAudio.getAttribute("data-duration-seconds")) || 0;
                    if (ds > 0) dur = ds;
                }
                if(range && dur) {
                    range.value = (cur / dur) * 100;
                    var val = (cur / dur) * 100;
                    range.style.background = "linear-gradient(to right, #f59e0b "+val+"%, transparent "+val+"%)";
                }
                if(timeEl) {
                    var durStr = currentAudio.getAttribute("data-duration") || (dur ? fmtTime(dur) : "0:00");
                    timeEl.textContent = fmtTime(cur) + " / " + durStr;
                }
                if(waveProg && dur) {
                    var valp = (cur / dur) * 100;
                    waveProg.style.width = valp + "%";
                }
            }

            if(playBtn) {
                playBtn.addEventListener("click", function(){
                    if(!currentAudio) {
                        var playing = Array.from(document.querySelectorAll(".podify-episode-audio")).find(function(x){ return !x.paused; });
                        if (playing) { currentAudio = playing; }
                    }
                    if(!currentAudio) {
                        if(stickyAudio && stickyAudio.src) {
                            currentAudio = stickyAudio;
                            document.body.classList.add("podify-player-active");
                            player.style.setProperty("display", "block", "important");
                            if(playBtn) playBtn.innerHTML = SVG_PAUSE;
                            if(volBtn) volBtn.innerHTML = currentAudio.muted ? SVG_VOL_OFF : SVG_VOL_ON;
                            try{stickyAudio.load()}catch(_e){}
                            setInitialTimeFromSticky();
                        } else {
                            var first = document.querySelector(".podify-episode-audio[src]");
                            if (first) {
                                if(stickyAudio){
                                    stickyAudio.src = first.src;
                                    stickyAudio.setAttribute("data-duration", first.getAttribute("data-duration") || "");
                                    stickyAudio.setAttribute("data-duration-seconds", first.getAttribute("data-duration-seconds") || "");
                                    currentAudio = stickyAudio;
                                    document.body.classList.add("podify-player-active");
                                    player.style.setProperty("display", "block", "important");
                                    if(titleEl) titleEl.textContent = first.getAttribute("data-title") || titleEl.textContent;
                                    if(imgEl) imgEl.src = first.getAttribute("data-image") || imgEl.src;
                                    if(playBtn) playBtn.innerHTML = SVG_PAUSE;
                                    if(volBtn) volBtn.innerHTML = currentAudio.muted ? SVG_VOL_OFF : SVG_VOL_ON;
                                    try{stickyAudio.load()}catch(_e){}
                                    setInitialTimeFromSticky();
                                }
                            } else {
                                var url = EP_URL + "?limit=10&offset=0" + (FEED_ID_JS ? ("&feed_id="+encodeURIComponent(FEED_ID_JS)) : "");
                                function pickAndPlay(list){
                                    var it = (list||[]).find(function(x){ return x.audio_url; });
                                    if(!it) return false;
                                    stickyAudio.src = it.audio_url;
                                    if(titleEl) titleEl.textContent = it.title || titleEl.textContent;
                                    if(imgEl) imgEl.src = it.image_url || imgEl.src;
                                    (function(){
                                        var s = it.duration || "";
                                        if (s) {
                                            var dsFmt = /^[0-9]+$/.test(s) ? (function(x){ var m=Math.floor(x/60), se=Math.floor(x%60), h=Math.floor(m/60); m=m%60; return h>0?(h+":"+(m<10?"0":"")+m+":"+(se<10?"0":"")+se):(m+":"+(se<10?"0":"")+se); })(parseInt(s,10)) : s;
                                            stickyAudio.setAttribute("data-duration", dsFmt);
                                        } else { stickyAudio.removeAttribute("data-duration"); }
                                    })();
                                    stickyAudio.setAttribute("data-duration-seconds", (function(){
                                        var s = it.duration || "";
                                        var sec = 0;
                                        if(/^[0-9]+$/.test(s)) { sec = parseInt(s,10); }
                                        else { var parts = s.split(":").map(function(x){ return parseInt(x,10)||0; }); for(var i=0;i<parts.length;i++){ sec = sec*60 + parts[i]; } }
                                        return sec;
                                    })());
                                    setInitialTimeFromSticky();
                                    currentAudio = stickyAudio;
                                    document.body.classList.add("podify-player-active");
                                    player.style.setProperty("display", "block", "important");
                                    if(playBtn) playBtn.innerHTML = SVG_PAUSE;
                                    if(volBtn) volBtn.innerHTML = currentAudio.muted ? SVG_VOL_OFF : SVG_VOL_ON;
                                    try{stickyAudio.load()}catch(_e){}
                                    currentAudio.play().then(function(){ if(playBtn) playBtn.innerHTML = SVG_PAUSE; updateProgress(); }).catch(function(err){ console.error("Podify: Play failed after fetch", err); });
                                    return true;
                                }
                                fetch(url).then(function(r){ return r.json(); }).then(function(d){
                                    if(d && d.items) {
                                        if (pickAndPlay(d.items)) return;
                                        var tries = 0;
                                        function next(offset){
                                            if (tries++ >= 5) return;
                                            var u = EP_URL + "?limit=10&offset="+offset + (FEED_ID_JS ? ("&feed_id="+encodeURIComponent(FEED_ID_JS)) : "");
                                            fetch(u).then(function(rr){ return rr.json(); }).then(function(dd){
                                                if(dd && dd.items && dd.items.length){
                                                    if (pickAndPlay(dd.items)) return;
                                                    next(dd.next_offset || (offset + dd.items.length));
                                                }
                                            }).catch(function(err){ console.error("Podify: Fetch episodes page failed", err); });
                                        }
                                        next(d.next_offset || 10);
                                        // If nothing playable found at all, set metadata from the first item of the first page
                                        if (d.items && d.items.length && (!stickyAudio.src || stickyAudio.src.length===0)) {
                                            var it0 = d.items[0];
                                            if(titleEl) titleEl.textContent = it0.title || titleEl.textContent;
                                            if(imgEl && it0.image_url) imgEl.src = it0.image_url;
                                            if(subEl && it0.published){ try{ var dt0 = new Date(it0.published); subEl.textContent = dt0.toLocaleDateString(); }catch(_e){} }
                                            if(stickyAudio){
                                                if(it0.duration){
                                                    var s0 = it0.duration;
                                                    var dsFmt0 = /^[0-9]+$/.test(s0) ? (function(x){ var m=Math.floor(x/60), se=Math.floor(x%60), h=Math.floor(m/60); m=m%60; return h>0?(h+":"+(m<10?"0":"")+m+":"+(se<10?"0":"")+se):(m+":"+(se<10?"0":"")+se); })(parseInt(s0,10)) : s0;
                                                    stickyAudio.setAttribute("data-duration", dsFmt0);
                                                }
                                                var ds0 = 0; var s0 = it0.duration || ""; if(/^[0-9]+$/.test(s0)){ ds0=parseInt(s0,10);} else { var parts0=s0.split(":").map(function(x){ return parseInt(x,10)||0 }); for(var i0=0;i0<parts0.length;i0++){ ds0=ds0*60+parts0[i0]; } }
                                                if(ds0>0) stickyAudio.setAttribute("data-duration-seconds", ds0);
                                                setInitialTimeFromSticky();
                                            }
                                            updateProgress();
                                        }
                                    }
                                }).catch(function(err){ console.error("Podify: Fetch episodes for sticky failed", err); });
                                return;
                            }
                        }
                    }
                    if(currentAudio.paused) { currentAudio.play().then(function(){ if(playBtn) playBtn.innerHTML = SVG_PAUSE; updateProgress(); }).catch(function(err){ console.error("Podify: Play failed", err); }); }
                    else { currentAudio.pause(); if(playBtn) playBtn.innerHTML = SVG_PLAY; }
                });
            }

            if(volBtn) {
                volBtn.addEventListener("click", function(){
                    if(!currentAudio) {
                        if(stickyAudio && stickyAudio.src) { currentAudio = stickyAudio; }
                        else { currentAudio = document.querySelector(".podify-episode-audio[src]"); }
                        if(!currentAudio) return;
                    }
                    currentAudio.muted = !currentAudio.muted;
                    volBtn.innerHTML = currentAudio.muted ? SVG_VOL_OFF : SVG_VOL_ON;
                });
            }

            if(rewindBtn) {
                rewindBtn.addEventListener("click", function(){
                    if(!currentAudio) return;
                    currentAudio.currentTime = Math.max(0, currentAudio.currentTime - 15);
                });
            }

            if(range) {
                range.addEventListener("input", function(){
                    if(!currentAudio) return;
                    var dur = currentAudio.duration;
                    if(dur) {
                        currentAudio.currentTime = (range.value / 100) * dur;
                    }
                });
            }
            player.style.setProperty("display", "block", "important");
        })();
        </script>';
        
        return $html;
    }
}
