import api from './api'

export async function exportReport(reportType, filters) {
  const { data } = await api.get(`/reports/export/${reportType}`, {
    params: filters,
    responseType: 'blob',
  })
  downloadBlob(data, `${reportType}-report.csv`)
}

export async function exportData(endpoint, params = {}) {
  const { data } = await api.get(`/export/${endpoint}`, {
    params,
    responseType: 'blob',
  })
  downloadBlob(data, `${endpoint}-export.csv`)
}

export async function exportInvoice(invoiceId) {
  const { data } = await api.get(`/invoices/${invoiceId}/pdf`, {
    responseType: 'blob',
  })
  downloadBlob(data, `invoice-${invoiceId}.pdf`)
}

function downloadBlob(blob, filename) {
  const url = window.URL.createObjectURL(new Blob([blob]))
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', filename)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}
