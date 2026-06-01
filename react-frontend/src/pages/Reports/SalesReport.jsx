import { useState, useEffect } from 'react';
import api from '../../services/api';

export default function SalesReport() {
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);
  const [startDate, setStartDate] = useState(() => new Date(Date.now() - 30*86400000).toISOString().split('T')[0]);
  const [endDate, setEndDate] = useState(() => new Date().toISOString().split('T')[0]);

  useEffect(() => {
    loadReport();
  }, [startDate, endDate]);

  async function loadReport() {
    setLoading(true);
    try {
      const res = await api.get('/reports/sales', { params: { start_date: startDate, end_date: endDate } });
      setReport(res.data?.data || res.data);
    } catch (err) {
      console.error('Failed to load report:', err);
    } finally {
      setLoading(false);
    }
  }

  async function exportReport(format) {
    try {
      const res = await api.get('/reports/export/sales', {
        params: { start_date: startDate, end_date: endDate, format },
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `sales-report-${startDate}-${endDate}.${format}`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Export failed:', err);
    }
  }

  if (loading) return <div className="p-6">Loading sales report...</div>;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Sales Report</h1>
        <div className="flex gap-2">
          <button onClick={() => exportReport('xlsx')} className="bg-green-600 text-white px-4 py-2 rounded-lg text-sm">Excel</button>
          <button onClick={() => exportReport('csv')} className="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">CSV</button>
          <button onClick={() => exportReport('pdf')} className="bg-red-600 text-white px-4 py-2 rounded-lg text-sm">PDF</button>
        </div>
      </div>

      <div className="flex gap-4 mb-6">
        <div>
          <label className="block text-sm text-gray-600 mb-1">From</label>
          <input type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} className="border rounded-lg px-4 py-2" />
        </div>
        <div>
          <label className="block text-sm text-gray-600 mb-1">To</label>
          <input type="date" value={endDate} onChange={(e) => setEndDate(e.target.value)} className="border rounded-lg px-4 py-2" />
        </div>
      </div>

      {report && (
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <StatBox label="Total Revenue" value={`${(report.total_revenue || 0).toFixed(2)} EGP`} />
          <StatBox label="Total Orders" value={report.total_orders || 0} />
          <StatBox label="Avg Order Value" value={`${(report.average_order_value || 0).toFixed(2)} EGP`} />
          <StatBox label="Daily Breakdown" value={`${report.daily_breakdown?.length || 0} days`} />
        </div>
      )}

      <div className="border rounded-lg overflow-hidden">
        <table className="w-full">
          <thead className="bg-gray-50">
            <tr>
              <th className="text-left p-3">Date</th>
              <th className="text-right p-3">Orders</th>
              <th className="text-right p-3">Revenue</th>
            </tr>
          </thead>
          <tbody>
            {report?.daily_breakdown?.map((day) => (
              <tr key={day.date} className="border-t">
                <td className="p-3">{day.date}</td>
                <td className="p-3 text-right">{day.count}</td>
                <td className="p-3 text-right">{Number(day.revenue).toFixed(2)} EGP</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function StatBox({ label, value }) {
  return (
    <div className="bg-white rounded-lg shadow p-4">
      <p className="text-gray-500 text-sm">{label}</p>
      <p className="text-xl font-bold">{value}</p>
    </div>
  );
}
