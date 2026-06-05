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
  const [warehouses, setWarehouses] = useState([])
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [selectedOrder, setSelectedOrder] = useState(null)
  const [saving, setSaving] = useState(false)
  const [form, setForm] = useState({
    supplier_id: '', warehouse_id: '', order_date: new Date().toISOString().split('T')[0], notes: '',
    items: [{ product_id: '', quantity_ordered: 1, unit_cost: '' }],
  })

  useEffect(() => {
    Promise.all([
      api.get('/purchase-orders'),
      api.get('/suppliers'),
      api.get('/warehouses'),
      api.get('/products'),
    ]).then(([ord, sup, wh, prod]) => {
      setOrders(ord.data.results || ord.data || [])
      setSuppliers(sup.data.results || sup.data || [])
      setWarehouses(wh.data.results || wh.data || [])
      setProducts(prod.data.results || prod.data || [])
    }).catch(() => toast.error(t('purchaseOrders.loadFailed')))
    .finally(() => setLoading(false))
  }, [t])

  const calcTotal = (items) => {
    const subtotal = items.reduce((sum, i) => sum + (parseFloat(i.unit_cost) || 0) * (parseInt(i.quantity_ordered) || 0), 0)
    return { subtotal, total: subtotal }
  }

  const handleAddItem = () => {
    setForm(f => ({ ...f, items: [...f.items, { product_id: '', quantity_ordered: 1, unit_cost: '' }] }))
  }

  const handleItemChange = (idx, key, value) => {
    const items = [...form.items]
    items[idx][key] = value
    if (key === 'product_id') {
      const product = products.find(p => p.id === parseInt(value))
      if (product) items[idx].unit_cost = product.cost_price || ''
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
      const { subtotal, total } = calcTotal(form.items)
      const payload = {
        supplier_id: form.supplier_id,
        warehouse_id: form.warehouse_id,
        order_date: form.order_date,
        subtotal,
        total,
        notes: form.notes,
        items: form.items.map(i => ({
          product_id: i.product_id,
          quantity_ordered: parseInt(i.quantity_ordered) || 1,
          unit_cost: parseFloat(i.unit_cost) || 0,
        })),
      }
      await api.post('/purchase-orders', payload)
      toast.success(t('purchaseOrders.created'))
      setModalOpen(false)
      setForm({ supplier_id: '', warehouse_id: '', order_date: new Date().toISOString().split('T')[0], notes: '', items: [{ product_id: '', quantity_ordered: 1, unit_cost: '' }] })
      const { data } = await api.get('/purchase-orders')
      setOrders(data.results || data || [])
    } catch (err) { toast.error(err.message) }
    finally { setSaving(false) }
  }

  const handleReceive = async (order) => {
    if (!confirm(t('purchaseOrders.receiveConfirm'))) return
    try {
      let items = order.items
      if (!items) {
        const { data } = await api.get(`/purchase-orders/${order.id}`)
        items = data.data?.items || data.items || []
      }
      const payload = items.map(itm => ({
        purchase_order_item_id: itm.id,
        quantity: itm.quantity_ordered,
      }))
      await api.post(`/purchase-orders/${order.id}/receive`, { items: payload })
      toast.success(t('purchaseOrders.received'))
      setDetailModalOpen(false)
      const { data } = await api.get('/purchase-orders')
      setOrders(data.results || data || [])
    } catch (err) { toast.error(err.message) }
  }

  const columns = [
    { header: t('purchaseOrders.poNo'), accessor: 'order_number', render: (r) => <span className="font-medium">#{r.order_number || r.id}</span> },
    { header: t('purchaseOrders.supplier'), accessor: 'supplier', render: (r) => r.supplier?.business_name || '-' },
    { header: t('purchaseOrders.items'), accessor: 'item_count', render: (r) => r.items?.length || r.item_count || 0 },
    { header: t('purchaseOrders.total'), accessor: 'total', render: (r) => formatCurrency(r.total) },
    { header: t('purchaseOrders.status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status}</span> },
    { header: t('purchaseOrders.date'), accessor: 'created_at', render: (r) => formatDate(r.created_at) },
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <div className="flex gap-1">
        <button onClick={() => { setSelectedOrder(r); setDetailModalOpen(true) }} className="p-1.5 rounded-lg hover:bg-gray-100"><Eye size={14} /></button>
        {(r.status === 'pending' || r.status === 'ordered') && <button onClick={() => handleReceive(r)} className="text-xs text-brand-600 hover:text-brand-800">{t('purchaseOrders.receive')}</button>}
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
        <button className="btn-primary" onClick={() => setModalOpen(true)}><Plus size={18} /> {t('purchaseOrders.newPo')}</button>
      </div>
      <div className="card">
        <DataTable columns={columns} data={orders} loading={loading} searchPlaceholder={t('purchaseOrders.search')} />
      </div>

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title={t('purchaseOrders.createPo')} size="xl">
        <form onSubmit={handleCreate} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('purchaseOrders.supplierLabel')}</label>
              <select className="select-field" value={form.supplier_id} onChange={e => setForm(f => ({ ...f, supplier_id: e.target.value }))} required>
                <option value="">{t('purchaseOrders.selectSupplier')}</option>
                {suppliers.map(s => <option key={s.id} value={s.id}>{s.business_name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('purchaseOrders.notesLabel')}</label>
              <input className="input-field" value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('purchaseOrders.warehouseLabel')}</label>
              <select className="select-field" value={form.warehouse_id} onChange={e => setForm(f => ({ ...f, warehouse_id: e.target.value }))} required>
                <option value="">{t('purchaseOrders.selectWarehouse')}</option>
                {warehouses.map(w => <option key={w.id} value={w.id}>{w.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('purchaseOrders.date')}</label>
              <input type="date" className="input-field" value={form.order_date} onChange={e => setForm(f => ({ ...f, order_date: e.target.value }))} required />
            </div>
          </div>
          <div>
            <div className="flex items-center justify-between mb-2">
              <label className="text-sm font-medium text-gray-700">{t('purchaseOrders.itemsLabel')}</label>
              <button type="button" className="text-sm text-brand-600 hover:text-brand-800" onClick={handleAddItem}>{t('purchaseOrders.addItem')}</button>
            </div>
            <div className="space-y-2">
              {form.items.map((item, idx) => (
                <div key={idx} className="flex items-center gap-2 p-2 bg-gray-50 rounded-lg">
                  <select className="select-field flex-1" value={item.product_id} onChange={e => handleItemChange(idx, 'product_id', e.target.value)} required>
                    <option value="">{t('purchaseOrders.selectProduct')}</option>
                    {products.map(p => <option key={p.id} value={p.id}>{p.name} ({p.sku})</option>)}
                  </select>
                  <input type="number" className="input-field w-20" placeholder={t('purchaseOrders.qty')} min="1" value={item.quantity_ordered}
                    onChange={e => handleItemChange(idx, 'quantity_ordered', parseInt(e.target.value) || 1)} />
                  <input type="number" step="0.01" className="input-field w-28" placeholder={t('purchaseOrders.cost')} value={item.unit_cost}
                    onChange={e => handleItemChange(idx, 'unit_cost', e.target.value)} />
                  <button type="button" className="p-1.5 text-red-500 hover:bg-red-50 rounded" onClick={() => handleRemoveItem(idx)}>X</button>
                </div>
              ))}
            </div>
          </div>
          <div className="flex justify-end gap-2 pt-4">
            <button type="button" className="btn-secondary" onClick={() => setModalOpen(false)}>{t('purchaseOrders.cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? t('purchaseOrders.creating') : t('purchaseOrders.createPoBtn')}</button>
          </div>
        </form>
      </Modal>

      <Modal isOpen={detailModalOpen} onClose={() => setDetailModalOpen(false)} title={`${t('purchaseOrders.poNo')} ${selectedOrder?.order_number || selectedOrder?.id}`} size="lg">
        {selectedOrder && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">{t('purchaseOrders.supplierColon')}</span> <span className="font-medium">{selectedOrder.supplier?.business_name || '-'}</span></div>
              <div><span className="text-gray-500">{t('purchaseOrders.statusColon')}</span> <span className={`badge ${getStatusBadgeClass(selectedOrder.status)}`}>{selectedOrder.status}</span></div>
              <div><span className="text-gray-500">{t('purchaseOrders.createdColon')}</span> {formatDate(selectedOrder.created_at)}</div>
              <div><span className="text-gray-500">{t('purchaseOrders.totalColon')}</span> {formatCurrency(selectedOrder.total)}</div>
            </div>
            <div className="border-t pt-4">
              <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('purchaseOrders.itemsLabel')}</h4>
              <table className="min-w-full text-sm">
                <thead><tr className="bg-gray-50"><th className="px-3 py-2 text-left">{t('purchaseOrders.product')}</th><th className="px-3 py-2 text-right">{t('purchaseOrders.qty')}</th><th className="px-3 py-2 text-right">{t('purchaseOrders.cost')}</th><th className="px-3 py-2 text-right">{t('purchaseOrders.subtotal')}</th></tr></thead>
                <tbody className="divide-y">
                  {(selectedOrder.items || []).map((itm, i) => (
                    <tr key={i}><td className="px-3 py-2">{itm.product?.name || itm.product_name || '-'}</td>
                      <td className="px-3 py-2 text-right">{itm.quantity_ordered}</td>
                      <td className="px-3 py-2 text-right">{formatCurrency(itm.unit_cost)}</td>
                      <td className="px-3 py-2 text-right font-medium">{formatCurrency(itm.quantity_ordered * itm.unit_cost)}</td></tr>
                  ))}
                </tbody>
              </table>
            </div>
            {(selectedOrder.status === 'pending' || selectedOrder.status === 'ordered') && (
              <div className="pt-2"><button className="btn-primary" onClick={() => handleReceive(selectedOrder)}>{t('purchaseOrders.receiveOrder')}</button></div>
            )}
          </div>
        )}
      </Modal>
    </div>
  )
}
