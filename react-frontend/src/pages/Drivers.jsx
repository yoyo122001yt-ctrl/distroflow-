import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Pencil, Trash2, User, Phone, DollarSign } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatPhone, formatCurrency, getStatusBadgeClass } from '../utils/formatters'

const emptyDriver = { name: '', phone: '', email: '', license_number: '', vehicle_plate: '', status: 'active', pay_rate: 0, pay_type: 'per_delivery' }

export default function Drivers() {
  const { t } = useTranslation()
  const [drivers, setDrivers] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [settlementModalOpen, setSettlementModalOpen] = useState(false)
  const [selectedDriver, setSelectedDriver] = useState(null)
  const [settlements, setSettlements] = useState([])
  const [editing, setEditing] = useState(null)
  const [form, setForm] = useState(emptyDriver)
  const [saving, setSaving] = useState(false)

  useEffect(() => { fetchDrivers() }, [])

  const fetchDrivers = async () => {
    try {
      const { data } = await api.get('/drivers')
      setDrivers(data.results || data || [])
    } catch { toast.error(t('drivers:failedLoadDrivers')) }
    finally { setLoading(false) }
  }

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      if (editing) { await api.put(`/drivers/${editing.id}`, form); toast.success(t('drivers:updated')) }
      else { await api.post('/drivers', form); toast.success(t('drivers:created')) }
      setModalOpen(false); fetchDrivers()
    } catch (err) { toast.error(err.message) }
    finally { setSaving(false) }
  }

  const handleDelete = async (d) => {
    if (!confirm(t('drivers:deleteConfirm'))) return
    try { await api.delete(`/drivers/${d.id}`); toast.success(t('drivers:deleted')); fetchDrivers() }
    catch (err) { toast.error(err.message) }
  }

  const viewSettlements = async (driver) => {
    setSelectedDriver(driver)
    try {
      const { data } = await api.get(`/drivers/${driver.id}/settlements`)
      setSettlements(data.results || data || [])
      setSettlementModalOpen(true)
    } catch { toast.error(t('drivers:failedLoadSettlements')) }
  }

  const columns = [
    { header: t('drivers:driver'), accessor: 'name', render: (r) => (
      <div className="flex items-center gap-2">
        <div className="w-8 h-8 bg-brand-100 rounded-full flex items-center justify-center"><User size={14} className="text-brand-600" /></div>
        <div><p className="font-medium text-gray-900">{r.name}</p><p className="text-xs text-gray-500">{r.license_number}</p></div>
      </div>
    )},
    { header: t('drivers:contact'), accessor: 'phone', render: (r) => (
      <div className="text-sm"><p className="flex items-center gap-1"><Phone size={12} />{formatPhone(r.phone)}</p><p className="text-xs text-gray-500">{r.email}</p></div>
    )},
    { header: t('drivers:vehicle'), accessor: 'vehicle_plate' },
    { header: t('drivers:payRate'), accessor: 'pay_rate', render: (r) => `${formatCurrency(r.pay_rate)}/${r.pay_type === 'per_delivery' ? t('drivers:delivery') : t('drivers:hour')}` },
    { header: t('drivers:status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status}</span> },
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <div className="flex gap-1">
        <button onClick={() => viewSettlements(r)} className="text-xs text-brand-600 hover:text-brand-800">{t('drivers:settlements')}</button>
        <button onClick={() => { setEditing(r); setForm({ ...r }); setModalOpen(true) }} className="p-1.5 rounded-lg hover:bg-gray-100"><Pencil size={14} /></button>
        <button onClick={() => handleDelete(r)} className="p-1.5 rounded-lg hover:bg-red-50 text-red-500"><Trash2 size={14} /></button>
      </div>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('drivers:title')}</h1>
          <p className="text-gray-500 mt-1">{t('drivers:subtitle')}</p>
        </div>
        <button className="btn-primary" onClick={() => { setEditing(null); setForm(emptyDriver); setModalOpen(true) }}><Plus size={18} /> {t('drivers:addDriver')}</button>
      </div>
      <div className="card">
        <DataTable columns={columns} data={drivers} loading={loading} searchPlaceholder={t('drivers:searchPlaceholder')} />
      </div>

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editing ? t('drivers:editDriver') : t('drivers:addDriver')} size="lg">
        <form onSubmit={handleSave} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="col-span-2"><label className="block text-sm font-medium text-gray-700 mb-1">{t('drivers:nameRequired')}</label>
              <input className="input-field" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('drivers:phone')}</label>
              <input className="input-field" value={form.phone} onChange={e => setForm(f => ({ ...f, phone: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('drivers:email')}</label>
              <input type="email" className="input-field" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('drivers:licenseNumber')}</label>
              <input className="input-field" value={form.license_number} onChange={e => setForm(f => ({ ...f, license_number: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('drivers:vehiclePlate')}</label>
              <input className="input-field" value={form.vehicle_plate} onChange={e => setForm(f => ({ ...f, vehicle_plate: e.target.value }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('drivers:payType')}</label>
              <select className="select-field" value={form.pay_type} onChange={e => setForm(f => ({ ...f, pay_type: e.target.value }))}>
                <option value="per_delivery">{t('drivers:perDelivery')}</option>
                <option value="hourly">{t('drivers:hourly')}</option>
                <option value="salary">{t('drivers:salary')}</option>
              </select></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('drivers:payRate')}</label>
              <input type="number" step="0.01" className="input-field" value={form.pay_rate} onChange={e => setForm(f => ({ ...f, pay_rate: parseFloat(e.target.value) || 0 }))} /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('drivers:status')}</label>
              <select className="select-field" value={form.status} onChange={e => setForm(f => ({ ...f, status: e.target.value }))}>
                <option value="active">{t('drivers:active')}</option><option value="inactive">{t('drivers:inactive')}</option>
              </select></div>
          </div>
          <div className="flex justify-end gap-2 pt-4">
            <button type="button" className="btn-secondary" onClick={() => setModalOpen(false)}>{t('drivers:cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? t('drivers:saving') : editing ? t('drivers:update') : t('drivers:create')}</button>
          </div>
        </form>
      </Modal>

      <Modal isOpen={settlementModalOpen} onClose={() => setSettlementModalOpen(false)} title={t('drivers:settlementsTitle', { name: selectedDriver?.name })} size="lg">
        {settlements.length > 0 ? (
          <div className="space-y-3">
            {settlements.map((s, i) => (
              <div key={s.id || i} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div>
                  <p className="text-sm font-medium">{s.period || formatDate(s.created_at)}</p>
                  <p className="text-xs text-gray-500">{t('drivers:deliveriesCount', { count: s.deliveries_completed || 0 })} | {t('drivers:distanceKm', { distance: s.total_distance || 0 })}</p>
                </div>
                <div className="text-right">
                  <p className="text-sm font-medium">{formatCurrency(s.total_pay || s.amount)}</p>
                  <span className={`badge ${getStatusBadgeClass(s.status)}`}>{s.status}</span>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <p className="text-center py-8 text-gray-400">{t('drivers:noSettlements')}</p>
        )}
      </Modal>
    </div>
  )
}
