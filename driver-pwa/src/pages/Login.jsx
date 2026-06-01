import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Truck, Eye, EyeOff, Wifi, WifiOff, Download } from 'lucide-react';
import toast from 'react-hot-toast';
import { setAuthToken, getAuthToken } from '../services/api';
import { loginOffline } from '../services/syncManager';
import { saveAuthData } from '../services/offlineStorage';

export default function Login() {
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [deferredPrompt, setDeferredPrompt] = useState(null);
  const [isInstallable, setIsInstallable] = useState(false);

  useEffect(() => {
    if (getAuthToken()) {
      navigate('/', { replace: true });
    }
  }, [navigate]);

  useEffect(() => {
    const handler = (e) => {
      e.preventDefault();
      setDeferredPrompt(e);
      setIsInstallable(true);
    };
    window.addEventListener('beforeinstallprompt', handler);
    return () => window.removeEventListener('beforeinstallprompt', handler);
  }, []);

  const handleInstall = async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    const result = await deferredPrompt.userChoice;
    if (result.outcome === 'accepted') {
      toast.success('App installed!');
      setIsInstallable(false);
    }
    setDeferredPrompt(null);
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    if (!email.trim() || !password.trim()) {
      toast.error('Please enter email and password');
      return;
    }

    setLoading(true);
    try {
      const result = await loginOffline(email, password);

      if (result.offline) {
        const token = `offline_${Date.now()}`;
        setAuthToken(token);
        await saveAuthData('offline_session', true);
        toast.success('Logged in with cached credentials (offline mode)');
      } else {
        setAuthToken(result.token);
        if (result.refresh_token) {
          localStorage.setItem('df_refresh_token', result.refresh_token);
        }
        localStorage.setItem('df_user', JSON.stringify(result.user));
        await saveAuthData('last_user', result.user);
        toast.success('Welcome back!');
      }

      navigate('/', { replace: true });
    } catch (err) {
      const message =
        err.response?.data?.message ||
        err.response?.data?.detail ||
        err.message ||
        'Login failed';

      if (!navigator.onLine) {
        toast.error('No cached credentials available. Connect to internet to login.');
      } else {
        toast.error(message);
      }
    } finally {
      setLoading(false);
    }
  };

  const isOnline = navigator.onLine;

  return (
    <div className="min-h-screen bg-gradient-to-br from-primary-600 to-primary-900 flex flex-col">
      <div className="flex-1 flex flex-col items-center justify-center px-6">
        <div className="w-20 h-20 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center mb-6">
          <Truck className="w-10 h-10 text-white" />
        </div>

        <h1 className="text-3xl font-bold text-white mb-1">DistroFlow</h1>
        <p className="text-primary-200 text-sm mb-8">Driver Portal</p>

        <div className="w-full max-w-sm">
          <div className="bg-white/10 backdrop-blur-md rounded-2xl p-6 shadow-xl">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-white text-lg font-semibold">Sign In</h2>
              <div className="flex items-center gap-1.5">
                {isOnline ? (
                  <Wifi className="w-4 h-4 text-green-300" />
                ) : (
                  <WifiOff className="w-4 h-4 text-yellow-300" />
                )}
                <span className={`text-xs ${isOnline ? 'text-green-300' : 'text-yellow-300'}`}>
                  {isOnline ? 'Online' : 'Offline'}
                </span>
              </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-primary-100 mb-1.5">
                  Email Address
                </label>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="driver@example.com"
                  className="w-full px-4 py-2.5 bg-white/10 border border-white/20 rounded-xl text-white placeholder-primary-300
                           focus:outline-none focus:ring-2 focus:ring-white/40 focus:border-transparent transition-all"
                  autoComplete="email"
                  disabled={loading}
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-primary-100 mb-1.5">
                  Password
                </label>
                <div className="relative">
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="••••••••"
                    className="w-full px-4 py-2.5 bg-white/10 border border-white/20 rounded-xl text-white placeholder-primary-300
                             focus:outline-none focus:ring-2 focus:ring-white/40 focus:border-transparent transition-all pr-10"
                    autoComplete="current-password"
                    disabled={loading}
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-primary-200 hover:text-white"
                    tabIndex={-1}
                  >
                    {showPassword ? (
                      <EyeOff className="w-4 h-4" />
                    ) : (
                      <Eye className="w-4 h-4" />
                    )}
                  </button>
                </div>
              </div>

              <button
                type="submit"
                disabled={loading}
                className="w-full py-3 bg-white text-primary-700 font-semibold rounded-xl
                         hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-white/50
                         disabled:opacity-60 disabled:cursor-not-allowed transition-all active:scale-[0.98]"
              >
                {loading ? (
                  <span className="flex items-center justify-center gap-2">
                    <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" fill="none" />
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Signing in...
                  </span>
                ) : (
                  'Sign In'
                )}
              </button>
            </form>
          </div>

          {!isOnline && (
            <div className="mt-4 bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-3">
              <p className="text-yellow-200 text-xs text-center">
                You are offline. Sign in with cached credentials if available.
              </p>
            </div>
          )}
        </div>
      </div>

      <div className="pb-8 px-6">
        {isInstallable && (
          <button
            onClick={handleInstall}
            className="w-full max-w-sm mx-auto flex items-center justify-center gap-2 py-3
                     bg-white/10 backdrop-blur border border-white/20 rounded-xl text-white font-medium
                     hover:bg-white/20 transition-all active:scale-[0.98]"
          >
            <Download className="w-4 h-4" />
            Install App
          </button>
        )}
      </div>
    </div>
  );
}
