import { openDB } from 'idb';

const DB_NAME = 'distroflow-driver';
const DB_VERSION = 2;

let dbPromise;

function getDb() {
  if (!dbPromise) {
    dbPromise = openDB(DB_NAME, DB_VERSION, {
      upgrade(db) {
        if (!db.objectStoreNames.contains('pendingDeliveries')) {
          const delStore = db.createObjectStore('pendingDeliveries', {
            keyPath: 'id',
            autoIncrement: true,
          });
          delStore.createIndex('stopId', 'stopId');
          delStore.createIndex('synced', 'synced');
        }

        if (!db.objectStoreNames.contains('pendingPayments')) {
          const payStore = db.createObjectStore('pendingPayments', {
            keyPath: 'id',
            autoIncrement: true,
          });
          payStore.createIndex('stopId', 'stopId');
          payStore.createIndex('synced', 'synced');
        }

        if (!db.objectStoreNames.contains('pendingSignatures')) {
          const sigStore = db.createObjectStore('pendingSignatures', {
            keyPath: 'id',
            autoIncrement: true,
          });
          sigStore.createIndex('stopId', 'stopId');
          sigStore.createIndex('synced', 'synced');
        }

        if (!db.objectStoreNames.contains('pendingPhotos')) {
          const photoStore = db.createObjectStore('pendingPhotos', {
            keyPath: 'id',
            autoIncrement: true,
          });
          photoStore.createIndex('stopId', 'stopId');
          photoStore.createIndex('synced', 'synced');
        }

        if (!db.objectStoreNames.contains('cachedData')) {
          const cacheStore = db.createObjectStore('cachedData', {
            keyPath: 'key',
          });
          cacheStore.createIndex('timestamp', 'timestamp');
        }

        if (!db.objectStoreNames.contains('auth')) {
          db.createObjectStore('auth', { keyPath: 'key' });
        }
      },
    });
  }
  return dbPromise;
}

// --- Pending Deliveries ---
export async function savePendingDelivery(delivery) {
  const db = await getDb();
  return db.add('pendingDeliveries', {
    ...delivery,
    synced: false,
    createdAt: new Date().toISOString(),
  });
}

export async function getPendingDeliveries() {
  const db = await getDb();
  return db.getAllFromIndex('pendingDeliveries', 'synced', false);
}

export async function getPendingDeliveriesByStop(stopId) {
  const db = await getDb();
  return db.getAllFromIndex('pendingDeliveries', 'stopId', stopId);
}

export async function markDeliverySynced(id) {
  const db = await getDb();
  const tx = db.transaction('pendingDeliveries', 'readwrite');
  const item = await tx.store.get(id);
  if (item) {
    item.synced = true;
    item.syncedAt = new Date().toISOString();
    await tx.store.put(item);
  }
  await tx.done;
}

// --- Pending Payments ---
export async function savePendingPayment(payment) {
  const db = await getDb();
  return db.add('pendingPayments', {
    ...payment,
    synced: false,
    createdAt: new Date().toISOString(),
  });
}

export async function getPendingPayments() {
  const db = await getDb();
  return db.getAllFromIndex('pendingPayments', 'synced', false);
}

export async function markPaymentSynced(id) {
  const db = await getDb();
  const tx = db.transaction('pendingPayments', 'readwrite');
  const item = await tx.store.get(id);
  if (item) {
    item.synced = true;
    item.syncedAt = new Date().toISOString();
    await tx.store.put(item);
  }
  await tx.done;
}

// --- Pending Signatures ---
export async function savePendingSignature(signature) {
  const db = await getDb();
  return db.add('pendingSignatures', {
    ...signature,
    synced: false,
    createdAt: new Date().toISOString(),
  });
}

export async function getPendingSignatures() {
  const db = await getDb();
  return db.getAllFromIndex('pendingSignatures', 'synced', false);
}

export async function markSignatureSynced(id) {
  const db = await getDb();
  const tx = db.transaction('pendingSignatures', 'readwrite');
  const item = await tx.store.get(id);
  if (item) {
    item.synced = true;
    item.syncedAt = new Date().toISOString();
    await tx.store.put(item);
  }
  await tx.done;
}

// --- Pending Photos ---
export async function savePendingPhoto(photo) {
  const db = await getDb();
  return db.add('pendingPhotos', {
    ...photo,
    synced: false,
    createdAt: new Date().toISOString(),
  });
}

export async function getPendingPhotos() {
  const db = await getDb();
  return db.getAllFromIndex('pendingPhotos', 'synced', false);
}

export async function markPhotoSynced(id) {
  const db = await getDb();
  const tx = db.transaction('pendingPhotos', 'readwrite');
  const item = await tx.store.get(id);
  if (item) {
    item.synced = true;
    item.syncedAt = new Date().toISOString();
    await tx.store.put(item);
  }
  await tx.done;
}

// --- Cached Data ---
export async function saveCachedData(key, data) {
  const db = await getDb();
  return db.put('cachedData', {
    key,
    data,
    timestamp: Date.now(),
  });
}

export async function getCachedData(key) {
  const db = await getDb();
  return db.get('cachedData', key);
}

export async function getAllCachedKeys() {
  const db = await getDb();
  return db.getAllKeys('cachedData');
}

// --- Auth ---
export async function saveAuthData(key, value) {
  const db = await getDb();
  return db.put('auth', { key, value });
}

export async function getAuthData(key) {
  const db = await getDb();
  const result = await db.get('auth', key);
  return result?.value;
}

export async function clearAuthData() {
  const db = await getDb();
  return db.clear('auth');
}

// --- Clear Synced ---
export async function clearSynced(storeName) {
  const db = await getDb();
  const tx = db.transaction(storeName, 'readwrite');
  const index = tx.store.index('synced');
  let cursor = await index.openCursor(false);
  while (cursor) {
    cursor.delete();
    cursor = await cursor.continue();
  }
  await tx.done;
}

export async function clearAllSynced() {
  await clearSynced('pendingDeliveries');
  await clearSynced('pendingPayments');
  await clearSynced('pendingSignatures');
  await clearSynced('pendingPhotos');
}

// --- Counts ---
export async function getPendingCounts() {
  const [deliveries, payments, signatures, photos] = await Promise.all([
    getPendingDeliveries(),
    getPendingPayments(),
    getPendingSignatures(),
    getPendingPhotos(),
  ]);
  return {
    deliveries: deliveries.length,
    payments: payments.length,
    signatures: signatures.length,
    photos: photos.length,
    total: deliveries.length + payments.length + signatures.length + photos.length,
  };
}
