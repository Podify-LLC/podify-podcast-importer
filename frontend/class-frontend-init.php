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
        self::enqueue_assets();
        $css = isset($settings['custom_css']) ? (string)$settings['custom_css'] : '';
        if (trim($css) !== '') {
            wp_add_inline_style('podify_frontend', $css);
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
        $cols = isset($atts['cols']) ? max(1, min(4, intval($atts['cols']))) : 3;
        $limit = isset($atts['limit']) ? intval($atts['limit']) : ($cols * 2);
        $feed_id = isset($atts['feed_id']) ? intval($atts['feed_id']) : null;
        $category_id = isset($atts['category_id']) ? intval($atts['category_id']) : null;
        $settings = \PodifyPodcast\Core\Settings::get();
        
        // Normalize layout
        $layout_raw = isset($atts['layout']) ? sanitize_key($atts['layout']) : 'classic';
        $layout = ($layout_raw === 'modern') ? 'modern' : 'classic';
        $is_modern = ($layout === 'modern');

        $css = isset($settings['custom_css']) ? (string)$settings['custom_css'] : '';
        if (trim($css) !== '') {
            wp_add_inline_style('podify_frontend', $css);
        }

        // Resolve category slug to ID if needed
        $cat_slug = isset($atts['category']) ? sanitize_title((string)$atts['category']) : '';
        if (!$category_id && $cat_slug && $feed_id) {
            $cats_for_feed = \PodifyPodcast\Core\Database::get_categories(intval($feed_id));
            if (is_array($cats_for_feed)) {
                foreach ($cats_for_feed as $c) {
                    if (!empty($c['slug']) && sanitize_title($c['slug']) === $cat_slug) {
                        $category_id = intval($c['id']);
                        break;
                    }
                }
            }
        }

        $episodes = \PodifyPodcast\Core\Database::get_episodes($feed_id ?: null, $limit, 0, $category_id ?: null);
        
        if (!$episodes) {
            return '<div class="podify-episodes-grid">No episodes</div>';
        }

        $container_id = 'podify-ep-'.wp_generate_uuid4();
        
        $html = '<div id="'.$container_id.'" class="podify-episodes-grid podify-cols-'.$cols.'" data-limit="'.$limit.'"'.($feed_id?' data-feed="'.$feed_id.'"':'').($category_id?' data-category="'.$category_id.'"':'').' data-offset="'.count($episodes).'" data-layout="'.$layout.'">';
        
        foreach ($episodes as $e) {
            $title = !empty($e['title']) ? esc_html($e['title']) : 'Untitled Episode';
            $date = !empty($e['published']) ? esc_html( date_i18n(get_option('date_format'), strtotime($e['published'])) ) : '';
            $duration = !empty($e['duration']) ? esc_html(self::format_duration($e['duration'])) : '';
            $dur_raw = !empty($e['duration']) ? $e['duration'] : '';
            $tags = !empty($e['tags']) ? array_map('trim', explode(',', $e['tags'])) : [];
            $tags_str = $tags ? esc_html(implode(', ', array_slice($tags, 0, 3))) : '';
            $img = !empty($e['image_url']) ? esc_url($e['image_url']) : '';
            $audio = !empty($e['audio_url']) ? esc_url($e['audio_url']) : '';
            $pid = !empty($e['post_id']) ? intval($e['post_id']) : 0;
            
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
            
            $permalink = $pid > 0 ? get_permalink($pid) : home_url('/'.sanitize_title($e['title']).'/');
            $desc_raw = !empty($e['description']) ? wp_strip_all_tags($e['description']) : '';
            $desc = $desc_raw ? esc_html( wp_trim_words($desc_raw, 18) ) : '';
            $meta_parts = array_filter([$date, $tags_str], function($x){ return !empty($x); });
            $meta_line = $meta_parts ? implode(' · ', $meta_parts) : '';
            
            $data_attrs = ' data-title="'.esc_attr($title).'"';
            if ($audio) { $data_attrs .= ' data-audio="'.$audio.'"'; }
            if ($img) { $data_attrs .= ' data-image="'.$img.'"'; }
            $data_attrs .= ' data-duration="'.esc_attr(self::format_duration($dur_raw)).'"';
            $data_attrs .= ' data-duration-seconds="'.esc_attr(self::duration_seconds($dur_raw)).'"';
            
            $card_classes = 'podify-episode-card';
            $card_classes .= $is_modern ? ' podify-modern' : ' podify-row';

            $html .= '<div class="'.$card_classes.'"'.$data_attrs.'>';
            
            // Media Area
            $html .= '<div class="podify-episode-media">';
            if ($img) {
                $html .= '<img src="'.$img.'" alt="'.$title.'" loading="lazy">';
            } else {
                $html .= '<div class="podify-episode-placeholder"></div>';
            }
            $html .= '</div>';
            
            // Body Area
            $html .= '<div class="podify-episode-body">';
            
            // Title (Linked in both layouts)
            $html .= '<div class="podify-episode-top">';
            $html .= '<h3 class="podify-episode-title"><a href="'.esc_url($permalink).'" class="podify-episode-link">'.$title.'</a></h3>';
            $html .= '</div>';
            
            // Categories
            $cats = \PodifyPodcast\Core\Database::get_episode_categories(intval($e['id']));
            if (is_array($cats) && !empty($cats)) {
                $html .= '<div class="podify-category-pills">';
                foreach ($cats as $cat) {
                    $html .= '<span class="podify-category-pill">'.esc_html($cat['name']).'</span>';
                }
                $html .= '</div>';
            }
            
            // Description
            if ($desc) {
                $html .= '<div class="podify-episode-desc podify-clamp-2">'.$desc.'</div>';
            }
            
            if ($is_modern) {
                // Modern Layout Structure
                if ($meta_line) {
                    $html .= '<div class="podify-episode-meta">'.$meta_line.'</div>';
                }
                
                $html .= '<div class="podify-episode-actions">';
                $html .= '<a class="podify-read-more" href="'.esc_url($permalink).'">Read more <i class="fa fa-angle-right"></i></a>';
                
                if ($audio) {
                    $html .= '<button class="podify-play-action-btn" aria-label="Play"><svg viewBox="0 0 24 24" width="36" height="36" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></button>';
                }
                if ($duration) {
                    $html .= '<span class="podify-episode-duration">'.$duration.'</span>';
                }
                $html .= '</div>'; // End Actions
                
            } else {
                // Classic Layout Structure
                $html .= '<a class="podify-read-more" href="'.esc_url($permalink).'">Read more <i class="fa fa-angle-right"></i></a>';
                
                $html .= '<div class="podify-episode-actions">';
                if ($audio) {
                    $html .= '<button class="podify-play-action-btn" aria-label="Play"><svg viewBox="0 0 24 24" width="36" height="36" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></button>';
                }
                if ($duration) {
                    $html .= '<span class="podify-episode-duration">'.$duration.'</span>';
                }
                $html .= '</div>';
                
                if ($meta_line) {
                    $html .= '<div class="podify-episode-meta">'.$meta_line.'</div>';
                }
            }
            
            $html .= '</div>'; // End Body
            $html .= '</div>'; // End Card
        }
        
        $html .= '</div>'; // End Grid
        
        $episodes_url = esc_url_raw(rest_url('podify/v1/episodes'));
        $total_count = \PodifyPodcast\Core\Database::count_episodes($feed_id ?: null, $category_id ?: null);
        $remaining = max(0, intval($total_count) - count($episodes));
        
        if ($remaining > 0) {
            $html .= '<div class="podify-load-more-wrap" style="text-align:center;margin-top:16px;"><button class="podify-load-more button" data-target="'.$container_id.'">Load more</button></div>';
        }
        
        $html .= '<script>(function(){/*Safe*/';
        $html .= 'var EP_URL='.wp_json_encode($episodes_url).';';
        $html .= 'var TOTAL_COUNT='.wp_json_encode(intval($total_count)).';';
        $html .= 'var LAYOUT='.wp_json_encode($layout).';';
        $html .= 'var BASE_URL='.wp_json_encode( trailingslashit(home_url()) ).';';
        $html .= 'function parseJSONSafe(r){return r.text().then(function(t){console.log("Podify Response:", t.substring(0,200)); if(!t||t.trim().charAt(0)==="<"){console.warn("Podify: Received HTML/Invalid JSON", t.substring(0,100));return null;}try{return JSON.parse(t);}catch(_e){console.error("Podify JSON Parse Error:", _e); return null;}});}';
        $html .= 'console.log("Podify Inline JS Init: EP_URL=", EP_URL, "LAYOUT=", LAYOUT);';
        
        // Helper: Ensure aspect ratio
        $html .= 'function setCardMediaAspect(root){var imgs=(root?root.querySelectorAll(".podify-episode-media img"):document.querySelectorAll(".podify-episode-media img"));imgs.forEach(function(img){function apply(){var w=img.naturalWidth||0,h=img.naturalHeight||0;if(w>0&&h>0){var p=img.parentElement;if(p){p.style.aspectRatio=w+" / "+h;img.style.width="100%";img.style.height="100%";img.style.objectFit="contain";}}}if(img.complete){apply();}else{img.addEventListener("load",apply,{once:true});}});}setCardMediaAspect();';
        
        // Helper: Ensure Layout Classes and Links (Fixes any JS-rendered inconsistencies)
        $html .= 'function ensureLayoutAndLinks(root){';
        $html .= '  var grids = root ? root.querySelectorAll(".podify-episodes-grid") : document.querySelectorAll(".podify-episodes-grid");';
        $html .= '  grids.forEach(function(g){';
        $html .= '    var lay = g.getAttribute("data-layout") || LAYOUT || "classic";';
        $html .= '    var isModern = (lay === "modern");';
        $html .= '    g.querySelectorAll(".podify-episode-card").forEach(function(card){';
        $html .= '      if(isModern && !card.classList.contains("podify-modern")){ card.classList.add("podify-modern"); card.classList.remove("podify-row"); }';
        $html .= '      else if(!isModern && !card.classList.contains("podify-row")){ card.classList.add("podify-row"); card.classList.remove("podify-modern"); }';
        $html .= '      var link = card.querySelector(".podify-read-more");';
        $html .= '      if(!link){';
        $html .= '        var t = card.getAttribute("data-title") || "";';
        $html .= '        var slug = t.toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"");';
        $html .= '        var url = BASE_URL + slug + "/";';
        $html .= '        link = document.createElement("a"); link.className = "podify-read-more"; link.innerHTML = "Read more <i class=\'fa fa-angle-right\'></i>"; link.href = url;';
        $html .= '        var actions = card.querySelector(".podify-episode-actions");';
        $html .= '        var body = card.querySelector(".podify-episode-body");';
        $html .= '        if(isModern && actions){ actions.insertBefore(link, actions.firstChild); }';
        $html .= '        else if(!isModern && body && actions){ body.insertBefore(link, actions); }';
        $html .= '        else if(body){ body.appendChild(link); }';
        $html .= '      }';
        $html .= '    });';
        $html .= '  });';
        $html .= '}/* ensureLayoutAndLinks(); */';
        
        // Play Button Click Handler
        $html .= 'document.addEventListener("click",function(e){var btn=e.target.closest(".podify-play-action-btn");if(!btn)return;var card=btn.closest(".podify-episode-card");if(!card)return;var src=card.getAttribute("data-audio");if(!src)return;e.preventDefault();try{var player=document.getElementById("podify-sticky-player");var stickyAudio=document.getElementById("podify-sticky-audio");var titleEl=document.getElementById("podify-sticky-title");var imgEl=document.getElementById("podify-sticky-img");var playBtn=document.getElementById("podify-sticky-play");var volBtn=document.getElementById("podify-sticky-volume");if(stickyAudio&&player){stickyAudio.src=src;stickyAudio.setAttribute("data-duration",card.getAttribute("data-duration")||"");stickyAudio.setAttribute("data-duration-seconds",card.getAttribute("data-duration-seconds")||"");document.body.classList.add("podify-player-active");player.style.setProperty("display","block","important");if(titleEl)titleEl.textContent=card.getAttribute("data-title")||titleEl.textContent;if(imgEl)imgEl.src=card.getAttribute("data-image")||imgEl.src;if(playBtn)playBtn.innerHTML=\'<svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><circle cx="12" cy="12" r="12" fill="white"/><path d="M9 8h2v8H9V8zm4 0h2v8h-2V8z" fill="black"/></svg>\';try{stickyAudio.load()}catch(_e){}document.querySelectorAll(".podify-episode-card.podify-playing").forEach(function(x){x.classList.remove("podify-playing")});card.classList.add("podify-playing");stickyAudio.play().catch(function(err){console.error("Podify: Sticky play failed from overlay click",err)})}}catch(err){console.error("Podify: Overlay click error",err)}});';
        
        // Duration Formatter
        $html .= 'function fmtDur(s){if(!s)return"";var sec=0;if(/^[0-9]+$/.test(s)){sec=parseInt(s,10)}else{var parts=s.split(":").map(function(x){return parseInt(x,10)||0});for(var i=0;i<parts.length;i++){sec=sec*60+parts[i]}}var h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),se=sec%60;return h>0?(h+":"+(m<10?"0":"")+m+":"+(se<10?"0":"")+se):(m+":"+(se<10?"0":"")+se)}';
        
        // Load More Handler
        $html .= 'document.addEventListener("click",function(e){';
        $html .= '  var btn=e.target.closest(".podify-load-more");if(!btn)return;';
        $html .= '  e.preventDefault(); e.stopImmediatePropagation();';
        $html .= '  var id=btn.getAttribute("data-target"); var grid=document.getElementById(id); if(!grid)return;';
        $html .= '  var limit=parseInt(grid.getAttribute("data-limit"))||9; var offset=parseInt(grid.getAttribute("data-offset"))||0;';
        $html .= '  var feed=grid.getAttribute("data-feed")||""; var cat=grid.getAttribute("data-category")||"";';
        $html .= '  var layout=grid.getAttribute("data-layout")||"classic"; var isModern=(layout==="modern");';
        $html .= '  btn.disabled=true; var oldT=btn.textContent; btn.textContent="Loading...";';
        $html .= '  var url=EP_URL+"?limit="+limit+"&offset="+offset+(feed?("&feed_id="+encodeURIComponent(feed)):"")+(cat?("&category_id="+encodeURIComponent(cat)):"");';
        $html .= '  fetch(url).then(parseJSONSafe).then(function(d){';
        $html .= '    btn.disabled=false; btn.textContent=oldT;';
        $html .= '    if(!d||!d.items||!d.items.length){ btn.textContent="No more"; btn.disabled=true; return; }';
        $html .= '    var h="";';
        $html .= '    d.items.forEach(function(ei){';
        $html .= '      var t=ei.title||"Untitled"; var pm=ei.permalink||(BASE_URL+(t.toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,""))+"/");';
        $html .= '      var dt=ei.published?new Date(ei.published):null; var dtS=dt?dt.toLocaleDateString():"";';
        $html .= '      var dur=fmtDur(ei.duration||""); var dSec=(function(){var s=ei.duration||"";var z=0;if(/^[0-9]+$/.test(s)){z=parseInt(s,10)}else{var p=s.split(":");p.forEach(function(x){z=z*60+(parseInt(x,10)||0)})}return z})();';
        $html .= '      var tg=(ei.tags||"").split(",").filter(function(x){return x.trim().length}).slice(0,3).join(", ");';
        $html .= '      var im=ei.image_url||""; var au=ei.audio_url||""; var de=ei.description||"";';
        $html .= '      if(de.length>0){de=de.replace(/<[^>]+>/g,"");if(de.length>180)de=de.slice(0,180)+"…";}';
        $html .= '      var mp=[]; if(dtS)mp.push(dtS); if(tg)mp.push(tg); var ml=mp.join(" · ");';
        $html .= '      var cc=isModern?"podify-episode-card podify-modern":"podify-episode-card podify-row";';
        $html .= '      var da=" data-title=\""+t.replace(/"/g,"&quot;")+"\""; if(au)da+=" data-audio=\""+au+"\""; if(im)da+=" data-image=\""+im+"\""; da+=" data-duration=\""+dur+"\" data-duration-seconds=\""+dSec+"\"";';
        $html .= '      h+="<div class=\""+cc+"\""+da+">";';
        $html .= '      if(isModern){';
        $html .= '        h+="<div class=\"podify-episode-media\">"+(im?"<img src=\""+im+"\" alt=\""+t.replace(/"/g,"&quot;")+"\" loading=\"lazy\">":"<div class=\"podify-episode-placeholder\"></div>")+"</div>";';
        $html .= '        h+="<div class=\"podify-episode-body\"><div class=\"podify-episode-top\"><h3 class=\"podify-episode-title\"><a href=\""+pm+"\" class=\"podify-episode-link\">"+t+"</a></h3></div>";';
        $html .= '        if(de)h+="<div class=\"podify-episode-desc podify-clamp-2\">"+de+"</div>";';
        $html .= '        if(ml)h+="<div class=\"podify-episode-meta\">"+ml+"</div>";';
        $html .= '        h+="<div class=\"podify-episode-actions\"><a class=\"podify-read-more\" href=\""+pm+"\">Read more <i class=\"fa fa-angle-right\"></i></a>";';
        $html .= '        if(au)h+="<button class=\"podify-play-action-btn\" aria-label=\"Play\"><svg viewBox=\"0 0 24 24\" width=\"36\" height=\"36\" fill=\"currentColor\"><path d=\"M8 5v14l11-7z\"/></svg></button>";';
        $html .= '        if(dur)h+="<span class=\"podify-episode-duration\">"+dur+"</span>";';
        $html .= '        h+="</div></div>";';
        $html .= '      }else{';
        $html .= '        h+="<div class=\"podify-episode-media\">"+(im?"<img src=\""+im+"\" alt=\""+t.replace(/"/g,"&quot;")+"\" loading=\"lazy\">":"<div class=\"podify-episode-placeholder\"></div>")+"</div>";';
        $html .= '        h+="<div class=\"podify-episode-body\"><div class=\"podify-episode-top\"><h3 class=\"podify-episode-title\"><a href=\""+pm+"\" class=\"podify-episode-link\">"+t+"</a></h3></div>";';
        $html .= '        if(de)h+="<div class=\"podify-episode-desc podify-clamp-2\">"+de+"</div>";';
        $html .= '        h+="<a class=\"podify-read-more\" href=\""+pm+"\">Read more <i class=\"fa fa-angle-right\"></i></a>";';
        $html .= '        h+="<div class=\"podify-episode-actions\">";';
        $html .= '        if(au)h+="<button class=\"podify-play-action-btn\" aria-label=\"Play\"><svg viewBox=\"0 0 24 24\" width=\"36\" height=\"36\" fill=\"currentColor\"><path d=\"M8 5v14l11-7z\"/></svg></button>";';
        $html .= '        if(dur)h+="<span class=\"podify-episode-duration\">"+dur+"</span>";';
        $html .= '        h+="</div>";';
        $html .= '        if(ml)h+="<div class=\"podify-episode-meta\">"+ml+"</div>";';
        $html .= '        h+="</div>";';
        $html .= '      }';
        $html .= '      h+="</div>";';
        $html .= '    });';
        $html .= '    var tmp=document.createElement("div"); tmp.innerHTML=h;';
        $html .= '    Array.from(tmp.children).forEach(function(n){ grid.appendChild(n); });';
        $html .= '    grid.setAttribute("data-offset", offset+d.items.length);';
        $html .= '    setCardMediaAspect(grid); ensureLayoutAndLinks(grid);';
        $html .= '  }).catch(function(e){ console.error(e); btn.disabled=false; btn.textContent="Error"; });';
        $html .= '});';
        $html .= '})();</script>';
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
        $ep_title = $ep && !empty($ep['title']) ? esc_html($ep['title']) : 'Untitled Episode';
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
            function parseJSONSafe(r){return r.text().then(function(t){console.log("Podify Sticky Response:", t.substring(0,200)); if(!t||t.trim().charAt(0)==="<"){console.warn("Podify: Received HTML/Invalid JSON in sticky", t.substring(0,100));return null;}try{return JSON.parse(t);}catch(_e){console.error("Podify Sticky JSON Error:", _e); return null;}});}
            console.log("Podify Sticky Init");
            var EP_URL = '.wp_json_encode( esc_url_raw( rest_url('podify/v1/episodes') ) ).';
            var FEED_ID = '.wp_json_encode( $feed_id ? intval($feed_id) : null ).';
            var FEED_ID_JS = (FEED_ID !== null && FEED_ID !== undefined) ? FEED_ID : (function(){ var el = document.querySelector(".podify-episodes-grid[data-feed]"); if(!el) return null; var v = parseInt(el.getAttribute("data-feed")); return isNaN(v)?null:v; })();
            var player = document.getElementById("podify-sticky-player");
            if(!player) { console.error("Podify: Sticky player element not found in DOM"); return; }
            else {
                // Move player to body to ensure fixed positioning works relative to viewport
                // This fixes issues where the player is trapped inside a container with transform/filter
                if (player.parentNode !== document.body) {
                    document.body.appendChild(player);
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
                fetch(url).then(parseJSONSafe).then(function(d){
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
                                fetch(url).then(parseJSONSafe).then(function(d){
                                    if(d && d.items) {
                                        if (pickAndPlay(d.items)) return;
                                        var tries = 0;
                                        function next(offset){
                                            if (tries++ >= 5) return;
                                            var u = EP_URL + "?limit=10&offset="+offset + (FEED_ID_JS ? ("&feed_id="+encodeURIComponent(FEED_ID_JS)) : "");
                                            fetch(u).then(parseJSONSafe).then(function(dd){
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
        }catch(e){console.error("Podify Init Error",e);}})();
        </script>';
        
        return $html;
    }
}
