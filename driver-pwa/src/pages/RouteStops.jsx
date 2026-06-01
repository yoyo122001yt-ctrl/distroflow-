import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  MapPin,
  ChevronRight,
  RefreshCw,
  Package,
  Clock,
  CheckCircle2,
  XCircle,
  Navigation,
  WifiOff,
  AlertCircle,
} from 'lucide-react';
import useApi from '../hooks/useApi';
import { format } from 'date-fns';

const STATUS_CONFIG = {
  pending: { icon: Clock, color: 'text-yellow-500', bg: 'bg-yellow-50', label: 'Pending' },
  arrived: { icon: MapPin, color: 'text-blue-500', bg: 'bg-blue-50', label: 'Arrived' },
  in_progress: { icon: Package, color: 'text-purple-500', bg: 'bg-purple-50', label: 'In Progress' },
  delivered: { icon: CheckCircle2, color: 'text-green-500', bg: 'bg-green-50', label: 'Delivered' },
  skipped: { icon: XCircle, color: 'text-gray-400', bg: 'bg-gray-50', label: 'Skipped' },
};

function StopCard({ stop, index, onClick }) {
  const statusConfig = STATUS_CONFIG[stop.status] || STATUS_CONFIG.pending;
  const StatusIcon = statusConfig.icon;

  return (
    <button
      onClick={() => onClick(stop.id)}
      className="w-full card text-left hover:shadow-md transition-all active:scale-[0.99] mb-3"
    >
      <div className="flex items-start gap-3">
        <div className="flex-shrink-0 w-8 h-8 rounded-full bg-primary-50 flex items-center justify-center mt-0.5">
          <span className="text-sm font-bold text-primary-600">{index + 1}</span>
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between gap-2 mb-1">
            <h3 className="font-semibold text-gray-900 truncate">{stop.storeName || stop.name}</h3>
            <div className={`flex-shrink-0 flex items-center gap-1 px-2 py-0.5 rounded-full text-xs ${statusConfig.bg} ${statusConfig.color}`}>
              <StatusIcon className="w-3 h-3" />
              <span className="font-medium">{statusConfig.label}</span>
            </div>
          </div>

          <p className="text-sm text-gray-500 truncate flex items-center gap-1">
            <MapPin className="w-3 h-3 flex-shrink-0" />
            {stop.address || 'Address not available'}
          </p>

          <div className="flex items-center gap-3 mt-2 text-xs text-gray-400">
            {stop.expectedItems && (
              <span className="flex items-center gap-1">
                <Package className="w-3 h-3" />
                {stop.expectedItems} items
              </span>
            )}
            {stop.scheduledTime && (
              <span className="flex items-center gap-1">
                <Clock className="w-3 h-3" />
                {stop.scheduledTime}
              </span>
            )}
            {stop.distance && (
              <span className="flex items-center gap-1">
                <Navigation className="w-3 h-3" />
                {stop.distance}
              </span>
            )}
          </div>

          {stop.notes && (
            <div className="mt-2 flex items-start gap-1.5 text-xs text-amber-600 bg-amber-50 rounded-lg px-2 py-1.5">
              <AlertCircle className="w-3 h-3 flex-shrink-0 mt-0.5" />
              <span>{stop.notes}</span>
            </div>
          )}
        </div>

        <ChevronRight className="w-4 h-4 text-gray-300 flex-shrink-0 mt-2" />
      </div>
    </button>
  );
}

export default function RouteStops() {
  const navigate = useNavigate();
  const [stops, setStops] = useState([]);
  const [isOnline, setIsOnline] = useState(navigator.onLine);

  const { data, loading, error, execute } = useApi('stops');

  useEffect(() => {
    execute({ url: '/stops' });

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
        setStops(data);
      } else if (data.stops) {
        setStops(data.stops);
      }
    }
  }, [data]);

  const handleRefresh = () => {
    execute({ url: '/stops', skipCache: true });
  };

  const completedCount = stops.filter(
    (s) => s.status === 'delivered' || s.status === 'skipped'
  ).length;
  const totalCount = stops.length;

  if (loading && stops.length === 0) {
    return (
      <div className="page-container">
        <div className="flex flex-col items-center justify-center py-20">
          <RefreshCw className="w-8 h-8 text-primary-500 animate-spin mb-4" />
          <p className="text-gray-500 text-sm">Loading stops...</p>
        </div>
      </div>
    );
  }

  if (error && stops.length === 0) {
    return (
      <div className="page-container">
        <div className="flex flex-col items-center justify-center py-20">
          <XCircle className="w-10 h-10 text-red-400 mb-3" />
          <p className="text-gray-700 font-medium mb-1">Failed to load stops</p>
          <p className="text-sm text-gray-500 mb-4 text-center">{error}</p>
          <button onClick={handleRefresh} className="btn-primary">
            Try Again
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="page-container">
      <div className="page-header">
        <div>
          <h1 className="page-title">Route Stops</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {completedCount}/{totalCount} completed
          </p>
        </div>
        <div className="flex items-center gap-2">
          {!isOnline && (
            <span className="flex items-center gap-1 text-xs text-yellow-600 bg-yellow-50 px-2 py-1 rounded-full">
              <WifiOff className="w-3 h-3" />
              Offline
            </span>
          )}
          <button
            onClick={handleRefresh}
            disabled={loading}
            className="p-2 text-gray-400 hover:text-primary-600 rounded-lg hover:bg-primary-50 transition-colors disabled:opacity-50"
          >
            <RefreshCw className={`w-5 h-5 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>
      </div>

      {totalCount > 0 && (
        <div className="w-full bg-gray-200 rounded-full h-2 mb-6">
          <div
            className="bg-primary-600 h-2 rounded-full transition-all duration-500"
            style={{ width: `${totalCount > 0 ? (completedCount / totalCount) * 100 : 0}%` }}
          />
        </div>
      )}

      {loading && (
        <div className="flex items-center justify-center py-3 mb-3">
          <RefreshCw className="w-4 h-4 text-primary-500 animate-spin" />
          <span className="text-xs text-gray-400 ml-2">Refreshing...</span>
        </div>
      )}

      {stops.length === 0 ? (
        <div className="flex flex-col items-center justify-center py-16">
          <MapPin className="w-12 h-12 text-gray-300 mb-3" />
          <p className="text-gray-500 font-medium">No stops for today</p>
          <p className="text-sm text-gray-400 mt-1">Your route has no stops assigned</p>
        </div>
      ) : (
        <div>
          {stops.map((stop, index) => (
            <StopCard
              key={stop.id || index}
              stop={stop}
              index={index}
              onClick={(id) => navigate(`/stops/${id}`)}
            />
          ))}
        </div>
      )}
    </div>
  );
}
