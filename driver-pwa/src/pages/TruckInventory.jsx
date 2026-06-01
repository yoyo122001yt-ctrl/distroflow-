import React, { useEffect, useState, useCallback } from 'react';
import {
  Package,
  RefreshCw,
  Search,
  AlertTriangle,
  Calendar,
  Hash,
  ChevronDown,
  ChevronUp,
  WifiOff,
  XCircle,
} from 'lucide-react';
import useApi from '../hooks/useApi';

function formatDate(dateStr) {
  if (!dateStr) return 'N/A';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  } catch {
    return dateStr;
  }
}

function isExpiringSoon(expiryDate) {
  if (!expiryDate) return false;
  try {
    const expiry = new Date(expiryDate);
    const soon = new Date();
    soon.setDate(soon.getDate() + 30);
    return expiry <= soon;
  } catch {
    return false;
  }
}

function isExpired(expiryDate) {
  if (!expiryDate) return false;
  try {
    const expiry = new Date(expiryDate);
    return expiry < new Date();
  } catch {
    return false;
  }
}

function ProductCard({ product }) {
  const [expanded, setExpanded] = useState(false);

  const expiringSoon = isExpiringSoon(product.expiryDate);
  const expired = isExpired(product.expiryDate);

  return (
    <div className="card mb-3">
      <button
        onClick={() => setExpanded(!expanded)}
        className="w-full text-left"
      >
        <div className="flex items-start justify-between">
          <div className="flex-1 min-w-0">
            <h3 className="font-medium text-gray-900 truncate">
              {product.name || product.productName}
            </h3>
            <p className="text-sm text-gray-500 mt-0.5">
              SKU: {product.sku || product.productCode || 'N/A'}
            </p>
          </div>
          <div className="flex items-center gap-2 ml-3">
            <span className="text-lg font-bold text-gray-900">
              {product.quantity || 0}
            </span>
            <span className="text-xs text-gray-400">
              {product.unit || 'units'}
            </span>
            {expanded ? (
              <ChevronUp className="w-4 h-4 text-gray-400" />
            ) : (
              <ChevronDown className="w-4 h-4 text-gray-400" />
            )}
          </div>
        </div>

        {(expiringSoon || expired) && (
          <div className={`mt-2 flex items-center gap-1.5 text-xs rounded-lg px-2 py-1 ${
            expired ? 'bg-red-50 text-red-600' : 'bg-yellow-50 text-yellow-600'
          }`}>
            <AlertTriangle className="w-3 h-3" />
            <span>{expired ? 'Expired' : 'Expiring soon'}</span>
          </div>
        )}
      </button>

      {expanded && (
        <div className="mt-3 pt-3 border-t border-gray-100 space-y-2">
          {product.batch && (
            <div className="flex items-center gap-2 text-sm">
              <Hash className="w-4 h-4 text-gray-400" />
              <span className="text-gray-600">Batch:</span>
              <span className="text-gray-900 font-medium">{product.batch}</span>
            </div>
          )}
          {product.expiryDate && (
            <div className="flex items-center gap-2 text-sm">
              <Calendar className="w-4 h-4 text-gray-400" />
              <span className="text-gray-600">Expiry:</span>
              <span className={`font-medium ${
                expired ? 'text-red-600' : expiringSoon ? 'text-yellow-600' : 'text-gray-900'
              }`}>
                {formatDate(product.expiryDate)}
              </span>
            </div>
          )}
          {product.category && (
            <div className="flex items-center gap-2 text-sm">
              <Package className="w-4 h-4 text-gray-400" />
              <span className="text-gray-600">Category:</span>
              <span className="text-gray-900">{product.category}</span>
            </div>
          )}
          {product.notes && (
            <p className="text-sm text-gray-500 bg-gray-50 rounded-lg p-2">{product.notes}</p>
          )}
        </div>
      )}
    </div>
  );
}

