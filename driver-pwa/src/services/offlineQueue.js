const DB_NAME = 'distroflow-sync-queue';
const STORE_NAME = 'sync-queue';
const DB_VERSION = 1;

let dbInstance = null;

function openDB() {
  return new Promise((resolve, reject) => {
    if (dbInstance) return resolve(dbInstance);
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
        store.createIndex('type', 'type', { unique: false });
        store.createIndex('status', 'status', { unique: false });
        store.createIndex('createdAt', 'createdAt', { unique: false });
      }
    };
    request.onsuccess = (event) => {
      dbInstance = event.target.result;
      resolve(dbInstance);
    };
    request.onerror = (event) => reject(event.target.error);
  });
}

export async function enqueueAction(action) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    store.add({
      type: action.type,
      endpoint: action.endpoint,
      payload: action.payload,
      createdAt: new Date().toISOString(),
      status: 'pending',
      retryCount: 0,
    });
    tx.oncomplete = () => resolve();
    tx.onerror = (event) => reject(event.target.error);
  });
}

export async function getPendingActions() {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readonly');
    const store = tx.objectStore(STORE_NAME);
    const index = store.index('status');
    const request = index.getAll('pending');
    request.onsuccess = () => resolve(request.result);
    request.onerror = (event) => reject(event.target.error);
  });
}

export async function markSynced(id) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    store.delete(id);
    tx.oncomplete = () => resolve();
    tx.onerror = (event) => reject(event.target.error);
  });
}

export async function markFailed(id) {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    const request = store.get(id);
    request.onsuccess = () => {
      const action = request.result;
      action.status = 'failed';
      action.retryCount = (action.retryCount || 0) + 1;
      store.put(action);
    };
    tx.oncomplete = () => resolve();
    tx.onerror = (event) => reject(event.target.error);
  });
}

export async function getQueueStats() {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readonly');
    const store = tx.objectStore(STORE_NAME);
    const request = store.getAll();
    request.onsuccess = () => {
      const items = request.result;
      resolve({
        total: items.length,
        pending: items.filter((i) => i.status === 'pending').length,
        failed: items.filter((i) => i.status === 'failed').length,
      });
    };
    request.onerror = (event) => reject(event.target.error);
  });
}

export async function clearQueue() {
  const db = await openDB();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);
    store.clear();
    tx.oncomplete = () => resolve();
    tx.onerror = (event) => reject(event.target.error);
  });
}

export async function syncAll(token) {
  const actions = await getPendingActions();
  let synced = 0;
  let failed = 0;

  for (const action of actions) {
    try {
      const response = await fetch(action.endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify(action.payload),
      });

      if (response.ok) {
        await markSynced(action.id);
        synced++;
      } else if (response.status >= 500) {
        const delayMs = Math.min(1000 * Math.pow(2, action.retryCount), 30000);
        await new Promise((r) => setTimeout(r, delayMs));
        await markFailed(action.id);
        failed++;
      } else {
        await markFailed(action.id);
        failed++;
      }
    } catch (err) {
      if (action.retryCount < 3) {
        await markFailed(action.id);
        failed++;
      }
    }
  }

  return { synced, failed, total: actions.length };
}

let syncListener = null;

export function startAutoSync(token, intervalMs = 30000) {
  stopAutoSync();
  syncListener = setInterval(async () => {
    if (navigator.onLine) {
      await syncAll(token);
    }
  }, intervalMs);

  window.addEventListener('online', async () => {
    await syncAll(token);
  });
}

export function stopAutoSync() {
  if (syncListener) {
    clearInterval(syncListener);
    syncListener = null;
  }
}
