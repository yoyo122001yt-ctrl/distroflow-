import { useState, useEffect } from 'react'
import { Plus, Eye, CheckCircle, XCircle, Truck } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import OrderCart from '../components/Orders/OrderCart'
import { formatCurrency, formatDate, getStatusBadgeClass } from '../utils/formatters'

export default function SalesOrders() {
  const { t } = useTranslation()
  const [orders, setOrders] = useState([])
  const [stores, setStores] = useState([])
  const [products, setProducts] = useState([])
  const [routes, setRoutes] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [selectedOrder, setSelectedOrder] = useState(null)
  const [saving, setSaving] = useState(false)
  const [cart, setCart] = useState([])
  const [form, setForm] = useState({ store_id: '', route_id: '', notes: '', delivery_date: '' })

  useEffect(() => {
    Promise.all([
      api.get('/orders'),
      api.get('/stores'),
      api.get('/products'),
      api.get('/routes'),
    ]).then(([ord, st, pr, rt]) => {
      setOrders(ord.data.results || ord.data || [])
      setStores(st.data.results || st.data || [])
      setProducts(pr.data.results || pr.data || [])
      setRoutes(rt.data.results || rt.data || [])
    }).catch(() => toast.error(t('salesOrders.errors.failedToLoad')))
    .finally(() => setLoading(false))
  }, [t])

  const handleUpdateQuantity = (productId, quantity, product) => {
    if (quantity <= 0) {
      setCart(c => c.filter(i => i.product_id !== productId))
      return
    }
    setCart(c => {
      const existing = c.find(i => i.product_id === productId)
      if (existing) {
        return c.map(i => i.product_id === productId ? { ...i, quantity } : i)
      }
      return [...c, { product_id: productId, name: product.name, price: product.price, quantity: 1, sku: product.sku }]
    })
  }

  const cartTotal = cart.reduce((sum, i) => sum + (i.price || 0) * i.quantity, 0)

  const handleCreate = async (e) => {
    e.preventDefault()
    if (cart.length === 0) { toast.error(t('salesOrders.errors.addItem')); return }
    setSaving(true)
    try {
      await api.post('/orders', {
        ...form,
        items: cart.map(i => ({ product_id: i.product_id, quantity: i.quantity, price: i.price })),
        total: cartTotal,
      })
      toast.success(t('salesOrders.success.created'))
      setModalOpen(false)
      setCart([])
      setForm({ store_id: '', route_id: '', notes: '', delivery_date: '' })
      const { data } = await api.get('/orders')
      setOrders(data.results || data || [])
    } catch (err) { toast.error(err.message) }
    finally { setSaving(false) }
  }

  const handleApprove = async (order) => {
    try { await api.post(`/orders/${order.id}/approve`); toast.success(t('salesOrders.success.approved')); refreshOrders() }
    catch (err) { toast.error(err.message) }
  }

  const handleReject = async (order) => {
    try { await api.post(`/orders/${order.id}/cancel`, { reason: 'Rejected' }); toast.success(t('salesOrders.success.rejected')); refreshOrders() }
    catch (err) { toast.error(err.message) }
  }

  const refreshOrders = async () => {
    const { data } = await api.get('/orders')
    setOrders(data.results || data || [])
  }

  const columns = [
    { header: t('salesOrders.columns.orderNumber'), accessor: 'order_number', render: (r) => <span className="font-medium">#{r.order_number || r.id}</span> },
    { header: t('salesOrders.columns.store'), accessor: 'store_name' },
    { header: t('salesOrders.columns.items'), accessor: 'item_count', render: (r) => r.items?.length || r.item_count || 0 },
    { header: t('salesOrders.columns.total'), accessor: 'total', render: (r) => formatCurrency(r.total) },
    { header: t('salesOrders.columns.status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status}</span> },
    { header: t('salesOrders.columns.date'), accessor: 'created_at', render: (r) => formatDate(r.created_at) },
    { header: '', accessor: 'actions', sortable: false, render: (r) => (
      <div className="flex items-center gap-1">
        <button onClick={() => { setSelectedOrder(r); setDetailModalOpen(true) }} className="p-1.5 rounded-lg hover:bg-gray-100"><Eye size={14} /></button>
        {r.status === 'pending' && <><button onClick={() => handleApprove(r)} className="p-1.5 rounded-lg hover:bg-green-50 text-green-600"><CheckCircle size={14} /></button>
          <button onClick={() => handleReject(r)} className="p-1.5 rounded-lg hover:bg-red-50 text-red-500"><XCircle size={14} /></button></>}
      </div>
    )},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('salesOrders.title')}</h1>
          <p className="text-gray-500 mt-1">{t('salesOrders.subtitle')}</p>
        </div>
        <button className="btn-primary" onClick={() => setModalOpen(true)}><Plus size={18} /> {t('salesOrders.actions.newOrder')}</button>
      </div>
      <div className="card">
        <DataTable columns={columns} data={orders} loading={loading} searchPlaceholder={t('salesOrders.searchPlaceholder')} />
      </div>

      <Modal isOpen={modalOpen} onClose={() => { setModalOpen(false); setCart([]) }} title={t('salesOrders.createOrder.title')} size="xl">
        <form onSubmit={handleCreate} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('salesOrders.createOrder.store')}</label>
              <select className="select-field" value={form.store_id} onChange={e => setForm(f => ({ ...f, store_id: e.target.value }))} required>
                <option value="">{t('salesOrders.createOrder.selectStore')}</option>
                {stores.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('salesOrders.createOrder.route')}</label>
              <select className="select-field" value={form.route_id} onChange={e => setForm(f => ({ ...f, route_id: e.target.value }))}>
                <option value="">{t('salesOrders.createOrder.noRoute')}</option>
                {routes.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('salesOrders.createOrder.deliveryDate')}</label>
              <input type="date" className="input-field" value={form.delivery_date} onChange={e => setForm(f => ({ ...f, delivery_date: e.target.value }))} />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('salesOrders.createOrder.notes')}</label>
              <input className="input-field" value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} />
            </div>
          </div>
          <OrderCart items={cart} onUpdateQuantity={handleUpdateQuantity} onRemoveItem={(pid) => setCart(c => c.filter(i => i.product_id !== pid))} onClear={() => setCart([])} products={products} />
          <div className="flex justify-end gap-2 pt-4">
            <button type="button" className="btn-secondary" onClick={() => { setModalOpen(false); setCart([]) }}>{t('salesOrders.actions.cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? t('salesOrders.actions.creating') : t('salesOrders.actions.createOrder', { total: formatCurrency(cartTotal) })}</button>
          </div>
        </form>
      </Modal>

      <Modal isOpen={detailModalOpen} onClose={() => setDetailModalOpen(false)} title={t('salesOrders.details.orderTitle', { number: selectedOrder?.order_number || selectedOrder?.id })} size="lg">
        {selectedOrder && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">{t('salesOrders.details.store')}</span> <span className="font-medium">{selectedOrder.store_name}</span></div>
              <div><span className="text-gray-500">{t('salesOrders.details.status')}</span> <span className={`badge ${getStatusBadgeClass(selectedOrder.status)}`}>{selectedOrder.status}</span></div>
              <div><span className="text-gray-500">{t('salesOrders.details.date')}</span> {formatDate(selectedOrder.created_at)}</div>
              <div><span className="text-gray-500">{t('salesOrders.details.total')}</span> {formatCurrency(selectedOrder.total)}</div>
              {selectedOrder.route_name && <div><span className="text-gray-500">{t('salesOrders.details.route')}</span> {selectedOrder.route_name}</div>}
            </div>
            <div className="border-t pt-4">
              <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('salesOrders.details.items')}</h4>
              <table className="min-w-full text-sm">
                <thead><tr className="bg-gray-50"><th className="px-3 py-2 text-left">{t('salesOrders.details.product')}</th><th className="px-3 py-2 text-right">{t('salesOrders.details.qty')}</th><th className="px-3 py-2 text-right">{t('salesOrders.details.price')}</th><th className="px-3 py-2 text-right">{t('salesOrders.details.subtotal')}</th></tr></thead>
                <tbody className="divide-y">
                  {(selectedOrder.items || []).map((itm, i) => (
                    <tr key={i}><td className="px-3 py-2">{itm.product_name || itm.product}</td>
                      <td className="px-3 py-2 text-right">{itm.quantity}</td>
                      <td className="px-3 py-2 text-right">{formatCurrency(itm.price)}</td>
                      <td className="px-3 py-2 text-right font-medium">{formatCurrency(itm.quantity * itm.price)}</td></tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
