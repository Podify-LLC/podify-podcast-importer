document.addEventListener('DOMContentLoaded', function() {

    var players = document.querySelectorAll('.podify-single-player');
    if (!players.length) {
        return;
    }

    // Initialize all players
    players.forEach(function(player) {
        var audio = player.querySelector('.podify-episode-audio');
        
        // Fallback: Check if audio is adjacent (fix for some themes/plugins moving it)
        if (!audio) {
            // Try finding any audio tag inside
            audio = player.querySelector('audio');
        }
        if (!audio) {
            var next = player.nextElementSibling;
            if (next && next.tagName === 'AUDIO') {
                player.appendChild(next);
                audio = next;
            }
        }

        var playBtn = player.querySelector('.podify-sp-play-btn');
        if (!playBtn) {
            // Fallback: Try finding button by aria-label
            playBtn = player.querySelector('button[aria-label="Play"]');
        }

        var volBtn = player.querySelector('.podify-sp-volume-btn');
        var volSlider = player.querySelector('.podify-sp-volume-slider');
        var gradient = player.querySelector('linearGradient');
        var clickArea = player.querySelector('.podify-sp-click-area');
        var curTimeEl = player.querySelector('.podify-sp-current');
        var durTimeEl = player.querySelector('.podify-sp-duration');

        if (!audio || !playBtn) {
            console.warn('Podify: Missing required elements for player ' + player.id, {
                foundAudio: !!audio, 
                foundPlayBtn: !!playBtn,
                htmlContent: player.innerHTML.substring(0, 200) + '...'
            });
            return;
        }

        // Clean src
        if (audio.src) {
            var raw = audio.src;
            var clean = raw.trim();
            if (raw !== clean) {
                audio.src = clean;
            }
        }
        
        // Debug: Listen for errors
        audio.addEventListener('error', function(e) {
            console.error('Podify: Audio Error', e.target.error, e.target.src);
        });

        // Icons
        var iconPlay = '<svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><circle cx="12" cy="12" r="12" fill="#0b5bd3"/><path d="M9.5 8l6 4-6 4V8z" fill="white"/></svg>';
        var iconPause = '<svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><circle cx="12" cy="12" r="12" fill="#0b5bd3"/><path d="M9 8h2v8H9V8zm4 0h2v8h-2V8z" fill="white"/></svg>';
        
        var iconVolOn = '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M3 9v6h4l5 4V5L7 9H3z"/><path d="M14 8.5a4.5 4.5 0 010 7" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
        var iconVolOff = '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M3 9v6h4l5 4V5L7 9H3z"/><path d="M16 8l4 4-4 4M12 8l-4 4 4 4" fill="none" stroke="currentColor" stroke-width="2"/></svg>';

        // Init Progress (Gradient)
        var stops = gradient ? gradient.querySelectorAll('stop') : null;
        if (stops && stops.length >= 2) {
             stops[0].setAttribute('offset', '0%');
             stops[1].setAttribute('offset', '0%');
        }

        // Play/Pause
        playBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!audio.src || audio.src.trim() === '') {
                 console.error('Podify: No audio source found for this player!');
            }
            
            if (audio.paused) {
                // Pause other players
                document.querySelectorAll('audio.podify-episode-audio').forEach(function(a) {
                    if (a !== audio && !a.paused) a.pause();
                });
                var p = audio.play();
                if (p !== undefined) {
                    p.catch(function(err){ console.error("Podify: Local play request failed", err); });
                }
            } else {
                audio.pause();
            }
        });

        // Volume
        if (volBtn) {
            volBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                audio.muted = !audio.muted;
                volBtn.innerHTML = audio.muted ? iconVolOff : iconVolOn;
                if (volSlider) {
                    volSlider.value = audio.muted ? 0 : (audio.volume || 1);
                }
            });
            
            audio.addEventListener('volumechange', function() {
                var isMuted = audio.muted || audio.volume === 0;
                volBtn.innerHTML = isMuted ? iconVolOff : iconVolOn;
                if (volSlider && !audio.muted) {
                    volSlider.value = audio.volume;
                } else if (volSlider && audio.muted) {
                    volSlider.value = 0;
                }
            });
        }

        if (volSlider) {
            volSlider.addEventListener('input', function(e) {
                e.stopPropagation(); // Prevent bubbling
                var val = parseFloat(this.value);
                audio.volume = val;
                audio.muted = (val === 0);
            });
            // Initialize slider value
            volSlider.value = audio.muted ? 0 : audio.volume;
        }

        audio.addEventListener('play', function() {
            playBtn.innerHTML = iconPause;
        });

        audio.addEventListener('pause', function() {
            playBtn.innerHTML = iconPlay;
        });

        audio.addEventListener('timeupdate', function() {
            var cur = audio.currentTime;
            var dur = audio.duration;
            
            if (curTimeEl) curTimeEl.textContent = formatTime(cur);
            if (dur && durTimeEl && (durTimeEl.textContent === '0:00' || durTimeEl.textContent === '')) {
                 durTimeEl.textContent = formatTime(dur);
            }

            if (dur > 0 && stops && stops.length >= 2) {
                var pct = (cur / dur) * 100;
                var pctStr = pct + '%';
                stops[0].setAttribute('offset', pctStr);
                stops[1].setAttribute('offset', pctStr);
            }
        });

        // Seek
        if (clickArea) {
            clickArea.addEventListener('click', function(e) {
                var rect = clickArea.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var w = rect.width;
                if (w > 0 && audio.duration) {
                    var pct = x / w;
                    var newTime = audio.duration * pct;
                    audio.currentTime = newTime;
                }
            });
        }
    });

    function formatTime(s) {
        if (!s || isNaN(s)) return '0:00';
        var sec = Math.floor(s);
        var m = Math.floor(sec / 60);
        var se = sec % 60;
        var h = Math.floor(m / 60);
        m = m % 60;
        if (h > 0) {
            return h + ':' + (m < 10 ? '0' : '') + m + ':' + (se < 10 ? '0' : '') + se;
        }
        return m + ':' + (se < 10 ? '0' : '') + se;
    }
});
