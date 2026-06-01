import { useState, useEffect } from 'react';
import api from '../../services/api';

export default function ActivityLog() {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ user: '', action: '', date_from: '', date_to: '' });

  useEffect(() => {
    loadLogs();
  }, []);

  async function loadLogs() {
    setLoading(true);
    try {
      const params = {};
      Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v; });
      const res = await api.get('/activity-logs', { params });
      setLogs(res.data?.data || []);
    } catch (err) {
      console.error('Failed to load activity logs:', err);
    } finally {
      setLoading(false);
    }
  }

  function handleFilter(key, value) {
    setFilters((prev) => ({ ...prev, [key]: value }));
  }

  function applyFilters() {
    loadLogs();
  }

  function formatDiff(oldValues, newValues) {
    if (!oldValues && !newValues) return null;
    const old = oldValues ? (typeof oldValues === 'string' ? JSON.parse(oldValues) : oldValues) : {};
    const updated = newValues ? (typeof newValues === 'string' ? JSON.parse(newValues) : newValues) : {};
    const allKeys = [...new Set([...Object.keys(old), ...Object.keys(updated)])];
    return (
      <div className="text-xs">
        {allKeys.map((key) => {
          if (old[key] !== updated[key]) {
            return (
              <div key={key} className="mb-1">
                <span className="font-medium">{key}:</span>{' '}
                <span className="text-red-500 line-through mr-1">{JSON.stringify(old[key])}</span>
                <span className="text-green-500">{JSON.stringify(updated[key])}</span>
              </div>
            );
          }
          return null;
        })}
      </div>
    );
  }

  async function exportCsv() {
    try {
      const res = await api.get('/activity-logs/export', {
        params: filters,
        responseType: 'blob',
      });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = 'activity-log.csv';
      a.click();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Export failed:', err);
    }
  }

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Activity Log</h1>
        <button onClick={exportCsv} className="bg-green-600 text-white px-4 py-2 rounded-lg text-sm">Export CSV</button>
      </div>

      <div className="flex gap-4 mb-6 flex-wrap">
        <input
          type="text" placeholder="Filter by user..."
          value={filters.user} onChange={(e) => handleFilter('user', e.target.value)}
          className="border rounded-lg px-4 py-2"
        />
        <input
          type="text" placeholder="Filter by action..."
          value={filters.action} onChange={(e) => handleFilter('action', e.target.value)}
          className="border rounded-lg px-4 py-2"
        />
        <input
          type="date" value={filters.date_from} onChange={(e) => handleFilter('date_from', e.target.value)}
          className="border rounded-lg px-4 py-2"
        />
        <input
          type="date" value={filters.date_to} onChange={(e) => handleFilter('date_to', e.target.value)}
          className="border rounded-lg px-4 py-2"
        />
        <button onClick={applyFilters} className="bg-blue-600 text-white px-6 py-2 rounded-lg">Filter</button>
      </div>

      {loading ? (
        <div>Loading activity logs...</div>
      ) : (
        <div className="space-y-3">
          {logs.map((log) => (
            <div key={log.id} className="border rounded-lg p-4">
              <div className="flex justify-between items-start mb-2">
                <div>
                  <span className="font-bold">{log.user_name || log.user_id || 'System'}</span>
                  <span className="text-gray-500 mx-2">-</span>
                  <span className="font-medium">{log.action || log.event}</span>
                </div>
                <span className="text-xs text-gray-400">{new Date(log.created_at).toLocaleString()}</span>
              </div>
              <div className="text-sm text-gray-600 mb-2">
                {log.resource_type && (
                  <span className="mr-4">
                    Resource: <span className="font-medium">{log.resource_type}</span>
                    {log.resource_id && <> (#{log.resource_id})</>}
                  </span>
                )}
                {log.user_role && (
                  <span>Role: <span className="font-medium">{log.user_role}</span></span>
                )}
              </div>
              {log.old_values || log.new_values ? (
                <div className="bg-gray-50 rounded p-2 mt-2">
                  {formatDiff(log.old_values, log.new_values)}
                </div>
              ) : log.details ? (
                <div className="bg-gray-50 rounded p-2 mt-2 text-sm">
                  {typeof log.details === 'string' ? log.details : JSON.stringify(log.details)}
                </div>
              ) : null}
            </div>
          ))}
          {logs.length === 0 && <p className="text-gray-500 text-center py-8">No activity logs found.</p>}
        </div>
      )}
    </div>
  );
}
