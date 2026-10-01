/**
 * RemoteMonitor - IndexedDB Vault (Phase 4)
 * Secure, persistent local storage for device credentials, canonical configurations,
 * and offline store-and-forward telemetry queue with bounded overflow and quota protection.
 * Pure Vanilla JavaScript, zero framework dependencies.
 */
const IDBVault = (function () {
  'use strict';

  const DB_NAME = 'RemoteMonitorVault';
  const DB_VERSION = 2;
  const STORE_AUTH = 'auth';
  const STORE_DEVICE = 'device';
  const STORE_MONITORS = 'monitors';
  const STORE_TELEMETRY = 'telemetry_queue';

  const MAX_QUEUE_SIZE = 1000; // Bounded offline queue limit

  let dbInstance = null;

  function getDB() {
    if (dbInstance) {
      return Promise.resolve(dbInstance);
    }

    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains(STORE_AUTH)) {
          db.createObjectStore(STORE_AUTH, { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains(STORE_DEVICE)) {
          db.createObjectStore(STORE_DEVICE, { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains(STORE_MONITORS)) {
          db.createObjectStore(STORE_MONITORS, { keyPath: 'key' });
        }
        if (!db.objectStoreNames.contains(STORE_TELEMETRY)) {
          const tStore = db.createObjectStore(STORE_TELEMETRY, { keyPath: 'event_id' });
          tStore.createIndex('sequence', 'sequence', { unique: false });
          tStore.createIndex('status', 'status', { unique: false });
          tStore.createIndex('captured_at', 'captured_at', { unique: false });
        }
      };

      request.onsuccess = (event) => {
        dbInstance = event.target.result;
        resolve(dbInstance);
      };

      request.onerror = (event) => {
        console.error('[IDBVault] Database open error:', event.target.error);
        reject(event.target.error);
      };
    });
  }

  function put(storeName, data) {
    return getDB().then((db) => {
      return new Promise((resolve, reject) => {
        try {
          const tx = db.transaction(storeName, 'readwrite');
          const store = tx.objectStore(storeName);
          const req = store.put(data);

          req.onsuccess = () => resolve(true);
          req.onerror = (e) => reject(e.target.error);
        } catch (err) {
          reject(err);
        }
      });
    });
  }

  function get(storeName, key) {
    return getDB().then((db) => {
      return new Promise((resolve, reject) => {
        try {
          const tx = db.transaction(storeName, 'readonly');
          const store = tx.objectStore(storeName);
          const req = store.get(key);

          req.onsuccess = () => {
            resolve(req.result ? req.result.value : null);
          };
          req.onerror = (e) => reject(e.target.error);
        } catch (err) {
          reject(err);
        }
      });
    });
  }

  function clearStore(storeName) {
    return getDB().then((db) => {
      return new Promise((resolve, reject) => {
        try {
          const tx = db.transaction(storeName, 'readwrite');
          const store = tx.objectStore(storeName);
          const req = store.clear();

          req.onsuccess = () => resolve(true);
          req.onerror = (e) => reject(e.target.error);
        } catch (err) {
          reject(err);
        }
      });
    });
  }

  return {
    requestPersistentStorage: function () {
      if (navigator.storage && navigator.storage.persist) {
        return navigator.storage.persist().then((isPersisted) => {
          return isPersisted;
        }).catch(() => false);
      }
      return Promise.resolve(false);
    },

    saveCredential: function (token) {
      return put(STORE_AUTH, {
        key: 'device_token',
        value: token,
        updatedAt: Date.now()
      });
    },

    getCredential: function () {
      return get(STORE_AUTH, 'device_token');
    },

    isPaired: function () {
      return this.getCredential().then((token) => !!token);
    },

    saveDevice: function (profile) {
      return put(STORE_DEVICE, {
        key: 'profile',
        value: profile,
        updatedAt: Date.now()
      });
    },

    getDevice: function () {
      return get(STORE_DEVICE, 'profile');
    },

    /**
     * Atomically save the canonical configuration and version.
     */
    saveCanonicalConfig: function (config) {
      return getDB().then((db) => {
        return new Promise((resolve, reject) => {
          try {
            const tx = db.transaction([STORE_DEVICE, STORE_MONITORS], 'readwrite');
            const devStore = tx.objectStore(STORE_DEVICE);
            const monStore = tx.objectStore(STORE_MONITORS);

            const version = (config && config.config_version) ? Number(config.config_version) : 1;

            devStore.put({ key: 'canonical_config', value: config, updatedAt: Date.now() });
            devStore.put({ key: 'config_version', value: version, updatedAt: Date.now() });

            if (config && Array.isArray(config.monitors)) {
              monStore.put({ key: 'slots', value: config.monitors, updatedAt: Date.now() });
            }

            tx.oncomplete = () => resolve(true);
            tx.onerror = (e) => reject(e.target.error);
          } catch (err) {
            reject(err);
          }
        });
      });
    },

    getCanonicalConfig: function () {
      return get(STORE_DEVICE, 'canonical_config');
    },

    getConfigVersion: function () {
      return get(STORE_DEVICE, 'config_version').then((v) => (v !== null && v !== undefined) ? Number(v) : 0);
    },

    saveMonitors: function (monitors) {
      return put(STORE_MONITORS, {
        key: 'slots',
        value: monitors,
        updatedAt: Date.now()
      });
    },

    getMonitors: function () {
      return get(STORE_MONITORS, 'slots').then((res) => res || []);
    },

    // --- PHASE 4: Telemetry Monotonic Sequence Generator ---

    /**
     * Atomically retrieve and increment the persistent monotonic sequence.
     * Survives page reloads, PWA restarts, and network losses.
     */
    getNextSequence: function () {
      return getDB().then((db) => {
        return new Promise((resolve, reject) => {
          try {
            const tx = db.transaction(STORE_DEVICE, 'readwrite');
            const store = tx.objectStore(STORE_DEVICE);
            const req = store.get('telemetry_sequence');

            req.onsuccess = () => {
              let seq = (req.result && req.result.value) ? Number(req.result.value) : 0;
              seq += 1;
              store.put({ key: 'telemetry_sequence', value: seq, updatedAt: Date.now() });
              resolve(seq);
            };
            req.onerror = (e) => reject(e.target.error);
          } catch (err) {
            reject(err);
          }
        });
      });
    },

    getSequence: function () {
      return get(STORE_DEVICE, 'telemetry_sequence').then((v) => (v ? Number(v) : 0));
    },

    // --- PHASE 4: Bounded Telemetry Queue (Offline Store-and-Forward) ---

    /**
     * Enqueue a telemetry event into IndexedDB.
     * Enforces bounded queue limit; drops oldest events on overflow while preserving newest data.
     */
    enqueueTelemetry: function (event) {
      return getDB().then((db) => {
        return new Promise((resolve, reject) => {
          try {
            const tx = db.transaction([STORE_TELEMETRY, STORE_DEVICE], 'readwrite');
            const tStore = tx.objectStore(STORE_TELEMETRY);
            const devStore = tx.objectStore(STORE_DEVICE);

            // Check current queue depth
            const countReq = tStore.count();
            countReq.onsuccess = () => {
              const currentCount = countReq.result || 0;
              
              if (currentCount >= MAX_QUEUE_SIZE) {
                // Drop oldest events first
                const index = tStore.index('sequence');
                const cursorReq = index.openCursor();
                let droppedThisTime = 0;
                cursorReq.onsuccess = (e) => {
                  const cursor = e.target.result;
                  if (cursor && droppedThisTime < (currentCount - MAX_QUEUE_SIZE + 10)) {
                    cursor.delete();
                    droppedThisTime++;
                    cursor.continue();
                  }
                };

                // Track dropped count in device store
                const dropReq = devStore.get('telemetry_dropped_count');
                dropReq.onsuccess = () => {
                  const currentDropped = (dropReq.result && dropReq.result.value) ? Number(dropReq.result.value) : 0;
                  devStore.put({ key: 'telemetry_dropped_count', value: currentDropped + droppedThisTime + 1, updatedAt: Date.now() });
                };
              }

              // Save new event
              const record = Object.assign({}, event, {
                status: 'pending',
                enqueued_at: Date.now()
              });
              tStore.put(record);
            };

            tx.oncomplete = () => resolve(true);
            tx.onerror = (e) => {
              console.warn('[IDBVault] Quota or transaction error queueing telemetry:', e.target.error);
              resolve(false); // Graceful recovery: do not throw or crash runtime
            };
          } catch (err) {
            console.warn('[IDBVault] Failed to enqueue telemetry:', err);
            resolve(false);
          }
        });
      });
    },

    /**
     * Peek up to `limit` oldest pending events for batch upload.
     */
    peekTelemetryBatch: function (limit = 100) {
      return getDB().then((db) => {
        return new Promise((resolve, reject) => {
          try {
            const tx = db.transaction(STORE_TELEMETRY, 'readonly');
            const store = tx.objectStore(STORE_TELEMETRY);
            const index = store.index('sequence');
            const req = index.openCursor();
            const batch = [];

            req.onsuccess = (e) => {
              const cursor = e.target.result;
              if (cursor && batch.length < limit) {
                batch.push(cursor.value);
                cursor.continue();
              } else {
                resolve(batch);
              }
            };
            req.onerror = (e) => reject(e.target.error);
          } catch (err) {
            reject(err);
          }
        });
      });
    },

    /**
     * Remove acknowledged event IDs from the local queue.
     */
    removeAcknowledgedTelemetry: function (eventIds) {
      if (!Array.isArray(eventIds) || eventIds.length === 0) {
        return Promise.resolve(0);
      }

      return getDB().then((db) => {
        return new Promise((resolve, reject) => {
          try {
            const tx = db.transaction(STORE_TELEMETRY, 'readwrite');
            const store = tx.objectStore(STORE_TELEMETRY);
            let removedCount = 0;

            eventIds.forEach((id) => {
              store.delete(id);
              removedCount++;
            });

            tx.oncomplete = () => resolve(removedCount);
            tx.onerror = (e) => reject(e.target.error);
          } catch (err) {
            reject(err);
          }
        });
      });
    },

    /**
     * Retrieve queue diagnostics.
     */
    getQueueDiagnostics: function () {
      return getDB().then((db) => {
        return new Promise((resolve) => {
          try {
            const tx = db.transaction([STORE_TELEMETRY, STORE_DEVICE], 'readonly');
            const tStore = tx.objectStore(STORE_TELEMETRY);
            const devStore = tx.objectStore(STORE_DEVICE);

            let depth = 0;
            let dropped = 0;
            let seq = 0;

            const countReq = tStore.count();
            countReq.onsuccess = () => { depth = countReq.result || 0; };

            const dropReq = devStore.get('telemetry_dropped_count');
            dropReq.onsuccess = () => { dropped = (dropReq.result && dropReq.result.value) || 0; };

            const seqReq = devStore.get('telemetry_sequence');
            seqReq.onsuccess = () => { seq = (seqReq.result && seqReq.result.value) || 0; };

            tx.oncomplete = () => {
              resolve({
                queue_depth: depth,
                dropped_events_count: dropped,
                latest_sequence: seq,
                max_queue_size: MAX_QUEUE_SIZE
              });
            };
            tx.onerror = () => resolve({ queue_depth: 0, dropped_events_count: 0, latest_sequence: 0 });
          } catch (err) {
            resolve({ queue_depth: 0, dropped_events_count: 0, latest_sequence: 0 });
          }
        });
      });
    },

    clearTelemetryQueue: function () {
      return clearStore(STORE_TELEMETRY);
    },

    wipeAll: function () {
      return Promise.all([
        clearStore(STORE_AUTH),
        clearStore(STORE_DEVICE),
        clearStore(STORE_MONITORS),
        clearStore(STORE_TELEMETRY)
      ]);
    }
  };
})();


window.IDBVault = IDBVault;
window.IDBStorage = {
  getCredentials: async function () {
    const token = await IDBVault.getCredential();
    const device = await IDBVault.getDevice();
    return { token, device };
  },
  saveCredentials: async function (device, token) {
    await IDBVault.saveCredential(token);
    await IDBVault.saveDevice(device);
  },
  clear: function () {
    return IDBVault.wipeAll();
  }
};



