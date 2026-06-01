import { useState, useEffect } from 'react';
import api from '../../services/api';

export default function StoreProducts() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const [categories, setCategories] = useState([]);
  const [cartCount, setCartCount] = useState(0);
  const [addingId, setAddingId] = useState(null);

  useEffect(() => {
    async function load() {
      try {
        const params = {};
        if (search) params.search = search;
        if (category) params.category_id = category;
        const [prodRes, catRes] = await Promise.all([
          api.get('/store/products', { params }),
          api.get('/store/categories'),
        ]);
        setProducts(prodRes.data?.data || []);
        setCategories(catRes.data?.data || []);
      } catch (err) {
        console.error('Failed to load products:', err);
      } finally {
        setLoading(false);
      }
    }
    load();
  }, [search, category]);

  async function addToCart(productId) {
    setAddingId(productId);
    try {
      const res = await api.post('/store/cart/add', { product_id: productId, quantity: 1 });
      setCartCount(res.data?.data?.count || 0);
    } catch (err) {
      console.error('Failed to add to cart:', err);
    } finally {
      setAddingId(null);
    }
  }

  if (loading) return <div className="p-6">Loading products...</div>;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Products</h1>
        <span className="bg-blue-600 text-white px-3 py-1 rounded-full text-sm">
          Cart: {cartCount}
        </span>
      </div>

      <div className="flex gap-4 mb-6">
        <input
          type="text"
          placeholder="Search products..."
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="flex-1 border rounded-lg px-4 py-2"
        />
        <select
          value={category}
          onChange={(e) => setCategory(e.target.value)}
          className="border rounded-lg px-4 py-2"
        >
          <option value="">All Categories</option>
          {categories.map((cat) => (
            <option key={cat.id} value={cat.id}>{cat.name}</option>
          ))}
        </select>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
        {products.map((product) => (
          <div key={product.id} className="border rounded-lg p-4 hover:shadow-lg transition">
            {product.image_url && (
              <img src={product.image_url} alt={product.name} className="w-full h-40 object-cover rounded mb-3" />
            )}
            <h3 className="font-medium text-sm">{product.name}</h3>
            <p className="text-gray-500 text-xs">{product.sku}</p>
            <p className="text-blue-600 font-bold mt-2">{product.selling_price} EGP</p>
            <button
              onClick={() => addToCart(product.id)}
              disabled={addingId === product.id}
              className="mt-3 w-full bg-blue-600 text-white rounded-lg py-2 text-sm hover:bg-blue-700 disabled:opacity-50"
            >
              {addingId === product.id ? 'Adding...' : 'Add to Cart'}
            </button>
          </div>
        ))}
      </div>
    </div>
  );
}
