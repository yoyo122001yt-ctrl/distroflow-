import { useState, useEffect } from 'react'
import { Routes, Route, Navigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import './i18n/i18n'
import { useAuth } from './contexts/AuthContext'
import Sidebar from './components/Layout/Sidebar'
import Header from './components/Layout/Header'

import FuturisticLogin from './app/components/FuturisticLogin'
import Dashboard from './pages/Dashboard' // .tsx resolved by Vite
import Products from './pages/Products'
import WarehouseInventory from './pages/WarehouseInventory'
import RetailStores from './pages/RetailStores'
import Suppliers from './pages/Suppliers'
import PurchaseOrders from './pages/PurchaseOrders'
import SalesOrders from './pages/SalesOrders'
import Picking from './pages/Picking'
import Loading from './pages/Loading'
import RoutesPage from './pages/Routes'
import Drivers from './pages/Drivers'
import Deliveries from './pages/Deliveries'
import Settlements from './pages/Settlements'
import Reports from './pages/Reports'
import Invoices from './pages/Invoices'
import Settings from './pages/Settings'

function ProtectedRoute({ children }) {
  const { user, loading } = useAuth()
  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-600"></div>
      </div>
    )
  }
  if (!user) return <Navigate to="/login" replace />
  return children
}

function Layout({ children, collapsed, onToggle }) {
  return (
    <div className="min-h-screen bg-gray-50">
      <Sidebar collapsed={collapsed} onToggle={onToggle} />
      <div className={`transition-all duration-300 ${collapsed ? 'lg:ml-16' : 'lg:ml-64'}`}>
        <Header onMenuToggle={onToggle} />
        <main className="p-4 lg:p-6">
          {children}
        </main>
      </div>
    </div>
  )
}

export default function App() {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false)
  const { i18n } = useTranslation()

  useEffect(() => {
    const updateDirection = () => {
      const lang = localStorage.getItem('lang')
      if (lang === 'ar') {
        document.documentElement.dir = 'rtl'
        document.documentElement.lang = 'ar'
      } else {
        document.documentElement.dir = 'ltr'
        document.documentElement.lang = 'en'
      }
    }
    updateDirection()
    i18n.on('languageChanged', updateDirection)
    return () => {
      i18n.off('languageChanged', updateDirection)
    }
  }, [i18n])

  const PublicRoute = ({ children }) => {
    const { user, loading } = useAuth()
    if (loading) {
      return (
        <div className="min-h-screen flex items-center justify-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-brand-600"></div>
        </div>
      )
    }
    if (user) return <Navigate to="/" replace />
    return children
  }

  const ProtectedLayout = ({ children }) => (
    <ProtectedRoute>
      <Layout collapsed={sidebarCollapsed} onToggle={() => setSidebarCollapsed(c => !c)}>
        {children}
      </Layout>
    </ProtectedRoute>
  )

  return (
    <Routes>
      <Route path="/login" element={<PublicRoute><FuturisticLogin /></PublicRoute>} />
      <Route path="/" element={<ProtectedLayout><Dashboard /></ProtectedLayout>} />
      <Route path="/products" element={<ProtectedLayout><Products /></ProtectedLayout>} />
      <Route path="/inventory" element={<ProtectedLayout><WarehouseInventory /></ProtectedLayout>} />
      <Route path="/stores" element={<ProtectedLayout><RetailStores /></ProtectedLayout>} />
      <Route path="/suppliers" element={<ProtectedLayout><Suppliers /></ProtectedLayout>} />
      <Route path="/purchase-orders" element={<ProtectedLayout><PurchaseOrders /></ProtectedLayout>} />
      <Route path="/orders" element={<ProtectedLayout><SalesOrders /></ProtectedLayout>} />
      <Route path="/picking" element={<ProtectedLayout><Picking /></ProtectedLayout>} />
      <Route path="/loading" element={<ProtectedLayout><Loading /></ProtectedLayout>} />
      <Route path="/routes" element={<ProtectedLayout><RoutesPage /></ProtectedLayout>} />
      <Route path="/drivers" element={<ProtectedLayout><Drivers /></ProtectedLayout>} />
      <Route path="/deliveries" element={<ProtectedLayout><Deliveries /></ProtectedLayout>} />
      <Route path="/settlements" element={<ProtectedLayout><Settlements /></ProtectedLayout>} />
      <Route path="/reports" element={<ProtectedLayout><Reports /></ProtectedLayout>} />
      <Route path="/invoices" element={<ProtectedLayout><Invoices /></ProtectedLayout>} />
      <Route path="/settings" element={<ProtectedLayout><Settings /></ProtectedLayout>} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
