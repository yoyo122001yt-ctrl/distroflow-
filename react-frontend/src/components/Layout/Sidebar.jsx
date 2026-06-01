import { useState } from 'react'
import { NavLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  LayoutDashboard, Package, Warehouse, Store, Truck, ShoppingCart,
  ClipboardList, Truck as TruckIcon, Map, Users, ClipboardCheck,
  FileText, Settings, ChevronLeft, ChevronRight, Menu,
  Receipt, BarChart3, Navigation,
} from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'

const menuItems = [
  { path: '/', icon: LayoutDashboard, key: 'sidebar.dashboard' },
  { path: '/products', icon: Package, key: 'sidebar.products', roles: ['admin', 'manager', 'staff'] },
  { path: '/inventory', icon: Warehouse, key: 'sidebar.inventory', roles: ['admin', 'manager', 'staff'] },
  { path: '/stores', icon: Store, key: 'sidebar.stores', roles: ['admin', 'manager', 'sales'] },
  { path: '/suppliers', icon: Truck, key: 'sidebar.suppliers', roles: ['admin', 'manager'] },
  { path: '/purchase-orders', icon: ShoppingCart, key: 'sidebar.purchaseOrders', roles: ['admin', 'manager'] },
  { path: '/orders', icon: ClipboardList, key: 'sidebar.salesOrders', roles: ['admin', 'manager', 'sales'] },
  { path: '/picking', icon: ClipboardCheck, key: 'sidebar.picking', roles: ['admin', 'manager', 'staff'] },
  { path: '/loading', icon: TruckIcon, key: 'sidebar.loading', roles: ['admin', 'manager', 'staff'] },
  { path: '/routes', icon: Map, key: 'sidebar.routes', roles: ['admin', 'manager', 'driver'] },
  { path: '/drivers', icon: Users, key: 'sidebar.drivers', roles: ['admin', 'manager'] },
  { path: '/deliveries', icon: Truck, key: 'sidebar.deliveries', roles: ['admin', 'manager', 'driver'] },
  { path: '/settlements', icon: ClipboardCheck, key: 'sidebar.settlements', roles: ['admin', 'manager'] },
  { path: '/invoices', icon: Receipt, key: 'sidebar.invoices', roles: ['admin', 'manager', 'sales'] },
  { path: '/reports', icon: BarChart3, key: 'sidebar.reports', roles: ['admin', 'manager'] },
  { path: '/settings', icon: Settings, key: 'sidebar.settings', roles: ['admin'] },
  { path: 'http://localhost:8000/warehouse/tracking', icon: Navigation, key: 'sidebar.tracking', roles: ['admin', 'manager'], external: true },
]

export default function Sidebar({ collapsed, onToggle }) {
  const { t } = useTranslation()
  const { hasRole } = useAuth()

  const visibleItems = menuItems.filter(item => !item.roles || hasRole(item.roles))

  return (
    <>
      <div
        className={`fixed inset-0 bg-black/50 z-20 lg:hidden ${collapsed ? 'hidden' : 'block'}`}
        onClick={onToggle}
      />
      <aside
        className={`fixed top-0 left-0 z-30 h-full bg-white border-r border-gray-200 transition-all duration-300 flex flex-col ${
          collapsed ? '-translate-x-full lg:translate-x-0 lg:w-16' : 'translate-x-0 w-64'
        }`}
      >
        <div className="flex items-center h-16 px-4 border-b border-gray-200">
          {!collapsed && (
            <div className="flex items-center gap-2 flex-1">
              <div className="w-8 h-8 bg-brand-600 rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-sm">{t('app.nameShort')}</span>
              </div>
              <span className="font-bold text-lg text-gray-900">{t('app.name')}</span>
            </div>
          )}
          {collapsed && (
            <div className="w-8 h-8 bg-brand-600 rounded-lg flex items-center justify-center mx-auto">
              <span className="text-white font-bold text-sm">{t('app.nameShort')}</span>
            </div>
          )}
        </div>

        <nav className="flex-1 overflow-y-auto py-4 px-2">
          {visibleItems.map(item =>
            item.external ? (
              <a
                key={item.path}
                href={item.path.includes('localhost') ? item.path + '?token=' + (localStorage.getItem('df_token') || '') : item.path}
                target="_blank"
                rel="noopener noreferrer"
                className={`flex items-center gap-3 px-3 py-2.5 rounded-lg mb-0.5 transition-colors text-gray-600 hover:bg-gray-100 hover:text-gray-900 ${collapsed ? 'justify-center' : ''}`}
              >
                <item.icon size={20} />
                {!collapsed && <span className="text-sm">{t(item.key)}</span>}
              </a>
            ) : (
              <NavLink
                key={item.path}
                to={item.path}
                end={item.path === '/'}
                onClick={() => window.innerWidth < 1024 && onToggle()}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3 py-2.5 rounded-lg mb-0.5 transition-colors ${
                    isActive
                      ? 'bg-brand-50 text-brand-700 font-medium'
                      : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                  } ${collapsed ? 'justify-center' : ''}`
                }
              >
                <item.icon size={20} />
                {!collapsed && <span className="text-sm">{t(item.key)}</span>}
              </NavLink>
            )
          )}
        </nav>

        <div className="border-t border-gray-200 p-2">
          <button
            onClick={onToggle}
            className="hidden lg:flex items-center justify-center w-full p-2 rounded-lg hover:bg-gray-100 text-gray-500"
          >
            {collapsed ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
          </button>
        </div>
      </aside>
    </>
  )
}
