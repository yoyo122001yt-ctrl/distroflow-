import React, { useState, useEffect, useCallback } from 'react';
import {
  Wifi,
  WifiOff,
  RefreshCw,
  Package,
  DollarSign,
  PenTool,
  Camera,
  CheckCircle2,
  XCircle,
  Clock,
  Upload,
  AlertTriangle,
  ChevronRight,
} from 'lucide-react';
import toast from 'react-hot-toast';
import { format } from 'date-fns';
import useOffline from '../hooks/useOffline';
import {
  getPendingDeliveries,
  getPendingPayments,
  getPendingSignatures,
  getPendingPhotos,
  clearAllSynced,
} from '../services/offlineStorage';
import {
  syncDeliveries,
  syncPayments,
  syncSignatures,
  syncPhotos,
  syncAll,
} from '../services/syncManager';

export default function OfflineSync() {
  const { isOnline, pendingCount, syncAll: syncAllItems } = useOffline();
  const [syncing, setSyncing] = useState(false);
  const [lastSync, setLastSync] = useState(null);
  const [pendingItems, setPendingItems] = useState({
    deliveries: [],
    payments: [],
    signatures: [],
    photos: [],
  });
  const [syncResults, setSyncResults] = useState(null);
  const [expandedSection, setExpandedSection] = useState(null);

  const loadPendingItems = useCallback(async () => {
    try {
      const [deliveries, payments, signatures, photos] = await Promise.all([
        getPendingDeliveries(),
        getPendingPayments(),
        getPendingSignatures(),
        getPendingPhotos(),
      ]);
      setPendingItems({ deliveries, payments, signatures, photos });
    } catch {}
  }, []);

  useEffect(() => {
    loadPendingItems();
    const saved = localStorage.getItem('df_last_sync');
    if (saved) {
      setLastSync(new Date(saved));
    }
  }, [loadPendingItems]);

  useEffect(() => {
    loadPendingItems();
  }, [pendingCount, loadPendingItems]);

  const handleSyncAll = async () => {
    if (!isOnline) {
      toast.error('No internet connection');
      return;
    }
    setSyncing(true);
    setSyncResults(null);
    try {
      const results = await syncAll();
      setSyncResults(results);
      const now = new Date();
      setLastSync(now);
      localStorage.setItem('df_last_sync', now.toISOString());
      await loadPendingItems();
      await syncAllItems();
    } catch (err) {
      toast.error('Sync failed');
    } finally {
      setSyncing(false);
    }
  };

  const handleSyncItem = async (type) => {
    if (!isOnline) {
      toast.error('No internet connection');
      return;
    }
    setSyncing(true);
    try {
      let count = 0;
      if (type === 'deliveries') count = await syncDeliveries();
      else if (type === 'payments') count = await syncPayments();
      else if (type === 'signatures') count = await syncSignatures();
      else if (type === 'photos') count = await syncPhotos();

      toast.success(`Synced ${count} ${type}`);
      await loadPendingItems();
      await syncAllItems();
    } catch {
      toast.error(`Failed to sync ${type}`);
    } finally {
      setSyncing(false);
    }
  };

  const handleClearSynced = async () => {
    if (!confirm('Clear all synced items?')) return;
    try {
      await clearAllSynced();
      await loadPendingItems();
      toast.success('Cleared synced items');
    } catch {
      toast.error('Failed to clear');
    }
  };

  const totalPending =
    pendingItems.deliveries.length +
    pendingItems.payments.length +
    pendingItems.signatures.length +
    pendingItems.photos.length;

  const syncSections = [
    {
      key: 'deliveries',
      label: 'Pending Deliveries',
      icon: Package,
      color: 'text-primary-600',
      bg: 'bg-primary-50',
      items: pendingItems.deliveries,
      count: pendingItems.deliveries.length,
    },
    {
      key: 'payments',
      label: 'Pending Payments',
      icon: DollarSign,
      color: 'text-green-600',
      bg: 'bg-green-50',
      items: pendingItems.payments,
      count: pendingItems.payments.length,
    },
    {
      key: 'signatures',
      label: 'Pending Signatures',
      icon: PenTool,
      color: 'text-purple-600',
      bg: 'bg-purple-50',
      items: pendingItems.signatures,
      count: pendingItems.signatures.length,
    },
    {
      key: 'photos',
      label: 'Pending Photos',
      icon: Camera,
      color: 'text-pink-600',
      bg: 'bg-pink-50',
      items: pendingItems.photos,
      count: pendingItems.photos.length,
    },
  ];

  return (
    <div className="page-container">
      <div className="page-header">
        <div>
          <h1 className="page-title">Sync Manager</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            Manage offline data synchronization
          </p>
        </div>
      </div>

      {/* Status Card */}
      <div className={`card mb-4 ${isOnline ? 'border-green-200' : 'border-yellow-200'}`}>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className={`p-2 rounded-full ${isOnline ? 'bg-green-50' : 'bg-yellow-50'}`}>
              {isOnline ? (
                <Wifi className="w-5 h-5 text-green-600" />
              ) : (
                <WifiOff className="w-5 h-5 text-yellow-600" />
              )}
            </div>
            <div>
              <p className={`font-medium ${isOnline ? 'text-green-700' : 'text-yellow-700'}`}>
                {isOnline ? 'Connected' : 'Offline'}
              </p>
              {lastSync && (
                <p className="text-xs text-gray-400 mt-0.5">
                  Last sync: {format(lastSync, 'h:mm a')}
                </p>
              )}
            </div>
          </div>

          {totalPending > 0 && (
            <div className="text-right">
              <p className="text-2xl font-bold text-gray-900">{totalPending}</p>
              <p className="text-xs text-gray-500">pending</p>
            </div>
          )}
        </div>
      </div>

      {/* Sync All Button */}
      {totalPending > 0 && (
        <button
          onClick={handleSyncAll}
          disabled={syncing || !isOnline}
          className="btn-primary w-full py-3 flex items-center justify-center gap-2 text-base mb-6"
        >
          {syncing ? (
            <>
              <RefreshCw className="w-4 h-4 animate-spin" />
              Syncing...
            </>
          ) : (
            <>
              <Upload className="w-5 h-5" />
              Sync All ({totalPending} items)
            </>
          )}
        </button>
      )}

      {/* Sync Results */}
      {syncResults && (
        <div className="card mb-4 bg-green-50 border-green-200">
          <div className="flex items-center gap-2 mb-2">
            <CheckCircle2 className="w-5 h-5 text-green-600" />
            <h3 className="font-medium text-green-800">Sync Complete</h3>
          </div>
          <div className="grid grid-cols-2 gap-2 text-sm">
            <div className="flex items-center gap-1.5">
              <Package className="w-3 h-3 text-primary-600" />
              <span className="text-green-700">{syncResults.deliveries} deliveries</span>
            </div>
            <div className="flex items-center gap-1.5">
              <DollarSign className="w-3 h-3 text-green-600" />
              <span className="text-green-700">{syncResults.payments} payments</span>
            </div>
            <div className="flex items-center gap-1.5">
              <PenTool className="w-3 h-3 text-purple-600" />
              <span className="text-green-700">{syncResults.signatures} signatures</span>
            </div>
            <div className="flex items-center gap-1.5">
              <Camera className="w-3 h-3 text-pink-600" />
              <span className="text-green-700">{syncResults.photos} photos</span>
            </div>
          </div>
        </div>
      )}

      {/* No Pending Items */}
      {totalPending === 0 && !syncing && (
        <div className="flex flex-col items-center justify-center py-12">
          <CheckCircle2 className="w-12 h-12 text-green-400 mb-3" />
          <p className="text-gray-700 font-medium">All synced up!</p>
          <p className="text-sm text-gray-400 mt-1">No pending items to sync</p>
        </div>
      )}

      {/* Pending Items Sections */}
      {syncSections.map((section) => {
        const Icon = section.icon;
        const isExpanded = expandedSection === section.key;

        if (section.count === 0) return null;

        return (
          <div key={section.key} className="card mb-3">
            <button
              onClick={() => setExpandedSection(isExpanded ? null : section.key)}
              className="w-full flex items-center justify-between"
            >
              <div className="flex items-center gap-3">
                <div className={`p-2 rounded-lg ${section.bg}`}>
                  <Icon className={`w-4 h-4 ${section.color}`} />
                </div>
                <div className="text-left">
                  <p className="text-sm font-medium text-gray-900">{section.label}</p>
                  <p className="text-xs text-gray-500">{section.count} item(s)</p>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    handleSyncItem(section.key);
                  }}
                  disabled={syncing || !isOnline}
                  className="text-xs text-primary-600 font-medium px-3 py-1.5 rounded-lg bg-primary-50 hover:bg-primary-100 transition-colors disabled:opacity-50"
                >
                  {syncing ? '...' : 'Sync'}
                </button>
                <ChevronRight className={`w-4 h-4 text-gray-300 transition-transform ${
                  isExpanded ? 'rotate-90' : ''
                }`} />
              </div>
            </button>

            {isExpanded && section.items.length > 0 && (
              <div className="mt-3 pt-3 border-t border-gray-100 space-y-2 max-h-60 overflow-y-auto">
                {section.items.slice(0, 20).map((item, idx) => (
                  <div key={item.id || idx} className="flex items-center justify-between py-1.5 text-xs">
                    <div className="flex items-center gap-2">
                      <Clock className="w-3 h-3 text-gray-400" />
                      <span className="text-gray-600">
                        {item.stopId ? `Stop #${item.stopId}` : 'Unknown'}
                      </span>
                    </div>
                    <span className="text-gray-400">
                      {item.createdAt
                        ? format(new Date(item.createdAt), 'h:mm a')
                        : ''}
                    </span>
                  </div>
                ))}
                {section.items.length > 20 && (
                  <p className="text-xs text-gray-400 text-center pt-1">
                    +{section.items.length - 20} more items
                  </p>
                )}
              </div>
            )}
          </div>
        );
      })}

      {/* Offline Warning */}
      {!isOnline && (
        <div className="mt-4 bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-start gap-3">
          <AlertTriangle className="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" />
          <div>
            <p className="text-sm font-medium text-yellow-800">You are offline</p>
            <p className="text-xs text-yellow-700 mt-1">
              Data will sync automatically when you reconnect. Pending items are stored safely on your device.
            </p>
          </div>
        </div>
      )}

      {/* Actions */}
      {totalPending > 0 && (
        <div className="mt-4 flex items-center justify-between">
          <button
            onClick={handleClearSynced}
            className="text-xs text-red-600 hover:text-red-700"
          >
            Clear synced items
          </button>
          <button
            onClick={loadPendingItems}
            className="text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1"
          >
            <RefreshCw className="w-3 h-3" />
            Refresh
          </button>
        </div>
      )}
    </div>
  );
}
