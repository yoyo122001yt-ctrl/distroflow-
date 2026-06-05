import { useState, useEffect } from 'react'
import { Plus, Eye, CheckCircle, XCircle } from 'lucide-react'
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
  const [warehouses, setWarehouses] = useState([])
  const [products, setProducts] = useState([])
  const [routes, setRoutes] = useState([])
  const [loading, setLoading] = useState(true)
  const [modalOpen, setModalOpen] = useState(false)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [selectedOrder, setSelectedOrder] = useState(null)
  const [saving, setSaving] = useState(false)
  const [cart, setCart] = useState([])
  const [form, setForm] = useState({
    retail_store_id: '', warehouse_id: '', route_id: '', notes: '',
    order_date: new Date().toISOString().split('T')[0], source: 'phone',
  })

  useEffect(() => {
    Promise.all([
      api.get('/orders'),
      api.get('/stores'),
      api.get('/warehouses'),
      api.get('/products'),
      api.get('/routes'),
    ]).then(([ord, st, wh, pr, rt]) => {
      setOrders(ord.data.results || ord.data || [])
      setStores(st.data.results || st.data || [])
      setWarehouses(wh.data.results || wh.data || [])
      setProducts(pr.data.results || pr.data || [])
      setRoutes(rt.data.results || rt.data || [])
    }).catch(() => toast.error(t('salesOrders.loadFailed')))
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
      return [...c, { product_id: productId, name: product.name, unit_price: product.selling_price, quantity: 1, sku: product.sku }]
    })
  }

  const cartTotal = cart.reduce((sum, i) => sum + (i.unit_price || 0) * i.quantity, 0)

  const handleCreate = async (e) => {
    e.preventDefault()
    if (cart.length === 0) { toast.error(t('salesOrders.addItemFirst')); return }
    setSaving(true)
    try {
      await api.post('/orders', {
        retail_store_id: form.retail_store_id,
        warehouse_id: form.warehouse_id,
        order_date: form.order_date,
        source: form.source,
        route_id: form.route_id || undefined,
        notes: form.notes,
        subtotal: cartTotal,
        total: cartTotal,
        items: cart.map(i => ({
          product_id: i.product_id,
          quantity_ordered: i.quantity,
          unit_price: i.unit_price,
        })),
      })
      toast.success(t('salesOrders.created'))
      setModalOpen(false)
      setCart([])
      setForm({
        retail_store_id: '', warehouse_id: '', route_id: '', notes: '',
        order_date: new Date().toISOString().split('T')[0], source: 'phone',
      })
      const { data } = await api.get('/orders')
      setOrders(data.results || data || [])
    } catch (err) { toast.error(err.message) }
    finally { setSaving(false) }
  }

  const handleApprove = async (order) => {
    try { await api.post(`/orders/${order.id}/approve`); toast.success(t('salesOrders.approved')); refreshOrders() }
    catch (err) { toast.error(err.message) }
  }

  const handleReject = async (order) => {
    try { await api.post(`/orders/${order.id}/cancel`, { reason: 'Rejected' }); toast.success(t('salesOrders.rejected')); refreshOrders() }
    catch (err) { toast.error(err.message) }
  }

  const refreshOrders = async () => {
    const { data } = await api.get('/orders')
    setOrders(data.results || data || [])
  }

  const columns = [
    { header: t('salesOrders.orderNo'), accessor: 'order_number', render: (r) => <span className="font-medium">#{r.order_number || r.id}</span> },
    { header: t('salesOrders.store'), accessor: 'retail_store', render: (r) => r.retail_store?.business_name || '-' },
    { header: t('salesOrders.items'), accessor: 'item_count', render: (r) => r.items?.length || r.item_count || 0 },
    { header: t('salesOrders.total'), accessor: 'total', render: (r) => formatCurrency(r.total) },
    { header: t('salesOrders.status'), accessor: 'status', render: (r) => <span className={`badge ${getStatusBadgeClass(r.status)}`}>{r.status}</span> },
    { header: t('salesOrders.date'), accessor: 'created_at', render: (r) => formatDate(r.created_at) },
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
        <button className="btn-primary" onClick={() => setModalOpen(true)}><Plus size={18} /> {t('salesOrders.newOrder')}</button>
      </div>
      <div className="card">
        <DataTable columns={columns} data={orders} loading={loading} searchPlaceholder={t('salesOrders.search')} />
      </div>

      <Modal isOpen={modalOpen} onClose={() => { setModalOpen(false); setCart([]) }} title={t('salesOrders.createOrder')} size="xl">
        <form onSubmit={handleCreate} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('salesOrders.storeLabel')}</label>
              <select className="select-field" value={form.retail_store_id} onChange={e => setForm(f => ({ ...f, retail_store_id: e.target.value }))} required>
                <option value="">{t('salesOrders.selectStore')}</option>
                {stores.map(s => <option key={s.id} value={s.id}>{s.business_name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('salesOrders.routeLabel')}</label>
              <select className="select-field" value={form.route_id} onChange={e => setForm(f => ({ ...f, route_id: e.target.value }))}>
                <option value="">{t('salesOrders.noRoute')}</option>
                {routes.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('salesOrders.deliveryDateLabel')}</label>
              <input type="date" className="input-field" value={form.order_date} onChange={e => setForm(f => ({ ...f, order_date: e.target.value }))} required />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">{t('salesOrders.notesLabel')}</label>
              <input className="input-field" value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} />
            </div>
          </div>
          <OrderCart items={cart} onUpdateQuantity={handleUpdateQuantity} onRemoveItem={(pid) => setCart(c => c.filter(i => i.product_id !== pid))} onClear={() => setCart([])} products={products} />
          <div className="flex justify-end gap-2 pt-4">
            <button type="button" className="btn-secondary" onClick={() => { setModalOpen(false); setCart([]) }}>{t('salesOrders.cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? t('salesOrders.creating') : t('salesOrders.createOrderBtn', { amount: formatCurrency(cartTotal) })}</button>
          </div>
        </form>
      </Modal>

      <Modal isOpen={detailModalOpen} onClose={() => setDetailModalOpen(false)} title={`${t('salesOrders.orderNo')} ${selectedOrder?.order_number || selectedOrder?.id}`} size="lg">
        {selectedOrder && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">{t('salesOrders.storeColon')}</span> <span className="font-medium">{selectedOrder.retail_store?.business_name || '-'}</span></div>
              <div><span className="text-gray-500">{t('salesOrders.statusColon')}</span> <span className={`badge ${getStatusBadgeClass(selectedOrder.status)}`}>{selectedOrder.status}</span></div>
              <div><span className="text-gray-500">{t('salesOrders.dateColon')}</span> {formatDate(selectedOrder.created_at)}</div>
              <div><span className="text-gray-500">{t('salesOrders.totalColon')}</span> {formatCurrency(selectedOrder.total)}</div>
              {selectedOrder.route && <div><span className="text-gray-500">{t('salesOrders.routeColon')}</span> {selectedOrder.route.name}</div>}
            </div>
            <div className="border-t pt-4">
              <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('salesOrders.items')}</h4>
              <table className="min-w-full text-sm">
                <thead><tr className="bg-gray-50"><th className="px-3 py-2 text-left">{t('salesOrders.product')}</th><th className="px-3 py-2 text-right">{t('salesOrders.qty')}</th><th className="px-3 py-2 text-right">{t('salesOrders.price')}</th><th className="px-3 py-2 text-right">{t('salesOrders.subtotal')}</th></tr></thead>
                <tbody className="divide-y">
                  {(selectedOrder.items || []).map((itm, i) => (
                    <tr key={i}><td className="px-3 py-2">{itm.product?.name || '-'}</td>
                      <td className="px-3 py-2 text-right">{itm.quantity_ordered}</td>
                      <td className="px-3 py-2 text-right">{formatCurrency(itm.unit_price)}</td>
                      <td className="px-3 py-2 text-right font-medium">{formatCurrency((itm.quantity_ordered || 0) * (itm.unit_price || 0))}</td></tr>
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
