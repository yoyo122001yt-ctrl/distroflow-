import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Square,
  DollarSign,
  Gauge,
  Package,
  RefreshCw,
  AlertTriangle,
  CheckCircle2,
  Clock,
  TrendingDown,
  TrendingUp,
} from 'lucide-react';
import toast from 'react-hot-toast';
import useApi from '../hooks/useApi';
import api from '../services/api';

function CashReconciliation({ expected, actual, onChange }) {
  const variance = (parseFloat(actual) || 0) - (parseFloat(expected) || 0);
  const isVariance = Math.abs(variance) > 0.01;

  return (
    <div className="card mb-4">
      <div className="flex items-center gap-2 mb-3">
        <DollarSign className="w-5 h-5 text-green-600" />
        <h2 className="font-semibold text-gray-900">Cash Reconciliation</h2>
      </div>

      <div className="bg-gray-50 rounded-lg p-4 mb-3">
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm text-gray-600">Expected Collections</span>
          <span className="text-lg font-bold text-gray-900">${(parseFloat(expected) || 0).toFixed(2)}</span>
        </div>
        <div className="h-px bg-gray-200 my-2" />
        <div>
          <label className="label">Actual Cash on Hand</label>
          <input
            type="number"
            step="0.01"
            min="0"
            value={actual}
            onChange={(e) => onChange(e.target.value)}
            className="input-field text-lg font-mono"
            placeholder="0.00"
          />
        </div>
        <div className="h-px bg-gray-200 my-3" />
        <div className="flex items-center justify-between">
          <span className="text-sm font-medium text-gray-700">Variance</span>
          <div className={`flex items-center gap-1.5 ${
            Math.abs(variance) < 0.01
              ? 'text-green-600'
              : variance > 0
              ? 'text-blue-600'
              : 'text-red-600'
          }`}>
            {Math.abs(variance) < 0.01 ? (
              <CheckCircle2 className="w-4 h-4" />
            ) : variance > 0 ? (
              <TrendingUp className="w-4 h-4" />
            ) : (
              <TrendingDown className="w-4 h-4" />
            )}
            <span className="text-lg font-bold">
              {variance >= 0 ? '+' : ''}${variance.toFixed(2)}
            </span>
          </div>
        </div>
        {isVariance && (
          <div className="mt-2 flex items-start gap-1.5 text-xs text-amber-600 bg-amber-50 rounded-lg px-3 py-2">
            <AlertTriangle className="w-3 h-3 flex-shrink-0 mt-0.5" />
            <span>Variance detected. Please double-check your cash count.</span>
          </div>
        )}
      </div>
    </div>
  );
}

function RemainingInventory({ products }) {
  const [expanded, setExpanded] = useState(false);
  const totalItems = products.reduce((s, p) => s + (p.quantity || 0), 0);
  const totalProducts = products.length;

  if (totalProducts === 0) {
    return (
      <div className="card mb-4">
        <div className="flex items-center gap-2 mb-3">
          <Package className="w-5 h-5 text-primary-600" />
          <h2 className="font-semibold text-gray-900">Remaining Inventory</h2>
        </div>
        <p className="text-sm text-gray-400 text-center py-4">No remaining inventory</p>
      </div>
    );
  }

  const visible = expanded ? products : products.slice(0, 5);

  return (
    <div className="card mb-4">
      <div className="flex items-center justify-between mb-3">
        <div className="flex items-center gap-2">
          <Package className="w-5 h-5 text-primary-600" />
          <h2 className="font-semibold text-gray-900">Remaining Inventory</h2>
        </div>
        <span className="text-sm text-gray-500">{totalItems} units</span>
      </div>

      <div className="space-y-2">
        {visible.map((p, idx) => (
          <div key={p.id || idx} className="flex items-center justify-between py-1.5">
            <span className="text-sm text-gray-700 truncate flex-1">
              {p.name || p.productName}
            </span>
            <span className="text-sm font-medium text-gray-900 ml-3">{p.quantity}</span>
          </div>
        ))}
      </div>

      {products.length > 5 && (
        <button
          onClick={() => setExpanded(!expanded)}
          className="w-full text-center text-xs text-primary-600 mt-2 py-1"
        >
          {expanded ? 'Show less' : `Show ${products.length - 5} more`}
        </button>
      )}
    </div>
  );
}

