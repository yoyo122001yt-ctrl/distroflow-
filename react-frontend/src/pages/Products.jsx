import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Pencil, Trash2, Languages } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatCurrency } from '../utils/formatters'

const emptyProduct = { name: '', name_ar: '', sku: '', description: '', description_ar: '', selling_price: '', cost_price: '', category_id: '', unit: 'piece', min_stock_level: 0, is_expiry_tracked: true, shelf_life_days: 0 }

export default function Products() {
  const { t, i18n } = useTranslation()
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptyProduct)
  const [categoryFilter, setCategoryFilter] = useState('')
  const [categories, setCategories] = useState([])
  const [categoryList, setCategoryList] = useState([])
  const [saving, setSaving] = useState(false)
  const [showNewCategory, setShowNewCategory] = useState(false)
  const [newCatName, setNewCatName] = useState('')
  const [newCatNameAr, setNewCatNameAr] = useState('')

  useEffect(() => {
    fetchProducts()
    fetchCategories()
  }, [])

  const catDisplayName = (cat) => {
    if (!cat) return '-'
    const name = cat.name || cat
    return i18n.language === 'ar' ? (cat.name_ar || name) : name
  }

  const fetchProducts = async () => {
    try {
      const { data } = await api.get('/products')
      const results = data.results || data || []
      setProducts(results)
      const cats = [...new Set(results.map(p => catDisplayName(p.category)).filter(Boolean))]
      setCategories(cats)
    } catch (err) {
      toast.error(t('products.loadFailed'))
    } finally {
      setLoading(false)
    }
  }

  const fetchCategories = async () => {
    try {
      const { data } = await api.get('/categories')
      setCategoryList(data || [])
    } catch (err) {
    }
  }

  const filtered = categoryFilter ? products.filter(p => catDisplayName(p.category) === categoryFilter) : products

  const openCreate = () => {
    setEditing(null)
    setForm(emptyProduct)
    setModalOpen(true)
  }

  const openEdit = (product) => {
    setEditing(product)
    setForm({
      name: product.name || '',
      name_ar: product.name_ar || '',
      sku: product.sku || '',
      description: product.description || '',
      description_ar: product.description_ar || '',
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
      let categoryId = form.category_id || null
      if (showNewCategory && newCatName.trim()) {
        const { data: newCat } = await api.post('/categories', { name: newCatName, name_ar: newCatNameAr || null })
        categoryId = newCat.id
        await fetchCategories()
      }
      const payload = {
        name: form.name,
        name_ar: form.name_ar || null,
        sku: form.sku,
        description: form.description || '',
        description_ar: form.description_ar || null,
        unit: form.unit,
        cost_price: parseFloat(form.cost_price) || 0,
        selling_price: parseFloat(form.selling_price) || 0,
        min_stock_level: parseInt(form.min_stock_level) || 0,
        is_expiry_tracked: form.is_expiry_tracked,
        shelf_life_days: parseInt(form.shelf_life_days) || 0,
        category_id: categoryId,
      }
      if (editing) {
        await api.put(`/products/${editing.id}`, payload)
        toast.success(t('products.updated'))
      } else {
        await api.post('/products', payload)
        toast.success(t('products.created'))
      }
      setModalOpen(false)
      setShowNewCategory(false)
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

  const isAr = i18n.language === 'ar'
  const columns = [
    { header: t('products.name'), accessor: 'name', render: (r) => isAr ? (r.name_ar || r.name) : r.name },
    { header: t('products.sku'), accessor: 'sku' },
    { header: t('products.category'), accessor: 'category', render: (r) => { const cat = r.category?.name || r.category || '-'; return isAr ? (r.category?.name_ar || cat) : cat } },
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
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.nameLabel')} *</label>
              <input className="input-field" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1"><Languages size={14} className="inline" /> {t('products.nameLabel')} (العربية)</label>
              <input className="input-field" value={form.name_ar} onChange={e => setForm(f => ({ ...f, name_ar: e.target.value }))} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.skuLabel')} *</label>
              <input className="input-field" value={form.sku} onChange={e => setForm(f => ({ ...f, sku: e.target.value }))} required />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('products.categoryLabel')}</label>
              <select className="select-field" value={form.category_id} onChange={e => {
                const val = e.target.value
                if (val === '__new__') {
                  setShowNewCategory(true)
                  setNewCatName('')
                  setNewCatNameAr('')
                } else {
                  setShowNewCategory(false)
                  setForm(f => ({ ...f, category_id: parseInt(val) || '' }))
                }
              }}>
                <option value="">{t('products.selectCategory')}</option>
                {categoryList.map(cat => (
                  <option key={cat.id} value={cat.id}>{catDisplayName(cat)}</option>
                ))}
                <option value="__new__">{t('products.addCategory')}</option>
              </select>
              {showNewCategory && (
                <div className="mt-2 p-2 border border-dashed border-gray-300 rounded-lg space-y-2">
                  <input className="input-field text-sm" placeholder={t('products.categoryNamePlaceholder')} value={newCatName} onChange={e => setNewCatName(e.target.value)} />
                  <input className="input-field text-sm" placeholder={t('products.categoryNameArPlaceholder')} value={newCatNameAr} onChange={e => setNewCatNameAr(e.target.value)} />
                </div>
              )}
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
              <textarea className="input-field" rows={2} value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} />
            </div>
            <div className="col-span-2">
              <label className="block text-sm font-medium text-gray-700 mb-1"><Languages size={14} className="inline" /> {t('products.descriptionLabel')} (العربية)</label>
              <textarea className="input-field" rows={2} value={form.description_ar} onChange={e => setForm(f => ({ ...f, description_ar: e.target.value }))} />
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
