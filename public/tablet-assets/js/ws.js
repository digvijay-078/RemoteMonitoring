/**
 * RemoteMonitor Tablet Realtime Client
 * Pure Vanilla JavaScript WebSocket Client (Pusher Protocol v7 / Laravel Reverb)
 * Zero external libraries, zero framework dependencies.
 */
(function (window) {
  'use strict';

  // 1. Connection State Enum
  const ConnectionState = Object.freeze({
    BOOTING: 'BOOTING',
    PAIRING_REQUIRED: 'PAIRING_REQUIRED',
    CONNECTING: 'CONNECTING',
    AUTHENTICATING: 'AUTHENTICATING',
    ONLINE: 'ONLINE',
    RECONNECTING: 'RECONNECTING',
    OFFLINE: 'OFFLINE',
    REVOKED: 'REVOKED'
  });

  // State Manager
  let currentState = ConnectionState.BOOTING;
  const stateListeners = [];

  function getState() {
    return currentState;
  }

  function onStateChange(listener) {
    stateListeners.push(listener);
  }

  function setState(newState, meta = {}) {
    // If revoked, cannot transition unless explicitly re-paired / reset
    if (currentState === ConnectionState.REVOKED && newState !== ConnectionState.PAIRING_REQUIRED) {
      return;
    }

    const oldState = currentState;
    currentState = newState;
    console.log(`[DeviceWS] State: ${oldState} -> ${newState}`, meta);

    stateListeners.forEach((fn) => {
      try {
        fn(newState, oldState, meta);
      } catch (err) {
        console.error('[DeviceWS] Error in state listener:', err);
      }
    });
  }

  // 2. Realtime WebSocket & Heartbeat Manager
  let socket = null;
  let socketId = null;
  let deviceRecord = null;
  let deviceToken = null;
  let heartbeatTimer = null;
  let reconnectTimer = null;
  let transportKeepAliveTimer = null;
  let reconnectAttempts = 0;
  let isExplicitlyClosed = false;

  const HEARTBEAT_INTERVAL_MS = 4000; // 4 seconds (fast real-time sweep)
  const MAX_RECONNECT_DELAY_MS = 30000; // 30 seconds max backoff

  /**
   * Start the realtime connection workflow for an authenticated device.
   */
  async function connect(device, token) {
    deviceRecord = device;
    deviceToken = token;
    isExplicitlyClosed = false;

    if (!device || !token) {
      setState(ConnectionState.PAIRING_REQUIRED);
      return;
    }

    if (!navigator.onLine) {
      setState(ConnectionState.OFFLINE);
      return;
    }

    initiateWebSocket();
  }

  /**
   * Open WebSocket connection to Laravel Reverb server.
   */
  function initiateWebSocket() {
    if (currentState === ConnectionState.REVOKED) {
      return;
    }

    if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) {
      return;
    }

    setState(ConnectionState.CONNECTING);

    const config = window.REVERB_CONFIG || {};
    const appKey = config.appKey || 'nw2zhrpowiazy7xm9esc';
    const host = config.host || window.location.hostname || 'localhost';
    const scheme = config.scheme || (window.location.protocol === 'https:' ? 'wss' : 'ws');
    const port = config.port;

    const isStandard = (!port || (port === 443 && scheme === 'wss') || (port === 80 && scheme === 'ws'));
    const portPart = isStandard ? '' : `:${port}`;

    const wsUrl = `${scheme}://${host}${portPart}/app/${appKey}?protocol=7&client=js&version=8.4.0-reverb`;
    console.log(`[DeviceWS] Connecting to ${scheme}://${host}${portPart}...`);

    try {
      socket = new WebSocket(wsUrl);
    } catch (err) {
      console.warn('[DeviceWS] WebSocket instantiation error:', err);
      scheduleReconnect();
      return;
    }

    socket.onopen = function () {
      console.log('[DeviceWS] Transport connection opened.');
      startTransportKeepAlive();
    };

    socket.onmessage = function (event) {
      try {
        const message = JSON.parse(event.data);
        handleIncomingMessage(message);
      } catch (err) {
        console.warn('[DeviceWS] Failed to parse message:', event.data);
      }
    };

    socket.onerror = function (err) {
      console.warn('[DeviceWS] Transport error encountered.');
    };

    socket.onclose = function (event) {
      console.log(`[DeviceWS] Connection closed (code: ${event.code}).`);
      stopTransportKeepAlive();
      stopApplicationHeartbeat();

      if (!isExplicitlyClosed && currentState !== ConnectionState.REVOKED) {
        scheduleReconnect();
      }
    };
  }

  /**
   * Handle incoming Pusher v7 / Reverb protocol frames.
   */
  async function handleIncomingMessage(msg) {
    const event = (msg.event || '').replace(/^\./, '');
    const data = typeof msg.data === 'string' ? safeJsonParse(msg.data) : (msg.data || {});

    switch (event) {
      case 'pusher:connection_established':
        socketId = data.socket_id;
        console.log('[DeviceWS] Handshake established. Socket ID:', socketId);
        setState(ConnectionState.AUTHENTICATING);
        await authorizeAndSubscribe();
        break;

      case 'pusher:ping':
        // Transport ping/pong keepalive handled in WebSocket layer without PHP/MySQL requests
        if (socket && socket.readyState === WebSocket.OPEN) {
          socket.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
        }
        break;

      case 'pusher:pong':
        // Transport pong acknowledged
        break;

      case 'pusher_internal:subscription_succeeded':
        console.log(`[DeviceWS] Subscribed successfully to channel: ${msg.channel}`);
        reconnectAttempts = 0;
        setState(ConnectionState.ONLINE);
        startApplicationHeartbeat();
        window.dispatchEvent(new CustomEvent('device-ws-subscribed', { detail: { channel: msg.channel } }));
        break;

      case 'pusher:error':
        console.error('[DeviceWS] Protocol error from Reverb:', data);
        if (data.code === 4004 || data.code === 4001) {
          handleRevocation('Reverb authentication error: ' + (data.message || 'Access Denied'));
        }
        break;

      case 'webrtc.signal.offer':
      case 'WebRtcOfferReceived':
      case 'App\\Events\\WebRtcOfferReceived':
        console.log('[DeviceWS] Received WebRTC SDP Offer:', data);
        if (window.WebRtcViewer && window.WebRtcViewer.handleOfferReceived) {
          window.WebRtcViewer.handleOfferReceived(data);
        }
        break;

      case 'webrtc.signal.ice_candidate':
      case 'WebRtcIceCandidateReceived':
      case 'App\\Events\\WebRtcIceCandidateReceived':
        if (window.WebRtcViewer && window.WebRtcViewer.handleIceCandidateReceived) {
          window.WebRtcViewer.handleIceCandidateReceived(data);
        }
        break;

      case 'webrtc.session.status':
      case 'WebRtcSessionStatusChanged':
      case 'App\\Events\\WebRtcSessionStatusChanged':
        console.log('[DeviceWS] Received WebRTC session status change:', data);
        break;

      case 'DeviceRevoked':
      case 'App\\Events\\DeviceRevoked':
        console.warn('[DeviceWS] Received DeviceRevoked event from server:', data);
        handleRevocation(data.reason || 'Revoked by administrator');
        break;

      case 'mapping.changed':
      case 'TabletMappingChanged':
      case 'App\\Events\\TabletMappingChanged':
        console.log('[DeviceWS] Received TabletMappingChanged event from server:', data);
        if (window.WebRtcViewer && window.WebRtcViewer.refreshDesktops) {
          window.WebRtcViewer.refreshDesktops();
        }
        break;

      default:
        // Unknown events safely ignored without error
        break;
    }
  }

  /**
   * Request private channel authorization from Laravel backend.
   */
  async function authorizeAndSubscribe() {
    if (!socketId || !deviceRecord || !deviceToken) {
      return;
    }

    const channelName = `private-device.${deviceRecord.uuid}`;

    try {
      const response = await fetch('/api/v1/device/broadcasting/auth', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${deviceToken}`
        },
        body: JSON.stringify({
          socket_id: socketId,
          channel_name: channelName
        })
      });

      if (response.status === 401 || response.status === 403) {
        const errorData = await response.json().catch(() => ({}));
        handleRevocation(errorData.error || errorData.message || 'Authorization rejected by server');
        return;
      }

      if (!response.ok) {
        throw new Error(`Channel authorization failed with status ${response.status}`);
      }

      const authPayload = await response.json();

      // Send Pusher subscription frame
      if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({
          event: 'pusher:subscribe',
          data: {
            channel: channelName,
            auth: authPayload.auth
          }
        }));
      }
    } catch (err) {
      console.warn('[DeviceWS] Channel authorization request error:', err);
      scheduleReconnect();
    }
  }

  /**
   * Transport Keep-Alive: Sends ping frame every 30s to prevent NAT/proxy timeouts.
   */
  function startTransportKeepAlive() {
    stopTransportKeepAlive();
    transportKeepAliveTimer = setInterval(() => {
      if (socket && socket.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({ event: 'pusher:ping', data: {} }));
      }
    }, 30000);
  }

  function stopTransportKeepAlive() {
    if (transportKeepAliveTimer) {
      clearInterval(transportKeepAliveTimer);
      transportKeepAliveTimer = null;
    }
  }

  /**
   * Application-Level Heartbeat: Sends telemetry to /api/v1/device/heartbeat every 60s.
   */
  function startApplicationHeartbeat() {
    stopApplicationHeartbeat();
    // Send immediate initial heartbeat
    sendHeartbeat();
    heartbeatTimer = setInterval(sendHeartbeat, HEARTBEAT_INTERVAL_MS);
  }

  function stopApplicationHeartbeat() {
    if (heartbeatTimer) {
      clearInterval(heartbeatTimer);
      heartbeatTimer = null;
    }
  }

  async function sendHeartbeat() {
    if (currentState !== ConnectionState.ONLINE && currentState !== ConnectionState.AUTHENTICATING) {
      return;
    }

    if (!deviceRecord || !deviceToken) {
      return;
    }

    const telemetry = await gatherDeviceTelemetry();

    try {
      const response = await fetch('/api/v1/device/heartbeat', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${deviceToken}`
        },
        body: JSON.stringify({
          action: 'HEARTBEAT',
          device_id: deviceRecord.identifier,
          timestamp: Math.floor(Date.now() / 1000),
          telemetry: telemetry
        })
      });

      if (response.status === 401 || response.status === 403) {
        handleRevocation('Heartbeat rejected: Device credentials revoked or disabled');
        return;
      }

      const resData = await response.json();
      if (resData.status === 'disabled') {
        handleRevocation('Device disabled by administrator');
      }
    } catch (err) {
      console.warn('[DeviceWS] Application heartbeat failed:', err.message);
    }
  }

  /**
   * Collect non-invasive device telemetry (battery level, charging status, network type).
   */
  async function gatherDeviceTelemetry() {
    const telemetry = {
      network: navigator.onLine ? 'online' : 'offline',
      userAgent: navigator.userAgent
    };

    try {
      if ('getBattery' in navigator) {
        const battery = await navigator.getBattery();
        telemetry.battery = Math.round(battery.level * 100);
        telemetry.isCharging = battery.charging;
      }
    } catch (e) {
      // Battery API unavailable
    }

    if (navigator.connection) {
      telemetry.effectiveType = navigator.connection.effectiveType || 'unknown';
    }

    return telemetry;
  }

  /**
   * Automatic reconnect with Exponential Backoff and Randomized Jitter.
   * Delays: 1s, 2s, 4s, 8s, 16s, up to 30s max + [0-1000ms] jitter.
   */
  function scheduleReconnect() {
    if (reconnectTimer || currentState === ConnectionState.REVOKED) {
      return;
    }

    if (!navigator.onLine) {
      setState(ConnectionState.OFFLINE);
      return;
    }

    reconnectAttempts++;
    setState(ConnectionState.RECONNECTING, { attempt: reconnectAttempts });

    // Backoff formula: min(30s, 2^(attempt - 1) * 1000ms) + random jitter
    const baseDelay = Math.min(MAX_RECONNECT_DELAY_MS, Math.pow(2, reconnectAttempts - 1) * 1000);
    const jitter = Math.floor(Math.random() * 1000);
    const delay = baseDelay + jitter;

    console.log(`[DeviceWS] Reconnecting in ${(delay / 1000).toFixed(2)}s (attempt ${reconnectAttempts})...`);

    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      if (currentState !== ConnectionState.REVOKED && !isExplicitlyClosed) {
        initiateWebSocket();
      }
    }, delay);
  }

  /**
   * Handle server-side device revocation or admin disable.
   */
  async function handleRevocation(reason) {
    console.warn('[DeviceWS] PERMANENT REVOCATION:', reason);
    isExplicitlyClosed = true;

    stopTransportKeepAlive();
    stopApplicationHeartbeat();

    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }

    if (socket) {
      try {
        socket.close();
      } catch (e) {}
      socket = null;
    }

    // Wipe local credentials from IndexedDB to prevent reconnection
    try {
      if (window.IDBVault) {
        await window.IDBVault.wipeAll();
      }
    } catch (e) {
      console.error('[DeviceWS] Error wiping credentials on revocation:', e);
    }

    setState(ConnectionState.REVOKED, { reason });
  }

  /**
   * Send CONFIG_ACK back to server to acknowledge applied configuration.
   */
  async function sendConfigAck(configVersion, status = 'applied') {
    if (!deviceToken) return;

    try {
      const response = await fetch('/api/v1/device/config-ack', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${deviceToken}`
        },
        body: JSON.stringify({
          config_version: Number(configVersion),
          status: status,
          device_uuid: deviceRecord ? deviceRecord.uuid : undefined,
          received_at: Math.floor(Date.now() / 1000)
        })
      });

      if (response.ok) {
        console.log(`[DeviceWS] CONFIG_ACK recorded successfully for v${configVersion}`);
      }
    } catch (err) {
      console.warn('[DeviceWS] Failed to send CONFIG_ACK:', err);
    }
  }

  /**
   * Send SYNC_REQUEST to /api/v1/device/sync upon reconnect or initialization.
   */
  async function requestSync(localVersion) {
    if (!deviceToken) return null;

    try {
      console.log(`[DeviceWS] Requesting configuration sync (local version: v${localVersion})...`);
      const response = await fetch('/api/v1/device/sync', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${deviceToken}`
        },
        body: JSON.stringify({
          action: 'SYNC_REQUEST',
          device_uuid: deviceRecord ? deviceRecord.uuid : undefined,
          config_version: Number(localVersion) || 0
        })
      });

      if (!response.ok) {
        throw new Error(`Sync request failed with HTTP ${response.status}`);
      }

      const data = await response.json();
      console.log('[DeviceWS] Sync response received:', data);
      return data;
    } catch (err) {
      console.warn('[DeviceWS] Sync request error:', err);
      return null;
    }
  }

  /**
   * Disconnect client manually (e.g. on unpair/reset).
   */
  function disconnect() {
    isExplicitlyClosed = true;
    sendDisconnectSignal('manual_disconnect');
    stopTransportKeepAlive();
    stopApplicationHeartbeat();

    if (reconnectTimer) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }

    if (socket) {
      try {
        socket.close();
      } catch (e) {}
      socket = null;
    }
  }

  /**
   * Send instant beacon / fetch to notify backend of client disconnect / unload.
   */
  function sendDisconnectSignal(reason = 'page_unload') {
    if (!deviceToken) return;
    try {
      const payload = JSON.stringify({
        token: deviceToken,
        reason: reason,
        timestamp: Math.floor(Date.now() / 1000)
      });
      const url = '/api/v1/device/disconnect';

      if (navigator.sendBeacon) {
        const blob = new Blob([payload], { type: 'application/json' });
        navigator.sendBeacon(url, blob);
      } else {
        fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${deviceToken}`
          },
          body: payload,
          keepalive: true
        }).catch(() => {});
      }
    } catch (e) {
      // Ignored during page teardown
    }
  }

  // Network offline / online events and page unload detection
  window.addEventListener('beforeunload', () => {
    sendDisconnectSignal('tab_closed');
  });

  window.addEventListener('pagehide', () => {
    sendDisconnectSignal('page_hidden');
  });

  window.addEventListener('offline', () => {
    console.log('[DeviceWS] Network offline detected.');
    setState(ConnectionState.OFFLINE);
    sendDisconnectSignal('network_offline');
  });

  window.addEventListener('online', () => {
    console.log('[DeviceWS] Network online restored.');
    if (currentState !== ConnectionState.REVOKED && !isExplicitlyClosed) {
      reconnectAttempts = 0;
      if (reconnectTimer) {
        clearTimeout(reconnectTimer);
        reconnectTimer = null;
      }
      initiateWebSocket();
    }
  });

  function safeJsonParse(str) {
    try {
      return JSON.parse(str);
    } catch (e) {
      return {};
    }
  }

  // Export API
  window.DeviceRealtime = {
    ConnectionState,
    getState,
    onStateChange,
    connect,
    disconnect,
    sendHeartbeat,
    sendConfigAck,
    requestSync,
    handleRevocation
  };

})(window);
