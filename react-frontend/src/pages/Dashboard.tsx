import { useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { Store, ShoppingCart, Truck, DollarSign, TrendingUp, TrendingDown, ArrowUpRight } from 'lucide-react'
import { motion, AnimatePresence } from 'motion/react'
import api from '../services/api'
import { formatCurrency } from '../utils/formatters'
import StatCard from '../app/components/dashboard/StatCard'
import SalesTrendChart from '../app/components/dashboard/SalesTrendChart'
import OrderStatusChart from '../app/components/dashboard/OrderStatusChart'
import CinematicIntro from '../app/components/CinematicIntro'

export default function Dashboard() {
  const [showIntro, setShowIntro] = useState(true)
  if (showIntro) {
    return <CinematicIntro onComplete={() => setShowIntro(false)} />
  }
  return <DashboardContent />
}

interface DashboardStats {
  total_stores: number
  total_products: number
  active_products: number
  orders_today: number
  pending_orders: number
  monthly_revenue: number
}

interface SalesData {
  date: string
  amount: number
}

interface OrderStatusData {
  status: string
  count: number
  color?: string
  label?: string
}

interface RecentOrder {
  id: number
  order_number?: string
  store_name?: string
  store?: { name: string }
  item_count?: number
  items?: any[]
  total: number
  status: string
}

const STATUS_COLORS: Record<string, string> = {
  delivered: '#10B981',
  pending: '#F59E0B',
  in_transit: '#3B82F6',
  cancelled: '#EF4444',
  processing: '#8B5CF6',
  approved: '#3B82F6',
}

const ORDER_STATUS_LABELS: Record<string, string> = {
  delivered: 'Delivered',
  pending: 'Pending',
  in_transit: 'In Transit',
  cancelled: 'Cancelled',
  processing: 'Processing',
  approved: 'Approved',
}

const containerVariants = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: { staggerChildren: 0.1, delayChildren: 0.1 },
  },
}

const itemVariants = {
  hidden: { opacity: 0, y: 16 },
  visible: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.4, ease: [0.16, 1, 0.3, 1] },
  },
}

