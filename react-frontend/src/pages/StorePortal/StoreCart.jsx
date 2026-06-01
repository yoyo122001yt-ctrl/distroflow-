import { useState, useEffect } from 'react';
import api from '../../services/api';

export default function StoreCart() {
  const [cart, setCart] = useState(null);
  const [loading, setLoading] = useState(true);
  const [placing, setPlacing] = useState(false);
  const [deliveryDate, setDeliveryDate] = useState('');

  useEffect(() => {
    loadCart();
  }, []);

  async function loadCart() {
    try {
      const res = await api.get('/store/cart');
      setCart(res.data?.data || res.data);
    } catch (err) {
      console.error('Failed to load cart:', err);
    } finally {
      setLoading(false);
    }
  }

  async function updateQuantity(productId, quantity) {
    try {
      const res = await api.post('/store/cart/update', { items: cart.items.map(item =>
        item.product_id === productId ? { ...item, quantity: Math.max(0, quantity) } : item
      )});
      if (quantity === 0) {
        await api.delete(`/store/cart/item/${productId}`);
      }
      await loadCart();
    } catch (err) {
      console.error('Failed to update cart:', err);
    }
  }

  async function removeItem(productId) {
    try {
      await api.delete(`/store/cart/item/${productId}`);
      await loadCart();
    } catch (err) {
      console.error('Failed to remove item:', err);
    }
  }

  async function placeOrder() {
    setPlacing(true);
    try {
      const payload = {};
      if (deliveryDate) payload.delivery_date = deliveryDate;
      const res = await api.post('/store/orders/place', payload);
      alert('Order placed successfully!');
      setCart({ items: [], total: 0, count: 0 });
    } catch (err) {
      alert('Failed to place order: ' + (err.response?.data?.message || err.message));
    } finally {
      setPlacing(false);
    }
  }

  if (loading) return <div className="p-6">Loading cart...</div>;

  if (!cart || !cart.items || cart.items.length === 0) {
    return (
      <div className="p-6 text-center">
        <h1 className="text-2xl font-bold mb-4">Shopping Cart</h1>
        <p className="text-gray-500">Your cart is empty.</p>
      </div>
    );
  }

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">Shopping Cart ({cart.count} items)</h1>

      <div className="space-y-4">
        {cart.items.map((item) => (
          <div key={item.product_id} className="border rounded-lg p-4 flex items-center justify-between">
            <div>
              <h3 className="font-medium">{item.name}</h3>
              <p className="text-gray-500 text-sm">{item.sku}</p>
              <p className="text-blue-600 font-bold">{item.unit_price} EGP</p>
            </div>
            <div className="flex items-center gap-3">
              <button
                onClick={() => updateQuantity(item.product_id, item.quantity - 1)}
                className="w-8 h-8 rounded-full border"
              >-</button>
              <span className="font-medium">{item.quantity}</span>
              <button
                onClick={() => updateQuantity(item.product_id, item.quantity + 1)}
                className="w-8 h-8 rounded-full border"
              >+</button>
              <button
                onClick={() => removeItem(item.product_id)}
                className="text-red-500 text-sm ml-4"
              >Remove</button>
            </div>
            <div className="font-bold">{item.subtotal.toFixed(2)} EGP</div>
          </div>
        ))}
      </div>

      <div className="mt-6 border-t pt-4">
        <div className="flex justify-between items-center mb-4">
          <span className="text-xl font-bold">Total:</span>
          <span className="text-2xl font-bold text-blue-600">{cart.total.toFixed(2)} EGP</span>
        </div>

        <div className="flex gap-4 items-end">
          <div>
            <label className="block text-sm text-gray-600 mb-1">Delivery Date (optional)</label>
            <input
              type="date"
              value={deliveryDate}
              onChange={(e) => setDeliveryDate(e.target.value)}
              className="border rounded-lg px-4 py-2"
            />
          </div>
          <button
            onClick={placeOrder}
            disabled={placing}
            className="bg-blue-600 text-white rounded-lg px-8 py-3 font-bold hover:bg-blue-700 disabled:opacity-50"
          >
            {placing ? 'Placing Order...' : 'Place Order'}
          </button>
        </div>
      </div>
    </div>
  );
}
