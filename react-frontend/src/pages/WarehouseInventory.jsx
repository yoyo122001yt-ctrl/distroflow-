import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus, AlertTriangle } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import ExpiryAlert from '../components/Inventory/ExpiryAlert'
import BarcodeScanner from '../components/Common/BarcodeScanner'
import { formatDate, formatCurrency } from '../utils/formatters'

export default function WarehouseInventory() {
  const { t } = useTranslation()
  const [batches, setBatches] = useState([])
  const [loading, setLoading] = useState(true)
  const [adjustModalOpen, setAdjustModalOpen] = useState(false)
  const [selectedBatch, setSelectedBatch] = useState(null)
  const [adjustment, setAdjustment] = useState({ quantity: 0, reason: '', type: 'add' })
  const [showExpiry, setShowExpiry] = useState(false)

  useEffect(() => { fetchBatches() }, [])

  const fetchBatches = async () => {
    try {
      const { data } = await api.get('/inventory/batches')
      const rows = data.results || data || []
      setBatches(rows.map(b => ({
        ...b,
        product_name: b.product?.name || b.product_name,
        product_sku: b.product?.sku || b.product_sku,
        batch_code: b.batch_number || b.batch_code,
        quantity: b.available_quantity ?? b.quantity,
        min_stock: b.product?.min_stock_level ?? b.min_stock,
        unit: b.product?.unit || b.unit,
        location: b.location || b.warehouse?.name || '',
      })))
    } catch { toast.error(t('inventory.loadFailed')) }
    finally { setLoading(false) }
  }

  const handleScan = (barcode) => {
    const batch = batches.find(b => b.batch_code === barcode || b.product_sku === barcode)
    if (batch) {
      setSelectedBatch(batch)
      setAdjustment({ quantity: 0, reason: '', type: 'add' })
      setAdjustModalOpen(true)
      toast.success(t('inventory.found', { name: batch.product_name }))
    } else {
      toast.error(t('inventory.notFound'))
    }
  }

  const handleAdjustment = async (e) => {
    e.preventDefault()
    try {
      await api.post(`/inventory/batches/${selectedBatch.id}/adjust`, adjustment)
      toast.success(t('inventory.adjusted'))
      setAdjustModalOpen(false)
      fetchBatches()
    } catch (err) { toast.error(err.message) }
  }

  const columns = [
    { header: t('inventory.product'), accessor: 'product_name' },
    { header: t('inventory.sku'), accessor: 'product_sku' },
    { header: t('inventory.batch'), accessor: 'batch_code' },
    { header: t('inventory.quantity'), accessor: 'quantity', render: (r) => (
      <span className={`font-medium ${r.quantity <= (r.min_stock || 0) ? 'text-danger-500' : r.quantity <= (r.min_stock || 0) * 2 ? 'text-warning-500' : 'text-gray-900'}`}>
        {r.quantity} {r.unit}
      </span>
    )},
    { header: t('inventory.location'), accessor: 'location' },
    { header: t('inventory.expiry'), accessor: 'expiry_date', render: (r) => {
      if (!r.expiry_date) return '-'
      const days = Math.ceil((new Date(r.expiry_date) - new Date()) / (1000 * 60 * 60 * 24))
      return (
        <span className={`${days <= 0 ? 'text-danger-500 font-medium' : days <= 30 ? 'text-warning-500' : ''}`}>
          {formatDate(r.expiry_date)} {days <= 0 ? t('inventory.expired') : days <= 7 ? t('inventory.daysLeft', { days }) : ''}
        </span>
      )
    }},
    { header: t('inventory.actions'), accessor: 'actions', sortable: false, render: (r) => (
      <button onClick={() => { setSelectedBatch(r); setAdjustment({ quantity: 0, reason: '', type: 'add' }); setAdjustModalOpen(true) }}
        className="text-sm text-brand-600 hover:text-brand-800">{t('inventory.adjust')}</button>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('inventory.title')}</h1>
          <p className="text-gray-500 mt-1">{t('inventory.subtitle')}</p>
        </div>
        <button onClick={() => setShowExpiry(s => !s)} className="btn-secondary">
          <AlertTriangle size={18} />
          {showExpiry ? t('inventory.hideAlerts') : t('inventory.expiryAlerts')}
        </button>
      </div>

      {showExpiry && (
        <div className="card mb-6">
          <ExpiryAlert batches={batches} onViewBatch={(b) => { setSelectedBatch(b); setAdjustModalOpen(true) }} />
        </div>
      )}

      <div className="card mb-6">
        <BarcodeScanner onScan={handleScan} />
      </div>

      <div className="card">
        <DataTable columns={columns} data={batches} loading={loading} searchPlaceholder={t('inventory.search')} />
      </div>

      <Modal isOpen={adjustModalOpen} onClose={() => setAdjustModalOpen(false)} title={t('inventory.stockAdjustment')} size="sm">
        {selectedBatch && (
          <form onSubmit={handleAdjustment} className="space-y-4">
            <div className="bg-gray-50 rounded-lg p-3">
              <p className="font-medium text-gray-900">{selectedBatch.product_name}</p>
              <p className="text-sm text-gray-500">{t('inventory.batchLabel')} {selectedBatch.batch_code} | {t('inventory.currentLabel')} {selectedBatch.quantity}</p>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('inventory.type')}</label>
              <select className="select-field" value={adjustment.type} onChange={e => setAdjustment(a => ({ ...a, type: e.target.value }))}>
                <option value="add">{t('inventory.addStock')}</option>
                <option value="remove">{t('inventory.removeStock')}</option>
                <option value="damage">{t('inventory.damageWriteOff')}</option>
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('inventory.quantity')}</label>
              <input type="number" className="input-field" value={adjustment.quantity}
                onChange={e => setAdjustment(a => ({ ...a, quantity: parseInt(e.target.value) || 0 }))} required min="1" />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('inventory.reason')}</label>
              <input className="input-field" value={adjustment.reason}
                onChange={e => setAdjustment(a => ({ ...a, reason: e.target.value }))} required />
            </div>
            <div className="flex justify-end gap-2 pt-2">
              <button type="button" className="btn-secondary" onClick={() => setAdjustModalOpen(false)}>{t('common.cancel')}</button>
              <button type="submit" className="btn-primary">{t('inventory.recordAdjustment')}</button>
            </div>
          </form>
        )}
      </Modal>
    </div>
  )
}
