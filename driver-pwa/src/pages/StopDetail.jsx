import React, { useEffect, useState, useRef, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeft,
  Package,
  DollarSign,
  Camera,
  PenTool,
  RefreshCw,
  CheckCircle2,
  XCircle,
  RotateCcw,
  Truck,
  WifiOff,
  AlertCircle,
  ClipboardCheck,
  ChevronDown,
  Image,
} from 'lucide-react';
import toast from 'react-hot-toast';
import useApi from '../hooks/useApi';
import useOffline from '../hooks/useOffline';
import { savePendingDelivery, savePendingPayment, savePendingSignature, savePendingPhoto } from '../services/offlineStorage';
import { registerSync } from '../services/syncManager';

function SignatureCanvas({ onCapture, disabled }) {
  const canvasRef = useRef(null);
  const [isDrawing, setIsDrawing] = useState(false);
  const [hasSignature, setHasSignature] = useState(false);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.strokeStyle = '#1f2937';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
  }, []);

  const getPos = (e) => {
    const canvas = canvasRef.current;
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
      x: (clientX - rect.left) * (canvas.width / rect.width),
      y: (clientY - rect.top) * (canvas.height / rect.height),
    };
  };

  const startDraw = (e) => {
    if (disabled) return;
    e.preventDefault();
    const pos = getPos(e);
    const ctx = canvasRef.current.getContext('2d');
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
    setIsDrawing(true);
  };

  const draw = (e) => {
    if (!isDrawing || disabled) return;
    e.preventDefault();
    const pos = getPos(e);
    const ctx = canvasRef.current.getContext('2d');
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
  };

  const endDraw = (e) => {
    e.preventDefault();
    if (isDrawing) {
      setIsDrawing(false);
      setHasSignature(true);
    }
  };

  const clear = () => {
    const canvas = canvasRef.current;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    setHasSignature(false);
    onCapture(null);
  };

  const capture = () => {
    const canvas = canvasRef.current;
    const dataUrl = canvas.toDataURL('image/png');
    onCapture(dataUrl);
  };

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <span className="text-sm font-medium text-gray-700">Signature</span>
        {hasSignature && (
          <button type="button" onClick={clear} className="text-xs text-red-600 hover:text-red-700">
            Clear
          </button>
        )}
      </div>
      <canvas
        ref={canvasRef}
        width={600}
        height={200}
        className="w-full h-32 border-2 border-dashed border-gray-300 rounded-lg cursor-crosshair touch-none"
        style={{ background: '#fafafa' }}
        onMouseDown={startDraw}
        onMouseMove={draw}
        onMouseUp={endDraw}
        onMouseLeave={endDraw}
        onTouchStart={startDraw}
        onTouchMove={draw}
        onTouchEnd={endDraw}
      />
      {hasSignature && (
        <button
          type="button"
          onClick={capture}
          className="text-xs text-primary-600 font-medium"
        >
          Confirm Signature
        </button>
      )}
    </div>
  );
}