export default function ShiftEnd() {
  const navigate = useNavigate();
  const [odometer, setOdometer] = useState('');
  const [actualCash, setActualCash] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const { data: dashboard, loading: dashLoading, execute: fetchDashboard } = useApi('dashboard');
  const { data: inventory, loading: invLoading, execute: fetchInventory } = useApi('truck-inventory-end');

  useEffect(() => {
    fetchDashboard({ url: '/driver/dashboard' });
    fetchInventory({ url: '/truck/inventory' });
  }, []);

  const shiftStartOdometer = localStorage.getItem('df_shift_start_odometer');
  const shiftStartTime = localStorage.getItem('df_shift_start_time');

  const expectedCollections = dashboard?.todayCollections || 0;
  const remainingProducts = inventory?.products || inventory?.items || inventory || [];

  const handleEndShift = async () => {
    if (!odometer.trim()) {
      toast.error('Please enter ending odometer reading');
      return;
    }

    const odometerNum = parseInt(odometer);
    if (isNaN(odometerNum) || odometerNum < 0) {
      toast.error('Please enter a valid odometer reading');
      return;
    }

    if (shiftStartOdometer && parseInt(odometer) < parseInt(shiftStartOdometer)) {
      toast.error('Ending odometer cannot be less than starting odometer');
      return;
    }

    setSubmitting(true);
    try {
      await api.post('/shift/end', {
        endOdometer: odometerNum,
        startOdometer: shiftStartOdometer ? parseInt(shiftStartOdometer) : null,
        cashReconciliation: {
          expected: expectedCollections,
          actual: parseFloat(actualCash) || 0,
          variance: (parseFloat(actualCash) || 0) - expectedCollections,
        },
        remainingInventory: (Array.isArray(remainingProducts) ? remainingProducts : []).map((p) => ({
          productId: p.id,
          productName: p.name || p.productName,
          quantity: p.quantity || 0,
        })),
        shiftDuration: shiftStartTime
          ? Math.round((Date.now() - new Date(shiftStartTime).getTime()) / 1000)
          : null,
      });

      localStorage.removeItem('df_shift_active');
      localStorage.removeItem('df_shift_start_odometer');
      localStorage.removeItem('df_shift_start_time');
      localStorage.removeItem('df_shift_pending');

      toast.success('Shift ended successfully!');
      navigate('/', { replace: true });
    } catch (err) {
      if (!navigator.onLine) {
        localStorage.setItem('df_shift_end_pending', 'true');
        localStorage.setItem('df_shift_end_data', JSON.stringify({
          endOdometer: odometerNum,
          actualCash: parseFloat(actualCash) || 0,
          time: new Date().toISOString(),
        }));
        localStorage.removeItem('df_shift_active');
        toast.success('Shift ended (offline). Will sync later.');
        navigate('/', { replace: true });
      } else {
        toast.error(err.response?.data?.message || 'Failed to end shift');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const loading = dashLoading || invLoading;

  if (loading) {
    return (
      <div className="page-container">
        <div className="flex flex-col items-center justify-center py-20">
          <RefreshCw className="w-8 h-8 text-primary-500 animate-spin mb-4" />
          <p className="text-gray-500 text-sm">Preparing shift end...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="page-container">
      <h1 className="page-title mb-6">End Shift</h1>

      {/* Shift Summary */}
      <div className="card mb-4">
        <div className="flex items-center gap-2 mb-3">
          <Clock className="w-5 h-5 text-gray-600" />
          <h2 className="font-semibold text-gray-900">Shift Summary</h2>
        </div>
        {shiftStartTime && (
          <div className="flex items-center justify-between text-sm">
            <span className="text-gray-500">Started</span>
            <span className="text-gray-900 font-medium">
              {new Date(shiftStartTime).toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
              })}
            </span>
          </div>
        )}
        {shiftStartOdometer && (
          <div className="flex items-center justify-between text-sm mt-2">
            <span className="text-gray-500">Start Odometer</span>
            <span className="text-gray-900 font-medium">{shiftStartOdometer}</span>
          </div>
        )}
      </div>

      {/* Odometer */}
      <div className="card mb-4">
        <div className="flex items-center gap-2 mb-3">
          <Gauge className="w-5 h-5 text-primary-600" />
          <h2 className="font-semibold text-gray-900">Ending Odometer</h2>
        </div>
        <div className="relative">
          <Gauge className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            type="number"
            value={odometer}
            onChange={(e) => setOdometer(e.target.value)}
            placeholder="Enter ending odometer reading"
            className="input-field pl-10 text-lg font-mono"
            min={shiftStartOdometer || '0'}
          />
        </div>
        {shiftStartOdometer && odometer && parseInt(odometer) >= parseInt(shiftStartOdometer) && (
          <p className="text-xs text-gray-500 mt-2">
            Distance traveled: {parseInt(odometer) - parseInt(shiftStartOdometer)} units
          </p>
        )}
      </div>

      {/* Cash Reconciliation */}
      <CashReconciliation
        expected={expectedCollections}
        actual={actualCash}
        onChange={setActualCash}
      />

      {/* Remaining Inventory */}
      <RemainingInventory products={Array.isArray(remainingProducts) ? remainingProducts : []} />

      {/* End Shift Button */}
      <button
        onClick={handleEndShift}
        disabled={submitting}
        className="btn-danger w-full py-3 flex items-center justify-center gap-2 text-base"
      >
        {submitting ? (
          <>
            <RefreshCw className="w-4 h-4 animate-spin" />
            Ending Shift...
          </>
        ) : (
          <>
            <Square className="w-5 h-5" />
            End Shift
          </>
        )}
      </button>
    </div>
  );
}
