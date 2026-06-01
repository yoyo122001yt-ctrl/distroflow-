import { useState, useRef } from 'react';
import api from '../../services/api';

export default function ImportModal({ onClose, onComplete }) {
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [result, setResult] = useState(null);
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef();

  function handleFileSelect(e) {
    const f = e.target.files?.[0];
    if (f) processFile(f);
  }

  function handleDrop(e) {
    e.preventDefault();
    setDragOver(false);
    const f = e.dataTransfer.files?.[0];
    if (f) processFile(f);
  }

  function processFile(f) {
    const maxSize = 10 * 1024 * 1024;
    if (f.size > maxSize) {
      alert('File too large. Maximum 10MB.');
      return;
    }
    const validTypes = [
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/vnd.ms-excel',
      'text/csv',
    ];
    if (!validTypes.includes(f.type) && !f.name.match(/\.(xlsx|xls|csv)$/i)) {
      alert('Invalid file type. Please upload xlsx, xls, or csv.');
      return;
    }
    setFile(f);
    previewFile(f);
  }

  function previewFile(f) {
    const reader = new FileReader();
    reader.onload = (e) => {
      const text = e.target.result;
      const lines = text.split('\n').filter((l) => l.trim());
      const headers = lines[0]?.split(',').map((h) => h.trim()) || [];
      const rows = lines.slice(1, 11).map((line) => {
        const vals = line.split(',').map((v) => v.trim());
        return headers.reduce((obj, h, i) => ({ ...obj, [h]: vals[i] || '' }), {});
      });
      setPreview({ headers, rows });
    };
    if (f.type === 'text/csv' || f.name.endsWith('.csv')) {
      reader.readAsText(f);
    } else {
      setPreview({ headers: [], rows: [], note: 'Preview not available for xlsx files.' });
    }
  }

  async function upload() {
    if (!file) return;
    setUploading(true);
    try {
      const formData = new FormData();
      formData.append('file', file);
      const res = await api.post('/products/import', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setResult(res.data?.data || res.data);
      onComplete?.(res.data);
    } catch (err) {
      setResult({ error: err.response?.data?.message || err.message });
    } finally {
      setUploading(false);
    }
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div className="bg-white rounded-xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-xl font-bold">Import Products</h2>
          <button onClick={onClose} className="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
        </div>

        {!result ? (
          <>
            <div
              className={`border-2 border-dashed rounded-lg p-8 text-center mb-4 cursor-pointer transition ${
                dragOver ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400'
              }`}
              onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
              onDragLeave={() => setDragOver(false)}
              onDrop={handleDrop}
              onClick={() => fileInputRef.current?.click()}
            >
              <input
                ref={fileInputRef}
                type="file"
                accept=".xlsx,.xls,.csv"
                onChange={handleFileSelect}
                className="hidden"
              />
              <div className="text-4xl mb-2">
                {file ? '\u2705' : '\u{1F4C4}'}
              </div>
              {file ? (
                <p className="font-medium">{file.name} ({(file.size / 1024).toFixed(1)} KB)</p>
              ) : (
                <>
                  <p className="font-medium mb-1">Drag & drop your file here</p>
                  <p className="text-sm text-gray-500">or click to browse (xlsx, xls, csv - max 10MB)</p>
                </>
              )}
            </div>

            <div className="mb-4">
              <a href="/api/products/import/template" className="text-blue-600 text-sm hover:underline">
                Download import template
              </a>
            </div>

            {preview.rows?.length > 0 && (
              <div className="mb-4">
                <h3 className="text-sm font-semibold mb-2">Preview (first {preview.rows.length} rows):</h3>
                <div className="overflow-x-auto border rounded">
                  <table className="w-full text-xs">
                    <thead className="bg-gray-50">
                      <tr>
                        {preview.headers.map((h) => (
                          <th key={h} className="p-2 text-left">{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {preview.rows.map((row, i) => (
                        <tr key={i} className="border-t">
                          {preview.headers.map((h) => (
                            <td key={h} className="p-2">{row[h]}</td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            {preview.note && <p className="text-sm text-gray-500 mb-4">{preview.note}</p>}

            <div className="flex gap-3 justify-end">
              <button onClick={onClose} className="px-4 py-2 border rounded-lg">Cancel</button>
              <button
                onClick={upload}
                disabled={!file || uploading}
                className="bg-blue-600 text-white px-6 py-2 rounded-lg disabled:opacity-50"
              >
                {uploading ? (
                  <span className="flex items-center gap-2">
                    <span className="animate-spin">&#9696;</span> Uploading...
                  </span>
                ) : 'Import'}
              </button>
            </div>
          </>
        ) : (
          <div className="text-center py-6">
            {result.error ? (
              <>
                <div className="text-4xl mb-3">&#10060;</div>
                <p className="text-red-600 font-medium">{result.error}</p>
              </>
            ) : (
              <>
                <div className="text-4xl mb-3">&#9989;</div>
                <p className="font-medium text-green-600 mb-2">Import completed!</p>
                <p className="text-sm text-gray-600">
                  Imported: {result.imported || 0} | Failed: {result.failed || 0}
                </p>
                {result.errors?.length > 0 && (
                  <div className="mt-4 text-left">
                    <p className="text-sm font-semibold text-red-600 mb-2">Errors:</p>
                    <ul className="text-xs text-red-500 list-disc pl-4 space-y-1">
                      {result.errors.map((err, i) => <li key={i}>{err}</li>)}
                    </ul>
                  </div>
                )}
              </>
            )}
            <button onClick={onClose} className="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg">Close</button>
          </div>
        )}
      </div>
    </div>
  );
}
