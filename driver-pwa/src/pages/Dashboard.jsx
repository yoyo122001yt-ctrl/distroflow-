import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  MapPin,
  Package,
  DollarSign,
  Truck,
  Play,
  Square,
  RefreshCw,
  Wifi,
  WifiOff,
  ChevronRight,
  Clock,
  Box,
  TrendingUp,
} from 'lucide-react';
import toast from 'react-hot-toast';
import useApi from '../hooks/useApi';
import useOffline from '../hooks/useOffline';
import { format } from 'date-fns';

export default function Dashboard() {
  const navigate = useNavigate();
  const { isOnline, pendingCount } = useOffline();
  const [shiftActive, setShiftActive] = useState(false);
  const [routeData, setRouteData] = useState(null);
  const [stats, setStats] = useState({
    totalStops: 0,
    completedStops: 0,
    totalItems: 0,
    deliveredItems: 0,
    todaySales: 0,
    todayCollections: 0,
    truckItems: 0,
  });

  const {
    data: route,
    loading: routeLoading,
    execute: fetchRoute,
  } = useApi('route');

  const {
    data: dashboard,
    loading: dashLoading,
    execute: fetchDashboard,
  } = useApi('dashboard');

  useEffect(() => {
    fetchRoute({ url: '/route/current' });
    fetchDashboard({ url: '/driver/dashboard' });
    checkShiftStatus();
  }, []);

  const checkShiftStatus = () => {
    const active = localStorage.getItem('df_shift_active') === 'true';
    setShiftActive(active);
  };

  useEffect(() => {
    if (dashboard) {
      setStats({
        totalStops: dashboard.totalStops || 0,
        completedStops: dashboard.completedStops || 0,
        totalItems: dashboard.totalItems || 0,
        deliveredItems: dashboard.deliveredItems || 0,
        todaySales: dashboard.todaySales || 0,
        todayCollections: dashboard.todayCollections || 0,
        truckItems: dashboard.truckItems || 0,
      });
    }
    if (route) {
      setRouteData(route);
    }
  }, [dashboard, route]);

  const handleRefresh = useCallback(() => {
    fetchRoute({ url: '/route/current', skipCache: true });
    fetchDashboard({ url: '/driver/dashboard', skipCache: true });
    toast.success('Refreshed!');
  }, [fetchRoute, fetchDashboard]);

  const stopProgress = stats.totalStops > 0
    ? Math.round((stats.completedStops / stats.totalStops) * 100)
    : 0;

  const deliveryProgress = stats.totalItems > 0
    ? Math.round((stats.deliveredItems / stats.totalItems) * 100)
    : 0;

  const loading = routeLoading || dashLoading;

  if (loading && !routeData && !dashboard) {
    return (
      <div className="page-container">
        <div className="flex flex-col items-center justify-center py-20">
          <RefreshCw className="w-8 h-8 text-primary-500 animate-spin mb-4" />
          <p className="text-gray-500 text-sm">Loading dashboard...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="page-container">
      {/* Header */}
      <div className="page-header">
        <div>
          <h1 className="page-title">Dashboard</h1>
          <p className="text-sm text-gray-500 mt-0.5">
            {format(new Date(), 'EEEE, MMMM d')}
          </p>
        </div>
        <div className="flex items-center gap-2">
          {isOnline ? (
            <span className="flex items-center gap-1 text-xs text-green-600 bg-green-50 px-2 py-1 rounded-full">
              <Wifi className="w-3 h-3" />
              Online
            </span>
          ) : (
            <span className="flex items-center gap-1 text-xs text-yellow-600 bg-yellow-50 px-2 py-1 rounded-full">
              <WifiOff className="w-3 h-3" />
              Offline
            </span>
          )}
          <button
            onClick={handleRefresh}
            className="p-2 text-gray-400 hover:text-primary-600 rounded-lg hover:bg-primary-50 transition-colors"
          >
            <RefreshCw className="w-5 h-5" />
          </button>
        </div>
      </div>

      {pendingCount > 0 && (
        <div className="mb-4 bg-yellow-50 border border-yellow-200 rounded-xl p-3 flex items-center justify-between">
          <span className="text-sm text-yellow-800">
            {pendingCount} item(s) pending sync
          </span>
          <button
            onClick={() => navigate('/sync')}
            className="text-xs font-medium text-yellow-700 underline"
          >
            View
          </button>
        </div>
      )}

      {/* Route Card */}
      <div className="card mb-4">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <MapPin className="w-5 h-5 text-primary-600" />
            <h2 className="font-semibold text-gray-900">Today's Route</h2>
          </div>
          <button
            onClick={() => navigate('/stops')}
            className="text-xs text-primary-600 font-medium flex items-center gap-1"
          >
            View all <ChevronRight className="w-3 h-3" />
          </button>
        </div>

        {routeData ? (
          <div>
            <p className="text-sm text-gray-600 mb-3">
              {routeData.name || 'Main Route'} &middot; {routeData.driver || 'You'}
            </p>
            <div className="grid grid-cols-2 gap-3">
              <div className="bg-gray-50 rounded-lg p-3">
                <p className="text-xs text-gray-500 mb-1">Stops</p>
                <p className="text-lg font-bold text-gray-900">
                  {stats.completedStops}/{stats.totalStops}
                </p>
                <div className="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                  <div
                    className="bg-primary-600 h-1.5 rounded-full transition-all duration-500"
                    style={{ width: `${stopProgress}%` }}
                  />
                </div>
              </div>
              <div className="bg-gray-50 rounded-lg p-3">
                <p className="text-xs text-gray-500 mb-1">Deliveries</p>
                <p className="text-lg font-bold text-gray-900">
                  {stats.deliveredItems}/{stats.totalItems}
                </p>
                <div className="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                  <div
                    className="bg-green-500 h-1.5 rounded-full transition-all duration-500"
                    style={{ width: `${deliveryProgress}%` }}
                  />
                </div>
              </div>
            </div>
          </div>
        ) : (
          <div className="text-center py-6">
            <MapPin className="w-8 h-8 text-gray-300 mx-auto mb-2" />
            <p className="text-sm text-gray-500">No active route assigned</p>
          </div>
        )}
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-2 gap-3 mb-4">
        <div className="card">
          <div className="flex items-center gap-2 mb-2">
            <div className="p-1.5 bg-green-50 rounded-lg">
              <DollarSign className="w-4 h-4 text-green-600" />
            </div>
          </div>
          <p className="text-xs text-gray-500">Today's Sales</p>
          <p className="text-lg font-bold text-gray-900">
            ${(stats.todaySales || 0).toLocaleString()}
          </p>
        </div>

        <div className="card">
          <div className="flex items-center gap-2 mb-2">
            <div className="p-1.5 bg-blue-50 rounded-lg">
              <TrendingUp className="w-4 h-4 text-blue-600" />
            </div>
          </div>
          <p className="text-xs text-gray-500">Collections</p>
          <p className="text-lg font-bold text-gray-900">
            ${(stats.todayCollections || 0).toLocaleString()}
          </p>
        </div>
      </div>

      {/* Truck Inventory */}
      <div className="card mb-4">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <Truck className="w-5 h-5 text-primary-600" />
            <h2 className="font-semibold text-gray-900">Truck Inventory</h2>
          </div>
          <button
            onClick={() => navigate('/truck')}
            className="text-xs text-primary-600 font-medium flex items-center gap-1"
          >
            View <ChevronRight className="w-3 h-3" />
          </button>
        </div>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <Box className="w-4 h-4 text-gray-400" />
            <span className="text-sm text-gray-600">
              {stats.truckItems} product{stats.truckItems !== 1 ? 's' : ''} loaded
            </span>
          </div>
          <span className="text-xs text-gray-400">Tap to manage</span>
        </div>
      </div>

      {/* Shift Controls */}
      <div className="card">
        <h2 className="font-semibold text-gray-900 mb-3">Shift</h2>
        {shiftActive ? (
          <div className="space-y-3">
            <div className="flex items-center gap-2 text-sm text-green-600 bg-green-50 rounded-lg px-3 py-2">
              <Clock className="w-4 h-4" />
              Shift is active
            </div>
            <button
              onClick={() => navigate('/shift/end')}
              className="btn-danger w-full flex items-center justify-center gap-2"
            >
              <Square className="w-4 h-4" />
              End Shift
            </button>
          </div>
        ) : (
          <button
            onClick={() => navigate('/shift/start')}
            className="btn-primary w-full flex items-center justify-center gap-2"
          >
            <Play className="w-4 h-4" />
            Start Shift
          </button>
        )}
      </div>
    </div>
  );
}
