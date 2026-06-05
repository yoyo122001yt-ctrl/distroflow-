import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { BarChart3 } from 'lucide-react'
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, LineChart, Line, PieChart, Pie, Cell, Legend } from 'recharts'
import toast from 'react-hot-toast'
import api from '../services/api'
import ReportFilters from '../components/Reports/ReportFilters'
import { formatCurrency } from '../utils/formatters'
import { exportReport } from '../services/export'

const COLORS = ['#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316']

export default function Reports() {
  const { t } = useTranslation()
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(false)

  const handleGenerate = async (filters) => {
    setLoading(true)
    setResult(null)
    try {
      const { data } = await api.get('/reports/' + filters.report_type, { params: filters })
      setResult(data)
    } catch (err) {
      toast.error(err.message || t('reports.failedToGenerate'))
    } finally {
      setLoading(false)
    }
  }

  const handleExport = async (filters) => {
    try {
      await exportReport(filters.report_type, filters)
      toast.success(t('reports.exported'))
    } catch (err) {
      toast.error(err.message || t('reports.exportFailed'))
    }
  }

  const renderChart = () => {
    if (!result?.chart_data?.length) return null
    const data = result.chart_data
    const xKey = result.x_key || 'label'
    const yKey = result.y_key || 'value'

    switch (result.chart_type) {
      case 'bar':
        return (
          <ResponsiveContainer width="100%" height={350}>
            <BarChart data={data}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey={xKey} tick={{ fontSize: 12 }} />
              <YAxis tick={{ fontSize: 12 }} />
              <Tooltip />
              <Bar dataKey={yKey} fill="#3b82f6" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        )
      case 'line':
        return (
          <ResponsiveContainer width="100%" height={350}>
            <LineChart data={data}>
              <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
              <XAxis dataKey={xKey} tick={{ fontSize: 12 }} />
              <YAxis tick={{ fontSize: 12 }} />
              <Tooltip />
              <Line type="monotone" dataKey={yKey} stroke="#3b82f6" strokeWidth={2} dot={{ fill: '#3b82f6' }} />
            </LineChart>
          </ResponsiveContainer>
        )
      case 'pie':
        return (
          <ResponsiveContainer width="100%" height={350}>
            <PieChart>
              <Pie data={data} cx="50%" cy="50%" outerRadius={120} paddingAngle={3} dataKey={yKey} nameKey={xKey}>
                {data.map((_, idx) => <Cell key={idx} fill={COLORS[idx % COLORS.length]} />)}
              </Pie>
              <Tooltip />
              <Legend />
            </PieChart>
          </ResponsiveContainer>
        )
      default:
        return null
    }
  }

  const renderTable = () => {
    if (!result?.columns || !result?.rows) return null
    return (
      <div className="overflow-x-auto rounded-lg border border-gray-200">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              {result.columns.map((col, i) => (
                <th key={i} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{col}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-200">
            {result.rows.map((row, i) => (
              <tr key={i} className="hover:bg-gray-50">
                {row.map((cell, j) => (
                  <td key={j} className="px-4 py-3 text-sm whitespace-nowrap">
                    {typeof cell === 'number' ? formatCurrency(cell) : cell}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    )
  }

  return (
    <div>
      <div className="flex items-center gap-2 mb-6">
        <BarChart3 size={24} className="text-brand-600" />
        <div>
          <h1 className="text-2xl font-bold text-gray-900">{t('reports.title')}</h1>
          <p className="text-gray-500 mt-1">{t('reports.subtitle')}</p>
        </div>
      </div>

      <ReportFilters onGenerate={handleGenerate} onExport={handleExport} loading={loading} />

      {loading && (
        <div className="card flex items-center justify-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-600"></div>
        </div>
      )}

      {result && !loading && (
        <div className="space-y-6">
          {result.summary && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {Object.entries(result.summary).map(([key, val]) => (
                <div key={key} className="card">
                  <p className="text-sm text-gray-500 capitalize">{key.replace(/_/g, ' ')}</p>
                  <p className="text-2xl font-bold text-gray-900 mt-1">
                    {typeof val === 'number' && key.toLowerCase().includes('revenue') || key.toLowerCase().includes('amount') || key.toLowerCase().includes('total')
                      ? formatCurrency(val) : val}
                  </p>
                </div>
              ))}
            </div>
          )}

          {renderChart()}

          {renderTable()}

          {!result.chart_data && !result.columns && !result.summary && (
            <div className="card text-center py-8 text-gray-400">
              <BarChart3 size={40} className="mx-auto mb-2 opacity-50" />
              <p>{t('reports.noData')}</p>
            </div>
          )}
        </div>
      )}

      {!result && !loading && (
        <div className="card text-center py-12 text-gray-400">
          <BarChart3 size={48} className="mx-auto mb-3 opacity-50" />
          <p className="text-lg font-medium">{t('reports.selectFilters')}</p>
          <p className="text-sm mt-1">{t('reports.chooseFilters')}</p>
        </div>
      )}
    </div>
  )
}
