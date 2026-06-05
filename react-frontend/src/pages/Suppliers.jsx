import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Pencil, Trash2, Building2 } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatPhone } from '../utils/formatters'

const emptySupplier = { code: '', business_name: '', contact_person: '', email: '', phone: '', address: '', city: '', state: '', tax_id: '', payment_terms: 'net30', status: 'active' }

export default function Suppliers() {
  const { t } = useTranslation()
  const [suppliers, setSuppliers] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptySupplier)
  const [saving, setSaving] = useState(false)

  useEffect(() => { fetchSuppliers() }, [])

  const fetchSuppliers = async () => {
    try {
      const { data } = await api.get('/suppliers')
      setSuppliers(data.results || data || [])
    } catch { toast.error(t('suppliers.loadFailed')) }
    finally { setLoading(false) }
  }

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      if (editing) { await api.put(`/suppliers/${editing.id}`, form); toast.success(t('suppliers.updated')) }
      else { await api.post('/suppliers', form); toast.success(t('suppliers.created')) }
      setModalOpen(false); fetchSuppliers()
    } catch (err) { toast.error(err.message) }
    finally { setSaving(false) }
  }

  const handleDelete = async (s) => {
    if (!confirm(t('suppliers.deleteConfirm'))) return
    try { await api.delete(`/suppliers/${s.id}`); toast.success(t('suppliers.deleted')); fetchSuppliers() }
    catch (err) { toast.error(err.message) }
  }

  const columns = [
    { header: t('suppliers.name'), accessor: 'business_name', render: (r) => (
      <div className="flex items-center gap-2">
        <Building2 size={16} className="text-gray-400" />
        <div><p className="font-medium text-gray-900">{r.business_name}</p><p className="text-xs text-gray-500">{r.contact_person}</p></div>
      </div>
    )},
    { header: t('suppliers.contact'), accessor: 'email', render: (r) => (
      <div className="text-sm"><p>{r.email}</p><p className="text-xs text-gray-500">{formatPhone(r.phone)}</p></div>
    )},
    { header: t('suppliers.city'), accessor: 'city' },
    { header: t('suppliers.paymentTerms'), accessor: 'payment_terms', render: (r) => (
      <span className="badge bg-blue-100 text-blue-800">{r.payment_terms === 'net15' ? t('suppliers.net15') : r.payment_terms === 'net30' ? t('suppliers.net30') : r.payment_terms === 'net60' ? t('suppliers.net60') : r.payment_terms === 'cod' ? t('suppliers.cod') : r.payment_terms}</span>
    )},
    { header: t('suppliers.status'), accessor: 'status', render: (r) => (
      <span className={`badge ${r.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>{r.status === 'active' ? t('suppliers.active') : t('suppliers.inactive')}</span>
    )},
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <div className="flex gap-1">
        <button onClick={() => { setEditing(r); setForm({ ...r }); setModalOpen(true) }} className="p-1.5 rounded-lg hover:bg-gray-100"><Pencil size={14} /></button>
        <button onClick={() => handleDelete(r)} className="p-1.5 rounded-lg hover:bg-red-50 text-red-500"><Trash2 size={14} /></button>
      </div>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('suppliers.title')}</h1>
          <p className="text-gray-500 mt-1">{t('suppliers.subtitle')}</p>
        </div>
        <button className="btn-primary" onClick={() => { setEditing(null); setForm(emptySupplier); setModalOpen(true) }}>
          <Plus size={18} /> {t('suppliers.addSupplier')}
        </button>
      </div>
      <div className="card">
        <DataTable columns={columns} data={suppliers} loading={loading} searchPlaceholder={t('suppliers.search')} />
      </div>
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editing ? t('suppliers.editSupplier') : t('suppliers.addSupplier')} size="lg">
        <form onSubmit={handleSave} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2"><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.name')} *</label>
              <input className="input-field" value={form.business_name} onChange={e => setForm(f => ({ ...f, business_name: e.target.value }))} required /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.codeLabel')} *</label>
              <input className="input-field" value={form.code} onChange={e => setForm(f => ({ ...f, code: e.target.value }))} required /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.contactPerson')}</label>
              <input className="input-field" value={form.contact_person} onChange={e => setForm(f => ({ ...f, contact_person: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.email')}</label>
              <input type="email" className="input-field" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.phone')}</label>
              <input className="input-field" value={form.phone} onChange={e => setForm(f => ({ ...f, phone: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.taxId')}</label>
              <input className="input-field" value={form.tax_id} onChange={e => setForm(f => ({ ...f, tax_id: e.target.value }))} /></div>
            <div className="col-span-2"><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.address')}</label>
              <input className="input-field" value={form.address} onChange={e => setForm(f => ({ ...f, address: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.city')}</label>
              <input className="input-field" value={form.city} onChange={e => setForm(f => ({ ...f, city: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.state')}</label>
              <input className="input-field" value={form.state} onChange={e => setForm(f => ({ ...f, state: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.paymentTerms')}</label>
              <select className="select-field" value={form.payment_terms} onChange={e => setForm(f => ({ ...f, payment_terms: e.target.value }))}>
                <option value="net15">{t('suppliers.net15')}</option><option value="net30">{t('suppliers.net30')}</option><option value="net60">{t('suppliers.net60')}</option><option value="cod">{t('suppliers.cod')}</option>
              </select></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('suppliers.status')}</label>
              <select className="select-field" value={form.status} onChange={e => setForm(f => ({ ...f, status: e.target.value }))}>
                <option value="active">{t('suppliers.active')}</option><option value="inactive">{t('suppliers.inactive')}</option>
              </select></div>
          </div>
          <div className="flex justify-end gap-2 pt-4">
            <button type="button" className="btn-secondary" onClick={() => setModalOpen(false)}>{t('common.cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? t('suppliers.saving') : editing ? t('suppliers.update') : t('suppliers.create')}</button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
