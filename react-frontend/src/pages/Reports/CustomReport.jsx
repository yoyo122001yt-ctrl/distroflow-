import { useState } from 'react';
import api from '../../services/api';
import toast from 'react-hot-toast';

const AVAILABLE_FIELDS = [
  { id: 'order_number', label: 'Order Number', group: 'Orders' },
  { id: 'business_name', label: 'Store Name', group: 'Orders' },
  { id: 'created_at', label: 'Order Date', group: 'Orders' },
  { id: 'status', label: 'Status', group: 'Orders' },
  { id: 'total_amount', label: 'Total Amount', group: 'Orders' },
  { id: 'product_name', label: 'Product Name', group: 'Items' },
  { id: 'quantity_ordered', label: 'Quantity', group: 'Items' },
  { id: 'unit_price', label: 'Unit Price', group: 'Items' },
  { id: 'driver_name', label: 'Driver Name', group: 'Deliveries' },
  { id: 'delivery_status', label: 'Delivery Status', group: 'Deliveries' },
];

export default function CustomReport() {
  const [selectedFields, setSelectedFields] = useState([]);
  const [dateRange, setDateRange] = useState({ from: '', to: '' });
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);

  function toggleField(fieldId) {
    setSelectedFields((prev) =>
      prev.includes(fieldId) ? prev.filter((f) => f !== fieldId) : [...prev, fieldId]
    );
  }

  function selectAll() {
    setSelectedFields(AVAILABLE_FIELDS.map((f) => f.id));
  }

  function clearAll() {
    setSelectedFields([]);
    setResult(null);
  }

  async function handleGenerate() {
    setLoading(true);
    setResult(null);
    try {
      const { data } = await api.get('/reports/custom', {
        params: { fields: selectedFields.join(','), from: dateRange.from, to: dateRange.to }
      });
      setResult(data?.data || data);
    } catch (err) {
      toast.error(err.response?.data?.message || err.message);
    } finally {
      setLoading(false);
    }
  }

  async function handleExport() {
    try {
      const res = await api.get('/reports/custom/export', {
        params: { fields: selectedFields.join(','), from: dateRange.from, to: dateRange.to },
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = 'custom-report.csv';
      a.click();
      window.URL.revokeObjectURL(url);
      toast.success('Report exported');
    } catch (err) {
      toast.error(err.response?.data?.message || err.message);
    }
  }

  const grouped = AVAILABLE_FIELDS.reduce((acc, field) => {
    if (!acc[field.group]) acc[field.group] = [];
    acc[field.group].push(field);
    return acc;
  }, {});

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">Custom Report</h1>

      <div className="flex gap-4 mb-6">
        <div>
          <label className="block text-sm text-gray-600 mb-1">From</label>
          <input type="date" value={dateRange.from} onChange={(e) => setDateRange({ ...dateRange, from: e.target.value })} className="border rounded-lg px-4 py-2" />
        </div>
        <div>
          <label className="block text-sm text-gray-600 mb-1">To</label>
          <input type="date" value={dateRange.to} onChange={(e) => setDateRange({ ...dateRange, to: e.target.value })} className="border rounded-lg px-4 py-2" />
        </div>
      </div>

      <div className="mb-4 flex gap-2">
        <button onClick={selectAll} className="text-sm bg-gray-200 px-3 py-1 rounded">Select All</button>
        <button onClick={clearAll} className="text-sm bg-gray-200 px-3 py-1 rounded">Clear All</button>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {Object.entries(grouped).map(([group, fields]) => (
          <div key={group} className="border rounded-lg p-4">
            <h3 className="font-semibold mb-2 text-sm text-gray-600">{group}</h3>
            <div className="space-y-2">
              {fields.map((field) => (
                <label key={field.id} className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={selectedFields.includes(field.id)}
                    onChange={() => toggleField(field.id)}
                    className="rounded"
                  />
                  <span className="text-sm">{field.label}</span>
                </label>
              ))}
            </div>
          </div>
        ))}
      </div>

      <div className="flex gap-4">
        <button
          disabled={selectedFields.length === 0 || loading}
          onClick={handleGenerate}
          className="bg-blue-600 text-white px-6 py-2 rounded-lg disabled:opacity-50"
        >
          {loading ? 'Generating...' : 'Generate Report'}
        </button>
        <button
          disabled={selectedFields.length === 0 || !result}
          onClick={handleExport}
          className="bg-green-600 text-white px-6 py-2 rounded-lg disabled:opacity-50"
        >
          Export CSV
        </button>
      </div>

      <div className="mt-6">
        {loading ? (
          <div className="p-6 text-center text-gray-400">Generating report...</div>
        ) : result ? (
          <div className="overflow-x-auto rounded-lg border">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  {result.columns?.map((col, i) => (
                    <th key={i} className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{col}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200">
                {result.rows?.map((row, i) => (
                  <tr key={i} className="hover:bg-gray-50">
                    {row.map((cell, j) => (
                      <td key={j} className="px-4 py-3 text-sm whitespace-nowrap">{cell}</td>
                    ))}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : selectedFields.length > 0 ? (
          <div className="p-6 border-2 border-dashed rounded-lg text-center text-gray-400">
            Report will display: {selectedFields.length} field(s)
          </div>
        ) : (
          <div className="p-6 border-2 border-dashed rounded-lg text-center text-gray-400">
            Select fields above to build your report
          </div>
        )}
      </div>
    </div>
  );
}
