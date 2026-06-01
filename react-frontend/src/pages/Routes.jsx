import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, Pencil, Map, GripVertical } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import RouteMap from '../components/Routes/RouteMap'
import { formatDate, getStatusBadgeClass } from '../utils/formatters'

export default function RoutesPage() {
  const { t } = useTranslation()
  const [routes, setRoutes] = useState([])
  const [stores, setStores] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [mapModalOpen, setMapModalOpen] = useState(false)
  const [selectedRoute, setSelectedRoute] = useState(null)
  const [editing, setEditing] = useState(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({ name: '', description: '', stops: [] })

  useEffect(() => {
    Promise.all([
      api.get('/routes'),
      api.get('/stores'),
    ]).then(([rt, st]) => {
      setRoutes(rt.data.results || rt.data || [])
      setStores(st.data.results || st.data || [])
    }).catch(() => toast.error(t('routes:failedLoadData')))
    .finally(() => setLoading(false))
  }, [])

  const openCreate = () => {
    setEditing(null)
    setForm({ name: '', description: '', stops: [] })
    setModalOpen(true)
  }

  const openEdit = (route) => {
    setEditing(route)
    setForm({ name: route.name, description: route.description || '', stops: route.stops || route.stop_details || [] })
    setModalOpen(true)
  }

  const handleSave = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      if (editing) {
        await api.put(`/routes/${editing.id}`, form)
        toast.success(t('routes:updated'))
      } else {
        await api.post('/routes', form)
        toast.success(t('routes:created'))
      }
      setModalOpen(false)
      const { data } = await api.get('/routes')
      setRoutes(data.results || data || [])
    } catch (err) { toast.error(err.message) }
    finally { setSaving(false) }
  }

  const showMap = (route) => {
    const stops = route.stops || route.stop_details || []
    const hasCoords = stops.some(s => s.latitude && s.longitude)
    if (!hasCoords) { toast.error(t('routes:noCoordinates')); return }
    setSelectedRoute(route)
    setMapModalOpen(true)
  }

  const addStop = () => {
    setForm(f => ({ ...f, stops: [...f.stops, { store_id: '', order: f.stops.length + 1, type: 'delivery' }] }))
  }

  const updateStop = (idx, key, value) => {
    const stops = [...form.stops]
    stops[idx] = { ...stops[idx], [key]: value }
    if (key === 'store_id') {
      const store = stores.find(s => s.id === parseInt(value))
      if (store) {
        stops[idx].name = store.name
        stops[idx].address = store.address
        stops[idx].latitude = store.latitude
        stops[idx].longitude = store.longitude
      }
    }
    setForm(f => ({ ...f, stops }))
  }

  const removeStop = (idx) => {
    setForm(f => ({ ...f, stops: f.stops.filter((_, i) => i !== idx).map((s, i) => ({ ...s, order: i + 1 })) }))
  }

  const columns = [
    { header: t('routes:routeName'), accessor: 'name', render: (r) => <span className="font-medium">{r.name}</span> },
    { header: t('routes:stops'), accessor: 'stop_count', render: (r) => (r.stops || r.stop_details || []).length },
    { header: t('routes:status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status}</span> },
    { header: t('routes:created'), accessor: 'created_at', render: (r) => formatDate(r.created_at) },
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <div className="flex gap-1">
        <button onClick={() => showMap(r)} className="p-1.5 rounded-lg hover:bg-gray-100"><Map size={14} /></button>
        <button onClick={() => openEdit(r)} className="p-1.5 rounded-lg hover:bg-gray-100"><Pencil size={14} /></button>
      </div>
    )},
  ]

  const routeCenter = selectedRoute?.stops?.find(s => s.latitude) ? [selectedRoute.stops.find(s => s.latitude).latitude, selectedRoute.stops.find(s => s.longitude).longitude] : [30.0444, 31.2357]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('routes:title')}</h1>
          <p className="text-gray-500 mt-1">{t('routes:subtitle')}</p>
        </div>
        <button className="btn-primary" onClick={openCreate}><Plus size={18} /> {t('routes:newRoute')}</button>
      </div>
      <div className="card">
        <DataTable columns={columns} data={routes} loading={loading} searchPlaceholder={t('routes:searchPlaceholder')} />
      </div>

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={editing ? t('routes:editRoute') : t('routes:createRoute')} size="xl">
        <form onSubmit={handleSave} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('routes:nameRequired')}</label>
              <input className="input-field" value={form.name} onChange={e => setForm(f => ({ ...f, name: e.target.value }))} required /></div>
            <div><label className="block text-sm font-medium text-gray-700 mb-1">{t('routes:description')}</label>
              <input className="input-field" value={form.description} onChange={e => setForm(f => ({ ...f, description: e.target.value }))} /></div>
          </div>
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="text-sm font-medium text-gray-700">{t('routes:stops')}</label>
              <button type="button" className="text-sm text-brand-600 hover:text-brand-800" onClick={addStop}>{t('routes:addStop')}</button>
            </div>
            <div className="space-y-2 max-h-60 overflow-y-auto">
              {form.stops.map((stop, idx) => (
                <div key={idx} className="flex items-center gap-2 p-2 bg-gray-50 rounded-lg">
                  <GripVertical size={16} className="text-gray-400" />
                  <span className="text-xs font-medium text-gray-500 w-6">{stop.order || idx + 1}.</span>
                  <select className="select-field flex-1" value={stop.store_id} onChange={e => updateStop(idx, 'store_id', e.target.value)}>
                    <option value="">{t('routes:selectStore')}</option>
                    {stores.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                  </select>
                  <select className="select-field w-28" value={stop.type} onChange={e => updateStop(idx, 'type', e.target.value)}>
                    <option value="delivery">{t('routes:delivery')}</option>
                    <option value="pickup">{t('routes:pickup')}</option>
                  </select>
                  <button type="button" className="p-1.5 text-red-500 hover:bg-red-50 rounded" onClick={() => removeStop(idx)}>X</button>
                </div>
              ))}
            </div>
          </div>
          <div className="flex justify-end gap-2 pt-4">
            <button type="button" className="btn-secondary" onClick={() => setModalOpen(false)}>{t('routes:cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? t('routes:saving') : editing ? t('routes:update') : t('routes:create')}</button>
          </div>
        </form>
      </Modal>

      <Modal isOpen={mapModalOpen} onClose={() => setMapModalOpen(false)} title={selectedRoute?.name || t('routes:routeMap')} size="full">
        {selectedRoute && (
          <RouteMap
            stops={selectedRoute.stops || selectedRoute.stop_details || []}
            center={routeCenter}
            height="500px"
          />
        )}
      </Modal>
    </div>
  )
}
