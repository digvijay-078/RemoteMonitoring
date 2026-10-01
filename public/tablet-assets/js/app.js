/**
 * RemoteMonitor Tablet Client Application Core.
 * Orchestrates IndexedDB credentials, Reverb WebSocket Presence, and WebRTC Remote Desktop Streaming.
 */
(function () {
  'use strict';

  // DOM Elements
  const pairingView = document.getElementById('pairing-view');
  const viewerView = document.getElementById('viewer-view');
  const revokedView = document.getElementById('revoked-view');
  const pairingForm = document.getElementById('pairing-form');
  const codeInput = document.getElementById('pairing-code-input');
  const pairBtn = document.getElementById('pair-submit-btn');
  const errorMessage = document.getElementById('error-message');
  const headerDeviceId = document.getElementById('header-device-id');
  const headerStatusDot = document.getElementById('header-status-dot');
  const headerStatusText = document.getElementById('header-status-text');
  const resetDeviceBtn = document.getElementById('reset-device-btn');
  const refreshDesktopsBtn = document.getElementById('refresh-desktops-btn');
  const revokedRePairBtn = document.getElementById('revoked-re-pair-btn');
  const revokedReasonText = document.getElementById('revoked-reason-text');

  // Screen Wake Lock API (Keep display awake)
  let wakeLock = null;
  async function requestWakeLock() {
    try {
      if ('wakeLock' in navigator) {
        wakeLock = await navigator.wakeLock.request('screen');
      }
    } catch (err) {
      // Wake lock unsupported or denied
    }
  }

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      if (wakeLock === null) {
        requestWakeLock();
      }
      console.log('[TabletApp] Visibility resumed -> Refreshing stream connections...');
      if (window.WebRtcViewer) {
        window.WebRtcViewer.refreshDesktops();
      }
    }
  });

  window.addEventListener('online', () => {
    console.log('[TabletApp] Network online detected -> Restoring Reverb and WebRTC streams...');
    if (window.DeviceRealtime && window.DeviceRealtime.reconnect) {
      window.DeviceRealtime.reconnect();
    }
    if (window.WebRtcViewer) {
      setTimeout(() => window.WebRtcViewer.refreshDesktops(), 1000);
    }
  });

  // Switch View States
  function showPairingView() {
    pairingView.classList.remove('hidden');
    viewerView.classList.add('hidden');
    if (revokedView) revokedView.classList.add('hidden');

    headerDeviceId.textContent = 'UNPAIRED';
    headerStatusDot.className = 'status-dot warning';
    headerStatusText.textContent = 'Awaiting Pairing';

    // Prefill code from URL if passed (e.g. ?code=RM-XXXX)
    const urlParams = new URLSearchParams(window.location.search);
    const prefillCode = urlParams.get('code');
    if (prefillCode) {
      codeInput.value = prefillCode.toUpperCase();
    }
    codeInput.focus();
  }

  function showViewerView(device) {
    pairingView.classList.add('hidden');
    viewerView.classList.remove('hidden');
    if (revokedView) revokedView.classList.add('hidden');

    headerDeviceId.textContent = device.identifier || device.device_identifier || 'TABLET';
    headerStatusDot.className = 'status-dot online';
    headerStatusText.textContent = 'ONLINE';

    // Initialize WebRTC Multi-Desktop Stream Viewer
    if (window.WebRtcViewer) {
      window.WebRtcViewer.init();
    }

    requestWakeLock();
  }

  function showRevokedScreen(reason) {
    if (viewerView) viewerView.classList.add('hidden');
    if (pairingView) pairingView.classList.add('hidden');
    if (revokedView) {
      revokedView.classList.remove('hidden');
      if (reason && revokedReasonText) {
        revokedReasonText.textContent = reason;
      }
    }
    headerStatusDot.className = 'status-dot offline';
    headerStatusText.textContent = 'Access Revoked';
  }

  function showError(msg) {
    errorMessage.textContent = msg;
    errorMessage.style.display = 'block';
    pairBtn.disabled = false;
    pairBtn.textContent = 'PAIR DEVICE';
  }

  function hideError() {
    errorMessage.style.display = 'none';
  }

  // Realtime Connection State Listener
  if (window.DeviceRealtime) {
    window.DeviceRealtime.onStateChange((state, oldState, meta) => {
      switch (state) {
        case 'BOOTING':
          headerStatusDot.className = 'status-dot';
          headerStatusText.textContent = 'Initializing...';
          break;
        case 'PAIRING_REQUIRED':
          showPairingView();
          break;
        case 'CONNECTING':
          headerStatusDot.className = 'status-dot warning';
          headerStatusText.textContent = 'Connecting...';
          break;
        case 'AUTHENTICATING':
          headerStatusDot.className = 'status-dot warning';
          headerStatusText.textContent = 'Authenticating...';
          break;
        case 'ONLINE':
          headerStatusDot.className = 'status-dot online';
          headerStatusText.textContent = 'ONLINE';
          break;
        case 'RECONNECTING':
          headerStatusDot.className = 'status-dot warning';
          headerStatusText.textContent = `Reconnecting (${meta.attempt || 1})...`;
          break;
        case 'OFFLINE':
          headerStatusDot.className = 'status-dot offline';
          headerStatusText.textContent = 'Offline (Retrying)';
          break;
        case 'REVOKED':
          showRevokedScreen(meta.reason);
          break;
      }
    });
  }

  // Handle Pairing Form Submission
  pairingForm.addEventListener('submit', (e) => {
    e.preventDefault();
    hideError();

    const code = codeInput.value.trim();
    if (!code) {
      showError('Please enter the pairing code displayed on the Admin screen.');
      return;
    }

    pairBtn.disabled = true;
    pairBtn.textContent = 'VERIFYING CODE...';

    PairingManager.submitPairing(code)
      .then(async (data) => {
        showViewerView(data.device);
        const token = data.device_token || data.token;
        if (window.DeviceRealtime) {
          window.DeviceRealtime.connect(data.device, token);
        }
      })
      .catch((err) => {
        showError(err.message || 'Pairing failed. Please check the code.');
      });
  });

  // Toolbar Actions
  if (refreshDesktopsBtn) {
    refreshDesktopsBtn.addEventListener('click', () => {
      if (window.WebRtcViewer) {
        window.WebRtcViewer.refreshDesktops();
      }
    });
  }

  // Handle Reset / Unpair
  if (resetDeviceBtn) {
    resetDeviceBtn.addEventListener('click', () => {
      if (confirm('Are you sure you want to unpair this tablet? This will wipe stored credentials.')) {
        if (window.DeviceRealtime) {
          window.DeviceRealtime.disconnect();
        }
        IDBVault.wipeAll().then(() => {
          showPairingView();
        });
      }
    });
  }

  if (revokedRePairBtn) {
    revokedRePairBtn.addEventListener('click', () => {
      if (revokedView) revokedView.classList.add('hidden');
      showPairingView();
    });
  }

  // Export for external trigger
  window.App = {
    showRevokedScreen
  };

  // Application Startup Flow
  IDBVault.isPaired().then(async (paired) => {
    const urlParams = new URLSearchParams(window.location.search);
    const directDevice = window.DIRECT_DEVICE || urlParams.get('device') || urlParams.get('t') || urlParams.get('id');
    const directToken = urlParams.get('token');
    const autoCode = urlParams.get('code');

    // 1. One-Click Direct Link Mode: Fixed URL with direct device ID (e.g. /t/TAB-001 or ?device=TAB-001)
    if (directDevice) {
      try {
        const authRes = await fetch('/api/v1/tablet/direct-auth', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ device: directDevice })
        });
        const authData = await authRes.json();
        if (authData.success && (authData.token || authData.device_token)) {
          const token = authData.token || authData.device_token;
          const dev = authData.device;
          await IDBVault.saveCredential(token);
          await IDBVault.saveDevice(dev);
          showViewerView(dev);
          if (window.DeviceRealtime) {
            window.DeviceRealtime.connect(dev, token);
          }
          return;
        }
      } catch (e) {
        console.warn('[TabletApp] Direct device auto-auth error:', e);
      }
    }

    // 2. Direct Token Mode
    if (!paired && directToken) {
      try {
        const res = await fetch('/api/v1/tablet/desktops', {
          headers: {
            'Authorization': 'Bearer ' + directToken,
            'Accept': 'application/json'
          }
        });
        if (res.ok) {
          const json = await res.json();
          const dev = {
            device_identifier: 'TABLET-PROVISIONED',
            name: 'Android Tablet Viewer'
          };
          await IDBVault.saveCredential(directToken);
          await IDBVault.saveDevice(dev);
          showViewerView(dev);
          if (window.DeviceRealtime) {
            window.DeviceRealtime.connect(dev, directToken);
          }
          return;
        }
      } catch (e) {
        console.warn('[TabletApp] Direct token provisioning error:', e);
      }
    }

    // 3. Saved Credentials in IndexedDB
    if (paired) {
      const device = (await IDBVault.getDevice()) || {};
      const token = await IDBVault.getCredential();

      showViewerView(device);

      if (window.DeviceRealtime) {
        window.DeviceRealtime.connect(device, token);
      }
    } else if (autoCode) {
      showPairingView();
      codeInput.value = autoCode.toUpperCase();
      PairingManager.submitPairing(autoCode)
        .then((data) => {
          showViewerView(data.device);
          const token = data.device_token || data.token;
          if (window.DeviceRealtime) {
            window.DeviceRealtime.connect(data.device, token);
          }
        })
        .catch((err) => {
          showError(err.message || 'Auto-pairing failed. Please verify code.');
        });
    } else {
      showPairingView();
    }
  }).catch((err) => {
    console.error('[TabletApp] Error initializing application:', err);
    showPairingView();
  });

})();