export default function TruckInventory() {
  const [products, setProducts] = useState([]);
  const [search, setSearch] = useState('');
  const [isOnline, setIsOnline] = useState(navigator.onLine);

  const { data, loading, error, execute } = useApi('truck-inventory');

  useEffect(() => {
    execute({ url: '/truck/inventory' });

    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  useEffect(() => {
    if (data) {
      if (Array.isArray(data)) {
        setProducts(data);
      } else if (data.products || data.items) {
        setProducts(data.products || data.items);
      }
    }
  }, [data]);

  const handleRefresh = () => {
    execute({ url: '/truck/inventory', skipCache: true });
  };

  const filtered = products.filter((p) => {
    if (!search.trim()) return true;
    const q = search.toLowerCase();
    const name = (p.name || p.productName || '').toLowerCase();
    const sku = (p.sku || p.productCode || '').toLowerCase();
    return name.includes(q) || sku.includes(q);
  });

  const totalItems = products.reduce((sum, p) => sum + (p.quantity || 0), 0);
  const uniqueProducts = products.length;
  const expiredCount = products.filter((p) => isExpired(p.expiryDate)).length;
  const expiringCount = products.filter((p) => isExpiringSoon(p.expiryDate) && !isExpired(p.expiryDate)).length;

  if (loading && products.length === 0) {
    return (
      <div className="page-container">
        <div className="flex flex-col items-center justify-center py-20">
          <RefreshCw className="w-8 h-8 text-primary-500 animate-spin mb-4" />
          <p className="text-gray-500 text-sm">Loading inventory...</p>
        </div>
      </div>
    );
  }

  if (error && products.length === 0) {
    return (
      <div className="page-container">
        <div className="flex flex-col items-center justify-center py-20">
          <XCircle className="w-10 h-10 text-red-400 mb-3" />
          <p className="text-gray-700 font-medium mb-1">Failed to load inventory</p>
          <p className="text-sm text-gray-500 mb-4">{error}</p>
          <button onClick={handleRefresh} className="btn-primary">
            Retry
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="page-container">
      <div className="page-header">
        <div>
          <h1 className="page-title">Truck Inventory</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {uniqueProducts} product{uniqueProducts !== 1 ? 's' : ''} &middot; {totalItems} total units
          </p>
        </div>
        <button
          onClick={handleRefresh}
          disabled={loading}
          className="p-2 text-gray-400 hover:text-primary-600 rounded-lg hover:bg-primary-50 transition-colors disabled:opacity-50"
        >
          <RefreshCw className={`w-5 h-5 ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {/* Alerts */}
      {(expiredCount > 0 || expiringCount > 0) && (
        <div className="mb-4 space-y-2">
          {expiredCount > 0 && (
            <div className="flex items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-3 py-2">
              <AlertTriangle className="w-4 h-4 text-red-600 flex-shrink-0" />
              <span className="text-xs text-red-700">
                {expiredCount} expired product{expiredCount > 1 ? 's' : ''} on truck
              </span>
            </div>
          )}
          {expiringCount > 0 && (
            <div className="flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-xl px-3 py-2">
              <AlertTriangle className="w-4 h-4 text-yellow-600 flex-shrink-0" />
              <span className="text-xs text-yellow-700">
                {expiringCount} product{expiringCount > 1 ? 's' : ''} expiring within 30 days
              </span>
            </div>
          )}
        </div>
      )}

      {/* Search */}
      <div className="relative mb-4">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search products..."
          className="input-field pl-10"
        />
      </div>

      {!isOnline && (
        <div className="mb-4 bg-yellow-50 border border-yellow-200 rounded-xl p-3 flex items-center gap-2">
          <WifiOff className="w-4 h-4 text-yellow-600 flex-shrink-0" />
          <span className="text-xs text-yellow-700">Showing cached inventory data</span>
        </div>
      )}

      {loading && (
        <div className="flex items-center justify-center py-2 mb-3">
          <RefreshCw className="w-4 h-4 text-primary-500 animate-spin" />
          <span className="text-xs text-gray-400 ml-2">Refreshing...</span>
        </div>
      )}

      {filtered.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16">
          <Package className="w-12 h-12 text-gray-300 mb-3" />
          <p className="text-gray-500 font-medium">
            {search ? 'No products match your search' : 'No products on truck'}
          </p>
          {search && (
            <button onClick={() => setSearch('')} className="text-sm text-primary-600 mt-2">
              Clear search
            </button>
          )}
        </div>
      ) : (
        <div>
          {filtered.map((product, idx) => (
            <ProductCard key={product.id || idx} product={product} />
          ))}
        </div>
      )}
    </div>
  );
}
