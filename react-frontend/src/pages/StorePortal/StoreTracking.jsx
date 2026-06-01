import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import api from '../../services/api';
import { subscribeToDriverLocation } from '../../services/websocket';

export default function StoreTracking() {
  const { deliveryId } = useParams();
  const [delivery, setDelivery] = useState(null);
  const [driverLocation, setDriverLocation] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function load() {
      try {
        const res = await api.get(`/store/track/${deliveryId}`);
        const data = res.data?.data || res.data;
        setDelivery(data);

        if (data.driver?.driver_locations?.length) {
          setDriverLocation(data.driver.driver_locations[data.driver.driver_locations.length - 1]);
        }

        if (data.driver?.id) {
          const unsubscribe = subscribeToDriverLocation(data.driver.id, (location) => {
            setDriverLocation(location);
          });
          return unsubscribe;
        }
      } catch (err) {
        console.error('Failed to load delivery:', err);
      } finally {
        setLoading(false);
      }
    }
    const cleanup = load();
    return () => {
      if (cleanup && typeof cleanup.then === 'function') {
        cleanup.then((unsub) => unsub?.());
      }
    };
  }, [deliveryId]);

  if (loading) return <div className="p-6">Loading delivery tracking...</div>;
  if (!delivery) return <div className="p-6">Delivery not found.</div>;

  const statusSteps = ['pending', 'assigned', 'in_transit', 'completed'];
  const currentIdx = statusSteps.indexOf(delivery.status);

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-4">Track Delivery #{delivery.id}</h1>

      {delivery.driver && (
        <div className="bg-blue-50 rounded-lg p-4 mb-6 flex items-center gap-4">
          <div className="w-12 h-12 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-lg">
            {delivery.driver.name.charAt(0).toUpperCase()}
          </div>
          <div>
            <p className="font-bold">{delivery.driver.name}</p>
            <p className="text-sm text-gray-600">{delivery.driver.phone || 'No phone'}</p>
          </div>
        </div>
      )}

      <div className="mb-6">
        <div className="flex items-center justify-between mb-4">
          {statusSteps.map((step, idx) => (
            <div key={step} className="flex flex-col items-center flex-1">
              <div className={`w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold ${
                idx <= currentIdx ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-500'
              }`}>
                {idx < currentIdx ? '\u2713' : idx + 1}
              </div>
              <p className={`text-xs mt-1 ${idx <= currentIdx ? 'text-green-600 font-medium' : 'text-gray-400'}`}>
                {step.replace('_', ' ')}
              </p>
            </div>
          ))}
        </div>
      </div>

      <div className="border rounded-lg p-4 mb-6">
        <h3 className="font-semibold mb-2">Driver Location</h3>
        {driverLocation ? (
          <div className="text-sm text-gray-600">
            <p>Lat: {driverLocation.lat || driverLocation.latitude}</p>
            <p>Lng: {driverLocation.lng || driverLocation.longitude}</p>
            <p className="text-xs text-gray-400 mt-1">
              Last updated: {new Date(driverLocation.updated_at || driverLocation.created_at).toLocaleTimeString()}
            </p>
          </div>
        ) : (
          <p className="text-sm text-gray-500">Waiting for location data...</p>
        )}
      </div>

      {delivery.stops?.length > 0 && (
        <div className="border rounded-lg p-4">
          <h3 className="font-semibold mb-3">Route Stops</h3>
          <ol className="list-decimal pl-5 space-y-2">
            {delivery.stops.map((stop) => (
              <li key={stop.id} className="text-sm">
                {stop.address || `Stop #${stop.id}`}
                <span className={`ml-2 px-2 py-0.5 rounded text-xs ${
                  stop.status === 'completed' ? 'bg-green-100 text-green-700' :
                  stop.status === 'in_progress' ? 'bg-blue-100 text-blue-700' :
                  'bg-gray-100 text-gray-600'
                }`}>
                  {stop.status?.replace('_', ' ')}
                </span>
              </li>
            ))}
          </ol>
        </div>
      )}
    </div>
  );
}
