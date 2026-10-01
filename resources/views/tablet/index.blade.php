<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#020617">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>RemoteMonitor Client — Live Remote Desktop</title>
    <link rel="manifest" href="/tablet-assets/manifest.json">
    <link rel="stylesheet" href="/tablet-assets/css/tablet.css">
</head>
<body>
    <div class="screen-container">
        <!-- Tablet Top Status Header -->
        <header class="tablet-header">
            <div class="brand-badge">
                <span class="brand-dot"></span>
                <span>RemoteMonitor</span>
                <span id="header-device-id" class="device-tag">CHECKING...</span>
            </div>
            <div class="header-center-info">
                <div class="info-pill" title="Tablet Connected At Time">
                    <span>🕒</span>
                    <span id="header-connected-time">--:--:--</span>
                </div>
                <div class="info-pill" title="Live Session Active Duration">
                    <span>⏱️</span>
                    <span id="header-uptime-text">00:00:00</span>
                </div>
                <div class="info-pill" title="Stream Mode">
                    <span class="speed-indicator-dot"></span>
                    <span>Zero-Lag Live</span>
                </div>
            </div>
            <div class="header-actions">
                <div class="status-indicator">
                    <span id="header-status-dot" class="status-dot"></span>
                    <span id="header-status-text">Initializing...</span>
                </div>
            </div>
        </header>

        <!-- View 1: Pairing Interface (Shown when unpaired) -->
        <main id="pairing-view" class="pairing-screen hidden">
            <div class="pairing-card">
                <div class="pairing-title">Pair Tablet Viewer</div>
                <div class="pairing-subtitle">
                    Enter the temporary 6-character code generated on the Admin Control Center to pair this tablet.
                </div>

                <form id="pairing-form" autocomplete="off">
                    <div class="code-input-wrapper">
                        <input type="text" id="pairing-code-input" class="code-input" placeholder="RM-XXXX" maxlength="12" spellcheck="false" autocomplete="off" required>
                    </div>

                    <button type="submit" id="pair-submit-btn" class="pair-btn">PAIR DEVICE</button>
                    <div id="error-message" class="error-message"></div>
                </form>
            </div>
        </main>

        <!-- View 2: Remote Desktops Viewer Grid (Shown when paired) -->
        <main id="viewer-view" class="viewer-main hidden">
            <div class="viewer-toolbar">
                <div class="viewer-heading">
                    <span>Authorized Desktops</span>
                    <span id="desktops-count-badge" class="desktops-count-badge">0 Assigned</span>
                </div>
                <div class="viewer-actions">
                    <button type="button" id="toggle-turn-btn" class="btn-icon" title="Toggle Forced TURN Relay Mode">
                        <span>🛡️</span>
                        <span id="turn-mode-text">ICE: AUTO</span>
                    </button>
                    <button type="button" id="refresh-desktops-btn" class="btn-icon" title="Refresh Desktop Fleet">
                        <span>🔄</span>
                        <span>Refresh</span>
                    </button>
                    <button type="button" id="reset-device-btn" class="btn-icon" style="color: #f87171;" title="Unpair Tablet">
                        <span>Unpair</span>
                    </button>
                </div>
            </div>

            <!-- Multi-Desktop Video Grid -->
            <div id="desktops-grid-container" class="desktop-grid">
                <!-- Dynamically rendered by WebRtcViewer -->
            </div>
        </main>

        <!-- View 3: Access Revoked View (Shown when revoked / disabled) -->
        <main id="revoked-view" class="pairing-screen hidden" style="background: rgba(2, 6, 23, 0.95); position: absolute; inset: 0; z-index: 50;">
            <div class="pairing-card" style="border-color: rgba(244, 63, 94, 0.4); box-shadow: 0 0 40px rgba(244, 63, 94, 0.15);">
                <div style="font-size: 2.75rem; margin-bottom: 12px;">🚫</div>
                <div class="pairing-title" style="color: #f43f5e;">Access Revoked</div>
                <div class="pairing-subtitle" id="revoked-reason-text">
                    This tablet device has been revoked or disabled by an administrator. Permanent credentials have been wiped from this device.
                </div>
                <div style="margin-top: 24px;">
                    <button type="button" id="revoked-re-pair-btn" class="pair-btn" style="background: #334155;">Pair With New Code</button>
                </div>
            </div>
        </main>
    </div>

    <!-- Reverb Environment Configuration -->
    <script>
        (function() {
            var isHttps = window.location.protocol === 'https:';
            var host = window.location.hostname;
            var scheme = isHttps ? 'wss' : 'ws';
            var port = isHttps ? 443 : (window.location.port === '8088' ? 8088 : 8080);

            window.REVERB_CONFIG = {
                appKey: '{{ config("reverb.apps.apps.0.key") ?: env("REVERB_APP_KEY", "nw2zhrpowiazy7xm9esc") }}',
                host: host,
                port: port,
                scheme: scheme
            };

            window.DIRECT_DEVICE = '{{ $directDevice ?? "" }}';
        })();
    </script>

    <!-- Scripts (Pure Vanilla JS WebRTC Client) -->
    <script src="/tablet-assets/js/idb.js?v={{ time() }}"></script>
    <script src="/tablet-assets/js/pairing.js?v={{ time() }}"></script>
    <script src="/tablet-assets/js/webrtc-viewer.js?v={{ time() }}"></script>
    <script src="/tablet-assets/js/ws.js?v={{ time() }}"></script>
    <script src="/tablet-assets/js/app.js?v={{ time() }}"></script>
</body>
</html>
