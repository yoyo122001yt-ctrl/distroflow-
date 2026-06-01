import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Truck, CheckCircle, XCircle } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatDate, formatCurrency, getStatusBadgeClass } from '../utils/formatters'

export default function Loading() {
  const { t } = useTranslation()
  const [loads, setLoads] = useState([])
  const [routes, setRoutes] = useState([])
  const [drivers, setDrivers] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [selectedLoad, setSelectedLoad] = useState(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({ route_id: '', driver_id: '', vehicle_plate: '', notes: '' })

  useEffect(() => {
    Promise.all([
      api.get('/loading'),
      api.get('/routes'),
      api.get('/drivers'),
    ]).then(([ld, rt, dr]) => {
      setLoads(ld.data.results || ld.data || [])
      setRoutes(rt.data.results || rt.data || [])
      setDrivers(dr.data.results || dr.data || [])
    }).catch(() => toast.error(t('loading:failedLoadData')))
    .finally(() => setLoading(false))
  }, [])

  const handleCreateLoad = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      await api.post('/loading', form)
      toast.success(t('loading:sheetCreated'))
      setModalOpen(false)
      setForm({ route_id: '', driver_id: '', vehicle_plate: '', notes: '' })
      const { data } = await api.get('/loading')
      setLoads(data.results || data || [])
    } catch (err) { toast.error(err.message) }
    finally { setSaving(false) }
  }

  const handleStartLoading = async (load) => {
    try { await api.post(`/loading/${load.id}/start`); toast.success(t('loading:started')); refresh() }
    catch (err) { toast.error(err.message) }
  }

  const handleCompleteLoading = async (load) => {
    try { await api.post(`/loading/${load.id}/complete`); toast.success(t('loading:completed')); setDetailModalOpen(false); refresh() }
    catch (err) { toast.error(err.message) }
  }

  const refresh = async () => {
    const { data } = await api.get('/loading')
    setLoads(data.results || data || [])
  }

  const columns = [
    { header: t('loading:loadNumber'), accessor: 'id', render: (r) => <span className="font-medium">#LD-{String(r.id).padStart(4, '0')}</span> },
    { header: t('loading:route'), accessor: 'route_name', render: (r) => r.route_name || '-' },
    { header: t('loading:driver'), accessor: 'driver_name' },
    { header: t('loading:vehicle'), accessor: 'vehicle_plate' },
    { header: t('loading:status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status}</span> },
    { header: t('loading:created'), accessor: 'created_at', render: (r) => formatDate(r.created_at) },
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <div className="flex gap-1">
        <button onClick={() => { setSelectedLoad(r); setDetailModalOpen(true) }} className="text-sm text-brand-600 hover:text-brand-800">{t('loading:view')}</button>
        {r.status === 'pending' && <button onClick={() => handleStartLoading(r)} className="text-sm text-green-600 hover:text-green-800">{t('loading:start')}</button>}
      </div>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('loading:title')}</h1>
          <p className="text-gray-500 mt-1">{t('loading:subtitle')}</p>
        </div>
        <button className="btn-primary" onClick={() => setModalOpen(true)}><Truck size={18} /> {t('loading:newSheet')}</button>
      </div>
      <div className="card">
        <DataTable columns={columns} data={loads} loading={loading} searchPlaceholder={t('loading:searchPlaceholder')} />
      </div>

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={t('loading:createSheet')} size="md">
        <form onSubmit={handleCreateLoad} className="space-y-4">
          <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('loading:routeRequired')}</label>
            <select className="select-field" value={form.route_id} onChange={e => setForm(f => ({ ...f, route_id: e.target.value }))} required>
              <option value="">{t('loading:selectRoute')}</option>
              {routes.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
            </select></div>
          <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('loading:driverRequired')}</label>
            <select className="select-field" value={form.driver_id} onChange={e => setForm(f => ({ ...f, driver_id: e.target.value }))} required>
              <option value="">{t('loading:selectDriver')}</option>
              {drivers.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
            </select></div>
          <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('loading:vehiclePlateRequired')}</label>
            <input className="input-field" value={form.vehicle_plate} onChange={e => setForm(f => ({ ...f, vehicle_plate: e.target.value }))} required /></div>
          <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('loading:notes')}</label>
            <textarea className="input-field" rows={2} value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} /></div>
          <div className="flex justify-end gap-2 pt-2">
            <button type="button" className="btn-secondary" onClick={() => setModalOpen(false)}>{t('loading:cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? t('loading:creating') : t('loading:create')}</button>
          </div>
        </form>
      </Modal>

      <Modal isOpen={detailModalOpen} onClose={() => setDetailModalOpen(false)} title={t('loading:loadDetail', { id: String(selectedLoad?.id || '').padStart(4, '0') })} size="lg">
        {selectedLoad && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">{t('loading:route')}:</span> <span className="font-medium">{selectedLoad.route_name || '-'}</span></div>
              <div><span className="text-gray-500">{t('loading:driver')}:</span> <span className="font-medium">{selectedLoad.driver_name || '-'}</span></div>
              <div><span className="text-gray-500">{t('loading:vehicle')}:</span> {selectedLoad.vehicle_plate}</div>
              <div><span className="text-gray-500">{t('loading:status')}:</span> <span className={`badge ${getStatusBadgeClass(selectedLoad.status)}`}>{selectedLoad.status}</span></div>
            </div>
            <div className="border-t pt-4">
              <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('loading:ordersInLoad')}</h4>
              {(selectedLoad.orders || []).length > 0 ? (
                <div className="space-y-2">
                  {selectedLoad.orders.map((o, i) => (
                    <div key={i} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg text-sm">
                      <span className="font-medium">#{o.order_number || o.id}</span>
                      <span>{o.store_name}</span>
                      <span>{formatCurrency(o.total)}</span>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-500">{t('loading:noOrders')}</p>
              )}
            </div>
            {selectedLoad.status === 'in_progress' && (
              <button className="btn-primary" onClick={() => handleCompleteLoading(selectedLoad)}>{t('loading:completeLoading')}</button>
            )}
            {selectedLoad.status === 'pending' && (
              <button className="btn-primary" onClick={() => handleStartLoading(selectedLoad)}>{t('loading:startLoading')}</button>
            )}
          </div>
        )}
      </Modal>
    </div>
  )
}
