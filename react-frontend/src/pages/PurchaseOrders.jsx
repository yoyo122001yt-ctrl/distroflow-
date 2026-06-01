import { useState, useEffect } from 'react'
import { Plus, Eye, Package } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatCurrency, formatDate, getStatusBadgeClass } from '../utils/formatters'

export default function PurchaseOrders() {
  const { t } = useTranslation()
  const [orders, setOrders] = useState([])
  const [suppliers, setSuppliers] = useState([])
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [selectedOrder, setSelectedOrder] = useState(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({
    supplier_id: '', notes: '', items: [{ product_id: '', quantity: 1, unit_cost: '' }],
  })

  useEffect(() => {
    Promise.all([
      api.get('/purchase-orders'),
      api.get('/suppliers'),
      api.get('/products'),
    ]).then(([ord, sup, prod]) => {
      setOrders(ord.data.results || ord.data || [])
      setSuppliers(sup.data.results || sup.data || [])
      setProducts(prod.data.results || prod.data || [])
    }).catch(() => toast.error(t('purchaseOrders.errors.failedToLoad')))
    .finally(() => setLoading(false))
  }, [t])

  const handleAddItem = () => {
    setForm(f => ({ ...f, items: [...f.items, { product_id: '', quantity: 1, unit_cost: '' }] }))
  }

  const handleItemChange = (idx, key, value) => {
    const items = [...form.items]
    items[idx][key] = value
    if (key === 'product_id') {
      const product = products.find(p => p.id === parseInt(value))
      if (product) items[idx].unit_cost = product.cost || ''
    }
    setForm(f => ({ ...f, items }))
  }

  const handleRemoveItem = (idx) => {
    if (form.items.length === 1) return
    setForm(f => ({ ...f, items: f.items.filter((_, i) => i !== idx) }))
  }

  const handleCreate = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      await api.post('/purchase-orders', form)
      toast.success(t('purchaseOrders.success.created'))
      setModalOpen(false)
      setForm({ supplier_id: '', notes: '', items: [{ product_id: '', quantity: 1, unit_cost: '' }] })
      const { data } = await api.get('/purchase-orders')
      setOrders(data.results || data || [])
    } catch (err) { toast.error(err.message) }
    finally { setSaving(false) }
  }

  const handleReceive = async (order) => {
    if (!confirm(t('purchaseOrders.confirm.markReceived'))) return
    try {
      await api.post(`/purchase-orders/${order.id}/receive`)
      toast.success(t('purchaseOrders.success.received'))
      const { data } = await api.get('/purchase-orders')
      setOrders(data.results || data || [])
    } catch (err) { toast.error(err.message) }
  }

  const columns = [
    { header: t('purchaseOrders.columns.poNumber'), accessor: 'order_number', render: (r) => <span className="font-medium">#{r.order_number || r.id}</span> },
    { header: t('purchaseOrders.columns.supplier'), accessor: 'supplier_name' },
    { header: t('purchaseOrders.columns.items'), accessor: 'item_count', render: (r) => r.items?.length || r.item_count || 0 },
    { header: t('purchaseOrders.columns.total'), accessor: 'total', render: (r) => formatCurrency(r.total) },
    { header: t('purchaseOrders.columns.status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status}</span> },
    { header: t('purchaseOrders.columns.date'), accessor: 'created_at', render: (r) => formatDate(r.created_at) },
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <div className="flex gap-1">
        <button onClick={() => { setSelectedOrder(r); setDetailModalOpen(true) }} className="p-1.5 rounded-lg hover:bg-gray-100"><Eye size={14} /></button>
        {r.status === 'ordered' && <button onClick={() => handleReceive(r)} className="text-xs text-brand-600 hover:text-brand-800">{t('purchaseOrders.actions.receive')}</button>}
      </div>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('purchaseOrders.title')}</h1>
          <p className="text-gray-500 mt-1">{t('purchaseOrders.subtitle')}</p>
        </div>
        <button className="btn-primary" onClick={() => setModalOpen(true)}><Plus size={18} /> {t('purchaseOrders.actions.newPo')}</button>
      </div>
      <div className="card">
        <DataTable columns={columns} data={orders} loading={loading} searchPlaceholder={t('purchaseOrders.searchPlaceholder')} />
      </div>

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={t('purchaseOrders.createOrder.title')} size="xl">
        <form onSubmit={handleCreate} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('purchaseOrders.createOrder.supplier')}</label>
              <select className="select-field" value={form.supplier_id} onChange={e => setForm(f => ({ ...f, supplier_id: e.target.value }))} required>
                <option value="">{t('purchaseOrders.createOrder.selectSupplier')}</option>
                {suppliers.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('purchaseOrders.createOrder.notes')}</label>
              <input className="input-field" value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} />
            </div>
          </div>
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="text-sm font-medium text-gray-700">{t('purchaseOrders.createOrder.itemsLabel')}</label>
              <button type="button" className="text-sm text-brand-600 hover:text-brand-800" onClick={handleAddItem}>{t('purchaseOrders.createOrder.addItem')}</button>
            </div>
            <div className="space-y-2">
              {form.items.map((item, idx) => (
                <div key={idx} className="flex items-center gap-2 p-2 bg-gray-50 rounded-lg">
                  <select className="select-field flex-1" value={item.product_id} onChange={e => handleItemChange(idx, 'product_id', e.target.value)} required>
                    <option value="">{t('purchaseOrders.createOrder.selectProduct')}</option>
                    {products.map(p => <option key={p.id} value={p.id}>{p.name} ({p.sku})</option>)}
                  </select>
                  <input type="number" className="input-field w-20" placeholder={t('purchaseOrders.createOrder.qty')} min="1" value={item.quantity}
                    onChange={e => handleItemChange(idx, 'quantity', parseInt(e.target.value) || 1)} />
                  <input type="number" step="0.01" className="input-field w-28" placeholder={t('purchaseOrders.createOrder.cost')} value={item.unit_cost}
                    onChange={e => handleItemChange(idx, 'unit_cost', e.target.value)} />
                  <button type="button" className="p-1.5 text-red-500 hover:bg-red-50 rounded" onClick={() => handleRemoveItem(idx)}>X</button>
                </div>
              ))}
            </div>
          </div>
          <div className="flex justify-end gap-2 pt-4">
            <button type="button" className="btn-secondary" onClick={() => setModalOpen(false)}>{t('purchaseOrders.actions.cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? t('purchaseOrders.actions.creating') : t('purchaseOrders.actions.createPo')}</button>
          </div>
        </form>
      </Modal>

      <Modal isOpen={detailModalOpen} onClose={() => setDetailModalOpen(false)} title={t('purchaseOrders.details.poTitle', { number: selectedOrder?.order_number || selectedOrder?.id })} size="lg">
        {selectedOrder && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">{t('purchaseOrders.details.supplier')}</span> <span className="font-medium">{selectedOrder.supplier_name}</span></div>
              <div><span className="text-gray-500">{t('purchaseOrders.details.status')}</span> <span className={`badge ${getStatusBadgeClass(selectedOrder.status)}`}>{selectedOrder.status}</span></div>
              <div><span className="text-gray-500">{t('purchaseOrders.details.created')}</span> {formatDate(selectedOrder.created_at)}</div>
              <div><span className="text-gray-500">{t('purchaseOrders.details.total')}</span> {formatCurrency(selectedOrder.total)}</div>
            </div>
            <div className="border-t pt-4">
              <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('purchaseOrders.details.items')}</h4>
              <table className="min-w-full text-sm">
                <thead><tr className="bg-gray-50"><th className="px-3 py-2 text-left">{t('purchaseOrders.details.product')}</th><th className="px-3 py-2 text-right">{t('purchaseOrders.details.qty')}</th><th className="px-3 py-2 text-right">{t('purchaseOrders.details.cost')}</th><th className="px-3 py-2 text-right">{t('purchaseOrders.details.subtotal')}</th></tr></thead>
                <tbody className="divide-y">
                  {(selectedOrder.items || []).map((itm, i) => (
                    <tr key={i}><td className="px-3 py-2">{itm.product_name || itm.product}</td>
                      <td className="px-3 py-2 text-right">{itm.quantity}</td>
                      <td className="px-3 py-2 text-right">{formatCurrency(itm.unit_cost)}</td>
                      <td className="px-3 py-2 text-right font-medium">{formatCurrency(itm.quantity * itm.unit_cost)}</td></tr>
                  ))}
                </tbody>
              </table>
            </div>
            {selectedOrder.status === 'ordered' && (
              <div className="pt-2"><button className="btn-primary" onClick={() => { handleReceive(selectedOrder); setDetailModalOpen(false) }}>{t('purchaseOrders.actions.receiveOrder')}</button></div>
            )}
          </div>
        )}
      </Modal>
    </div>
  )
}
