import { AlertTriangle, Clock, XCircle } from 'lucide-react'
import { formatDate } from '../../utils/formatters'
import { useTranslation } from 'react-i18next'

export default function ExpiryAlert({ batches, onViewBatch }) {
  const { t } = useTranslation()
  const now = new Date()
  const in30Days = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000)

  const expiring = batches.filter(b => {
    const exp = new Date(b.expiry_date)
    return exp <= in30Days
  }).sort((a, b) => new Date(a.expiry_date) - new Date(b.expiry_date))

  if (expiring.length === 0) return null

  const getAlertType = (expiryDate) => {
    const days = Math.ceil((new Date(expiryDate) - now) / (1000 * 60 * 60 * 24))
    if (days <= 0) return 'expired'
    if (days <= 7) return 'critical'
    return 'warning'
  }

  const alertConfig = {
    expired: { icon: XCircle, color: 'text-red-600', bg: 'bg-red-50', border: 'border-red-200', label: t('expiryAlert.expired') },
    critical: { icon: AlertTriangle, color: 'text-orange-600', bg: 'bg-orange-50', border: 'border-orange-200', label: t('expiryAlert.expiringSoon') },
    warning: { icon: Clock, color: 'text-yellow-600', bg: 'bg-yellow-50', border: 'border-yellow-200', label: t('expiryAlert.expiring') },
  }

  return (
    <div className="space-y-2">
      <h3 className="text-sm font-semibold text-gray-700 flex items-center gap-2">
        <AlertTriangle size={16} className="text-orange-500" />
        {t('expiryAlert.title', { count: expiring.length })}
      </h3>
      {expiring.slice(0, 5).map(batch => {
        const type = getAlertType(batch.expiry_date)
        const cfg = alertConfig[type]
        const Icon = cfg.icon
        const days = Math.ceil((new Date(batch.expiry_date) - now) / (1000 * 60 * 60 * 24))

        return (
          <div
            key={batch.id}
            className={`flex items-center justify-between p-3 rounded-lg border ${cfg.bg} ${cfg.border} cursor-pointer hover:opacity-80`}
            onClick={() => onViewBatch?.(batch)}
          >
            <div className="flex items-center gap-3">
              <Icon size={18} className={cfg.color} />
              <div>
                <p className="text-sm font-medium text-gray-900">{batch.product_name || batch.product}</p>
                <p className="text-xs text-gray-500">
                  {t('expiryAlert.batch', { code: batch.batch_code || batch.id, qty: batch.quantity })}
                </p>
              </div>
            </div>
            <div className="text-right">
              <span className={`badge ${cfg.color.replace('text-', 'bg-').replace('600', '100')} ${cfg.color}`}>
                {type === 'expired' ? t('expiryAlert.expired') : t('expiryAlert.daysLeft', { days })}
              </span>
              <p className="text-xs text-gray-500 mt-1">{formatDate(batch.expiry_date)}</p>
            </div>
          </div>
        )
      })}
      {expiring.length > 5 && (
        <p className="text-xs text-gray-500 text-center">{t('expiryAlert.moreItems', { count: expiring.length - 5 })}</p>
      )}
    </div>
  )
}
