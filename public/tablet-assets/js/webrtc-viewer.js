/**
 * RemoteMonitor Tablet WebRTC Viewer Manager.
 * Handles fetching assigned desktops, establishing WebRTC peer connections,
 * rendering multi-desktop live streams, audio focus, fullscreen, and auto-reconnection.
 */

window.WebRtcViewer = (function () {
    let _assignedDesktops = [];
    let _activeSessions = new Map(); // desktop_uuid -> { session_id, pc, videoEl, audioEl, desktopData, reconnectTimer, statsTimer, iceStats }
    let _audioFocusedDesktopUuid = null;
    let _pollTimer = null;
    let _forceTurnRelay = (new URLSearchParams(window.location.search)).get('relay') === '1' || window.FORCE_TURN_RELAY === true;
    let _pendingConnects = new Set();
    let _sessionStartTime = Date.now();
    let _uptimeTimer = null;

    function startUptimeTimer() {
        if (_uptimeTimer) clearInterval(_uptimeTimer);
        const timeEl = document.getElementById('header-connected-time');
        const uptimeEl = document.getElementById('header-uptime-text');
        if (timeEl && (timeEl.textContent === '--:--:--' || !timeEl.textContent)) {
            const now = new Date();
            timeEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }

        _uptimeTimer = setInterval(() => {
            if (!uptimeEl) return;
            const diffSec = Math.floor((Date.now() - _sessionStartTime) / 1000);
            const hrs = String(Math.floor(diffSec / 3600)).padStart(2, '0');
            const mins = String(Math.floor((diffSec % 3600) / 60)).padStart(2, '0');
            const secs = String(diffSec % 60).padStart(2, '0');
            uptimeEl.textContent = `${hrs}:${mins}:${secs}`;
        }, 1000);
    }

    function updateTurnButtonUI() {
        const btnText = document.getElementById('turn-mode-text');
        const btn = document.getElementById('toggle-turn-btn');
        if (btnText) {
            btnText.textContent = _forceTurnRelay ? 'ICE: FORCED TURN' : 'ICE: AUTO';
        }
        if (btn) {
            btn.style.color = _forceTurnRelay ? '#f59e0b' : '#94a3b8';
            btn.style.borderColor = _forceTurnRelay ? 'rgba(245, 158, 11, 0.4)' : '';
        }
    }

    async function getCredentials() {
        if (window._cachedCredentials && window._cachedCredentials.token) {
            return window._cachedCredentials;
        }
        if (window.IDBStorage && window.IDBStorage.getCredentials) {
            window._cachedCredentials = await window.IDBStorage.getCredentials();
            return window._cachedCredentials;
        }
        if (window.IDBVault && window.IDBVault.getCredential) {
            const token = await window.IDBVault.getCredential();
            const device = await window.IDBVault.getDevice();
            window._cachedCredentials = { token, device };
            return window._cachedCredentials;
        }
        return null;
    }

    /**
     * Initialize viewer manager.
     */
    async function init() {
        console.log('[WebRtcViewer] Initializing Remote Desktop Viewer...');
        updateTurnButtonUI();
        startUptimeTimer();

        const turnBtn = document.getElementById('toggle-turn-btn');
        if (turnBtn) {
            turnBtn.onclick = () => {
                _forceTurnRelay = !_forceTurnRelay;
                updateTurnButtonUI();
                console.log(`[WebRtcViewer] Forced TURN Relay mode toggled: ${_forceTurnRelay}`);
                // Reconnect all active desktops with new policy
                _assignedDesktops.forEach(d => {
                    disconnectDesktop(d.uuid);
                    connectToDesktop(d);
                });
            };
        }

        await refreshDesktops();

        // High-frequency 3-second poll so any desktop change in Admin switches immediately
        if (_pollTimer) clearInterval(_pollTimer);
        _pollTimer = setInterval(refreshDesktops, 3000);
    }

    /**
     * Fetch list of assigned desktops from server.
     */
    async function refreshDesktops() {
        const cred = await getCredentials();
        if (!cred || !cred.token) return;

        try {
            const res = await fetch('/api/v1/tablet/desktops', {
                headers: {
                    'Authorization': `Bearer ${cred.token}`,
                    'Accept': 'application/json'
                }
            });

            if (res.status === 401 || res.status === 403) {
                // Token revoked or tablet disabled
                if (window.App && window.App.showRevokedScreen) {
                    window.App.showRevokedScreen();
                }
                return;
            }

            if (!res.ok) {
                console.warn('[WebRtcViewer] Failed to load desktops (HTTP ' + res.status + ')');
                return;
            }

            const data = await res.json();
            const newDesktops = data.desktops || [];
            const newUuids = new Set(newDesktops.map(d => d.uuid));

            // Cleanly disconnect and remove desktops that Admin unassigned or switched away
            for (const oldDesktop of _assignedDesktops) {
                if (!newUuids.has(oldDesktop.uuid)) {
                    console.log(`[WebRtcViewer] Desktop ${oldDesktop.device_identifier} unassigned by Admin -> Disconnecting and switching`);
                    disconnectDesktop(oldDesktop.uuid);
                    const oldCard = document.getElementById(`desktop-card-${oldDesktop.uuid}`);
                    if (oldCard) oldCard.remove();
                }
            }

            _assignedDesktops = newDesktops;
            renderDesktopsGrid(_assignedDesktops);

            function tryConnectAll() {
                for (const desktop of _assignedDesktops) {
                    if (desktop.status !== 'disabled') {
                        connectToDesktop(desktop);
                    }
                }
            }

            if (window.DeviceRealtime && window.DeviceRealtime.getState() === 'ONLINE') {
                tryConnectAll();
            } else {
                console.log('[WebRtcViewer] Awaiting WebSocket subscription before initiating desktop stream...');
                window.addEventListener('device-ws-subscribed', () => {
                    console.log('[WebRtcViewer] WebSocket online -> Initiating desktop streams immediately');
                    tryConnectAll();
                }, { once: true });
                // Fallback timeout in case event was already fired
                setTimeout(tryConnectAll, 800);
            }
        } catch (err) {
            console.error('[WebRtcViewer] Network error refreshing desktops:', err);
        }
    }

    /**
     * Render the multi-desktop grid DOM.
     */
    function renderDesktopsGrid(desktops) {
        const container = document.getElementById('desktops-grid-container');
        const countBadge = document.getElementById('desktops-count-badge');
        if (!container) return;

        if (countBadge) {
            countBadge.textContent = `${desktops.length} Assigned`;
        }

        if (desktops.length === 0) {
            container.innerHTML = `
                <div class="empty-desktops-view">
                    <div class="empty-icon">🖥️</div>
                    <div class="empty-title">No Desktops Assigned</div>
                    <div class="empty-subtitle">
                        An administrator must authorize this tablet to view one or more Windows Desktop PCs in the Admin Control Center.
                    </div>
                </div>
            `;
            return;
        }

        // Apply grid layout class based on count
        container.className = 'desktop-grid';
        if (desktops.length === 1) container.classList.add('grid-1');
        else if (desktops.length === 2) container.classList.add('grid-2');
        else if (desktops.length <= 4) container.classList.add('grid-4');
        else container.classList.add('grid-4');

        // Render card for each desktop (preserve existing video elements if already streaming)
        desktops.forEach(desktop => {
            let card = document.getElementById(`desktop-card-${desktop.uuid}`);
            if (!card) {
                card = createDesktopCardElement(desktop);
                container.appendChild(card);
            } else {
                updateDesktopCardMetadata(card, desktop);
            }
        });

        // Clean up removed desktops
        const currentUuids = new Set(desktops.map(d => d.uuid));
        container.querySelectorAll('.desktop-stream-card').forEach(card => {
            const uuid = card.dataset.desktopUuid;
            if (!currentUuids.has(uuid)) {
                disconnectDesktop(uuid);
                card.remove();
            }
        });
    }

    /**
     * Create HTML element for a desktop stream card.
     */
    function createDesktopCardElement(desktop) {
        const card = document.createElement('div');
        card.id = `desktop-card-${desktop.uuid}`;
        card.className = 'desktop-stream-card';
        card.dataset.desktopUuid = desktop.uuid;

        card.innerHTML = `
            <div class="stream-card-header">
                <div class="stream-title-group">
                    <span class="desktop-id-badge">${desktop.device_identifier}</span>
                    <span class="desktop-name-text" title="${desktop.name}">${desktop.name}</span>
                </div>
                <div class="stream-meta-group">
                    <span class="latency-badge" id="latency-${desktop.uuid}">~30ms</span>
                    <span id="status-pill-${desktop.uuid}" class="stream-status-pill connecting">Connecting</span>
                </div>
            </div>

            <div class="stream-viewport" id="viewport-${desktop.uuid}">
                <video id="video-${desktop.uuid}" class="stream-video-element" autoplay playsinline muted style="display: none;"></video>
                <img id="stream-img-${desktop.uuid}" class="stream-video-element" style="display: block; width: 100%; height: 100%; object-fit: contain;" alt="Live Screen Stream">
                <audio id="audio-${desktop.uuid}" autoplay></audio>

                <div id="loading-${desktop.uuid}" class="stream-loading-overlay">
                    <div class="spinner"></div>
                    <div class="loading-text" id="loading-text-${desktop.uuid}">Connecting live stream...</div>
                </div>

                <div class="stream-overlay-controls">
                    <button type="button" class="overlay-btn audio-focus-btn" id="audio-btn-${desktop.uuid}" title="Tap to listen to system audio">
                        <span class="audio-icon">🔇</span>
                        <span class="audio-label">Tap to Listen</span>
                        <div class="audio-wave-bars hidden" id="audio-wave-${desktop.uuid}">
                            <span></span><span></span><span></span>
                        </div>
                    </button>
                    <button type="button" class="overlay-btn mic-toggle-btn" id="mic-btn-${desktop.uuid}" title="Speak to Desktop PC Speakers (Mic On/Off)">
                        <span class="mic-icon">🎙️</span>
                        <span class="mic-label">Mic: OFF</span>
                        <div class="mic-wave-bars hidden" id="mic-wave-${desktop.uuid}">
                            <span></span><span></span><span></span>
                        </div>
                    </button>
                    <button type="button" class="overlay-btn fullscreen-btn" id="fs-btn-${desktop.uuid}" title="Fullscreen">
                        ⛶ Fullscreen
                    </button>
                    <button type="button" class="overlay-btn reconnect-btn" id="rec-btn-${desktop.uuid}" title="Reconnect Stream">
                        🔄
                    </button>
                </div>
            </div>
        `;

        // Event listeners
        const audioBtn = card.querySelector(`#audio-btn-${desktop.uuid}`);
        audioBtn.addEventListener('click', () => toggleAudioFocus(desktop.uuid));

        const micBtn = card.querySelector(`#mic-btn-${desktop.uuid}`);
        if (micBtn) {
            micBtn.addEventListener('click', () => toggleDesktopMic(desktop.uuid));
        }

        const fsBtn = card.querySelector(`#fs-btn-${desktop.uuid}`);
        fsBtn.addEventListener('click', () => toggleFullscreen(card));

        const recBtn = card.querySelector(`#rec-btn-${desktop.uuid}`);
        recBtn.addEventListener('click', () => {
            console.log(`[WebRtcViewer] Manual reconnect requested for ${desktop.device_identifier}`);
            disconnectDesktop(desktop.uuid);
            connectToDesktop(desktop);
        });

        return card;
    }

    /**
     * Update card badges and text.
     */
    function updateDesktopCardMetadata(card, desktop) {
        const titleText = card.querySelector('.desktop-name-text');
        if (titleText) titleText.textContent = desktop.name;
    }

    let _pumpActive = new Map();
    let _streamReconnectTimers = new Map();

    function startZeroLagFramePump(desktop) {
        const uuid = desktop.uuid;
        _pumpActive.set(uuid, true);

        const img = document.getElementById(`stream-img-${uuid}`);
        const videoEl = document.getElementById(`video-${uuid}`);
        const loadingOverlay = document.getElementById(`loading-${uuid}`);
        const statusPill = document.getElementById(`status-pill-${uuid}`);
        const latencyBadge = document.getElementById(`latency-${uuid}`);

        if (!img) return;

        // If WebRTC is already actively streaming video, do not attach HTTP stream
        if (videoEl && videoEl.srcObject && videoEl.style.display !== 'none' && !videoEl.paused) {
            img.style.display = 'none';
            img.src = '';
            return;
        }

        // Clear existing reconnect timer if present
        if (_streamReconnectTimers.has(uuid)) {
            clearTimeout(_streamReconnectTimers.get(uuid));
            _streamReconnectTimers.delete(uuid);
        }

        img.onload = () => {
            if (!_pumpActive.get(uuid)) return;
            if (loadingOverlay) loadingOverlay.classList.add('hidden');
            if (latencyBadge) latencyBadge.textContent = '~25ms';
            if (statusPill && !statusPill.textContent.includes('P2P') && !statusPill.textContent.includes('RELAY')) {
                statusPill.className = 'stream-status-pill live';
                statusPill.textContent = 'LIVE (30 FPS)';
            }
            const session = _activeSessions.get(uuid);
            if (session && session.sessionId && !session._notifiedLive) {
                session._notifiedLive = true;
                getCredentials().then(cred => {
                    if (cred && cred.token) {
                        notifySessionStatus(session.sessionId, 'connected', cred.token);
                    }
                });
            }
        };

        img.onerror = () => {
            if (!_pumpActive.get(uuid)) return;
            console.warn(`[WebRtcViewer] Live stream disconnected for ${desktop.device_identifier} -> Auto-reconnecting in 1.5s...`);
            if (loadingOverlay) {
                loadingOverlay.classList.remove('hidden');
                const loadingText = document.getElementById(`loading-text-${uuid}`);
                if (loadingText) loadingText.textContent = 'Desktop Offline • Auto-Reconnecting...';
            }
            if (statusPill) {
                statusPill.className = 'stream-status-pill offline';
                statusPill.textContent = 'RECONNECTING';
            }
            const timer = setTimeout(() => {
                if (_pumpActive.get(uuid) && (!videoEl || videoEl.style.display === 'none')) {
                    img.src = `/stream/${uuid}/live?t=${Date.now()}`;
                }
            }, 1500);
            _streamReconnectTimers.set(uuid, timer);
        };

        // Attach continuous 30 FPS multipart stream (single persistent connection)
        img.style.display = 'block';
        img.src = `/stream/${uuid}/live?t=${Date.now()}`;
    }

    function stopZeroLagPump(uuid) {
        _pumpActive.delete(uuid);
        if (_streamReconnectTimers.has(uuid)) {
            clearTimeout(_streamReconnectTimers.get(uuid));
            _streamReconnectTimers.delete(uuid);
        }
        const img = document.getElementById(`stream-img-${uuid}`);
        if (img) {
            img.src = '';
            img.style.display = 'none';
        }
    }

    /**
     * Connect to a specific desktop via WebRTC.
     */
    async function connectToDesktop(desktop, forceRelayMode = null) {
        if (_pendingConnects.has(desktop.uuid)) {
            console.log(`[WebRtcViewer] Connection attempt already in-flight for ${desktop.device_identifier}`);
            return;
        }

        if (_activeSessions.has(desktop.uuid)) {
            const existing = _activeSessions.get(desktop.uuid);
            if (existing.pc) {
                const connState = existing.pc.connectionState;
                const iceState = existing.pc.iceConnectionState;
                if (!['failed', 'closed'].includes(connState) && !['failed', 'closed'].includes(iceState)) {
                    console.log(`[WebRtcViewer] Session already active/negotiating for ${desktop.device_identifier} (Conn: ${connState}, ICE: ${iceState})`);
                    return; // Already actively streaming or negotiating
                }
            }
        }

        _pendingConnects.add(desktop.uuid);

        const cred = await getCredentials();
        if (!cred || !cred.token) {
            _pendingConnects.delete(desktop.uuid);
            return;
        }

        const loadingOverlay = document.getElementById(`loading-${desktop.uuid}`);
        const loadingText = document.getElementById(`loading-text-${desktop.uuid}`);
        const statusPill = document.getElementById(`status-pill-${desktop.uuid}`);
        const videoEl = document.getElementById(`video-${desktop.uuid}`);
        const audioEl = document.getElementById(`audio-${desktop.uuid}`);
        const streamImg = document.getElementById(`stream-img-${desktop.uuid}`);

        if (loadingOverlay) loadingOverlay.classList.remove('hidden');
        if (loadingText) loadingText.textContent = 'Connecting live stream...';
        if (statusPill) {
            statusPill.className = 'stream-status-pill connecting';
            statusPill.textContent = 'Connecting';
        }

        // Engine 1: Start High-Speed Zero-Lag Real-Time Frame Pump
        if (streamImg) {
            streamImg.style.display = 'block';
            startZeroLagFramePump(desktop);
        }

        try {
            // Engine 2: Initiate WebRTC Session in parallel (Ultra-low latency P2P when on LAN)
            const res = await fetch('/api/v1/webrtc/session/initiate', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${cred.token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ desktop_uuid: desktop.uuid })
            });

            if (!res.ok) {
                const errData = await res.json().catch(() => ({}));
                console.warn(`[WebRtcViewer] Session initiate error for ${desktop.device_identifier}:`, errData);
                return;
            }

            const sessionData = await res.json();
            const sessionId = sessionData.session_id;
            const iceServers = sessionData.ice_servers || [{ urls: 'stun:stun.l.google.com:19302' }];

            console.log(`[WebRtcViewer] WebRTC session initiated: ${sessionId} for desktop ${desktop.device_identifier}`);

            // Create local RTCPeerConnection (Auto-enables TURN relay if STUN fails or forced)
            const useRelay = (forceRelayMode !== null) ? forceRelayMode : _forceTurnRelay;
            const pcConfig = {
                iceServers,
                iceTransportPolicy: useRelay ? 'relay' : 'all'
            };
            const pc = new RTCPeerConnection(pcConfig);
            try {
                pc.addTransceiver('video', { direction: 'recvonly' });
                pc.addTransceiver('audio', { direction: 'recvonly' });
            } catch (e) {
                console.warn('[WebRtcViewer] Notice adding transceivers:', e);
            }

            // Store session
            _activeSessions.set(desktop.uuid, {
                sessionId,
                pc,
                videoEl,
                audioEl,
                streamImg,
                desktopData: desktop,
                reconnectTimer: null,
                statsTimer: null,
                iceStats: null
            });

            function markStreamLive() {
                const session = _activeSessions.get(desktop.uuid);
                if (session && session.reconnectTimer) {
                    clearTimeout(session.reconnectTimer);
                    session.reconnectTimer = null;
                }
                if (loadingOverlay) loadingOverlay.classList.add('hidden');
                if (videoEl && videoEl.srcObject) {
                    videoEl.style.display = 'block';
                    if (streamImg) {
                        streamImg.style.display = 'none';
                        streamImg.src = '';
                    }
                    stopZeroLagPump(desktop.uuid);
                }
                if (statusPill) {
                    statusPill.className = 'stream-status-pill live';
                    statusPill.textContent = 'LIVE';
                }
                notifySessionStatus(sessionId, 'connected', cred.token);
                startStatsMonitoring(desktop.uuid);
            }

            // Handle incoming media tracks
            pc.ontrack = (event) => {
                console.log(`[WebRtcViewer] Received WebRTC track (${event.track.kind}) from ${desktop.device_identifier}`);
                const mediaStream = (event.streams && event.streams[0]) ? event.streams[0] : new MediaStream([event.track]);
                if (event.track.kind === 'video' && videoEl) {
                    videoEl.srcObject = mediaStream;
                    videoEl.onloadedmetadata = markStreamLive;
                    videoEl.onplaying = markStreamLive;
                    videoEl.play().catch(e => console.warn('Video autoplay notice:', e));
                }
                if (event.track.kind === 'audio' && audioEl) {
                    audioEl.srcObject = mediaStream;
                    audioEl.muted = (_audioFocusedDesktopUuid !== desktop.uuid);
                    audioEl.play().catch(e => console.warn('Audio autoplay notice:', e));
                }
            };

            // Handle ICE candidates generated locally
            pc.onicecandidate = async (event) => {
                if (event.candidate) {
                    await sendIceCandidate(sessionId, event.candidate, cred.token);
                }
            };

            // Handle ICE connection state changes
            pc.oniceconnectionstatechange = () => {
                const iceState = pc.iceConnectionState;
                console.log(`[WebRtcViewer] ICE state for ${desktop.device_identifier}: ${iceState}`);
                if (iceState === 'connected' || iceState === 'completed') {
                    markStreamLive();
                }
            };

            // Handle connection state changes
            pc.onconnectionstatechange = () => {
                const state = pc.connectionState;
                console.log(`[WebRtcViewer] Connection state for ${desktop.device_identifier}: ${state}`);

                if (state === 'connected') {
                    markStreamLive();
                } else if (state === 'failed') {
                    // Auto-retry once with Forced TURN Relay if direct STUN was blocked by symmetric NAT / CGNAT
                    if (!desktop._retriedTurn && !useRelay) {
                        desktop._retriedTurn = true;
                        console.log(`[WebRtcViewer] Direct P2P failed for ${desktop.device_identifier} -> Auto-retrying with Forced TURN Relay...`);
                        disconnectDesktop(desktop.uuid);
                        connectToDesktop(desktop, true);
                        return;
                    }

                    // Fallback to HTTP Live Stream if TURN also unavailable
                    console.log(`[WebRtcViewer] WebRTC ${state} -> Fallback to Universal Frame Pump`);
                    stopStatsMonitoring(desktop.uuid);
                    if (videoEl) {
                        videoEl.style.display = 'none';
                        videoEl.srcObject = null;
                    }
                    if (streamImg) {
                        streamImg.style.display = 'block';
                    }
                    startZeroLagFramePump(desktop);
                    if (loadingOverlay) loadingOverlay.classList.add('hidden');
                    if (statusPill) {
                        statusPill.className = 'stream-status-pill live';
                        statusPill.textContent = 'LIVE';
                        statusPill.style.background = '';
                    }
                } else if (state === 'disconnected' || state === 'closed') {
                    stopStatsMonitoring(desktop.uuid);
                    if (videoEl) {
                        videoEl.style.display = 'none';
                        videoEl.srcObject = null;
                    }
                    if (streamImg) {
                        streamImg.style.display = 'block';
                    }
                    startZeroLagFramePump(desktop);
                    if (loadingOverlay) loadingOverlay.classList.add('hidden');
                    if (statusPill) {
                        statusPill.className = 'stream-status-pill live';
                        statusPill.textContent = 'LIVE';
                        statusPill.style.background = '';
                    }
                }
            };

            if (loadingText) loadingText.textContent = 'Waiting for desktop live media stream...';

        } catch (err) {
            console.error(`[WebRtcViewer] Error connecting to desktop ${desktop.device_identifier}:`, err);
            scheduleReconnect(desktop);
        } finally {
            _pendingConnects.delete(desktop.uuid);
        }
    }

    /**
     * Handle incoming SDP Offer from Desktop (via Reverb WebSocket).
     */
    async function handleOfferReceived(payload) {
        const sessionId = payload.session_id;
        const desktopUuid = payload.desktop_uuid;
        const sdp = payload.sdp;

        let session = _activeSessions.get(desktopUuid);
        if (!session) {
            console.warn(`[WebRtcViewer] Received offer for desktop ${desktopUuid} before card initialized, creating session...`);
            const desktop = _assignedDesktops.find(d => d.uuid === desktopUuid) || { uuid: desktopUuid, device_identifier: 'DESK-PC', name: 'Remote Desktop' };
            await connectToDesktop(desktop);
            session = _activeSessions.get(desktopUuid);
        }

        if (session) {
            session.sessionId = sessionId;
        }

        if (!session || !session.pc) {
            console.warn(`[WebRtcViewer] Cannot apply offer: PeerConnection not ready`);
            return;
        }

        const pc = session.pc;
        const cred = await getCredentials();

        try {
            console.log(`[WebRtcViewer] Applying remote SDP Offer for session ${sessionId}...`);
            let rawSdp = typeof sdp === 'string' ? sdp : (sdp.sdp || '');
            let cleanSdp = rawSdp
                .replace(/\r\n/g, '\n')
                .replace(/\r/g, '\n')
                .split('\n')
                .map(l => l.trim())
                .filter(l => l.length > 0)
                .join('\r\n') + '\r\n';

            await pc.setRemoteDescription(new RTCSessionDescription({
                type: (typeof sdp === 'object' && sdp.type) ? sdp.type : 'offer',
                sdp: cleanSdp
            }));

            // Create SDP Answer
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);

            // Send Answer to server
            await fetch('/api/v1/webrtc/signal/answer', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${cred.token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    sdp: {
                        type: answer.type,
                        sdp: answer.sdp
                    }
                })
            });

            console.log(`[WebRtcViewer] Sent SDP Answer for session ${sessionId}`);
        } catch (err) {
            console.error(`[WebRtcViewer] Error processing SDP Offer:`, err);
        }
    }

    /**
     * Handle incoming ICE Candidate (via Reverb WebSocket).
     */
    async function handleIceCandidateReceived(payload) {
        const sessionId = payload.session_id;
        const senderUuid = payload.sender_uuid;
        const candidateData = payload.candidate;

        const session = _activeSessions.get(senderUuid);
        if (!session || !session.pc) {
            return;
        }

        try {
            await session.pc.addIceCandidate(new RTCIceCandidate(candidateData));
            console.log(`[WebRtcViewer] Added remote ICE candidate from ${senderUuid}`);
        } catch (err) {
            // Non-fatal candidate error
        }
    }

    /**
     * Send local ICE candidate to server.
     */
    async function sendIceCandidate(sessionId, candidate, token) {
        try {
            await fetch('/api/v1/webrtc/signal/ice-candidate', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    candidate: {
                        candidate: candidate.candidate,
                        sdpMid: candidate.sdpMid,
                        sdpMLineIndex: candidate.sdpMLineIndex
                    }
                })
            });
        } catch (err) {
            // Non-fatal
        }
    }

    /**
     * Notify server of session status change.
     */
    async function notifySessionStatus(sessionId, status, token, reason = null) {
        try {
            await fetch('/api/v1/webrtc/session/status', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    status: status,
                    reason: reason
                })
            });
        } catch (err) {
            // Non-fatal
        }
    }

    /**
     * Schedule auto-reconnect for a desktop stream.
     */
    function scheduleReconnect(desktop) {
        const existing = _activeSessions.get(desktop.uuid);
        if (existing && existing.reconnectTimer) {
            clearTimeout(existing.reconnectTimer);
        }

        const timer = setTimeout(() => {
            console.log(`[WebRtcViewer] Auto-reconnecting to ${desktop.device_identifier}...`);
            disconnectDesktop(desktop.uuid);
            connectToDesktop(desktop);
        }, 5000);

        if (existing) {
            existing.reconnectTimer = timer;
        }
    }

    /**
     * Disconnect a desktop stream cleanly.
     */
    function disconnectDesktop(desktopUuid) {
        const session = _activeSessions.get(desktopUuid);
        if (!session) return;

        stopStatsMonitoring(desktopUuid);

        if (session.reconnectTimer) {
            clearTimeout(session.reconnectTimer);
        }

        if (session.pc) {
            try {
                session.pc.close();
            } catch (e) {}
        }

        if (session.videoEl) {
            session.videoEl.srcObject = null;
        }

        if (session.audioEl) {
            session.audioEl.srcObject = null;
        }

        const streamImg = document.getElementById(`stream-img-${desktopUuid}`);
        if (streamImg) {
            streamImg.src = '';
            streamImg.style.display = 'none';
        }

        stopZeroLagPump(desktopUuid);

        if (_activeMicDesktopUuid === desktopUuid) {
            stopDesktopMic();
        }

        if (session.sessionId) {
            getCredentials().then(cred => {
                if (cred && cred.token) {
                    notifySessionStatus(session.sessionId, 'terminated', cred.token, 'Stream closed');
                }
            });
        }

        _activeSessions.delete(desktopUuid);
    }

    /**
     * Inspect WebRTC stats and active ICE candidate pair.
     */
    async function inspectIceStats(desktopUuid) {
        const session = _activeSessions.get(desktopUuid);
        if (!session || !session.pc) return null;

        try {
            const stats = await session.pc.getStats();
            let activePair = null;
            let localCand = null;
            let remoteCand = null;

            stats.forEach(report => {
                if (report.type === 'candidate-pair' && (report.selected || report.state === 'succeeded' || report.nominated)) {
                    activePair = report;
                    localCand = stats.get(report.localCandidateId);
                    remoteCand = stats.get(report.remoteCandidateId);
                }
            });

            const localType = localCand ? localCand.candidateType : 'unknown';
            const remoteType = remoteCand ? remoteCand.candidateType : 'unknown';

            let mediaPath = 'host (LAN Direct)';
            if (localType === 'relay' || remoteType === 'relay') {
                mediaPath = 'relay (TURN)';
            } else if (localType === 'srflx' || remoteType === 'srflx') {
                mediaPath = 'srflx (STUN P2P)';
            }

            const info = {
                connectionState: session.pc.connectionState,
                iceConnectionState: session.pc.iceConnectionState,
                iceGatheringState: session.pc.iceGatheringState,
                mediaPath: mediaPath,
                localCandidateType: localType,
                localProtocol: localCand ? localCand.protocol : 'unknown',
                remoteCandidateType: remoteType,
                remoteProtocol: remoteCand ? remoteCand.protocol : 'unknown',
                activePairId: activePair ? activePair.id : null,
                forcedRelay: _forceTurnRelay
            };

            session.iceStats = info;

            const statusPill = document.getElementById(`status-pill-${desktopUuid}`);
            if (statusPill && session.pc.connectionState === 'connected') {
                const label = (mediaPath.includes('relay') ? 'LIVE [RELAY/TURN]' : (mediaPath.includes('srflx') ? 'LIVE [STUN P2P]' : 'LIVE [P2P]'));
                statusPill.textContent = label;
                if (mediaPath.includes('relay')) {
                    statusPill.style.background = '#d97706';
                } else {
                    statusPill.style.background = '';
                }
            }

            return info;
        } catch (e) {
            console.warn('[WebRtcViewer] Error collecting ICE stats:', e);
            return null;
        }
    }

    function startStatsMonitoring(desktopUuid) {
        stopStatsMonitoring(desktopUuid);
        const session = _activeSessions.get(desktopUuid);
        if (!session) return;
        inspectIceStats(desktopUuid);
        session.statsTimer = setInterval(() => {
            inspectIceStats(desktopUuid);
        }, 3000);
    }

    function stopStatsMonitoring(desktopUuid) {
        const session = _activeSessions.get(desktopUuid);
        if (session && session.statsTimer) {
            clearInterval(session.statsTimer);
            session.statsTimer = null;
        }
    }

    let _audioCtx = null;
    let _audioAbortController = null;

    async function startPcmAudioStream(desktopUuid) {
        stopPcmAudioStream();

        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) {
            console.warn('[WebRtcViewer] Web Audio API not supported on this browser');
            return;
        }

        try {
            if (!_audioCtx || _audioCtx.state === 'closed') {
                _audioCtx = new AudioContextClass({ sampleRate: 48000 });
            }
            if (_audioCtx.state === 'suspended') {
                await _audioCtx.resume();
            }

            _audioAbortController = new AbortController();
            const signal = _audioAbortController.signal;

            console.log(`[WebRtcViewer] Initiating high-speed PCM audio stream for desktop ${desktopUuid}...`);
            const res = await fetch(`/stream/${desktopUuid}/audio?raw=1&t=${Date.now()}`, { signal });
            if (!res.ok || !res.body) {
                console.warn(`[WebRtcViewer] Audio stream HTTP ${res.status}`);
                return;
            }

            const reader = res.body.getReader();
            let nextStartTime = _audioCtx.currentTime;
            let leftover = new Uint8Array(0);

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                let data = value;
                if (leftover.length > 0) {
                    const merged = new Uint8Array(leftover.length + data.length);
                    merged.set(leftover);
                    merged.set(data, leftover.length);
                    data = merged;
                    leftover = new Uint8Array(0);
                }

                // 48kHz stereo 16-bit: each frame is 4 bytes (2 ch * 2 bytes)
                const sampleCount = Math.floor(data.length / 4);
                if (sampleCount === 0) {
                    leftover = data;
                    continue;
                }

                const usableBytes = sampleCount * 4;
                if (data.length > usableBytes) {
                    leftover = data.slice(usableBytes);
                }

                const int16View = new Int16Array(data.buffer, data.byteOffset, sampleCount * 2);
                const buffer = _audioCtx.createBuffer(2, sampleCount, 48000);
                const leftCh = buffer.getChannelData(0);
                const rightCh = buffer.getChannelData(1);

                for (let i = 0; i < sampleCount; i++) {
                    leftCh[i] = int16View[i * 2] / 32768.0;
                    rightCh[i] = int16View[i * 2 + 1] / 32768.0;
                }

                const source = _audioCtx.createBufferSource();
                source.buffer = buffer;
                source.connect(_audioCtx.destination);

                const cur = _audioCtx.currentTime;
                if (nextStartTime === 0 || nextStartTime < cur || nextStartTime > cur + 0.35) {
                    nextStartTime = cur + 0.060; // Stable 60ms jitter cushion to absorb all packet variations
                }
                source.start(nextStartTime);
                nextStartTime += buffer.duration;
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.warn('[WebRtcViewer] Audio stream connection ended:', err);
            }
        }
    }

    function stopPcmAudioStream() {
        if (_audioAbortController) {
            _audioAbortController.abort();
            _audioAbortController = null;
        }
    }

    /**
     * Audio Focus Management: Focus audio on single desktop, mute all others.
     */
    function toggleAudioFocus(desktopUuid) {
        const card = document.getElementById(`desktop-card-${desktopUuid}`);
        if (!card) return;

        const isCurrentlyFocused = (_audioFocusedDesktopUuid === desktopUuid);

        // Reset all audio buttons & cards
        document.querySelectorAll('.desktop-stream-card').forEach(c => {
            c.classList.remove('has-audio-focus');
            const btn = c.querySelector('.audio-focus-btn');
            if (btn) {
                btn.classList.remove('active-audio');
                btn.querySelector('.audio-icon').textContent = '🔇';
                btn.querySelector('.audio-label').textContent = 'Tap to Listen';
                const wave = btn.querySelector('.audio-wave-bars');
                if (wave) wave.classList.add('hidden');
            }
            const audioEl = c.querySelector('audio');
            if (audioEl) audioEl.muted = true;
        });

        stopPcmAudioStream();

        if (isCurrentlyFocused) {
            // Mute everything
            _audioFocusedDesktopUuid = null;
            console.log('[WebRtcViewer] Audio focus cleared (All muted)');
        } else {
            // Focus on this desktop
            _audioFocusedDesktopUuid = desktopUuid;
            card.classList.add('has-audio-focus');
            const btn = card.querySelector('.audio-focus-btn');
            if (btn) {
                btn.classList.add('active-audio');
                btn.querySelector('.audio-icon').textContent = '🔊';
                btn.querySelector('.audio-label').textContent = 'Live Audio';
                const wave = btn.querySelector('.audio-wave-bars');
                if (wave) wave.classList.remove('hidden');
            }

            const session = _activeSessions.get(desktopUuid);
            if (session && session.pc && session.pc.connectionState === 'connected' && session.audioEl && session.audioEl.srcObject) {
                session.audioEl.muted = false;
                session.audioEl.play().catch(e => console.warn('Audio play notice:', e));
            } else {
                // High-Speed Zero-Lag Web Audio PCM Stream
                startPcmAudioStream(desktopUuid);
            }
            console.log(`[WebRtcViewer] Audio focused on ${desktopUuid}`);
        }
    }

    /**
     * Realtime Reverse Mic Intercom (Tablet -> Desktop PC Speaker)
     */
    let _activeMicDesktopUuid = null;
    let _micMediaStream = null;
    let _micAudioCtx = null;
    let _micProcessorNode = null;
    let _micSourceNode = null;
    let _micPreviousMutedState = null;
    let _micPendingChunks = [];
    let _micSending = false;

    function _downsampleTo24k(sourceArray, srcRate) {
        if (srcRate === 24000) return sourceArray;
        const ratio = srcRate / 24000;
        const newLength = Math.round(sourceArray.length / ratio);
        const result = new Float32Array(newLength);
        for (let i = 0; i < newLength; i++) {
            const srcPos = i * ratio;
            const i0 = Math.floor(srcPos);
            const i1 = Math.min(i0 + 1, sourceArray.length - 1);
            const frac = srcPos - i0;
            result[i] = sourceArray[i0] * (1.0 - frac) + sourceArray[i1] * frac;
        }
        return result;
    }

    async function _flushMicQueue(desktopUuid) {
        if (_micSending || _micPendingChunks.length === 0) return;
        if (!_activeMicDesktopUuid || _activeMicDesktopUuid !== desktopUuid) {
            _micPendingChunks = [];
            return;
        }

        _micSending = true;

        // Combine queued chunks into a single byte packet
        let totalBytes = 0;
        for (let i = 0; i < _micPendingChunks.length; i++) {
            totalBytes += _micPendingChunks[i].byteLength;
        }

        const merged = new Uint8Array(totalBytes);
        let offset = 0;
        for (let i = 0; i < _micPendingChunks.length; i++) {
            merged.set(new Uint8Array(_micPendingChunks[i]), offset);
            offset += _micPendingChunks[i].byteLength;
        }
        _micPendingChunks = [];

        try {
            const resp = await fetch(`/stream/${desktopUuid}/mic`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/octet-stream' },
                body: merged
            });
            if (!resp.ok) {
                console.warn('[WebRtcViewer] Mic audio transmission returned status:', resp.status);
            }
        } catch (err) {
            console.warn('[WebRtcViewer] Mic audio packet dropped:', err);
        } finally {
            _micSending = false;
            // If new audio arrived during in-flight network transit, flush next batch
            if (_micPendingChunks.length > 0 && _activeMicDesktopUuid === desktopUuid) {
                _flushMicQueue(desktopUuid);
            }
        }
    }

    async function toggleDesktopMic(desktopUuid) {
        if (_activeMicDesktopUuid === desktopUuid) {
            stopDesktopMic();
        } else {
            if (_activeMicDesktopUuid) {
                stopDesktopMic();
            }
            await startDesktopMic(desktopUuid);
        }
    }

    async function startDesktopMic(desktopUuid) {
        const btn = document.getElementById(`mic-btn-${desktopUuid}`);
        const label = btn ? btn.querySelector('.mic-label') : null;
        const icon = btn ? btn.querySelector('.mic-icon') : null;
        const wave = document.getElementById(`mic-wave-${desktopUuid}`);

        if (label) label.textContent = 'Starting Mic...';

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    channelCount: 1
                }
            });

            _micMediaStream = stream;
            _activeMicDesktopUuid = desktopUuid;
            _micPendingChunks = [];
            _micSending = false;

            // Auto-mute incoming desktop speaker audio on tablet while talking to eliminate acoustic feedback
            const incomingAudioEl = document.getElementById(`audio-${desktopUuid}`);
            if (incomingAudioEl) {
                _micPreviousMutedState = incomingAudioEl.muted;
                incomingAudioEl.muted = true;
            }

            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            _micAudioCtx = new AudioContextClass();
            if (_micAudioCtx.state === 'suspended') {
                await _micAudioCtx.resume();
            }

            const nativeSampleRate = _micAudioCtx.sampleRate;
            console.log(`[WebRtcViewer] Tablet Mic active: native hardware sampleRate = ${nativeSampleRate}Hz (resampling to 24000Hz)`);

            _micSourceNode = _micAudioCtx.createMediaStreamSource(stream);

            // Buffer size: 4096 samples (~85ms at 48kHz, ~170ms at 24kHz) for smooth, jitter-free transmission
            const bufferSize = 4096;
            _micProcessorNode = _micAudioCtx.createScriptProcessor(bufferSize, 1, 1);

            _micProcessorNode.onaudioprocess = (e) => {
                if (!_activeMicDesktopUuid || _activeMicDesktopUuid !== desktopUuid) return;

                const inputFloat32 = e.inputBuffer.getChannelData(0);

                // Ensure local tablet speaker stays completely silent
                const outputBuffer = e.outputBuffer;
                for (let ch = 0; ch < outputBuffer.numberOfChannels; ch++) {
                    outputBuffer.getChannelData(ch).fill(0);
                }

                // 1. Resample to 24000Hz PCM
                const samples24k = _downsampleTo24k(inputFloat32, nativeSampleRate);
                const pcm16 = new Int16Array(samples24k.length);

                // 2. Apply warm analog speech boost (2.5x gain with tanh soft limiter for zero distortion)
                for (let i = 0; i < samples24k.length; i++) {
                    const s = Math.tanh(samples24k[i] * 2.5);
                    pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
                }

                // 3. Queue into sequential in-order packet transmitter
                _micPendingChunks.push(pcm16.buffer);
                _flushMicQueue(desktopUuid);
            };

            // Connect through a zero-gain silence sink so script processor runs without playing out tablet speakers
            const silenceGain = _micAudioCtx.createGain();
            silenceGain.gain.value = 0.0;
            _micSourceNode.connect(_micProcessorNode);
            _micProcessorNode.connect(silenceGain);
            silenceGain.connect(_micAudioCtx.destination);

            // Update UI
            if (btn) btn.classList.add('mic-active');
            if (icon) icon.textContent = '🔴';
            if (label) label.textContent = 'Mic: ON (Live)';
            if (wave) wave.classList.remove('hidden');

            console.log(`[WebRtcViewer] 🎙️ Mic Intercom Active -> Transmitting voice to desktop ${desktopUuid}`);

        } catch (err) {
            console.error('[WebRtcViewer] Error acquiring microphone:', err);
            stopDesktopMic();
            alert('Microphone access failed: ' + (err.message || 'Please grant microphone permissions in browser.'));
        }
    }

    function stopDesktopMic() {
        const desktopUuid = _activeMicDesktopUuid;
        _activeMicDesktopUuid = null;
        _micPendingChunks = [];
        _micSending = false;

        if (_micProcessorNode) {
            try { _micProcessorNode.disconnect(); } catch (e) {}
            _micProcessorNode = null;
        }
        if (_micSourceNode) {
            try { _micSourceNode.disconnect(); } catch (e) {}
            _micSourceNode = null;
        }
        if (_micAudioCtx) {
            try { _micAudioCtx.close(); } catch (e) {}
            _micAudioCtx = null;
        }
        if (_micMediaStream) {
            try {
                _micMediaStream.getTracks().forEach(t => t.stop());
            } catch (e) {}
            _micMediaStream = null;
        }

        if (desktopUuid) {
            // Restore incoming audio state
            const incomingAudioEl = document.getElementById(`audio-${desktopUuid}`);
            if (incomingAudioEl && _micPreviousMutedState !== null) {
                incomingAudioEl.muted = _micPreviousMutedState;
                _micPreviousMutedState = null;
            }

            const btn = document.getElementById(`mic-btn-${desktopUuid}`);
            const label = btn ? btn.querySelector('.mic-label') : null;
            const icon = btn ? btn.querySelector('.mic-icon') : null;
            const wave = document.getElementById(`mic-wave-${desktopUuid}`);

            if (btn) btn.classList.remove('mic-active');
            if (icon) icon.textContent = '🎙️';
            if (label) label.textContent = 'Mic: OFF';
            if (wave) wave.classList.add('hidden');
        }

        console.log('[WebRtcViewer] 🎙️ Microphone streaming stopped.');
    }

    /**
     * Fullscreen toggle.
     */
    function toggleFullscreen(card) {
        if (card.classList.contains('fullscreen-active')) {
            card.classList.remove('fullscreen-active');
            const exitBtn = card.querySelector('.exit-fullscreen-btn');
            if (exitBtn) exitBtn.remove();
        } else {
            card.classList.add('fullscreen-active');
            const exitBtn = document.createElement('button');
            exitBtn.className = 'exit-fullscreen-btn';
            exitBtn.textContent = '✕ Exit Fullscreen';
            exitBtn.onclick = (e) => {
                e.stopPropagation();
                toggleFullscreen(card);
            };
            card.appendChild(exitBtn);
        }
    }

    // Instant graceful teardown when user closes tab or navigates away
    function terminateAllSessionsBeacon() {
        stopDesktopMic();
        const cred = window._cachedCredentials;
        if (!cred || !cred.token) return;

        for (const [uuid, session] of _activeSessions) {
            if (session.sessionId) {
                try {
                    fetch('/api/v1/webrtc/session/status', {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${cred.token}`,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            session_id: session.sessionId,
                            status: 'terminated',
                            reason: 'Tablet Tab Closed / Navigated Away'
                        }),
                        keepalive: true
                    });
                } catch (e) {}
            }
        }

        try {
            fetch('/api/v1/device/disconnect', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${cred.token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ reason: 'Tablet closed' }),
                keepalive: true
            });
        } catch (e) {}
    }

    window.addEventListener('beforeunload', terminateAllSessionsBeacon);
    window.addEventListener('pagehide', terminateAllSessionsBeacon);

    return {
        init,
        refreshDesktops,
        handleOfferReceived,
        handleIceCandidateReceived,
        connectToDesktop,
        disconnectDesktop,
        toggleDesktopMic,
        stopDesktopMic,
        isMicActive: () => _activeMicDesktopUuid !== null,
        inspectIceStats,
        getActiveStats: (uuid) => {
            const s = _activeSessions.get(uuid);
            return s ? s.iceStats : null;
        },
        getActiveSessions: () => _activeSessions,
        isForceTurnRelay: () => _forceTurnRelay,
        setForceTurnRelay: (val) => {
            _forceTurnRelay = !!val;
            updateTurnButtonUI();
            _assignedDesktops.forEach(d => {
                disconnectDesktop(d.uuid);
                connectToDesktop(d);
            });
        }
    };
})();
