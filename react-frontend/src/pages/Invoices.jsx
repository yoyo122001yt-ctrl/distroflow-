import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Eye, Download } from 'lucide-react'
import toast from 'react-hot-toast'
import api from '../services/api'
import DataTable from '../components/Common/DataTable'
import Modal from '../components/Common/Modal'
import { formatDate, formatCurrency, formatDateTime, getStatusBadgeClass } from '../utils/formatters'
import { exportInvoice } from '../services/export'

export default function Invoices() {
  const { t } = useTranslation()
  const [invoices, setInvoices] = useState([])
  const [loading, setLoading] = useState(true)
  const [detailModalOpen, setDetailModalOpen] = useState(false)
  const [selectedInvoice, setSelectedInvoice] = useState(null)
  const [paymentModalOpen, setPaymentModalOpen] = useState(false)
  const [payment, setPayment] = useState({ amount: '', method: 'cash', reference: '', notes: '' })
  const [statusFilter, setStatusFilter] = useState('')
  const [saving, setSaving] = useState(false)

  useEffect(() => { fetchInvoices() }, [])

  const fetchInvoices = async () => {
    try {
      const { data } = await api.get('/invoices')
      setInvoices(data.results || data || [])
    } catch { toast.error(t('invoices.loadFailed')) }
    finally { setLoading(false) }
  }

  const filtered = statusFilter ? invoices.filter(i => i.status === statusFilter) : invoices

  const handleRecordPayment = async (e) => {
    e.preventDefault()
    setSaving(true)
    try {
      await api.post('/invoices/' + selectedInvoice.id + '/record-payment', {
        amount: payment.amount,
        payment_method: payment.method,
        reference_number: payment.reference,
        notes: payment.notes,
      })
      toast.success(t('invoices.paymentRecorded'))
      setPaymentModalOpen(false)
      setPayment({ amount: '', method: 'cash', reference: '', notes: '' })
      const { data } = await api.get('/invoices/' + selectedInvoice.id)
      setSelectedInvoice(data)
      fetchInvoices()
    } catch (err) { toast.error(err.message) }
    finally { setSaving(false) }
  }

  const handleDownload = async (invoice) => {
    try {
      await exportInvoice(invoice.id)
      toast.success(t('invoices.invoiceDownloaded'))
    } catch (err) { toast.error(err.message) }
  }

  const totalOutstanding = invoices.filter(i => i.status === 'unpaid' || i.status === 'overdue')
    .reduce((sum, i) => sum + (i.balance_due ?? i.total - (i.amount_paid || 0)), 0)

  const columns = [
    { header: t('invoices.invoiceNo'), accessor: 'invoice_number', render: (r) => <span className="font-medium">#{r.invoice_number || r.id}</span> },
    { header: t('invoices.customer'), accessor: 'customer_name', render: (r) => r.retail_store?.business_name || r.retail_store?.trade_name || r.customer_name || '-' },
    { header: t('invoices.amount'), accessor: 'total', render: (r) => formatCurrency(r.total) },
    { header: t('invoices.paid'), accessor: 'amount_paid', render: (r) => formatCurrency(r.amount_paid || 0) },
    { header: t('invoices.balance'), accessor: 'balance_due', render: (r) => {
      const bal = r.balance_due ?? r.total - (r.amount_paid || 0)
      return <span className={bal > 0 ? 'text-danger-500 font-medium' : 'text-green-600'}>{formatCurrency(bal)}</span>
    }},
    { header: t('invoices.status'), accessor: 'status', render: (r) => <span className={'badge ' + getStatusBadgeClass(r.status)}>{r.status}</span> },
    { header: t('invoices.date'), accessor: 'invoice_date', render: (r) => formatDate(r.invoice_date || r.created_at) },
    { header: '', accessor: 'actions', sortable: false, render: (r) => {
      const bal = r.balance_due ?? r.total - (r.amount_paid || 0)
      return (
        <div className="flex gap-1">
          <button onClick={async () => { const { data } = await api.get('/invoices/' + r.id); setSelectedInvoice(data); setDetailModalOpen(true) }} className="p-1.5 rounded-lg hover:bg-gray-100"><Eye size={14} /></button>
          <button onClick={() => handleDownload(r)} className="p-1.5 rounded-lg hover:bg-gray-100"><Download size={14} /></button>
          {bal > 0 && <button onClick={() => { setSelectedInvoice(r); setPayment({ amount: bal, method: 'cash', reference: '', notes: '' }); setPaymentModalOpen(true) }}
            className="text-xs text-brand-600 hover:text-brand-800">{t('invoices.pay')}</button>}
        </div>
      )
    }},
  ]

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('invoices.title')}</h1>
          <p className="text-gray-500 mt-1">{t('invoices.subtitle')}</p>
        </div>
        <div className="text-sm bg-white px-4 py-2 rounded-lg border">
          {t('invoices.outstanding', { amount: formatCurrency(totalOutstanding) })}
        </div>
      </div>
      <div className="card">
        <div className="flex items-center gap-2 mb-4 flex-wrap">
          {['', 'unpaid', 'paid', 'overdue', 'cancelled'].map(s => (
            <button key={s} className={'px-3 py-1.5 rounded-lg text-sm ' + (statusFilter === s ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200')}
              onClick={() => setStatusFilter(s)}>{s || t('invoices.all')}</button>
          ))}
        </div>
        <DataTable columns={columns} data={filtered} loading={loading} searchPlaceholder={t('invoices.search')} />
      </div>

      <Modal isOpen={detailModalOpen} onClose={() => setDetailModalOpen(false)} title={t('invoices.invoiceNo') + (selectedInvoice?.invoice_number || selectedInvoice?.id)} size="lg">
        {selectedInvoice && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4 text-sm">
              <div><span className="text-gray-500">{t('invoices.customer')}:</span> <span className="font-medium">{selectedInvoice.retail_store?.business_name || selectedInvoice.retail_store?.trade_name || selectedInvoice.customer_name}</span></div>
              <div><span className="text-gray-500">{t('invoices.status')}:</span> <span className={'badge ' + getStatusBadgeClass(selectedInvoice.status)}>{selectedInvoice.status}</span></div>
              <div><span className="text-gray-500">{t('invoices.issueDateColon')}</span> {formatDate(selectedInvoice.invoice_date || selectedInvoice.created_at)}</div>
              <div><span className="text-gray-500">{t('invoices.dueDateColon')}</span> {formatDate(selectedInvoice.due_date) || '-'}</div>
            </div>
            <div className="border-t pt-4">
              <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('invoices.items')}</h4>
              <table className="min-w-full text-sm">
                <thead><tr className="bg-gray-50"><th className="px-3 py-2 text-left">{t('invoices.description')}</th><th className="px-3 py-2 text-right">{t('invoices.qty')}</th><th className="px-3 py-2 text-right">{t('invoices.rate')}</th><th className="px-3 py-2 text-right">{t('invoices.amount')}</th></tr></thead>
                <tbody className="divide-y">
                  {(selectedInvoice.sales_order?.items || selectedInvoice.salesOrder?.items || []).map((item, i) => (
                    <tr key={i}><td className="px-3 py-2">{item.description || item.product_name}</td>
                      <td className="px-3 py-2 text-right">{item.quantity}</td>
                      <td className="px-3 py-2 text-right">{formatCurrency(item.rate || item.price)}</td>
                      <td className="px-3 py-2 text-right font-medium">{formatCurrency(item.amount || item.quantity * (item.rate || item.price))}</td></tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="border-t pt-4 text-right">
              <p className="text-sm">{t('invoices.totalColon')} <span className="font-bold">{formatCurrency(selectedInvoice.total)}</span></p>
              <p className="text-sm">{t('invoices.paidColon')} <span className="font-medium text-green-600">{formatCurrency(selectedInvoice.amount_paid || 0)}</span></p>
              <p className="text-lg font-bold text-gray-900">{t('invoices.balanceColon')} {formatCurrency(selectedInvoice.balance_due ?? selectedInvoice.total - (selectedInvoice.amount_paid || 0))}</p>
            </div>
            {(selectedInvoice.payments || []).length > 0 && (
              <div className="border-t pt-4">
                <h4 className="text-sm font-semibold text-gray-700 mb-2">{t('invoices.paymentHistory')}</h4>
                <div className="space-y-2">
                  {selectedInvoice.payments.map((p, i) => (
                    <div key={i} className="flex items-center justify-between p-2 bg-gray-50 rounded-lg text-sm">
                      <div>
                        <p className="font-medium">{t('invoices.paymentVia', { amount: formatCurrency(p.amount), method: p.method })}</p>
                        <p className="text-xs text-gray-500">{p.reference || ''} | {formatDateTime(p.created_at)}</p>
                      </div>
                      <span className={'badge ' + getStatusBadgeClass(p.status || 'completed')}>{p.status || 'completed'}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </Modal>

      <Modal isOpen={paymentModalOpen} onClose={() => setPaymentModalOpen(false)} title={t('invoices.recordPayment')} size="sm">
        <form onSubmit={handleRecordPayment} className="space-y-4">
          {selectedInvoice && (
            <div className="bg-gray-50 rounded-lg p-3 text-sm">
              <p className="font-medium">{t('invoices.invoiceNo')}{selectedInvoice.invoice_number || selectedInvoice.id}</p>
              <p className="text-gray-500">{t('invoices.balanceDue', { amount: formatCurrency(selectedInvoice.balance_due ?? selectedInvoice.total - (selectedInvoice.amount_paid || 0)) })}</p>
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{t('invoices.amountLabel')}</label>
            <input type="number" step="0.01" className="input-field" value={payment.amount}
              onChange={e => setPayment(p => ({ ...p, amount: e.target.value }))} required />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{t('invoices.methodLabel')}</label>
            <select className="select-field" value={payment.method} onChange={e => setPayment(p => ({ ...p, method: e.target.value }))}>
              <option value="cash">{t('invoices.cash')}</option>
              <option value="card">{t('invoices.card')}</option>
              <option value="bank_transfer">{t('invoices.bankTransfer')}</option>
              <option value="check">{t('invoices.check')}</option>
              <option value="mobile_money">{t('invoices.mobileMoney')}</option>
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{t('invoices.referenceLabel')}</label>
            <input className="input-field" value={payment.reference} onChange={e => setPayment(p => ({ ...p, reference: e.target.value }))} />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">{t('invoices.notesLabel')}</label>
            <input className="input-field" value={payment.notes} onChange={e => setPayment(p => ({ ...p, notes: e.target.value }))} />
          </div>
          <div className="flex justify-end gap-2 pt-2">
            <button type="button" className="btn-secondary" onClick={() => setPaymentModalOpen(false)}>{t('invoices.cancel')}</button>
            <button type="submit" className="btn-primary" disabled={saving}>{saving ? t('invoices.recording') : t('invoices.recordPaymentBtn')}</button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