function PhotoCapture({ onCapture, disabled }) {
  const fileInputRef = useRef(null);
  const videoRef = useRef(null);
  const canvasRef = useRef(null);
  const [mode, setMode] = useState(null);
  const [preview, setPreview] = useState(null);
  const [stream, setStream] = useState(null);

  useEffect(() => {
    return () => {
      if (stream) {
        stream.getTracks().forEach((t) => t.stop());
      }
    };
  }, [stream]);

  const startCamera = async () => {
    try {
      const s = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } },
      });
      if (videoRef.current) {
        videoRef.current.srcObject = s;
      }
      setStream(s);
      setMode('camera');
    } catch {
      toast.error('Camera access denied');
    }
  };

  const capturePhoto = () => {
    const video = videoRef.current;
    const canvas = canvasRef.current;
    if (!video || !canvas) return;

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
    setPreview(dataUrl);
    onCapture(dataUrl);

    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      setStream(null);
    }
    setMode(null);
  };

  const handleFile = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (ev) => {
      const dataUrl = ev.target.result;
      setPreview(dataUrl);
      onCapture(dataUrl);
    };
    reader.readAsDataURL(file);
    setMode(null);
  };

  const cancelCamera = () => {
    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      setStream(null);
    }
    setMode(null);
  };

  const removePhoto = () => {
    setPreview(null);
    onCapture(null);
  };

  if (mode === 'camera') {
    return (
      <div className="space-y-2">
        <span className="text-sm font-medium text-gray-700">Take Photo</span>
        <div className="relative bg-black rounded-lg overflow-hidden">
          <video ref={videoRef} autoPlay playsInline className="w-full h-64 object-cover" />
          <div className="absolute bottom-3 left-0 right-0 flex justify-center gap-4">
            <button
              type="button"
              onClick={capturePhoto}
              className="w-14 h-14 bg-white rounded-full flex items-center justify-center shadow-lg"
            >
              <Camera className="w-6 h-6 text-gray-800" />
            </button>
          </div>
        </div>
        <button type="button" onClick={cancelCamera} className="text-xs text-gray-500">
          Cancel
        </button>
        <canvas ref={canvasRef} className="hidden" />
      </div>
    );
  }

  return (
    <div className="space-y-2">
      <span className="text-sm font-medium text-gray-700">Photo</span>
      {preview ? (
        <div className="relative">
          <img src={preview} alt="Capture" className="w-full h-48 object-cover rounded-lg" />
          <button
            type="button"
            onClick={removePhoto}
            className="absolute top-2 right-2 p-1.5 bg-red-500 text-white rounded-full shadow"
          >
            <XCircle className="w-4 h-4" />
          </button>
        </div>
      ) : (
        <div className="flex gap-2">
          <button
            type="button"
            onClick={startCamera}
            disabled={disabled}
            className="flex-1 flex items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:border-primary-400 hover:text-primary-600 transition-colors disabled:opacity-50"
          >
            <Camera className="w-6 h-6" />
            <span className="text-sm">Camera</span>
          </button>
          <button
            type="button"
            onClick={() => fileInputRef.current?.click()}
            disabled={disabled}
            className="flex-1 flex items-center justify-center gap-2 py-8 border-2 border-dashed border-gray-300 rounded-lg text-gray-500 hover:border-primary-400 hover:text-primary-600 transition-colors disabled:opacity-50"
          >
            <Image className="w-6 h-6" />
            <span className="text-sm">Gallery</span>
          </button>
        </div>
      )}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        capture="environment"
        onChange={handleFile}
        className="hidden"
      />
    </div>
  );
}

function ReturnItem({ index, item, onRemove }) {
  const [returnData, setReturnData] = useState({ productId: item.id || '', quantity: '', reason: 'expired' });

  useEffect(() => {
    item._returnData = returnData;
  }, [returnData, item]);

  return (
    <div className="bg-gray-50 rounded-lg p-3 space-y-2">
      <div className="flex items-center justify-between">
        <span className="text-sm font-medium text-gray-700">{item.name || item.productName}</span>
        {onRemove && (
          <button type="button" onClick={onRemove} className="text-red-500">
            <XCircle className="w-4 h-4" />
          </button>
        )}
      </div>
      <div className="grid grid-cols-2 gap-2">
        <div>
          <label className="text-xs text-gray-500">Qty to return</label>
          <input
            type="number"
            min="0"
            max={item.quantity || 99}
            value={returnData.quantity}
            onChange={(e) => setReturnData({ ...returnData, quantity: e.target.value })}
            className="input-field text-sm mt-1"
          />
        </div>
        <div>
          <label className="text-xs text-gray-500">Reason</label>
          <select
            value={returnData.reason}
            onChange={(e) => setReturnData({ ...returnData, reason: e.target.value })}
            className="input-field text-sm mt-1"
          >
            <option value="expired">Expired</option>
            <option value="damaged">Damaged</option>
            <option value="wrong">Wrong Product</option>
            <option value="overstock">Overstock</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
    </div>
  );
}

