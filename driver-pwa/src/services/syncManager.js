import toast from 'react-hot-toast';
import api from './api';
import {
  getPendingDeliveries,
  getPendingPayments,
  getPendingSignatures,
  getPendingPhotos,
  markDeliverySynced,
  markPaymentSynced,
  markSignatureSynced,
  markPhotoSynced,
  saveCachedData,
  getCachedData,
  clearAllSynced,
} from './offlineStorage';

async function uploadDeliveries() {
  const pending = await getPendingDeliveries();
  if (pending.length === 0) return 0;

  let synced = 0;
  for (const item of pending) {
    try {
      await api.post('/deliveries/sync', {
        stopId: item.stopId,
        items: item.items,
        returns: item.returns || [],
        shelfRotation: item.shelfRotation || false,
        notes: item.notes || '',
        completedAt: item.createdAt,
      });
      await markDeliverySynced(item.id);
      synced++;
    } catch (err) {
      if (err.response?.status === 409) {
        await markDeliverySynced(item.id);
        synced++;
      }
    }
  }
  return synced;
}

async function uploadPayments() {
  const pending = await getPendingPayments();
  if (pending.length === 0) return 0;

  let synced = 0;
  for (const item of pending) {
    try {
      await api.post('/payments/sync', {
        stopId: item.stopId,
        amount: item.amount,
        method: item.method,
        reference: item.reference || '',
        collectedAt: item.createdAt,
      });
      await markPaymentSynced(item.id);
      synced++;
    } catch (err) {
      if (err.response?.status === 409) {
        await markPaymentSynced(item.id);
        synced++;
      }
    }
  }
  return synced;
}

async function uploadSignatures() {
  const pending = await getPendingSignatures();
  if (pending.length === 0) return 0;

  let synced = 0;
  for (const item of pending) {
    try {
      await api.post(
        '/signatures/sync',
        { stopId: item.stopId, data: item.signatureData, capturedAt: item.createdAt },
        { headers: { 'Content-Type': 'application/json' } }
      );
      await markSignatureSynced(item.id);
      synced++;
    } catch (err) {
      if (err.response?.status === 409) {
        await markSignatureSynced(item.id);
        synced++;
      }
    }
  }
  return synced;
}

async function uploadPhotos() {
  const pending = await getPendingPhotos();
  if (pending.length === 0) return 0;

  let synced = 0;
  for (const item of pending) {
    try {
      const formData = new FormData();
      formData.append('stopId', item.stopId);

      const blob = dataURLToBlob(item.photoData);
      if (blob) {
        formData.append('photo', blob, `photo-${item.stopId}-${Date.now()}.jpg`);
      }

      await api.post('/photos/sync', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      await markPhotoSynced(item.id);
      synced++;
    } catch (err) {
      if (err.response?.status === 409) {
        await markPhotoSynced(item.id);
        synced++;
      }
    }
  }
  return synced;
}

function dataURLToBlob(dataURL) {
  if (!dataURL || typeof dataURL !== 'string') return null;
  try {
    const parts = dataURL.split(',');
    if (parts.length < 2) return null;
    const mimeMatch = parts[0].match(/:(.*?);/);
    const mime = mimeMatch ? mimeMatch[1] : 'image/jpeg';
    const binary = atob(parts[1]);
    const array = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      array[i] = binary.charCodeAt(i);
    }
    return new Blob([array], { type: mime });
  } catch {
    return null;
  }
}

export async function syncDeliveries() {
  return uploadDeliveries();
}

export async function syncPayments() {
  return uploadPayments();
}

export async function syncSignatures() {
  return uploadSignatures();
}

export async function syncPhotos() {
  return uploadPhotos();
}

export async function syncAll() {
  if (!navigator.onLine) {
    toast.error('No internet connection. Cannot sync.');
    return { deliveries: 0, payments: 0, signatures: 0, photos: 0 };
  }

  toast.loading('Syncing data...', { id: 'sync-toast' });

  try {
    const [deliveries, payments, signatures, photos] = await Promise.all([
      uploadDeliveries(),
      uploadPayments(),
      uploadSignatures(),
      uploadPhotos(),
    ]);

    const total = deliveries + payments + signatures + photos;
    if (total > 0) {
      toast.success(`Synced ${total} item(s) successfully!`, { id: 'sync-toast' });
    } else {
      toast.success('Everything is up to date!', { id: 'sync-toast' });
    }

    return { deliveries, payments, signatures, photos };
  } catch (err) {
    toast.error('Sync failed. Will retry later.', { id: 'sync-toast' });
    return { deliveries: 0, payments: 0, signatures: 0, photos: 0 };
  }
}

export async function downloadRouteUpdates() {
  if (!navigator.onLine) {
    const cached = await getCachedData('route');
    return cached?.data || null;
  }

  try {
    const { data } = await api.get('/route/latest');
    if (data) {
      await saveCachedData('route', data);
    }
    return data;
  } catch (err) {
    const cached = await getCachedData('route');
    return cached?.data || null;
  }
}

export async function loginOffline(email, password) {
  try {
    const { data } = await api.post('/auth/login', { email, password });
    if (data.token) {
      const { clearAuthData, saveAuthData } = await import('./offlineStorage');
      await saveAuthData('cached_credentials', { email, password });
      await saveAuthData('last_user', data.user);
      return data;
    }
    throw new Error('Invalid credentials');
  } catch (err) {
    if (!navigator.onLine) {
      const { getAuthData } = await import('./offlineStorage');
      const cached = await getAuthData('cached_credentials');
      if (cached && cached.email === email) {
        const lastUser = await getAuthData('last_user');
        if (lastUser) {
          return { offline: true, user: lastUser };
        }
      }
    }
    throw err;
  }
}

export async function registerSync() {
  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    try {
      const registration = await navigator.serviceWorker.ready;
      await registration.sync.register('sync-deliveries');
    } catch {
    }
  }
}
