/**
 * RemoteMonitor - Tablet Pairing Controller
 * Handles pairing code input, hardware fingerprinting, and token handshake API.
 */
const PairingManager = (function () {
  'use strict';

  function collectHardwareProfile() {
    return {
      screenWidth: window.screen ? window.screen.width : window.innerWidth,
      screenHeight: window.screen ? window.screen.height : window.innerHeight,
      pixelRatio: window.devicePixelRatio || 1,
      platform: navigator.platform || 'Unknown',
      online: navigator.onLine,
      clientTime: new Date().toISOString()
    };
  }

  function submitPairing(pairingCode) {
    const cleanCode = (pairingCode || '').trim().toUpperCase();

    if (!cleanCode || cleanCode.length < 4) {
      return Promise.reject(new Error('Please enter a valid pairing code.'));
    }

    const payload = {
      pairing_code: cleanCode,
      hardware_info: collectHardwareProfile()
    };

    return fetch('/api/v1/device/pair', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload)
    })
      .then((res) => {
        return res.json().then((json) => {
          if (!res.ok) {
            throw new Error(json.message || 'Pairing request failed.');
          }
          return json;
        });
      })
      .then((response) => {
        if (!response.success || !response.data) {
          throw new Error(response.message || 'Invalid server response.');
        }

        const data = response.data;

        // Persist securely to IndexedDB
        return Promise.all([
          IDBVault.saveCredential(data.device_token),
          IDBVault.saveDevice(data.device)
        ]).then(() => {
          IDBVault.requestPersistentStorage();
          return data;
        });
      });
  }

  return {
    submitPairing: submitPairing
  };
})();
