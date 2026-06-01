import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const WS_HOST = import.meta.env.VITE_WS_HOST || 'localhost';
const WS_PORT = import.meta.env.VITE_WS_PORT || 6001;
const WS_SCHEME = import.meta.env.VITE_WS_SCHEME || 'http';

let echoInstance = null;
const channelCallbacks = new Map();
let reconnectAttempts = 0;
const MAX_RECONNECT_ATTEMPTS = 10;

function getEcho() {
  if (echoInstance) return echoInstance;

  const options = {
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY || 'distroflow-key',
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
    forceTLS: false,
    wsHost: WS_HOST,
    wsPort: parseInt(WS_PORT),
    wssPort: parseInt(WS_PORT),
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${localStorage.getItem('token')}`,
      },
    },
  };

  window.Pusher = Pusher;
  echoInstance = new Echo(options);

  echoInstance.connector.pusher.connection.bind('connected', () => {
    reconnectAttempts = 0;
  });

  echoInstance.connector.pusher.connection.bind('disconnected', () => {
    scheduleReconnect();
  });

  return echoInstance;
}

function scheduleReconnect() {
  if (reconnectAttempts >= MAX_RECONNECT_ATTEMPTS) return;
  const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000);
  reconnectAttempts++;
  setTimeout(() => {
    try {
      getEcho();
    } catch (e) {
      scheduleReconnect();
    }
  }, delay);
}

export function subscribeToDriverLocation(driverId, callback) {
  const channel = getEcho().channel(`driver.${driverId}`);
  channel.listen('.location.updated', (data) => {
    callback(data);
  });
  channelCallbacks.set(`driver.${driverId}`, callback);
  return () => unsubscribe(`driver.${driverId}`);
}

export function subscribeToStoreDelivery(storeId, callback) {
  const channel = getEcho().private(`store.${storeId}`);
  channel.listen('.delivery.status_changed', (data) => {
    callback(data);
  });
  channelCallbacks.set(`store.${storeId}`, callback);
  return () => unsubscribe(`store.${storeId}`);
}

export function subscribeToChannel(name, event, callback) {
  const isPrivate = name.startsWith('private-');
  const channel = isPrivate
    ? getEcho().private(name.replace('private-', ''))
    : getEcho().channel(name);
  channel.listen(event, callback);
  channelCallbacks.set(`${name}:${event}`, { channel, callback });
  return () => {
    channel.stopListening(event);
    channelCallbacks.delete(`${name}:${event}`);
  };
}

export function unsubscribe(name) {
  const callback = channelCallbacks.get(name);
  if (callback && typeof callback === 'function') {
    const channel = getEcho().channel(name);
    channel.stopListening('.location.updated');
    channel.stopListening('.delivery.status_changed');
    channelCallbacks.delete(name);
  }
}

export function disconnect() {
  if (echoInstance) {
    echoInstance.disconnect();
    echoInstance = null;
  }
  channelCallbacks.clear();
}

export function getConnectionStatus() {
  if (!echoInstance) return 'disconnected';
  const state = echoInstance.connector.pusher.connection.state;
  return state;
}

let heartbeatInterval = null;

export function startHeartbeat(intervalMs = 30000) {
  stopHeartbeat();
  heartbeatInterval = setInterval(() => {
    const status = getConnectionStatus();
    if (status === 'disconnected' || status === 'failed') {
      scheduleReconnect();
    }
  }, intervalMs);
}

export function stopHeartbeat() {
  if (heartbeatInterval) {
    clearInterval(heartbeatInterval);
    heartbeatInterval = null;
  }
}