function DashboardContent() {
  const { t } = useTranslation()
  const [stats, setStats] = useState<DashboardStats | null>(null)
  const [salesData, setSalesData] = useState<SalesData[]>([])
  const [orderStatusData, setOrderStatusData] = useState<OrderStatusData[]>([])
  const [recentOrders, setRecentOrders] = useState<RecentOrder[]>([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [statsRes, salesRes, ordersRes] = await Promise.all([
          api.get('/dashboard/stats'),
          api.get('/dashboard/sales-trend'),
          api.get('/orders', { params: { limit: 5, sort: '-created_at' } }),
        ])
        setStats(statsRes.data)
        setSalesData(salesRes.data || [])

        const orders = ordersRes.data?.results || ordersRes.data || []
        setRecentOrders(orders)

        const statusRes = await api.get('/orders/status-breakdown')
        const statusData = (statusRes.data || []).map((s: OrderStatusData) => ({
          ...s,
          color: STATUS_COLORS[s.status] || '#94A3B8',
          label: ORDER_STATUS_LABELS[s.status] || s.status,
        }))
        setOrderStatusData(statusData)
      } catch (err) {
        console.error('Dashboard fetch error:', err)
      } finally {
        setLoading(false)
      }
    }
    fetchData()
  }, [])

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <motion.div
          animate={{ rotate: 360 }}
          transition={{ duration: 0.8, repeat: Infinity, ease: 'linear' }}
          className="rounded-full h-10 w-10 border-[3px] border-[var(--color-border)] border-t-[var(--color-primary)]"
        />
      </div>
    )
  }

  const summaryCards = [
    {
      label: t('dashboard.totalStores'),
      value: stats?.total_stores || 0,
      icon: Store,
      color: 'text-[var(--color-primary)]',
      bg: 'bg-[var(--color-primary-50)]',
      subtext: t('dashboard.perMonth', { count: 2 }),
    },
    {
      label: t('dashboard.products'),
      value: stats?.total_products || 0,
      icon: ShoppingCart,
      color: 'text-[var(--color-success)]',
      bg: 'bg-[var(--color-success-50)]',
      subtext: `${stats?.active_products || 0} ${t('dashboard.active')}`,
    },
    {
      label: t('dashboard.ordersToday'),
      value: stats?.orders_today || 0,
      icon: Truck,
      color: 'text-[var(--color-warning)]',
      bg: 'bg-[var(--color-warning-50)]',
      subtext: `${stats?.pending_orders || 0} ${t('dashboard.pending')}`,
    },
    {
      label: t('dashboard.revenueMtd'),
      value: stats?.monthly_revenue || 0,
      icon: DollarSign,
      color: 'text-[var(--color-purple)]',
      bg: 'bg-[var(--color-purple-50)]',
      subtext: formatCurrency(stats?.monthly_revenue || 0),
      isCurrency: true,
    },
  ]

  return (
    <motion.div
      className="space-y-6"
      variants={containerVariants}
      initial="hidden"
      animate="visible"
    >
      {/* Page Header */}
      <motion.div variants={itemVariants}>
        <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
          {t('dashboard.title')}
        </h1>
        <p className="text-[var(--color-text-secondary)] mt-1">
          {t('dashboard.subtitle')}
        </p>
      </motion.div>

      {/* Stat Cards */}
      <motion.div
        variants={itemVariants}
        className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
      >
        {summaryCards.map((card, idx) => (
          <StatCard key={idx} {...card} delay={idx * 0.08} />
        ))}
      </motion.div>

      {/* Charts */}
      <motion.div
        variants={itemVariants}
        className="grid grid-cols-1 lg:grid-cols-3 gap-6"
      >
        {/* Sales Trend */}
        <div className="dashboard-card lg:col-span-2">
          <div className="flex items-center justify-between mb-5">
            <h3 className="font-semibold text-[var(--color-text-primary)]">
              {t('dashboard.salesTrend')}
            </h3>
            <TrendingUp size={18} className="text-[var(--color-success)]" />
          </div>
          <SalesTrendChart data={salesData} />
        </div>

        {/* Order Status */}
        <div className="dashboard-card">
          <div className="flex items-center justify-between mb-5">
            <h3 className="font-semibold text-[var(--color-text-primary)]">
              {t('dashboard.orderStatus')}
            </h3>
          </div>
          <OrderStatusChart data={orderStatusData} />
        </div>
      </motion.div>

      {/* Recent Orders */}
      <motion.div variants={itemVariants} className="dashboard-card">
        <div className="flex items-center justify-between mb-4">
          <h3 className="font-semibold text-[var(--color-text-primary)]">
            {t('dashboard.recentOrders')}
          </h3>
          <ArrowUpRight size={16} className="text-[var(--color-text-muted)]" />
        </div>

        <AnimatePresence mode="wait">
          {recentOrders.length > 0 ? (
            <motion.div
              key="table"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="overflow-x-auto -mx-6 px-6"
            >
              <table className="min-w-full divide-y divide-[var(--color-border)]">
                <thead>
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-[var(--color-text-muted)] uppercase tracking-wider">
                      {t('dashboard.orderNo')}
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-[var(--color-text-muted)] uppercase tracking-wider">
                      {t('dashboard.store')}
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-[var(--color-text-muted)] uppercase tracking-wider">
                      {t('dashboard.items')}
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-[var(--color-text-muted)] uppercase tracking-wider">
                      {t('dashboard.total')}
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-[var(--color-text-muted)] uppercase tracking-wider">
                      {t('dashboard.status')}
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[var(--color-border)]">
                  {recentOrders.map((order, idx) => (
                    <motion.tr
                      key={order.id || idx}
                      initial={{ opacity: 0, y: 8 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: idx * 0.06, duration: 0.3, ease: [0.16, 1, 0.3, 1] }}
                      className="hover:bg-[var(--color-bg-secondary)] transition-colors duration-150 cursor-default"
                    >
                      <td className="px-4 py-3.5 text-sm font-medium text-[var(--color-text-primary)]">
                        #{order.order_number || order.id}
                      </td>
                      <td className="px-4 py-3.5 text-sm text-[var(--color-text-secondary)]">
                        {order.store_name || order.store?.name || '-'}
                      </td>
                      <td className="px-4 py-3.5 text-sm text-[var(--color-text-secondary)]">
                        {order.item_count || order.items?.length || 0}
                      </td>
                      <td className="px-4 py-3.5 text-sm font-semibold text-[var(--color-text-primary)] tabular-nums">
                        {formatCurrency(order.total)}
                      </td>
                      <td className="px-4 py-3.5">
                        <StatusBadge status={order.status} />
                      </td>
                    </motion.tr>
                  ))}
                </tbody>
              </table>
            </motion.div>
          ) : (
            <motion.div
              key="empty"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="text-center py-10 text-[var(--color-text-muted)]"
            >
              <motion.div
                animate={{ y: [0, -6, 0] }}
                transition={{ duration: 3, repeat: Infinity, ease: 'easeInOut' }}
              >
                <Truck size={36} className="mx-auto mb-3 opacity-40" />
              </motion.div>
              <p className="text-sm font-medium">{t('dashboard.noRecentOrders')}</p>
            </motion.div>
          )}
        </AnimatePresence>
      </motion.div>
    </motion.div>
  )
}

function StatusBadge({ status }: { status: string }) {
  const colorMap: Record<string, { badge: string; label: string }> = {
    delivered: { badge: 'badge badge-success', label: 'Delivered' },
    pending: { badge: 'badge badge-warning', label: 'Pending' },
    in_transit: { badge: 'badge badge-primary', label: 'In Transit' },
    cancelled: { badge: 'badge !bg-red-50 !text-red-700', label: 'Cancelled' },
    processing: { badge: 'badge !bg-purple-50 !text-purple-700', label: 'Processing' },
  }

  const config = colorMap[status] || { badge: 'badge !bg-gray-100 !text-gray-700', label: status }

  return (
    <motion.span
      initial={{ scale: 0.9 }}
      animate={{ scale: 1 }}
      className={config.badge}
    >
      {config.label}
    </motion.span>
  )
}
