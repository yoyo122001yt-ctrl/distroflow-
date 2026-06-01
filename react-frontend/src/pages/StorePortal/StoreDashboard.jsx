import { useState, useEffect } from 'react';
import api from '../../services/api';

export default function StoreDashboard() {
  const [stats, setStats] = useState(null);
  const [popularProducts, setPopularProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function load() {
      try {
        const [statsRes, productsRes] = await Promise.all([
          api.get('/dashboard/stats').catch(() => ({ data: {} })),
          api.get('/store/products/popular').catch(() => ({ data: [] })),
        ]);
        setStats(statsRes.data?.data || statsRes.data || {});
        setPopularProducts(productsRes.data?.data || []);
      } catch (err) {
        console.error('Failed to load dashboard:', err);
      } finally {
        setLoading(false);
      }
    }
    load();
  }, []);

  if (loading) return <div className="p-6">Loading dashboard...</div>;

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">Store Dashboard</h1>
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <StatCard label="Total Orders" value={stats.totalOrders || 0} />
        <StatCard label="Pending" value={stats.pendingOrders || 0} />
        <StatCard label="Delivered" value={stats.deliveredOrders || 0} />
        <StatCard label="Credit Balance" value={`${(stats.creditBalance || 0).toFixed(2)} EGP`} />
      </div>
      <div>
        <h2 className="text-xl font-semibold mb-4">Popular Products</h2>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          {popularProducts.map((product) => (
            <div key={product.id} className="border rounded-lg p-4 hover:shadow-lg transition">
              {product.image_url && (
                <img src={product.image_url} alt={product.name} className="w-full h-32 object-cover rounded mb-2" />
              )}
              <h3 className="font-medium">{product.name}</h3>
              <p className="text-blue-600 font-bold">{product.selling_price} EGP</p>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

function StatCard({ label, value }) {
  return (
    <div className="bg-white rounded-lg shadow p-4">
      <p className="text-gray-500 text-sm">{label}</p>
      <p className="text-2xl font-bold">{value}</p>
    </div>
  );
}