export default function StopDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { isOnline } = useOffline();

  const [stop, setStop] = useState(null);
  const [deliveredItems, setDeliveredItems] = useState({});
  const [returns, setReturns] = useState([]);
  const [payment, setPayment] = useState({ amount: '', method: 'cash', reference: '' });
  const [signature, setSignature] = useState(null);
  const [photo, setPhoto] = useState(null);
  const [shelfRotation, setShelfRotation] = useState(false);
  const [notes, setNotes] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const { data, loading, error, execute } = useApi(`stop-${id}`);

  useEffect(() => {
    execute({ url: `/stops/${id}` });
  }, [id]);

  useEffect(() => {
    if (data) {
      setStop(data.stop || data);
    }
  }, [data]);

  const handleQuantityChange = (productId, value) => {
    setDeliveredItems((prev) => ({ ...prev, [productId]: value }));
  };

  const addReturn = () => {
    if (!stop?.items?.length) {
      toast.error('No items available for return');
      return;
    }
    const item = stop.items[0];
    setReturns([...returns, { ...item, _key: Date.now() }]);
  };

  const removeReturn = (index) => {
    setReturns(returns.filter((_, i) => i !== index));
  };

  const handleSubmit = useCallback(async () => {
    if (!stop) return;

    setSubmitting(true);
    try {
      const deliveryData = {
        stopId: stop.id || id,
        items: Object.entries(deliveredItems).map(([productId, quantity]) => ({
          productId,
          quantity: parseInt(quantity) || 0,
        })),
        returns: returns.map((r) => ({
          productId: r.productId || r.id,
          quantity: parseInt(r._returnData?.quantity) || 0,
          reason: r._returnData?.reason || 'other',
        })).filter((r) => r.quantity > 0),
        shelfRotation,
        notes,
      };

      if (!navigator.onLine) {
        await savePendingDelivery(deliveryData);
        if (payment.amount) {
          await savePendingPayment({
            stopId: stop.id || id,
            amount: parseFloat(payment.amount),
            method: payment.method,
            reference: payment.reference,
          });
        }
        if (signature) {
          await savePendingSignature({ stopId: stop.id || id, signatureData: signature });
        }
        if (photo) {
          await savePendingPhoto({ stopId: stop.id || id, photoData: photo });
        }
        await registerSync();
        toast.success('Saved offline. Will sync when online.');
      } else {
        await execute({
          method: 'post',
          url: `/stops/${id}/deliver`,
          data: {
            ...deliveryData,
            payment: payment.amount
              ? {
                  amount: parseFloat(payment.amount),
                  method: payment.method,
                  reference: payment.reference,
                }
              : null,
            signature,
            photo,
          },
        });
        toast.success('Delivery recorded!');
      }

      navigate('/stops');
    } catch (err) {
      const msg = err.response?.data?.message || err.message || 'Failed to submit';
      toast.error(msg);
    } finally {
      setSubmitting(false);
    }
  }, [stop, id, deliveredItems, returns, payment, signature, photo, shelfRotation, notes, navigate, execute]);

  if (loading && !stop) {
    return (
      <div className="page-container">
        <div className="flex flex-col items-center justify-center py-20">
          <RefreshCw className="w-8 h-8 text-primary-500 animate-spin mb-4" />
          <p className="text-gray-500 text-sm">Loading stop details...</p>
        </div>
      </div>
    );
  }

  if (error && !stop) {
    return (
      <div className="page-container">
        <button onClick={() => navigate(-1)} className="flex items-center gap-1 text-gray-600 mb-6">
          <ArrowLeft className="w-4 h-4" />
          <span className="text-sm">Back</span>
        </button>
        <div className="flex flex-col items-center justify-center py-16">
          <XCircle className="w-10 h-10 text-red-400 mb-3" />
          <p className="text-gray-700 font-medium mb-1">Failed to load stop</p>
          <p className="text-sm text-gray-500 mb-4">{error}</p>
          <button onClick={() => execute({ url: `/stops/${id}` })} className="btn-primary">
            Retry
          </button>
        </div>
      </div>
    );
  }

  if (!stop) return null;

  const isDelivered = stop.status === 'delivered';
  const items = stop.items || [];
  const expectedTotal = items.reduce((sum, i) => sum + (i.quantity || 0), 0);

  return (
    <div className="page-container">
      <button onClick={() => navigate(-1)} className="flex items-center gap-1 text-gray-600 mb-4">
        <ArrowLeft className="w-4 h-4" />
        <span className="text-sm">Back to stops</span>
      </button>

      {!isOnline && (
        <div className="mb-4 bg-yellow-50 border border-yellow-200 rounded-xl p-3 flex items-center gap-2">
          <WifiOff className="w-4 h-4 text-yellow-600 flex-shrink-0" />
          <span className="text-xs text-yellow-700">Offline mode. Data will sync later.</span>
        </div>
      )}

      {/* Store Info */}
      <div className="card mb-4">
        <div className="flex items-center justify-between mb-2">
          <h1 className="text-lg font-bold text-gray-900">{stop.storeName || stop.name}</h1>
          {isDelivered && (
            <span className="badge-success flex items-center gap-1">
              <CheckCircle2 className="w-3 h-3" />
              Delivered
            </span>
          )}
        </div>
        <p className="text-sm text-gray-500 mb-2">{stop.address}</p>
        {stop.phone && (
          <p className="text-sm text-gray-500">{stop.phone}</p>
        )}
        {stop.notes && (
          <div className="mt-2 flex items-start gap-1.5 text-xs text-amber-600 bg-amber-50 rounded-lg px-3 py-2">
            <AlertCircle className="w-3 h-3 flex-shrink-0 mt-0.5" />
            <span>{stop.notes}</span>
          </div>
        )}
      </div>

      {isDelivered ? (
        <div className="flex flex-col items-center justify-center py-12">
          <CheckCircle2 className="w-16 h-16 text-green-500 mb-4" />
          <p className="text-lg font-semibold text-gray-900">Delivery Completed</p>
          <p className="text-sm text-gray-500 mt-1">This stop has already been delivered</p>
        </div>
      ) : (
        <>
          {/* Expected Items */}
          <div className="card mb-4">
            <div className="flex items-center gap-2 mb-3">
              <Package className="w-5 h-5 text-primary-600" />
              <h2 className="font-semibold text-gray-900">Delivery Items</h2>
            </div>

            {items.length === 0 ? (
              <p className="text-sm text-gray-400 text-center py-4">No items expected</p>
            ) : (
              <div className="space-y-3">
                {items.map((item, idx) => (
                  <div key={item.id || idx} className="flex items-center justify-between pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                    <div className="flex-1">
                      <p className="text-sm font-medium text-gray-900">{item.name || item.productName}</p>
                      <p className="text-xs text-gray-500">Expected: {item.quantity || 0}</p>
                      {item.batch && (
                        <p className="text-xs text-gray-400">Batch: {item.batch}</p>
                      )}
                    </div>
                    <div className="w-20">
                      <input
                        type="number"
                        min="0"
                        max={item.quantity || 999}
                        value={deliveredItems[item.id] ?? item.quantity ?? ''}
                        onChange={(e) => handleQuantityChange(item.id, e.target.value)}
                        className="input-field text-sm text-center"
                        placeholder="Qty"
                      />
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Returns */}
          <div className="card mb-4">
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-2">
                <RotateCcw className="w-5 h-5 text-orange-500" />
                <h2 className="font-semibold text-gray-900">Returns</h2>
              </div>
              <button
                type="button"
                onClick={addReturn}
                className="text-xs text-primary-600 font-medium"
              >
                + Add Return
              </button>
            </div>
            {returns.length === 0 ? (
              <p className="text-sm text-gray-400 text-center py-3">No returns recorded</p>
            ) : (
              <div className="space-y-3">
                {returns.map((item, idx) => (
                  <ReturnItem
                    key={item._key || idx}
                    item={item}
                    index={idx}
                    onRemove={() => removeReturn(idx)}
                  />
                ))}
              </div>
            )}
          </div>

          {/* Payment */}
          <div className="card mb-4">
            <div className="flex items-center gap-2 mb-3">
              <DollarSign className="w-5 h-5 text-green-600" />
              <h2 className="font-semibold text-gray-900">Payment Collection</h2>
            </div>
            <div className="grid grid-cols-2 gap-3 mb-3">
              <div>
                <label className="label">Amount ($)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={payment.amount}
                  onChange={(e) => setPayment({ ...payment, amount: e.target.value })}
                  className="input-field"
                  placeholder="0.00"
                />
              </div>
              <div>
                <label className="label">Method</label>
                <select
                  value={payment.method}
                  onChange={(e) => setPayment({ ...payment, method: e.target.value })}
                  className="input-field"
                >
                  <option value="cash">Cash</option>
                  <option value="check">Check</option>
                  <option value="card">Card</option>
                  <option value="mobile">Mobile Payment</option>
                </select>
              </div>
            </div>
            {payment.method === 'check' && (
              <div>
                <label className="label">Check Reference</label>
                <input
                  type="text"
                  value={payment.reference}
                  onChange={(e) => setPayment({ ...payment, reference: e.target.value })}
                  className="input-field"
                  placeholder="Check number"
                />
              </div>
            )}
          </div>

          {/* Signature */}
          <div className="card mb-4">
            <div className="flex items-center gap-2 mb-3">
              <PenTool className="w-5 h-5 text-purple-600" />
              <h2 className="font-semibold text-gray-900">Signature</h2>
            </div>
            <SignatureCanvas onCapture={setSignature} disabled={submitting} />
          </div>

          {/* Photo */}
          <div className="card mb-4">
            <div className="flex items-center gap-2 mb-3">
              <Camera className="w-5 h-5 text-pink-600" />
              <h2 className="font-semibold text-gray-900">Delivery Photo</h2>
            </div>
            <PhotoCapture onCapture={setPhoto} disabled={submitting} />
          </div>

          {/* Shelf Rotation */}
          <div className="card mb-4">
            <label className="flex items-center gap-3 cursor-pointer">
              <input
                type="checkbox"
                checked={shelfRotation}
                onChange={(e) => setShelfRotation(e.target.checked)}
                className="w-4 h-4 text-primary-600 rounded border-gray-300 focus:ring-primary-500"
              />
              <div>
                <span className="text-sm font-medium text-gray-700">Shelf Rotation Completed</span>
                <p className="text-xs text-gray-400">Check if you rotated stock (FIFO)</p>
              </div>
            </label>
          </div>

          {/* Notes */}
          <div className="card mb-4">
            <label className="label">Notes</label>
            <textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
              className="input-field resize-none"
              placeholder="Any additional notes..."
            />
          </div>

          {/* Submit */}
          <button
            onClick={handleSubmit}
            disabled={submitting}
            className="btn-primary w-full py-3 flex items-center justify-center gap-2 text-base"
          >
            {submitting ? (
              <>
                <RefreshCw className="w-4 h-4 animate-spin" />
                Submitting...
              </>
            ) : (
              <>
                <ClipboardCheck className="w-5 h-5" />
                {isOnline ? 'Submit Delivery' : 'Save Offline'}
              </>
            )}
          </button>

          <div className="h-8" />
        </>
      )}
    </div>
  );
}
