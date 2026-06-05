import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Eye } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatDate, formatDateTime, getStatusBadgeClass } from '../utils/formatters'

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
    } catch { toast.error(t('deliveries.loadFailed')) }
    finally { setLoading(false) }
  }

  const filtered = statusFilter ? deliveries.filter(d => d.status === statusFilter) : deliveries

  const columns = [
    { header: t('deliveries.deliveryNo'), accessor: 'delivery_number', render: (r) => <span className="font-medium">#{r.delivery_number || String(r.id).padStart(4, '0')}</span> },
    { header: t('deliveries.order'), accessor: 'delivery_number', render: (r) => <span className="text-gray-500">{t('deliveries.orderColon')} {r.delivery_number}</span> },
    { header: t('deliveries.store'), accessor: 'store_name', render: (r) => r.store_name || r.route_assignment?.route?.name || '-' },
    { header: t('deliveries.driver'), accessor: 'driver_name', render: (r) => r.driver?.name || r.driver_name || '-' },
    { header: t('deliveries.status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status.replace('_', ' ')}</span> },
    { header: t('deliveries.date'), accessor: 'delivery_date', render: (r) => formatDate(r.delivery_date) },
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <button onClick={() => { setSelectedDelivery(r); setDetailModalOpen(true) }} className="p-1.5 rounded-lg hover:bg-gray-100"><Eye size={14} /></button>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('deliveries.title')}</h1>
          <p className="text-gray-500 mt-1">{t('deliveries.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          {[['', t('deliveries.all')], ['in_transit', t('deliveries.inTransit')], ['delivered', t('deliveries.delivered')], ['cancelled', t('deliveries.cancelled')]].map(([val, label]) => (
            <button key={val} className={`px-3 py-1.5 rounded-lg text-sm ${statusFilter === val ? 'bg-brand-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'}`}
              onClick={() => setStatusFilter(val)}>{label}</button>
          ))}
        </div>
      </div>
      <div className="card">
        <DataTable columns={columns} data={filtered} loading={loading} searchPlaceholder={t('deliveries.search')} />
      </div>

      <Modal isOpen={detailModalOpen} onClose={() => setDetailModalOpen(false)} title={`${t('deliveries.deliveryNo')} ${selectedDelivery?.delivery_number || String(selectedDelivery?.id || '').padStart(4, '0')}`} size="lg">
        {selectedDelivery && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">{t('deliveries.storeColon')}</span> <span className="font-medium">{selectedDelivery.store_name || selectedDelivery.route_assignment?.route?.name || '-'}</span></div>
              <div><span className="text-gray-500">{t('deliveries.driverColon')}</span> <span className="font-medium">{selectedDelivery.driver?.name || selectedDelivery.driver_name || t('deliveries.unassigned')}</span></div>
              <div><span className="text-gray-500">{t('deliveries.statusColon')}</span> <span className={`badge ${getStatusBadgeClass(selectedDelivery.status)}`}>{selectedDelivery.status}</span></div>
              <div><span className="text-gray-500">{t('deliveries.orderColon')}</span> {selectedDelivery.delivery_number || '-'}</div>
              <div><span className="text-gray-500">{t('deliveries.dateColon')}</span> {formatDate(selectedDelivery.delivery_date)}</div>
              {selectedDelivery.completed_at && <div><span className="text-gray-500">{t('deliveries.completedColon')}</span> {formatDateTime(selectedDelivery.completed_at)}</div>}
            </div>

            {(selectedDelivery.items || []).length > 0 && (
              <div className="border-t pt-4">
                <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('deliveries.items')}</h4>
                <table className="min-w-full text-sm">
                  <thead><tr className="bg-gray-50"><th className="px-3 py-2 text-left">{t('deliveries.product')}</th><th className="px-3 py-2 text-right">{t('deliveries.qtyLoaded')}</th><th className="px-3 py-2 text-right">{t('deliveries.qtyDelivered')}</th><th className="px-3 py-2 text-right">{t('deliveries.qtyReturned')}</th></tr></thead>
                  <tbody className="divide-y">
                    {(selectedDelivery.items || []).map((itm, i) => (
                      <tr key={i}><td className="px-3 py-2">{itm.product?.name || '-'}</td>
                        <td className="px-3 py-2 text-right">{itm.quantity_loaded}</td>
                        <td className="px-3 py-2 text-right">{itm.quantity_delivered}</td>
                        <td className="px-3 py-2 text-right">{itm.quantity_returned}</td></tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {(selectedDelivery.stops || []).length > 0 && (
              <div className="border-t pt-4">
                <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('deliveries.stops')}</h4>
                <div className="space-y-2">
                  {selectedDelivery.stops.map((stop, i) => (
                    <div key={i} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg text-sm">
                      <span className="font-medium">{stop.retail_store?.business_name || `Stop #${stop.stop_order}`}</span>
                      <span className={`badge ${getStatusBadgeClass(stop.status)}`}>{stop.status}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {selectedDelivery.notes && (
              <div className="border-t pt-2">
                <p className="text-sm"><span className="text-gray-500">{t('deliveries.notes')}</span> {selectedDelivery.notes}</p>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  )
}
