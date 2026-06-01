import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Eye, Truck, MapPin, Clock, CheckCircle, XCircle } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatDate, formatDateTime, formatCurrency, getStatusBadgeClass } from '../utils/formatters'

export default function Deliveries() {
  const { t } = useTranslation()
  const [deliveries, setDeliveries] = useState([])
  const [loading, setLoading] = useState(true)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [selectedDelivery, setSelectedDelivery] = useState(null)
  const [statusFilter, setStatusFilter] = useState('')

  useEffect(() => { fetchDeliveries() }, [])

  const fetchDeliveries = async () => {
    try {
      const { data } = await api.get('/deliveries')
      setDeliveries(data.results || data || [])
    } catch { toast.error(t('deliveries:failedToLoad')) }
    finally { setLoading(false) }
  }

  const filtered = statusFilter ? deliveries.filter(d => d.status === statusFilter) : deliveries

  const handleStatusUpdate = async (deliveryId, status) => {
    try {
      await api.post(`/deliveries/${deliveryId}/complete`, { status })
      toast.success(t('deliveries:deliveryStatusUpdate', { status }))
      fetchDeliveries()
      if (selectedDelivery?.id === deliveryId) {
        const { data } = await api.get(`/deliveries/${deliveryId}`)
        setSelectedDelivery(data)
      }
    } catch (err) { toast.error(err.message) }
  }

  const columns = [
    { header: t('deliveries:deliveryNumber'), accessor: 'id', render: (r) => <span className="font-medium">#DEL-{String(r.id).padStart(4, '0')}</span> },
    { header: t('deliveries:order'), accessor: 'order_number', render: (r) => r.order_number ? `#${r.order_number}` : '-' },
    { header: t('deliveries:store'), accessor: 'store_name' },
    { header: t('deliveries:driver'), accessor: 'driver_name' },
    { header: t('deliveries:status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status.replace('_', ' ')}</span> },
    { header: t('deliveries:eta'), accessor: 'eta', render: (r) => r.eta ? formatDateTime(r.eta) : '-' },
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <button onClick={() => { setSelectedDelivery(r); setDetailModalOpen(true) }} className="p-1.5 rounded-lg hover:bg-gray-100"><Eye size={14} /></button>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('deliveries:title')}</h1>
          <p className="text-gray-500 mt-1">{t('deliveries:subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          {['', 'in_transit', 'delivered', 'cancelled'].map(s => (
            <button key={s} className={`px-3 py-1.5 rounded-lg text-sm ${statusFilter === s ? 'bg-brand-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'}`}
              onClick={() => setStatusFilter(s)}>{s || t('deliveries:all')}</button>
          ))}
        </div>
      </div>
      <div className="card">
        <DataTable columns={columns} data={filtered} loading={loading} searchPlaceholder={t('deliveries:searchPlaceholder')} />
      </div>

      <Modal isOpen={detailModalOpen} onClose={() => setDetailModalOpen(false)} title={`Delivery #DEL-${String(selectedDelivery?.id || '').padStart(4, '0')}`} size="lg">
        {selectedDelivery && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">{t('deliveries:store')}:</span> <span className="font-medium">{selectedDelivery.store_name}</span></div>
              <div><span className="text-gray-500">{t('deliveries:driver')}:</span> <span className="font-medium">{selectedDelivery.driver_name || t('deliveries:unassigned')}</span></div>
              <div><span className="text-gray-500">{t('deliveries:status')}:</span> <span className={`badge ${getStatusBadgeClass(selectedDelivery.status)}`}>{selectedDelivery.status}</span></div>
              <div><span className="text-gray-500">{t('deliveries:order')}:</span> #{selectedDelivery.order_number || '-'}</div>
              {selectedDelivery.eta && <div><span className="text-gray-500">{t('deliveries:eta')}:</span> {formatDateTime(selectedDelivery.eta)}</div>}
              {selectedDelivery.actual_arrival && <div><span className="text-gray-500">{t('deliveries:arrived')}:</span> {formatDateTime(selectedDelivery.actual_arrival)}</div>}
            </div>

            <div className="flex items-center gap-2 pt-2 border-t">
              {selectedDelivery.status === 'in_transit' && (
                <>
                  <button className="btn-primary" onClick={() => handleStatusUpdate(selectedDelivery.id, 'delivered')}>
                    <CheckCircle size={16} /> {t('deliveries:markDelivered')}
                  </button>
                  <button className="btn-danger" onClick={() => handleStatusUpdate(selectedDelivery.id, 'cancelled')}>
                    <XCircle size={16} /> {t('deliveries:cancel')}
                  </button>
                </>
              )}
              {selectedDelivery.status === 'pending' && (
                <button className="btn-primary" onClick={() => handleStatusUpdate(selectedDelivery.id, 'in_transit')}>
                  <Truck size={16} /> {t('deliveries:startDelivery')}
                </button>
              )}
            </div>

            {selectedDelivery.proof_of_delivery && (
              <div className="border-t pt-4">
                <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('deliveries:proofOfDelivery')}</h4>
                {selectedDelivery.proof_of_delivery.recipient_name && (
                  <p className="text-sm">{t('deliveries:recipient')}: <span className="font-medium">{selectedDelivery.proof_of_delivery.recipient_name}</span></p>
                )}
                {selectedDelivery.proof_of_delivery.notes && (
                  <p className="text-sm text-gray-600">{t('deliveries:notes')}: {selectedDelivery.proof_of_delivery.notes}</p>
                )}
                {selectedDelivery.proof_of_delivery.signature && (
                  <img src={selectedDelivery.proof_of_delivery.signature} alt={t('deliveries:signature')} className="mt-2 max-h-20 border" />
                )}
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  )
}
