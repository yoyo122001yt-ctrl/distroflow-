import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Pencil, Trash2, Store, Phone, MapPin } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatCurrency, formatPhone } from '../utils/formatters'

const emptyStore = { name: '', code: '', phone: '', email: '', address: '', city: '', state: '', type: 'retail', credit_limit: 0, status: 'active' }

export default function RetailStores() {
  const { t } = useTranslation()
  const [stores, setStores] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptyStore)
  const [typeFilter, setTypeFilter] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => { fetchStores() }, [])

  const fetchStores = async () => {
    try {
      const { data } = await api.get('/stores')
      setStores(data.results || data || [])
    } catch { toast.error(t('stores.loadError')) }
    finally { setLoading(false) }
  }

  const filtered = typeFilter ? stores.filter(s => s.type === typeFilter) : stores

  const openEdit = (store) => { setEditing(store); setForm({ ...store }); setModalOpen(true) }

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      const payload = {
        business_name: form.name,
        code: form.code,
        store_type: form.type,
        phone: form.phone,
        email: form.email,
        address: form.address || '-',
        city: form.city || '-',
        state: form.state,
        credit_limit: form.credit_limit ?? 0,
        status: form.status || 'active',
      }
      if (editing) {
        await api.put(`/stores/${editing.id}`, payload)
        toast.success(t('stores.updated'))
      } else {
        await api.post('/stores', payload)
        toast.success(t('stores.created'))
      }
      setModalOpen(false)
      fetchStores()
    } catch (err) { toast.error(err.message) }
    finally { setSaving(false) }
  }

  const handleDelete = async (store) => {
    if (!confirm(t('stores.deleteConfirm'))) return
    try {
      await api.delete(`/stores/${store.id}`)
      toast.success(t('stores.deleted'))
      fetchStores()
    } catch (err) { toast.error(err.message) }
  }

  const columns = [
    { header: t('stores.name'), accessor: 'name', render: (r) => (
      <div className="flex items-center gap-2">
        <div className="w-8 h-8 bg-brand-100 rounded-full flex items-center justify-center">
          <Store size={14} className="text-brand-600" />
        </div>
        <div>
          <p className="font-medium text-gray-900">{r.name}</p>
          <p className="text-xs text-gray-500">{r.code}</p>
        </div>
      </div>
    )},
    { header: t('stores.type'), accessor: 'type', render: (r) => (
      <span className="badge bg-gray-100 text-gray-700 capitalize">{r.type === 'retail' ? t('stores.retail') : r.type === 'wholesale' ? t('stores.wholesale') : r.type === 'distributor' ? t('stores.distributor') : r.type}</span>
    )},
    { header: t('stores.contact'), accessor: 'phone', render: (r) => (
      <div className="text-sm">
        <p className="flex items-center gap-1"><Phone size={12} /> {formatPhone(r.phone)}</p>
        <p className="text-xs text-gray-500">{r.email}</p>
      </div>
    )},
    { header: t('stores.location'), accessor: 'city', render: (r) => (
      <span className="flex items-center gap-1 text-sm"><MapPin size={12} /> {r.city}{r.state ? `, ${r.state}` : ''}</span>
    )},
    { header: t('stores.creditBalance'), accessor: 'credit_limit', render: (r) => (
      <span className="font-medium">{formatCurrency(r.credit_balance || 0)} / {formatCurrency(r.credit_limit)}</span>
    )},
    { header: t('stores.status'), accessor: 'status', render: (r) => (
      <span className={`badge ${r.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>{r.status === 'active' ? t('stores.active') : t('stores.inactive')}</span>
    )},
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <div className="flex items-center gap-1">
        <button onClick={() => openEdit(r)} className="p-1.5 rounded-lg hover:bg-gray-100"><Pencil size={14} /></button>
        <button onClick={() => handleDelete(r)} className="p-1.5 rounded-lg hover:bg-red-50 text-red-500"><Trash2 size={14} /></button>
      </div>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('stores.title')}</h1>
          <p className="text-gray-500 mt-1">{t('stores.subtitle')}</p>
        </div>
        <button className="btn-primary" onClick={() => { setEditing(null); setForm(emptyStore); setModalOpen(true) }}>
          <Plus size={18} /> {t('stores.addStore')}
        </button>
      </div>

      <div className="card">
        <div className="flex items-center gap-2 mb-4 flex-wrap">
          <span className="text-sm text-gray-500">{t('stores.typeFilter')}</span>
          {['', 'retail', 'wholesale', 'distributor'].map(type => (
            <button key={type}
              className={`px-3 py-1 rounded-full text-sm ${typeFilter === type ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
              onClick={() => setTypeFilter(type)}>{type ? t(`stores.${type}`) : t('stores.all')}</button>
          ))}
        </div>
        <DataTable columns={columns} data={filtered} loading={loading} searchPlaceholder={t('stores.search')} />
      </div>

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editing ? t('stores.editStore') : t('stores.addStore')} size="lg">
        <form onSubmit={handleSave} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('stores.name')} *</label>
              <input className="input-field" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('stores.code')} *</label>
              <input className="input-field" value={form.code} onChange={e => setForm(f => ({ ...f, code: e.target.value }))} required /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('stores.type')}</label>
              <select className="select-field" value={form.type} onChange={e => setForm(f => ({ ...f, type: e.target.value }))}>
                <option value="retail">{t('stores.retail')}</option><option value="wholesale">{t('stores.wholesale')}</option><option value="distributor">{t('stores.distributor')}</option>
              </select></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('stores.status')}</label>
              <select className="select-field" value={form.status} onChange={e => setForm(f => ({ ...f, status: e.target.value }))}>
                <option value="active">{t('stores.active')}</option><option value="inactive">{t('stores.inactive')}</option>
              </select></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('stores.phone')}</label>
              <input className="input-field" value={form.phone} onChange={e => setForm(f => ({ ...f, phone: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('stores.email')}</label>
              <input type="email" className="input-field" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} /></div>
            <div className="col-span-2"><label className="block text-sm font-medium text-gray-700 mb-1">{t('stores.address')}</label>
              <input className="input-field" value={form.address} onChange={e => setForm(f => ({ ...f, address: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('stores.city')}</label>
              <input className="input-field" value={form.city} onChange={e => setForm(f => ({ ...f, city: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('stores.state')}</label>
              <input className="input-field" value={form.state} onChange={e => setForm(f => ({ ...f, state: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('stores.creditLimit')}</label>
              <input type="number" step="0.01" className="input-field" value={form.credit_limit}
                onChange={e => setForm(f => ({ ...f, credit_limit: parseFloat(e.target.value) || 0 }))} /></div>
          </div>
          <div className="flex justify-end gap-2 pt-4">
            <button type="button" className="btn-secondary" onClick={() => setModalOpen(false)}>{t('common.cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? t('stores.saving') : editing ? t('stores.update') : t('stores.create')}</button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
