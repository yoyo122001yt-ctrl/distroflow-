import { useState, useEffect } from 'react';
import api from '../../services/api';

export default function InventoryReport() {
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadReport();
  }, []);

  async function loadReport() {
    try {
      const res = await api.get('/reports/inventory');
      setReport(res.data?.data || res.data);
    } catch (err) {
      console.error('Failed to load inventory report:', err);
    } finally {
      setLoading(false);
    }
  }

  async function exportReport(format) {
    try {
      const res = await api.get('/reports/export/inventory', {
        params: { format },
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `inventory-report.${format}`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Export failed:', err);
    }
  }

  if (loading) return <div className="p-6">Loading inventory report...</div>;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Inventory Report</h1>
        <div className="flex gap-2">
          <button onClick={() => exportReport('xlsx')} className="bg-green-600 text-white px-4 py-2 rounded-lg text-sm">Excel</button>
          <button onClick={() => exportReport('pdf')} className="bg-red-600 text-white px-4 py-2 rounded-lg text-sm">PDF</button>
        </div>
      </div>

      {report && (
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
          <StatBox label="Total Products" value={report.total_products || 0} />
          <StatBox label="Active" value={report.active_products || 0} />
          <StatBox label="Low Stock" value={report.low_stock || 0} variant="warning" />
          <StatBox label="Out of Stock" value={report.out_of_stock || 0} variant="danger" />
          <StatBox label="Expiring Soon" value={report.expiring_soon || 0} variant="warning" />
        </div>
      )}
    </div>
  );
}

function StatBox({ label, value, variant }) {
  const colors = {
    warning: 'border-yellow-400',
    danger: 'border-red-400',
    default: 'border-gray-200',
  };
  return (
    <div className={`bg-white rounded-lg shadow p-4 border-t-4 ${colors[variant] || colors.default}`}>
      <p className="text-gray-500 text-sm">{label}</p>
      <p className="text-xl font-bold">{value}</p>
    </div>
  );
}
