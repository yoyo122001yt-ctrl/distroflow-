import { useState, useCallback, useRef, useEffect } from 'react';
import api from '../services/api';
import { saveCachedData, getCachedData } from '../services/offlineStorage';

export default function useApi(cacheKey = null) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const mountedRef = useRef(true);

  useEffect(() => {
    return () => {
      mountedRef.current = false;
    };
  }, []);

  const loadCached = useCallback(async () => {
    if (!cacheKey) return null;
    try {
      const cached = await getCachedData(cacheKey);
      return cached?.data || null;
    } catch {
      return null;
    }
  }, [cacheKey]);

  const execute = useCallback(
    async (config = {}) => {
      const {
        method = 'get',
        url,
        data: body,
        params,
        headers,
        onSuccess,
        onError,
        skipCache = false,
      } = config;

      if (!url) {
        setError(new Error('URL is required'));
        return null;
      }

      setLoading(true);
      setError(null);

      if (!skipCache && cacheKey && method === 'get') {
        const cached = await loadCached();
        if (cached) {
          setData(cached);
        }
      }

      try {
        const response = await api({
          method,
          url,
          data: body,
          params,
          headers,
        });

        if (mountedRef.current) {
          setData(response.data);
          setLoading(false);
          setError(null);

          if (cacheKey && method === 'get') {
            await saveCachedData(cacheKey, response.data);
          }

          if (onSuccess) onSuccess(response.data);
        }

        return response.data;
      } catch (err) {
        if (mountedRef.current) {
          const cached = await loadCached();
          if (cached && !data) {
            setData(cached);
          }

          const errorMessage =
            err.response?.data?.message ||
            err.response?.data?.detail ||
            err.message ||
            'An error occurred';

          setError(errorMessage);
          setLoading(false);

          if (onError) onError(err);
        }
        return null;
      }
    },
    [cacheKey, data, loadCached]
  );

  const refresh = useCallback(
    async (url) => {
      if (!url && cacheKey) {
        const cached = await loadCached();
        if (cached) {
          setData(cached);
        }
      }
    },
    [cacheKey, loadCached]
  );

  const reset = useCallback(() => {
    setData(null);
    setLoading(false);
    setError(null);
  }, []);

  return { data, loading, error, execute, refresh, reset };
}
