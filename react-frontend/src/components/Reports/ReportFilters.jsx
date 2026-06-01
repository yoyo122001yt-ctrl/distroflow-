import { useState } from 'react'
import { Filter, Download, Search, RotateCcw } from 'lucide-react'
import { useTranslation } from 'react-i18next'

export default function ReportFilters({ onGenerate, onExport, loading = false }) {
  const { t } = useTranslation()
  const today = new Date().toISOString().split('T')[0]
  const thirtyDaysAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]

  const [filters, setFilters] = useState({
    report_type: 'sales',
    date_from: thirtyDaysAgo,
    date_to: today,
    group_by: 'day',
    store_id: '',
    product_id: '',
    status: '',
  })

  const reportTypes = [
    { value: 'sales', label: t('reports:reportFilters.salesReport') },
    { value: 'inventory', label: t('reports:reportFilters.inventoryReport') },
    { value: 'orders', label: t('reports:reportFilters.ordersReport') },
    { value: 'deliveries', label: t('reports:reportFilters.deliveriesReport') },
    { value: 'settlements', label: t('reports:reportFilters.driverSettlements') },
    { value: 'expiry', label: t('reports:reportFilters.expiryReport') },
    { value: 'stores', label: t('reports:reportFilters.storePerformance') },
    { value: 'products', label: t('reports:reportFilters.productPerformance') },
  ]

  const groupByOptions = [
    { value: 'day', label: t('reports:reportFilters.daily') },
    { value: 'week', label: t('reports:reportFilters.weekly') },
    { value: 'month', label: t('reports:reportFilters.monthly') },
    { value: 'year', label: t('reports:reportFilters.yearly') },
  ]

  const update = (key, value) => setFilters(f => ({ ...f, [key]: value }))

  const handleReset = () => {
    setFilters({
      report_type: 'sales',
      date_from: thirtyDaysAgo,
      date_to: today,
      group_by: 'day',
      store_id: '',
      product_id: '',
      status: '',
    })
  }

  return (
    <div className="card mb-6">
      <div className="flex items-center gap-2 mb-4">
        <Filter size={18} className="text-brand-600" />
        <h3 className="font-semibold text-gray-900">{t('reports:reportFilters.title')}</h3>
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('reports:reportFilters.reportType')}</label>
          <select
            value={filters.report_type}
            onChange={e => update('report_type', e.target.value)}
            className="select-field"
          >
            {reportTypes.map(rt => (
              <option key={rt.value} value={rt.value}>{rt.label}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('reports:reportFilters.fromDate')}</label>
          <input
            type="date"
            value={filters.date_from}
            onChange={e => update('date_from', e.target.value)}
            className="input-field"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('reports:reportFilters.toDate')}</label>
          <input
            type="date"
            value={filters.date_to}
            onChange={e => update('date_to', e.target.value)}
            className="input-field"
          />
        </div>
        <div>
          <label className="block text-xs font-medium text-gray-600 mb-1">{t('reports:reportFilters.groupBy')}</label>
          <select
            value={filters.group_by}
            onChange={e => update('group_by', e.target.value)}
            className="select-field"
          >
            {groupByOptions.map(gb => (
              <option key={gb.value} value={gb.value}>{gb.label}</option>
            ))}
          </select>
        </div>
      </div>
      <div className="flex items-center gap-2 mt-4 pt-4 border-t border-gray-100">
        <button className="btn-primary" onClick={() => onGenerate(filters)} disabled={loading}>
          <Search size={16} />
          {t('reports:reportFilters.generateReport')}
        </button>
        <button className="btn-secondary" onClick={() => onExport(filters)} disabled={loading}>
          <Download size={16} />
          {t('reports:reportFilters.exportCSV')}
        </button>
        <button className="btn-secondary" onClick={handleReset}>
          <RotateCcw size={16} />
          {t('reports:reportFilters.reset')}
        </button>
      </div>
    </div>
  )
}
