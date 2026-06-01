import { useState, useEffect } from 'react';
import api from '../../services/api';

export default function DriverPerformance() {
  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);
  const [startDate, setStartDate] = useState(() => new Date(Date.now() - 7*86400000).toISOString().split('T')[0]);
  const [endDate, setEndDate] = useState(() => new Date().toISOString().split('T')[0]);

  useEffect(() => {
    loadReport();
  }, [startDate, endDate]);

  async function loadReport() {
    setLoading(true);
    try {
      const res = await api.get('/reports/driver-performance', { params: { start_date: startDate, end_date: endDate } });
      setReport(res.data?.data || res.data);
    } catch (err) {
      console.error('Failed to load driver report:', err);
    } finally {
      setLoading(false);
    }
  }

  if (loading) return <div className="p-6">Loading driver performance...</div>;

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">Driver Performance</h1>

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
        <>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <StatBox label="Total Drivers" value={report.total_drivers || 0} />
            <StatBox label="Total Deliveries" value={report.total_deliveries || 0} />
            <StatBox label="On-Time Rate" value={`${report.on_time_rate || 0}%`} />
          </div>

          <div className="border rounded-lg overflow-hidden">
            <table className="w-full">
              <thead className="bg-gray-50">
                <tr>
                  <th className="text-left p-3">Driver</th>
                  <th className="text-right p-3">Total</th>
                  <th className="text-right p-3">Completed</th>
                </tr>
              </thead>
              <tbody>
                {report.drivers?.map((driver, i) => (
                  <tr key={i} className="border-t">
                    <td className="p-3">{driver.name}</td>
                    <td className="p-3 text-right">{driver.total}</td>
                    <td className="p-3 text-right">{driver.completed}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
      )}
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
