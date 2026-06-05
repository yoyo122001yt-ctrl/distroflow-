import { useState, useEffect } from 'react'
import { ClipboardCheck, CheckCircle, Package } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import BarcodeScanner from '../components/Common/BarcodeScanner'
import { formatDate, getStatusBadgeClass } from '../utils/formatters'

export default function Picking() {
  const { t } = useTranslation()
  const [pickLists, setPickLists] = useState([])
  const [loading, setLoading] = useState(true)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [selectedPick, setSelectedPick] = useState(null)

  useEffect(() => { fetchPickLists() }, [])

  const fetchPickLists = async () => {
    try {
      const { data } = await api.get('/picking')
      setPickLists(data.results || data || [])
    } catch { toast.error(t('picking.loadFailed')) }
    finally { setLoading(false) }
  }

  const handleGenerate = async () => {
    try {
      await api.post('/picking/generate')
      toast.success(t('picking.generated'))
      fetchPickLists()
    } catch (err) { toast.error(err.message) }
  }

  const handleStartPicking = async (pick) => {
    try {
      await api.post(`/picking/${pick.id}/start`)
      toast.success(t('picking.started'))
      fetchPickLists()
    } catch (err) { toast.error(err.message) }
  }

  const handleCompleteItem = async (pickId, itemId) => {
    try {
      await api.post(`/picking/${pickId}/items/${itemId}/pick`)
      toast.success(t('picking.itemPicked'))
      const { data } = await api.get(`/picking/${pickId}`)
      setSelectedPick(data)
    } catch (err) { toast.error(err.message) }
  }

  const handleCompletePick = async (pick) => {
    try {
      await api.post(`/picking/${pick.id}/complete`)
      toast.success(t('picking.completed'))
      setDetailModalOpen(false)
      fetchPickLists()
    } catch (err) { toast.error(err.message) }
  }

  const isPicked = (item) => item.status === 'picked' || item.picked

  const handleScan = async (barcode) => {
    if (!selectedPick) return
    const item = selectedPick.items?.find(i =>
      i.product?.sku === barcode || i.batch?.batch_number === barcode ||
      i.product_sku === barcode || i.batch_code === barcode ||
      i.product?.name?.toLowerCase().includes(barcode.toLowerCase()) ||
      i.product_name?.toLowerCase().includes(barcode.toLowerCase())
    )
    if (item) {
      if (!isPicked(item)) await handleCompleteItem(selectedPick.id, item.id)
      toast.success(t('picking.found', { name: item.product?.name || item.product_name || '' }))
    } else {
      toast.error(t('picking.notFound'))
    }
  }

  const columns = [
    { header: t('picking.pickListNo'), accessor: 'pick_list_number', render: (r) => <span className="font-medium">#{r.pick_list_number || String(r.id).padStart(4, '0')}</span> },
    { header: t('picking.order'), accessor: 'order_number', render: (r) => r.order_number ? `#${r.order_number}` : '-' },
    { header: t('picking.items'), accessor: 'item_count', render: (r) => {
      const total = r.items?.length || r.item_count || 0
      const picked = r.items?.filter(i => isPicked(i))?.length || 0
      return `${picked}/${total}`
    }},
    { header: t('picking.status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status}</span> },
    { header: t('picking.created'), accessor: 'created_at', render: (r) => formatDate(r.created_at) },
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <div className="flex gap-1">
        <button onClick={() => { setSelectedPick(r); setDetailModalOpen(true) }} className="text-sm text-brand-600 hover:text-brand-800">{t('picking.view')}</button>
        {r.status === 'pending' && <button onClick={() => handleStartPicking(r)} className="text-sm text-green-600 hover:text-green-800">{t('picking.start')}</button>}
      </div>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('picking.title')}</h1>
          <p className="text-gray-500 mt-1">{t('picking.subtitle')}</p>
        </div>
        <button className="btn-primary" onClick={handleGenerate}>
          <ClipboardCheck size={18} /> {t('picking.generate')}
        </button>
      </div>
      <div className="card">
        <DataTable columns={columns} data={pickLists} loading={loading} searchPlaceholder={t('picking.search')} />
      </div>

      <Modal isOpen={detailModalOpen} onClose={() => setDetailModalOpen(false)} title={`${t('picking.pickListNo')} ${String(selectedPick?.id || '').padStart(4, '0')}`} size="lg">
        {selectedPick && (
          <div className="space-y-4">
            <div className="flex items-center gap-4">
              <span className={`badge ${getStatusBadgeClass(selectedPick.status)}`}>{selectedPick.status}</span>
              <span className="text-sm text-gray-500">{t('picking.orderColon')} {selectedPick.order_number || '-'}</span>
            </div>

            <BarcodeScanner onScan={handleScan} placeholder={t('picking.scanPlaceholder')} />

            <div className="space-y-2">
              {(selectedPick.items || []).map((item, idx) => (
                <div key={item.id || idx} className={`flex items-center justify-between p-3 rounded-lg border ${isPicked(item) ? 'bg-green-50 border-green-200' : 'bg-white border-gray-200'}`}>
                  <div className="flex items-center gap-3">
                    {isPicked(item) ? <CheckCircle size={18} className="text-green-500" /> : <Package size={18} className="text-gray-400" />}
                    <div>
                      <p className="text-sm font-medium text-gray-900">{item.product?.name || item.product_name || '-'}</p>
                      <p className="text-xs text-gray-500">{t('picking.sku')} {item.product?.sku || item.product_sku || '-'} | {t('picking.location')} {item.location || '-'}</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <span className="text-sm font-medium">{t('picking.qty', { qty: item.quantity })}</span>
                    {!isPicked(item) && selectedPick.status === 'in_progress' && (
                      <button className="btn-primary !py-1 !px-3 text-xs" onClick={() => handleCompleteItem(selectedPick.id, item.id)}>{t('picking.pick')}</button>
                    )}
                    {isPicked(item) && <span className="text-xs text-green-600 font-medium">{t('picking.picked')}</span>}
                  </div>
                </div>
              ))}
            </div>

            {selectedPick.status === 'in_progress' && (
              <div className="pt-2">
                <button className="btn-primary" onClick={() => handleCompletePick(selectedPick)}>{t('picking.complete')}</button>
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  )
}
