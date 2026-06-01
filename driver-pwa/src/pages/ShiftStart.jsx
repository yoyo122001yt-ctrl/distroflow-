import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Play,
  Truck,
  MapPin,
  Gauge,
  AlertTriangle,
  ChevronRight,
  RefreshCw,
  CheckCircle2,
} from 'lucide-react';
import toast from 'react-hot-toast';
import useApi from '../hooks/useApi';
import api from '../services/api';

export default function ShiftStart() {
  const navigate = useNavigate();
  const [step, setStep] = useState('confirm');
  const [odometer, setOdometer] = useState('');
  const [damageNotes, setDamageNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const { data: routeData, loading: routeLoading } = useApi('route');
  const { data: truckData, loading: truckLoading } = useApi('truck');

  useEffect(() => {
    if (!routeLoading && !truckLoading && routeData && truckData) {
    }
  }, [routeData, truckData, routeLoading, truckLoading]);

  const handleStartShift = async () => {
    if (!odometer.trim()) {
      toast.error('Please enter starting odometer reading');
      return;
    }

    const odometerNum = parseInt(odometer);
    if (isNaN(odometerNum) || odometerNum < 0) {
      toast.error('Please enter a valid odometer reading');
      return;
    }

    setSubmitting(true);
    try {
      await api.post('/shift/start', {
        odometer: odometerNum,
        damageNotes: damageNotes.trim() || null,
        routeId: routeData?.id || routeData?.route?.id,
        truckId: truckData?.id || truckData?.truck?.id,
      });

      localStorage.setItem('df_shift_active', 'true');
      localStorage.setItem('df_shift_start_odometer', odometer.toString());
      localStorage.setItem('df_shift_start_time', new Date().toISOString());

      toast.success('Shift started!');
      navigate('/', { replace: true });
    } catch (err) {
      if (!navigator.onLine) {
        localStorage.setItem('df_shift_active', 'true');
        localStorage.setItem('df_shift_start_odometer', odometer.toString());
        localStorage.setItem('df_shift_start_time', new Date().toISOString());
        localStorage.setItem('df_shift_pending', 'true');
        toast.success('Shift saved locally. Will sync when online.');
        navigate('/', { replace: true });
      } else {
        toast.error(err.response?.data?.message || 'Failed to start shift');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const route = routeData?.route || routeData;
  const truck = truckData?.truck || truckData;
  const loading = routeLoading || truckLoading;

  if (loading) {
    return (
      <div className="page-container">
        <div className="flex flex-col items-center justify-center py-20">
          <RefreshCw className="w-8 h-8 text-primary-500 animate-spin mb-4" />
          <p className="text-gray-500 text-sm">Loading shift data...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="page-container">
      <h1 className="page-title mb-6">Start Shift</h1>

      {/* Step 1: Confirm Route & Truck */}
      <div className="card mb-4">
        <div className="flex items-center gap-2 mb-4">
          <div className="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center">
            <span className="text-xs font-bold text-primary-600">1</span>
          </div>
          <h2 className="font-semibold text-gray-900">Confirm Details</h2>
        </div>

        {route ? (
          <div className="bg-blue-50 rounded-lg p-3 mb-3">
            <div className="flex items-center gap-2 mb-1">
              <MapPin className="w-4 h-4 text-blue-600" />
              <span className="text-sm font-medium text-blue-900">{route.name || 'Route'}</span>
            </div>
            <p className="text-xs text-blue-600 ml-6">
              {route.stopsCount || route.stops?.length || 0} stops
            </p>
          </div>
        ) : (
          <div className="bg-yellow-50 rounded-lg p-3 mb-3">
            <p className="text-xs text-yellow-700">No route assigned. You can start without one.</p>
          </div>
        )}

        {truck ? (
          <div className="bg-green-50 rounded-lg p-3">
            <div className="flex items-center gap-2 mb-1">
              <Truck className="w-4 h-4 text-green-600" />
              <span className="text-sm font-medium text-green-900">
                {truck.name || truck.plate || truck.registration || 'Truck'}
              </span>
            </div>
            {truck.plate && (
              <p className="text-xs text-green-600 ml-6">Plate: {truck.plate}</p>
            )}
          </div>
        ) : (
          <div className="bg-yellow-50 rounded-lg p-3">
            <p className="text-xs text-yellow-700">No truck assigned.</p>
          </div>
        )}
      </div>

      {/* Step 2: Odometer */}
      <div className="card mb-4">
        <div className="flex items-center gap-2 mb-4">
          <div className="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center">
            <span className="text-xs font-bold text-primary-600">2</span>
          </div>
          <h2 className="font-semibold text-gray-900">Starting Odometer</h2>
        </div>

        <div className="relative">
          <Gauge className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
          <input
            type="number"
            value={odometer}
            onChange={(e) => setOdometer(e.target.value)}
            placeholder="Enter odometer reading (miles/km)"
            className="input-field pl-10 text-lg font-mono"
            min="0"
          />
        </div>
      </div>

      {/* Step 3: Damage Notes */}
      <div className="card mb-6">
        <div className="flex items-center gap-2 mb-4">
          <div className="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center">
            <span className="text-xs font-bold text-primary-600">3</span>
          </div>
          <h2 className="font-semibold text-gray-900">Pre-Existing Damage</h2>
        </div>

        <div className="flex items-start gap-2 mb-3">
          <AlertTriangle className="w-4 h-4 text-orange-500 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-gray-500">
            Note any pre-existing damage to the truck before starting your shift.
          </p>
        </div>

        <textarea
          value={damageNotes}
          onChange={(e) => setDamageNotes(e.target.value)}
          placeholder="Describe any damage (e.g., scratch on rear bumper, dent on driver side door)..."
          rows={4}
          className="input-field resize-none"
        />

        {damageNotes.trim() && (
          <div className="mt-2 flex items-center gap-1.5 text-xs text-amber-600 bg-amber-50 rounded-lg px-3 py-2">
            <AlertTriangle className="w-3 h-3" />
            Damage noted and will be recorded with shift start
          </div>
        )}
      </div>

      {/* Start Button */}
      <button
        onClick={handleStartShift}
        disabled={submitting}
        className="btn-primary w-full py-3 flex items-center justify-center gap-2 text-base"
      >
        {submitting ? (
          <>
            <RefreshCw className="w-4 h-4 animate-spin" />
            Starting Shift...
          </>
        ) : (
          <>
            <Play className="w-5 h-5" />
            Start Shift
          </>
        )}
      </button>
    </div>
  );
}
