import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../../services/api';

const badgeColors = {
  pending: 'bg-yellow-100 text-yellow-800',
  confirmed: 'bg-blue-100 text-blue-800',
  processing: 'bg-indigo-100 text-indigo-800',
  shipped: 'bg-indigo-100 text-indigo-800',
  in_transit: 'bg-cyan-100 text-cyan-800',
  delivered: 'bg-green-100 text-green-800',
  cancelled: 'bg-red-100 text-red-800',
};

export default function StoreOrders() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function load() {
      try {
        const res = await api.get('/store/orders');
        setOrders(res.data?.data || []);
      } catch (err) {
        console.error('Failed to load orders:', err);
      } finally {
        setLoading(false);
      }
    }
    load();
  }, []);

  if (loading) return <div className="p-6">Loading orders...</div>;

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">My Orders</h1>

      {orders.length === 0 ? (
        <p className="text-gray-500">No orders yet.</p>
      ) : (
        <div className="space-y-4">
          {orders.map((order) => (
            <Link
              key={order.id}
              to={`/store/orders/${order.id}`}
              className="border rounded-lg p-4 block hover:shadow-lg transition"
            >
              <div className="flex justify-between items-center mb-2">
                <h3 className="font-bold">{order.order_number}</h3>
                <span className={`px-3 py-1 rounded-full text-xs font-medium ${badgeColors[order.status] || 'bg-gray-100 text-gray-800'}`}>
                  {order.status.replace('_', ' ')}
                </span>
              </div>
              <div className="flex justify-between text-sm text-gray-500">
                <span>{new Date(order.created_at || order.order_date).toLocaleDateString()}</span>
                <span>{order.items_count || 0} item(s)</span>
                <span className="font-bold text-black">{order.total_amount} EGP</span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}
