import { useState, useEffect, useCallback } from 'react';
import { getPendingCounts } from '../services/offlineStorage';
import { syncAll } from '../services/syncManager';

export default function useOffline() {
  const [isOnline, setIsOnline] = useState(navigator.onLine);
  const [pendingCount, setPendingCount] = useState(0);

  const updatePendingCount = useCallback(async () => {
    try {
      const counts = await getPendingCounts();
      setPendingCount(counts.total);
    } catch {
      setPendingCount(0);
    }
  }, []);

  useEffect(() => {
    const handleOnline = async () => {
      setIsOnline(true);
      await updatePendingCount();
      try {
        await syncAll();
      } catch {}
    };

    const handleOffline = () => {
      setIsOnline(false);
    };

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    updatePendingCount();

    const interval = setInterval(updatePendingCount, 30000);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
      clearInterval(interval);
    };
  }, [updatePendingCount]);

  const handleSyncAll = useCallback(async () => {
    if (!navigator.onLine) return;
    await syncAll();
    await updatePendingCount();
  }, [updatePendingCount]);

  return {
    isOnline,
    pendingCount,
    syncAll: handleSyncAll,
    refreshCount: updatePendingCount,
  };
}
