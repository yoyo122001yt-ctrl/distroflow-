import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import api from '../../services/api';

export default function StoreOrderDetail() {
  const { id } = useParams();
  const [order, setOrder] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function load() {
      try {
        const res = await api.get(`/store/orders/${id}`);
        setOrder(res.data?.data || res.data);
      } catch (err) {
        console.error('Failed to load order:', err);
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [id]);

  if (loading) return <div className="p-6">Loading order...</div>;
  if (!order) return <div className="p-6">Order not found.</div>;

  return (
    <div className="p-6">
      <Link to="/store/orders" className="text-blue-600 text-sm mb-4 block">&larr; Back to Orders</Link>
      <h1 className="text-2xl font-bold mb-4">Order {order.order_number}</h1>

      <div className="grid grid-cols-2 gap-4 mb-6">
        <div className="border rounded-lg p-4">
          <p className="text-gray-500 text-sm">Status</p>
          <p className="font-bold">{order.status}</p>
        </div>
        <div className="border rounded-lg p-4">
          <p className="text-gray-500 text-sm">Total</p>
          <p className="font-bold text-blue-600">{order.total_amount} EGP</p>
        </div>
        <div className="border rounded-lg p-4">
          <p className="text-gray-500 text-sm">Date</p>
          <p>{new Date(order.order_date).toLocaleDateString()}</p>
        </div>
        <div className="border rounded-lg p-4">
          <p className="text-gray-500 text-sm">Delivery Date</p>
          <p>{order.requested_delivery_date ? new Date(order.requested_delivery_date).toLocaleDateString() : 'Not set'}</p>
        </div>
      </div>

      <h2 className="text-xl font-semibold mb-3">Items</h2>
      <div className="border rounded-lg overflow-hidden mb-6">
        <table className="w-full">
          <thead className="bg-gray-50">
            <tr>
              <th className="text-left p-3">Product</th>
              <th className="text-center p-3">Qty</th>
              <th className="text-right p-3">Price</th>
              <th className="text-right p-3">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            {order.items?.map((item, i) => (
              <tr key={i} className="border-t">
                <td className="p-3">{item.product_name}</td>
                <td className="p-3 text-center">{item.quantity}</td>
                <td className="p-3 text-right">{item.unit_price} EGP</td>
                <td className="p-3 text-right">{item.subtotal} EGP</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {order.deliveries?.length > 0 && (
        <div>
          <h2 className="text-xl font-semibold mb-3">Deliveries</h2>
          {order.deliveries.map((delivery) => (
            <div key={delivery.id} className="border rounded-lg p-4 mb-2 flex justify-between items-center">
              <div>
                <p className="font-medium">Delivery #{delivery.id}</p>
                <p className="text-sm text-gray-500">{new Date(delivery.created_at).toLocaleDateString()}</p>
              </div>
              <div className="text-right">
                <span className="capitalize">{delivery.status}</span>
                {delivery.driver && <p className="text-sm text-gray-500">{delivery.driver.name}</p>}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
