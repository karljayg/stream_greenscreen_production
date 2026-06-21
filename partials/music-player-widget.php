            <!-- ── MUSIC PLAYER ────────────────── -->
            <div class="lp-music">
                <div class="lp-music-bar" id="lpMusicBar">
                    <span class="lp-mx-toggle-icon" id="lpMusicToggleIcon">&#8722;</span>
                    <span class="lp-music-label">&#9836; MUSIC</span>
                    <button id="lpMusicHelp" class="lp-mx-help-btn" title="How the music player works" onclick="event.stopPropagation()">?</button>
                </div>
                <div class="lp-music-controls" onclick="event.stopPropagation()">
                    <div class="lp-music-transport">
                        <button id="lpMusicPrev" title="Previous Track">&#x23EE;</button>
                        <button id="lpMusicPlayPause" class="lp-mx-dim" title="Play / Pause">&#9654;</button>
                        <button id="lpMusicNext" title="Next Track">&#x23ED;</button>
                        <button id="lpMusicRandom" title="Random — play any song from any mood">&#x1F500;</button>
                    </div>
                    <div class="lp-music-knobs">
                        <div class="lp-mx-knob-wrap" title="Volume — drag up/down or scroll">
                            <span class="lp-mx-dial-lbl">VOL</span>
                            <canvas id="lpMusicVolKnob" class="lp-mx-knob" width="30" height="30"></canvas>
                            <input type="number" id="lpMusicVol" min="5" max="100" value="22" style="display:none">
                        </div>
                        <div class="lp-mx-knob-wrap" title="Crossfade — drag up/down or scroll">
                            <span class="lp-mx-dial-lbl">FADE</span>
                            <canvas id="lpMusicFadeKnob" class="lp-mx-knob" width="30" height="30"></canvas>
                            <input type="number" id="lpMusicFade" min="0" max="10" step="0.5" value="1.5" style="display:none">
                        </div>
                    </div>
                </div>
                <div class="lp-mx-song-row">
                    <span class="lp-mx-song" id="lpMusicStatus">select a mood</span>
                    <div class="lp-mx-seek-wrap">
                        <span id="lpMusicTimeNow" class="lp-mx-time">0:00</span>
                        <input type="range" id="lpMusicSeek" class="lp-mx-seek" min="0" max="1000" value="0" step="1">
                        <span id="lpMusicTimeDur" class="lp-mx-time">-:--</span>
                    </div>
                </div>
                <!-- Stage bar — shown only for detailed/staged scenes (e.g. SC2). -->
                <div class="lp-mx-stage" id="lpMusicStage" style="display:none" onclick="event.stopPropagation()">
                    <div class="lp-mx-stage-hdr">
                        <span class="lp-mx-stage-title" id="lpMusicStageTitle">STAGES</span>
                        <span class="lp-mx-stage-now" id="lpMusicStageNow"></span>
                    </div>
                    <div class="lp-mx-stage-grid" id="lpMusicStageGrid"></div>
                </div>
                <div class="lp-music-grid" id="lpMusicGrid"></div>
            </div>
