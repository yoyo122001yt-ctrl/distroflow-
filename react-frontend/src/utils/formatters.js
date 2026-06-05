import { format, parseISO } from 'date-fns'
import i18n from '../i18n/i18n'

const currencyLocale = { en: 'en-US', ar: 'ar-EG' }

export function formatCurrency(amount) {
  const locale = currencyLocale[i18n.language] || 'en-US'
  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: 'EGP',
  }).format(amount || 0)
}

export function formatDate(dateString, fmt = 'MMM dd, yyyy') {
  if (!dateString) return '-'
  try {
    return format(parseISO(dateString), fmt)
  } catch {
    return dateString
  }
}

export function formatDateTime(dateString) {
  return formatDate(dateString, 'MMM dd, yyyy hh:mm a')
}

export function formatPhone(phone) {
  if (!phone) return '-'
  const cleaned = phone.replace(/\D/g, '')
  if (cleaned.length === 10) {
    return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6)}`
  }
  if (cleaned.length === 11 && cleaned[0] === '1') {
    return `(${cleaned.slice(1, 4)}) ${cleaned.slice(4, 7)}-${cleaned.slice(7)}`
  }
  return phone
}

export function getStatusBadgeClass(status) {
  const map = {
    pending: 'bg-yellow-100 text-yellow-800',
    draft: 'bg-gray-100 text-gray-800',
    confirmed: 'bg-blue-100 text-blue-800',
    processing: 'bg-indigo-100 text-indigo-800',
    picking: 'bg-purple-100 text-purple-800',
    open: 'bg-purple-100 text-purple-800',
    in_progress: 'bg-blue-100 text-blue-800',
    loaded: 'bg-orange-100 text-orange-800',
    in_transit: 'bg-cyan-100 text-cyan-800',
    delivered: 'bg-green-100 text-green-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    approved: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    paid: 'bg-green-100 text-green-800',
    unpaid: 'bg-yellow-100 text-yellow-800',
    overdue: 'bg-red-100 text-red-800',
    expired: 'bg-red-100 text-red-800',
    low_stock: 'bg-orange-100 text-orange-800',
    out_of_stock: 'bg-red-100 text-red-800',
    assigned: 'bg-indigo-100 text-indigo-800',
  }
  return map[status] || 'bg-gray-100 text-gray-800'
}

export function getInitials(name) {
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}
