import React, { useEffect, useState } from 'react';
import { Routes, Route, Navigate, useNavigate, useLocation } from 'react-router-dom';
import {
  LayoutDashboard,
  MapPin,
  Truck,
  LogIn,
  Sun,
  Moon,
  Wifi,
  WifiOff,
} from 'lucide-react';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';
import RouteStops from './pages/RouteStops';
import StopDetail from './pages/StopDetail';
import TruckInventory from './pages/TruckInventory';
import ShiftStart from './pages/ShiftStart';
import ShiftEnd from './pages/ShiftEnd';
import OfflineSync from './pages/OfflineSync';
import useOffline from './hooks/useOffline';
import { getAuthToken } from './services/api';
import { getPendingCounts } from './services/offlineStorage';

function ProtectedRoute({ children }) {
  const token = getAuthToken();
  if (!token) {
    return <Navigate to="/login" replace />;
  }
  return children;
}

function BottomNav() {
  const navigate = useNavigate();
  const location = useLocation();
  const [pendingSync, setPendingSync] = useState(0);
  const { isOnline } = useOffline();

  useEffect(() => {
    const checkPending = async () => {
      try {
        const counts = await getPendingCounts();
        setPendingSync(counts.total);
      } catch {}
    };
    checkPending();
    const interval = setInterval(checkPending, 10000);
    return () => clearInterval(interval);
  }, []);

  const navItems = [
    {
      path: '/',
      icon: LayoutDashboard,
      label: 'Dashboard',
    },
    {
      path: '/stops',
      icon: MapPin,
      label: 'Stops',
      badge: null,
    },
    {
      path: '/truck',
      icon: Truck,
      label: 'Truck',
    },
    {
      path: '/sync',
      icon: isOnline ? Wifi : WifiOff,
      label: 'Sync',
      count: pendingSync,
    },
  ];

  const isActive = (path) => {
    if (path === '/') return location.pathname === '/';
    return location.pathname.startsWith(path);
  };

  return (
    <nav className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 pb-safe">
      <div className="max-w-lg mx-auto flex items-center justify-around h-16">
        {navItems.map((item) => {
          const Icon = item.icon;
          const active = isActive(item.path);
          return (
            <button
              key={item.path}
              onClick={() => navigate(item.path)}
              className={`relative flex flex-col items-center justify-center w-full h-full px-2 transition-colors ${
                active ? 'text-primary-600' : 'text-gray-400 hover:text-gray-600'
              }`}
            >
              <div className="relative">
                <Icon className="w-5 h-5" />
                {(item.count || item.count === 0) && item.count > 0 && (
                  <span className="absolute -top-2 -right-2 bg-red-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
                    {item.count > 99 ? '99+' : item.count}
                  </span>
                )}
                {!isOnline && item.path === '/sync' && (
                  <span className="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full" />
                )}
              </div>
              <span className="text-[10px] mt-1 font-medium">{item.label}</span>
            </button>
          );
        })}
      </div>
    </nav>
  );
}

function AppLayout({ children }) {
  return (
    <div className="min-h-screen bg-gray-50 pb-16">
      {children}
      <BottomNav />
    </div>
  );
}

export default function App() {
  const [serviceWorkerReady, setServiceWorkerReady] = useState(false);

  useEffect(() => {
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', async () => {
        try {
          const registration = await navigator.serviceWorker.register('/sw.js', {
            scope: '/',
          });
          console.log('SW registered:', registration.scope);
          setServiceWorkerReady(true);

          registration.addEventListener('updatefound', () => {
            const newWorker = registration.installing;
            if (newWorker) {
              newWorker.addEventListener('statechange', () => {
                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                  if (confirm('New version available! Reload to update?')) {
                    newWorker.postMessage({ type: 'SKIP_WAITING' });
                    window.location.reload();
                  }
                }
              });
            }
          });
        } catch (err) {
          console.error('SW registration failed:', err);
        }
      });
    }
  }, []);

  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        path="/"
        element={
          <ProtectedRoute>
            <AppLayout>
              <Dashboard />
            </AppLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/stops"
        element={
          <ProtectedRoute>
            <AppLayout>
              <RouteStops />
            </AppLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/stops/:id"
        element={
          <ProtectedRoute>
            <AppLayout>
              <StopDetail />
            </AppLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/truck"
        element={
          <ProtectedRoute>
            <AppLayout>
              <TruckInventory />
            </AppLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/shift/start"
        element={
          <ProtectedRoute>
            <AppLayout>
              <ShiftStart />
            </AppLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/shift/end"
        element={
          <ProtectedRoute>
            <AppLayout>
              <ShiftEnd />
            </AppLayout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/sync"
        element={
          <ProtectedRoute>
            <AppLayout>
              <OfflineSync />
            </AppLayout>
          </ProtectedRoute>
        }
      />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
