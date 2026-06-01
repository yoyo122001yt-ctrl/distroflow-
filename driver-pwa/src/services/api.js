import axios from 'axios';
import toast from 'react-hot-toast';

const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
  },
  timeout: 15000,
});

let failedQueue = [];
let isRefreshing = false;

api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('df_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    if (!navigator.onLine) {
      config._offline = true;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;

    if (error.response?.status === 401 && !originalRequest._retry) {
      if (isRefreshing) {
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        }).then((token) => {
          originalRequest.headers.Authorization = `Bearer ${token}`;
          return api(originalRequest);
        });
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        const refreshToken = localStorage.getItem('df_refresh_token');
        if (refreshToken) {
          const { data } = await axios.post('/api/auth/refresh', {
            refresh_token: refreshToken,
          });
          localStorage.setItem('df_token', data.token);
          processQueue(null, data.token);
          originalRequest.headers.Authorization = `Bearer ${data.token}`;
          return api(originalRequest);
        }
      } catch (refreshError) {
        processQueue(refreshError, null);
        localStorage.removeItem('df_token');
        localStorage.removeItem('df_user');
        window.location.href = '/login';
        return Promise.reject(refreshError);
      } finally {
        isRefreshing = false;
      }
    }

    if (!navigator.onLine || error.code === 'ERR_NETWORK') {
      toast.error('You are offline. Data will sync when connected.', {
        duration: 4000,
      });
    }

    return Promise.reject(error);
  }
);

function processQueue(error, token = null) {
  failedQueue.forEach((prom) => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token);
    }
  });
  failedQueue = [];
}

export const setAuthToken = (token) => {
  if (token) {
    localStorage.setItem('df_token', token);
  } else {
    localStorage.removeItem('df_token');
  }
};

export const getAuthToken = () => localStorage.getItem('df_token');

export default api;
