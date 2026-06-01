import { useState, useMemo } from 'react'
import { ChevronUp, ChevronDown, ChevronsUpDown, ChevronLeft, ChevronRight, Search } from 'lucide-react'
import { useTranslation } from 'react-i18next'

export default function DataTable({
  columns,
  data,
  pageSize = 10,
  searchable = true,
  searchPlaceholder,
  onRowClick,
  loading = false,
}) {
  const { t } = useTranslation()

  const [sortKey, setSortKey] = useState(null)
  const [sortDir, setSortDir] = useState('asc')
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')

  const resolvedPlaceholder = searchPlaceholder ?? t('common.search')

  const handleSort = (key) => {
    if (sortKey === key) {
      setSortDir(d => d === 'asc' ? 'desc' : 'asc')
    } else {
      setSortKey(key)
      setSortDir('asc')
    }
  }

  const safeData = Array.isArray(data) ? data : []

  const filtered = useMemo(() => {
    if (!search || !searchable) return safeData
    const q = search.toLowerCase()
    return safeData.filter(row =>
      columns.some(col => {
        const val = col.accessor ? row[col.accessor] : null
        return val != null && String(val).toLowerCase().includes(q)
      })
    )
  }, [safeData, search, searchable, columns])

  const sorted = useMemo(() => {
    if (!sortKey) return filtered
    return [...filtered].sort((a, b) => {
      const aVal = a[sortKey]
      const bVal = b[sortKey]
      if (aVal == null) return 1
      if (bVal == null) return -1
      if (typeof aVal === 'number') {
        return sortDir === 'asc' ? aVal - bVal : bVal - aVal
      }
      return sortDir === 'asc'
        ? String(aVal).localeCompare(String(bVal))
        : String(bVal).localeCompare(String(aVal))
    })
  }, [filtered, sortKey, sortDir])

  const totalPages = Math.max(1, Math.ceil(sorted.length / pageSize))
  const paged = sorted.slice((page - 1) * pageSize, page * pageSize)

  const SortIcon = ({ column }) => {
    if (sortKey !== column) return <ChevronsUpDown size={14} className="text-gray-400" />
    return sortDir === 'asc' ? <ChevronUp size={14} /> : <ChevronDown size={14} />
  }

  const start = ((page - 1) * pageSize) + 1
  const end = Math.min(page * pageSize, sorted.length)

  return (
    <div>
      {searchable && (
        <div className="relative mb-4">
          <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            placeholder={resolvedPlaceholder}
            value={search}
            onChange={e => { setSearch(e.target.value); setPage(1) }}
            className="input-field pl-10"
          />
        </div>
      )}
      {loading ? (
        <div className="flex items-center justify-center py-12">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-600"></div>
        </div>
      ) : paged.length === 0 ? (
        <div className="text-center py-12 text-gray-500">
          <p className="text-lg font-medium">{t('common.noData')}</p>
          <p className="text-sm mt-1">{t('common.noDataHint')}</p>
        </div>
      ) : (
        <>
          <div className="overflow-x-auto rounded-lg border border-gray-200">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  {columns.map(col => (
                    <th
                      key={col.accessor || col.header}
                      className={`px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider ${col.sortable !== false ? 'cursor-pointer select-none hover:bg-gray-100' : ''}`}
                      onClick={() => col.sortable !== false && handleSort(col.accessor)}
                    >
                      <div className="flex items-center gap-1">
                        {col.header}
                        {col.sortable !== false && col.accessor && <SortIcon column={col.accessor} />}
                      </div>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {paged.map((row, i) => (
                  <tr
                    key={row.id || i}
                    className={`${onRowClick ? 'cursor-pointer hover:bg-gray-50' : ''} transition-colors`}
                    onClick={() => onRowClick?.(row)}
                  >
                    {columns.map(col => (
                      <td key={col.accessor || col.header} className="px-4 py-3 text-sm whitespace-nowrap">
                        {col.render ? col.render(row) : row[col.accessor]}
                      </td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span>
              {t('common.showing', { start, end, total: sorted.length })}
            </span>
            <div className="flex items-center gap-2">
              <button className="btn-secondary !p-2" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>
                <ChevronLeft size={16} />
              </button>
              {Array.from({ length: totalPages }, (_, i) => i + 1).map(p => (
                <button
                  key={p}
                  className={`px-3 py-1 rounded-lg text-sm ${p === page ? 'bg-brand-600 text-white' : 'hover:bg-gray-100'}`}
                  onClick={() => setPage(p)}
                >
                  {p}
                </button>
              ))}
              <button className="btn-secondary !p-2" disabled={page >= totalPages} onClick={() => setPage(p => p + 1)}>
                <ChevronRight size={16} />
              </button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
