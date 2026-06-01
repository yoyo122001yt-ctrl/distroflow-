import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { CheckCircle, XCircle, Eye, DollarSign } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatDate, formatCurrency, formatDateTime, getStatusBadgeClass } from '../utils/formatters'

export default function Settlements() {
  const { t } = useTranslation()
  const [settlements, setSettlements] = useState([])
  const [loading, setLoading] = useState(true)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [selectedSettlement, setSelectedSettlement] = useState(null)
  const [statusFilter, setStatusFilter] = useState('')

  useEffect(() => { fetchSettlements() }, [])

  const fetchSettlements = async () => {
    try {
      const { data } = await api.get('/settlements')
      setSettlements(data.results || data || [])
    } catch { toast.error(t('settlements:failedToLoad')) }
    finally { setLoading(false) }
  }

  const filtered = statusFilter ? settlements.filter(s => s.status === statusFilter) : settlements

  const handleApprove = async (settlement) => {
    try {
      await api.post(`/settlements/${settlement.id}/approve`)
      toast.success(t('settlements:approved'))
      fetchSettlements()
    } catch (err) { toast.error(err.message) }
  }

  const handleReject = async (settlement) => {
    try {
      await api.post(`/settlements/${settlement.id}/reject`)
      toast.success(t('settlements:rejected'))
      fetchSettlements()
    } catch (err) { toast.error(err.message) }
  }

  const handleGenerate = async () => {
    try {
      await api.post('/settlements/generate')
      toast.success(t('settlements:generated'))
      fetchSettlements()
    } catch (err) { toast.error(err.message) }
  }

  const totalPending = settlements.filter(s => s.status === 'pending').reduce((sum, s) => sum + (s.total_pay || s.amount || 0), 0)

  const columns = [
    { header: t('settlements:driver'), accessor: 'driver_name', render: (r) => (
      <div><p className="font-medium text-gray-900">{r.driver_name}</p><p className="text-xs text-gray-500">{r.period}</p></div>
    )},
    { header: t('settlements:deliveries'), accessor: 'deliveries_completed', render: (r) => `${r.deliveries_completed || 0} / ${r.total_deliveries || 0}` },
    { header: t('settlements:distance'), accessor: 'total_distance', render: (r) => `${r.total_distance || 0} km` },
    { header: t('settlements:totalPay'), accessor: 'total_pay', render: (r) => <span className="font-medium">{formatCurrency(r.total_pay || r.amount || 0)}</span> },
    { header: t('settlements:status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status}</span> },
    { header: t('settlements:date'), accessor: 'created_at', render: (r) => formatDate(r.created_at) },
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <div className="flex gap-1">
        <button onClick={() => { setSelectedSettlement(r); setDetailModalOpen(true) }} className="p-1.5 rounded-lg hover:bg-gray-100"><Eye size={14} /></button>
        {r.status === 'pending' && (
          <>
            <button onClick={() => handleApprove(r)} className="p-1.5 rounded-lg hover:bg-green-50 text-green-600"><CheckCircle size={14} /></button>
            <button onClick={() => handleReject(r)} className="p-1.5 rounded-lg hover:bg-red-50 text-red-500"><XCircle size={14} /></button>
          </>
        )}
      </div>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('settlements:title')}</h1>
          <p className="text-gray-500 mt-1">{t('settlements:subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-sm text-gray-500 bg-white px-3 py-1.5 rounded-lg border">
            {t('settlements:pending')}: <strong>{formatCurrency(totalPending)}</strong>
          </span>
          <button className="btn-primary" onClick={handleGenerate}>
            <DollarSign size={18} /> {t('settlements:generate')}
          </button>
        </div>
      </div>
      <div className="card">
        <div className="flex items-center gap-2 mb-4">
          {['', 'pending', 'approved', 'rejected', 'paid'].map(s => (
            <button key={s} className={`px-3 py-1.5 rounded-lg text-sm ${statusFilter === s ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'}`}
              onClick={() => setStatusFilter(s)}>{s || t('settlements:all')}</button>
          ))}
        </div>
        <DataTable columns={columns} data={filtered} loading={loading} searchPlaceholder={t('settlements:searchPlaceholder')} />
      </div>

      <Modal isOpen={detailModalOpen} onClose={() => setDetailModalOpen(false)} title={t('settlements:detailTitle')} size="lg">
        {selectedSettlement && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">{t('settlements:driver')}:</span> <span className="font-medium">{selectedSettlement.driver_name}</span></div>
              <div><span className="text-gray-500">{t('settlements:period')}:</span> {selectedSettlement.period}</div>
              <div><span className="text-gray-500">{t('settlements:status')}:</span> <span className={`badge ${getStatusBadgeClass(selectedSettlement.status)}`}>{selectedSettlement.status}</span></div>
              <div><span className="text-gray-500">{t('settlements:totalPay')}:</span> <span className="font-bold">{formatCurrency(selectedSettlement.total_pay || selectedSettlement.amount || 0)}</span></div>
            </div>
            <div className="border-t pt-4">
              <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('settlements:breakdown')}</h4>
              <div className="grid grid-cols-3 gap-4 text-sm">
                <div className="p-3 bg-gray-50 rounded-lg text-center">
                  <p className="text-xl font-bold text-gray-900">{selectedSettlement.deliveries_completed || 0}</p>
                  <p className="text-xs text-gray-500">{t('settlements:deliveries')}</p>
                </div>
                <div className="p-3 bg-gray-50 rounded-lg text-center">
                  <p className="text-xl font-bold text-gray-900">{selectedSettlement.total_distance || 0}</p>
                  <p className="text-xs text-gray-500">{t('settlements:totalKm')}</p>
                </div>
                <div className="p-3 bg-gray-50 rounded-lg text-center">
                  <p className="text-xl font-bold text-gray-900">{selectedSettlement.total_hours || 0}</p>
                  <p className="text-xs text-gray-500">{t('settlements:totalHours')}</p>
                </div>
              </div>
            </div>
            {selectedSettlement.status === 'pending' && (
              <div className="flex gap-2 pt-2">
                <button className="btn-primary" onClick={() => handleApprove(selectedSettlement)}><CheckCircle size={16} /> {t('settlements:approve')}</button>
                <button className="btn-danger" onClick={() => handleReject(selectedSettlement)}><XCircle size={16} /> {t('settlements:reject')}</button>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  )
}
