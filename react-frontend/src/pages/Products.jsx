import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Pencil, Trash2, Package } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatCurrency } from '../utils/formatters'

const emptyProduct = { name: '', sku: '', description: '', selling_price: '', cost_price: '', category_id: '', unit: 'piece', min_stock_level: 0, is_expiry_tracked: true, shelf_life_days: 0 }

export default function Products() {
  const { t } = useTranslation()
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptyProduct)
  const [categoryFilter, setCategoryFilter] = useState('')
  const [categories, setCategories] = useState([])
  const [categoryMap, setCategoryMap] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    fetchProducts()
  }, [])

  const fetchProducts = async () => {
    try {
      const { data } = await api.get('/products')
      const results = data.results || data || []
      setProducts(results)
      const cats = [...new Set(results.map(p => (p.category?.name || p.category)).filter(Boolean))]
      setCategories(cats)
      const map = {}
      results.forEach(p => { if (p.category?.id && p.category?.name) map[p.category.name] = p.category.id })
      setCategoryMap(map)
    } catch (err) {
      toast.error(t('products.loadFailed'))
    } finally {
      setLoading(false)
    }
  }

  const filtered = categoryFilter ? products.filter(p => (p.category?.name || p.category) === categoryFilter) : products

  const openCreate = () => {
    setEditing(null)
    setForm(emptyProduct)
    setModalOpen(true)
  }

  const openEdit = (product) => {
    setEditing(product)
    setForm({
      name: product.name || '',
      sku: product.sku || '',
      description: product.description || '',
      selling_price: product.selling_price || '',
      cost_price: product.cost_price || '',
      category_id: product.category_id || '',
      unit: product.unit || 'piece',
      min_stock_level: product.min_stock_level || 0,
      is_expiry_tracked: product.is_expiry_tracked ?? true,
      shelf_life_days: product.shelf_life_days || 0,
    })
    setModalOpen(true)
  }

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const payload = {
        name: form.name,
        sku: form.sku,
        description: form.description || '',
        unit: form.unit,
        cost_price: parseFloat(form.cost_price) || 0,
        selling_price: parseFloat(form.selling_price) || 0,
        min_stock_level: parseInt(form.min_stock_level) || 0,
        is_expiry_tracked: form.is_expiry_tracked,
        shelf_life_days: parseInt(form.shelf_life_days) || 0,
        category_id: form.category_id || null,
      }
      if (editing) {
        await api.put(`/products/${editing.id}`, payload)
        toast.success(t('products.updated'))
      } else {
        await api.post('/products', payload)
        toast.success(t('products.created'))
      }
      setModalOpen(false)
      fetchProducts()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  const handleDelete = async (product) => {
    if (!confirm(t('products.deleteConfirm'))) return
    try {
      await api.delete(`/products/${product.id}`)
      toast.success(t('products.deleted'))
      fetchProducts()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const columns = [
    { header: t('products.name'), accessor: 'name' },
    { header: t('products.sku'), accessor: 'sku' },
    { header: t('products.category'), accessor: 'category', render: (r) => r.category?.name || r.category || '-' },
    { header: t('products.price'), accessor: 'selling_price', render: (r) => formatCurrency(r.selling_price) },
    { header: t('products.cost'), accessor: 'cost_price', render: (r) => formatCurrency(r.cost_price) },
    { header: t('products.unit'), accessor: 'unit' },
    {
      header: t('products.actions'),
      accessor: 'actions',
      sortable: false,
      render: (r) => (
        <div className="flex items-center gap-2">
          <button onClick={() => openEdit(r)} className="p-1.5 rounded-lg hover:bg-gray-100 text-gray-600">
            <Pencil size={15} />
          </button>
          <button onClick={() => handleDelete(r)} className="p-1.5 rounded-lg hover:bg-red-50 text-red-500">
            <Trash2 size={15} />
          </button>
        </div>
      ),
    },
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('products.title')}</h1>
          <p className="text-gray-500 mt-1">{t('products.subtitle')}</p>
        </div>
        <button className="btn-primary" onClick={openCreate}>
          <Plus size={18} />
          {t('products.addProduct')}
        </button>
      </div>

      <div className="card">
        {categories.length > 0 && (
          <div className="flex items-center gap-2 mb-4 flex-wrap">
            <span className="text-sm text-gray-500">{t('products.filter')}</span>
            <button
              className={`px-3 py-1 rounded-full text-sm ${!categoryFilter ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
              onClick={() => setCategoryFilter('')}
            >{t('products.all')}</button>
            {categories.map(cat => (
              <button
                key={cat}
                className={`px-3 py-1 rounded-full text-sm ${categoryFilter === cat ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
                onClick={() => setCategoryFilter(cat)}
              >{cat}</button>
            ))}
          </div>
        )}
        <DataTable columns={columns} data={filtered} loading={loading} searchPlaceholder={t('products.search')} />
      </div>

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editing ? t('products.editProduct') : t('products.createProduct')} size="lg">
        <form onSubmit={handleSave} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.nameLabel')} *</label>
              <input className="input-field" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.skuLabel')} *</label>
              <input className="input-field" value={form.sku} onChange={e => setForm(f => ({ ...f, sku: e.target.value }))} required />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.categoryLabel')}</label>
              <select className="select-field" value={form.category_id} onChange={e => setForm(f => ({ ...f, category_id: parseInt(e.target.value) || '' }))}>
                <option value="">{t('products.selectCategory')}</option>
                {Object.entries(categoryMap).map(([name, id]) => (
                  <option key={id} value={id}>{name}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.sellingPriceLabel')} *</label>
              <input type="number" step="0.01" className="input-field" value={form.selling_price} onChange={e => setForm(f => ({ ...f, selling_price: e.target.value }))} required />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.costPriceLabel')}</label>
              <input type="number" step="0.01" className="input-field" value={form.cost_price} onChange={e => setForm(f => ({ ...f, cost_price: e.target.value }))} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.unitLabel')}</label>
              <select className="select-field" value={form.unit} onChange={e => setForm(f => ({ ...f, unit: e.target.value }))}>
                <option value="piece">{t('products.piece')}</option>
                <option value="kg">{t('products.kg')}</option>
                <option value="liter">{t('products.liter')}</option>
                <option value="box">{t('products.box')}</option>
                <option value="pack">{t('products.pack')}</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.minStockLabel')}</label>
              <input type="number" className="input-field" value={form.min_stock_level} onChange={e => setForm(f => ({ ...f, min_stock_level: parseInt(e.target.value) || 0 }))} />
            </div>
            <div className="col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.descriptionLabel')}</label>
              <textarea className="input-field" rows={3} value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} />
            </div>
          </div>
          <div className="flex justify-end gap-2 pt-4">
            <button type="button" className="btn-secondary" onClick={() => setModalOpen(false)}>{t('products.cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>
              {saving ? t('products.saving') : editing ? t('products.update') : t('products.create')}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
