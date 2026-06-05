@extends('layouts.app')

@section('title', 'My Trip')

@section('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #e2e8f0; }
    .trip-page { max-width: 480px; margin: 0 auto; padding: 0 0 80px 0; min-height: 100vh; background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); position: relative; overflow: hidden; }

    /* Animated background particles */
    .trip-page::before { content: ''; position: fixed; inset: 0; background: radial-gradient(ellipse at 20% 50%, rgba(59,130,246,0.08) 0%, transparent 50%), radial-gradient(ellipse at 80% 20%, rgba(34,197,94,0.05) 0%, transparent 50%); pointer-events: none; z-index: 0; }

    /* ===== HEADER ===== */
    .header { background: linear-gradient(135deg, #1e3a5f 0%, #1e40af 50%, #3b82f6 100%); color: #fff; padding: 1.25rem 1.25rem 1rem; position: sticky; top: 0; z-index: 100; border-bottom: 1px solid rgba(255,255,255,0.1); position: relative; overflow: hidden; }
    .header::after { content: ''; position: absolute; top: -50%; right: -20%; width: 200px; height: 200px; background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%); border-radius: 50%; }
    .header-top { display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 1; }
    .header h1 { font-size: 1.35rem; font-weight: 800; letter-spacing: -0.02em; background: linear-gradient(90deg, #fff, #93c5fd); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .header .subtitle { font-size: 0.8125rem; opacity: 0.9; margin-top: 0.125rem; }
    .header .logout { color: rgba(255,255,255,0.7); font-size: 0.75rem; text-decoration: none; padding: 0.375rem 0.75rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; transition: all 0.3s ease; backdrop-filter: blur(4px); }
    .header .logout:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.4); opacity: 1; transform: translateY(-1px); }

    /* ===== STATUS BAR ===== */
    .status-bar { display: flex; align-items: center; gap: 0.625rem; padding: 0.75rem 1.25rem; font-size: 0.8125rem; background: rgba(30,41,59,0.8); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255,255,255,0.05); }
    .status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; position: relative; }
    .status-dot.active { background: #22c55e; animation: dotPulse 1.5s ease-in-out infinite; }
    .status-dot.active::after { content: ''; position: absolute; inset: -4px; border-radius: 50%; border: 2px solid rgba(34,197,94,0.3); animation: ringPulse 1.5s ease-in-out infinite; }
    .status-dot.inactive { background: #64748b; }
    .status-dot.error { background: #ef4444; animation: dotPulse 0.5s ease-in-out infinite; }
    @keyframes dotPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.3); } }
    @keyframes ringPulse { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.8); opacity: 0; } }

    /* ===== GPS BAR ===== */
    .gps-bar { display: flex; gap: 1.25rem; padding: 0.5rem 1.25rem; font-size: 0.675rem; color: #94a3b8; background: rgba(15,23,42,0.6); border-bottom: 1px solid rgba(255,255,255,0.05); flex-wrap: wrap; animation: slideDown 0.3s ease-out; }
    .gps-bar span { display: flex; align-items: center; gap: 0.3rem; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

    /* ===== STATS DASHBOARD ===== */
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.5rem; padding: 1rem 1.25rem; background: transparent; }
    .stat-card { text-align: center; padding: 0.75rem 0.25rem; background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(30,64,175,0.2)); border-radius: 14px; border: 1px solid rgba(255,255,255,0.06); backdrop-filter: blur(8px); transition: all 0.3s cubic-bezier(0.4,0,0.2,1); animation: cardSlideUp 0.5s ease-out both; }
    .stat-card:nth-child(1) { animation-delay: 0.05s; }
    .stat-card:nth-child(2) { animation-delay: 0.1s; }
    .stat-card:nth-child(3) { animation-delay: 0.15s; }
    .stat-card:nth-child(4) { animation-delay: 0.2s; }
    .stat-card:hover { transform: translateY(-2px) scale(1.02); border-color: rgba(255,255,255,0.12); box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
    .stat-card .stat-value { font-size: 1.25rem; font-weight: 800; color: #f1f5f9; letter-spacing: -0.02em; }
    .stat-card .stat-label { font-size: 0.6rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; margin-top: 0.25rem; font-weight: 600; }
    @keyframes cardSlideUp { from { opacity: 0; transform: translateY(16px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }

    /* ===== PROGRESS BAR ===== */
    .progress-section { padding: 0.75rem 1.25rem; background: rgba(15,23,42,0.4); }
    .progress-header { display: flex; justify-content: space-between; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.5rem; font-weight: 500; }
    .progress-bar { height: 8px; background: rgba(255,255,255,0.08); border-radius: 999px; overflow: hidden; position: relative; }
    .progress-bar .progress-fill { height: 100%; background: linear-gradient(90deg, #22c55e, #16a34a, #22c55e); background-size: 200% 100%; border-radius: 999px; transition: width 0.6s cubic-bezier(0.4,0,0.2,1); width: 0%; animation: shimmer 2s ease-in-out infinite; }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    /* ===== TRIP ACTIONS ===== */
    .trip-actions { padding: 1rem 1.25rem; display: flex; gap: 0.75rem; }
    .btn-trip { flex: 1; padding: 1rem 1.25rem; border: none; border-radius: 16px; font-size: 1rem; font-weight: 800; cursor: pointer; text-align: center; display: flex; align-items: center; justify-content: center; gap: 0.5rem; position: relative; overflow: hidden; transition: all 0.25s cubic-bezier(0.4,0,0.2,1); letter-spacing: 0.02em; }
    .btn-trip::before { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, transparent 50%); pointer-events: none; }
    .btn-trip:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.3); }
    .btn-trip:active:not(:disabled) { transform: translateY(0) scale(0.98); }
    .btn-trip:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
    .btn-start { background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; box-shadow: 0 4px 15px rgba(34,197,94,0.3); }
    .btn-start:not(:disabled):hover { box-shadow: 0 8px 25px rgba(34,197,94,0.4); }
    .btn-end { background: linear-gradient(135deg, #dc2626, #ef4444); color: #fff; box-shadow: 0 4px 15px rgba(239,68,68,0.3); }
    .btn-end:not(:disabled):hover { box-shadow: 0 8px 25px rgba(239,68,68,0.4); }

    /* ===== MAP ===== */
    #tripMap { height: 200px; margin: 0 0 0 0; border-top: 1px solid rgba(255,255,255,0.08); position: relative; }

    /* ===== SECTION HEADERS ===== */
    .section-header { padding: 1rem 1.25rem 0.5rem; display: flex; justify-content: space-between; align-items: center; }
    .section-header h2 { font-size: 0.8125rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; }
    .section-header span { font-size: 0.75rem; color: #64748b; }

    /* ===== ROUTE TIMELINE ===== */
    .route-timeline { padding: 0 1.25rem 0.75rem; animation: timelineFadeIn 0.6s ease-out both; }
    .route-timeline:nth-child(1) { animation-delay: 0.05s; }
    .route-timeline:nth-child(2) { animation-delay: 0.1s; }
    .route-timeline:nth-child(3) { animation-delay: 0.15s; }
    .route-timeline:nth-child(4) { animation-delay: 0.2s; }
    .route-timeline:nth-child(5) { animation-delay: 0.25s; }
    @keyframes timelineFadeIn { from { opacity: 0; transform: translateX(-12px); } to { opacity: 1; transform: translateX(0); } }

    .timeline-item { display: flex; gap: 0.75rem; padding-bottom: 1rem; position: relative; }
    .timeline-item:last-child { padding-bottom: 0; }
    .timeline-item .timeline-line { width: 2px; background: linear-gradient(180deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05)); position: absolute; left: 10px; top: 24px; bottom: 0; }
    .timeline-item:last-child .timeline-line { display: none; }
    .timeline-item .timeline-dot { width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 800; color: #fff; margin-top: 3px; position: relative; transition: all 0.4s cubic-bezier(0.34,1.56,0.64,1); }
    .timeline-dot.pending { background: linear-gradient(135deg, #475569, #64748b); }
    .timeline-dot.current { background: linear-gradient(135deg, #2563eb, #3b82f6); box-shadow: 0 0 0 4px rgba(59,130,246,0.2), 0 0 20px rgba(59,130,246,0.15); animation: currentDot 2s ease-in-out infinite; }
    @keyframes currentDot { 0%, 100% { box-shadow: 0 0 0 4px rgba(59,130,246,0.2), 0 0 20px rgba(59,130,246,0.15); } 50% { box-shadow: 0 0 0 6px rgba(59,130,246,0.15), 0 0 30px rgba(59,130,246,0.1); } }
    .timeline-dot.completed { background: linear-gradient(135deg, #16a34a, #22c55e); box-shadow: 0 0 0 4px rgba(34,197,94,0.2); animation: completePop 0.4s cubic-bezier(0.34,1.56,0.64,1); }
    @keyframes completePop { 0% { transform: scale(0); } 50% { transform: scale(1.3); } 100% { transform: scale(1); } }

    .timeline-item .timeline-content { flex: 1; background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(30,41,59,0.4)); border: 1px solid rgba(255,255,255,0.06); border-radius: 14px; padding: 0.875rem 1rem; backdrop-filter: blur(8px); transition: all 0.3s cubic-bezier(0.4,0,0.2,1); }
    .timeline-item .timeline-content:hover { border-color: rgba(255,255,255,0.12); transform: translateX(3px); box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
    .timeline-item .timeline-content .store-name { font-weight: 700; font-size: 0.9375rem; color: #f1f5f9; }
    .timeline-item .timeline-content .store-address { font-size: 0.8125rem; color: #94a3b8; margin-top: 0.125rem; }
    .timeline-item .timeline-content .store-products { font-size: 0.75rem; color: #cbd5e1; margin-top: 0.5rem; }
    .timeline-item .timeline-content .store-products span { display: inline-block; background: rgba(255,255,255,0.06); padding: 0.2rem 0.6rem; border-radius: 6px; margin: 0.125rem; font-size: 0.675rem; border: 1px solid rgba(255,255,255,0.04); }
    .timeline-actions { display: flex; gap: 0.5rem; margin-top: 0.625rem; flex-wrap: wrap; }
    .timeline-actions .btn-sm { padding: 0.4rem 0.85rem; border-radius: 10px; font-size: 0.75rem; font-weight: 700; border: none; cursor: pointer; transition: all 0.2s cubic-bezier(0.4,0,0.2,1); display: flex; align-items: center; gap: 0.3rem; }
    .timeline-actions .btn-sm:hover { transform: translateY(-1px); filter: brightness(1.1); }
    .timeline-actions .btn-sm:active { transform: translateY(0) scale(0.96); }
    .btn-call { background: linear-gradient(135deg, #14532d, #16a34a); color: #fff; box-shadow: 0 2px 8px rgba(22,163,74,0.2); }
    .btn-navigate { background: linear-gradient(135deg, #1e3a5f, #3b82f6); color: #fff; box-shadow: 0 2px 8px rgba(59,130,246,0.2); }
    .btn-complete-stop { background: linear-gradient(135deg, #2563eb, #3b82f6); color: #fff; box-shadow: 0 2px 8px rgba(59,130,246,0.2); }
    .btn-complete-stop:disabled { opacity: 0.4; cursor: not-allowed; filter: none; }
    .btn-complete-stop.completed { background: linear-gradient(135deg, #166534, #22c55e); color: #fff; opacity: 0.8; }

    /* ===== EMPTY STATE ===== */
    .empty-state { text-align: center; padding: 2.5rem 1.5rem; color: #64748b; }
    .empty-state .icon { font-size: 3rem; margin-bottom: 0.75rem; display: block; animation: float 3s ease-in-out infinite; }
    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
    .empty-state p { font-size: 0.9375rem; color: #94a3b8; font-weight: 500; }

    /* ===== TOAST ===== */
    .toast { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%) translateY(20px); padding: 0.875rem 1.75rem; border-radius: 14px; font-size: 0.875rem; font-weight: 600; z-index: 200; display: none; box-shadow: 0 8px 32px rgba(0,0,0,0.4); max-width: 90%; text-align: center; backdrop-filter: blur(12px); animation: toastIn 0.3s cubic-bezier(0.34,1.56,0.64,1); }
    @keyframes toastIn { from { opacity: 0; transform: translateX(-50%) translateY(20px) scale(0.9); } to { opacity: 1; transform: translateX(-50%) translateY(0) scale(1); } }
    .toast.success { background: linear-gradient(135deg, #166534, #22c55e); color: #fff; display: block; border: 1px solid rgba(34,197,94,0.3); }
    .toast.error { background: linear-gradient(135deg, #7f1d1d, #ef4444); color: #fff; display: block; border: 1px solid rgba(239,68,68,0.3); }
    .toast.info { background: linear-gradient(135deg, #1e3a5f, #3b82f6); color: #fff; display: block; border: 1px solid rgba(59,130,246,0.3); }

    /* ===== UTILITIES ===== */
    .hidden { display: none !important; }
    .loading-spinner { display: inline-block; width: 18px; height: 18px; border: 2.5px solid rgba(255,255,255,0.2); border-radius: 50%; border-top-color: #fff; animation: spin 0.7s cubic-bezier(0.4,0,0.2,1) infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ===== LEALET OVERRIDES ===== */
    .leaflet-container { background: #0f172a !important; }
    .leaflet-control-zoom a { background: rgba(30,41,59,0.9) !important; color: #e2e8f0 !important; border-color: rgba(255,255,255,0.1) !important; }
    .leaflet-popup-content-wrapper { background: rgba(30,41,59,0.95) !important; color: #e2e8f0 !important; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px !important; }
    .leaflet-popup-tip { background: rgba(30,41,59,0.95) !important; }
</style>
@endSection

@section('content')
<div class="trip-page">
    <div class="header">
        <div class="header-top">
            <div>
                <h1><span id="greeting">My Trip</span></h1>
                <div class="subtitle">Welcome, {{ $driver->name }}</div>
            </div>
            <a href="{{ route('logout') }}" class="logout" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Sign Out</a>
        </div>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none">@csrf</form>
    </div>

    <div class="status-bar">
        <span class="status-dot inactive" id="statusDot"></span>
        <span id="statusText">Initializing...</span>
    </div>

    <div id="gpsBar" class="gps-bar hidden">
        <span>📍 <span id="gpsAccuracy">--</span></span>
        <span>🔄 <span id="gpsAge">--</span></span>
        <span>📡 <span id="gpsCoords">--</span></span>
    </div>

    <!-- Stats Dashboard -->
    <div id="statsDashboard" class="stats-grid hidden">
        <div class="stat-card">
            <div class="stat-value" id="statTime">--:--</div>
            <div class="stat-label">Time</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="statStops">0/0</div>
            <div class="stat-label">Stops</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="statSpeed">0</div>
            <div class="stat-label">Km/h</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" id="statDist">--</div>
            <div class="stat-label">Distance</div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div id="progressSection" class="progress-section hidden">
        <div class="progress-header">
            <span>Delivery Progress</span>
            <span id="progressText">0 / 0</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
    </div>
    <span id="completedStops" data-count="0" style="display:none"></span>
    <span id="totalStops" data-count="0" style="display:none"></span>

    <div class="trip-actions">
        <button class="btn-trip btn-start" id="startTripBtn">
            <span>▶</span><span>START TRIP</span>
        </button>
        <button class="btn-trip btn-end" id="endTripBtn" disabled>
            <span>⏹</span><span>END TRIP</span>
        </button>
    </div>

    <!-- Map -->
    <div id="tripMap"></div>

    <div id="toast" class="toast"></div>

    <!-- Route Timeline -->
    <div class="section-header">
        <h2>Route Stops</h2>
        <span id="stopCount" style="font-size:0.75rem;color:#64748b;">0 stops</span>
    </div>

    <div id="timelineContainer">
        @php $hasStops = false; @endphp
        @forelse($todayDeliveries as $delivery)
            @foreach($delivery->stops->sortBy('stop_order') as $stop)
                @php $hasStops = true; @endphp
                <div class="route-timeline">
                    <div class="timeline-item" data-stop-id="{{ $stop->id }}" data-delivery-id="{{ $delivery->id }}" data-lat="{{ $stop->retailStore?->latitude ?? 0 }}" data-lng="{{ $stop->retailStore?->longitude ?? 0 }}">
                        <div>
                            <div class="timeline-dot {{ $stop->status === 'completed' ? 'completed' : ($loop->first && !$activeTrip ? 'current' : 'pending') }}" id="dot-{{ $stop->id }}">
                                {{ $stop->stop_order }}
                            </div>
                            <div class="timeline-line"></div>
                        </div>
                        <div class="timeline-content">
                            <div class="store-name">{{ $stop->retailStore?->business_name ?? $stop->retailStore?->trade_name ?? 'Store #'.$stop->retail_store_id }}</div>
                            <div class="store-address">{{ $stop->retailStore?->address ?? 'Address not available' }}</div>
                            @if($delivery->items->count())
                                <div class="store-products">
                                    @foreach($delivery->items as $item)
                                        <span>{{ $item->product?->name ?? 'Item #'.$item->product_id }} ({{ $item->quantity_ordered }})</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="timeline-actions" id="actions-{{ $stop->id }}">
                                @if($stop->retailStore?->phone)
                                    <button class="btn-sm btn-call" onclick="callStore('{{ $stop->retailStore->phone }}')">📞 Call</button>
                                @endif
                                @if($stop->retailStore?->latitude && $stop->retailStore?->longitude)
                                    <button class="btn-sm btn-navigate" onclick="navigateTo({{ $stop->retailStore->latitude }}, {{ $stop->retailStore->longitude }})">🗺 Navigate</button>
                                @endif
                                <button class="btn-sm btn-complete-stop {{ $stop->status === 'completed' ? 'completed' : '' }}"
                                    id="complete-{{ $stop->id }}"
                                    onclick="completeStop({{ $stop->id }}, this)"
                                    {{ $stop->status === 'completed' ? 'disabled' : '' }}>
                                    {{ $stop->status === 'completed' ? '✓ Done' : '✓ Complete' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        @empty
            <div class="empty-state">
                <div class="icon">📭</div>
                <p>No deliveries assigned for today</p>
            </div>
        @endforelse
    </div>

    @if(!$hasStops)
    <div class="empty-state" style="padding-top:0;">
        <p style="font-size:0.875rem;color:#64748b;">Check back later or contact your dispatcher</p>
    </div>
    @endif
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    let watchId = null;
    let tripActive = false;
    let tripId = null;
    let locationInterval = null;
    let lastSendTime = 0;
    let startTime = null;
    let timerInterval = null;
    let map = null;
    let mapMarkers = [];
    let driverMarker = null;
    let prevLat = null;
    let prevLng = null;
    let totalDistance = 0;

    const BUTTONS = {
        start: document.getElementById('startTripBtn'),
        end: document.getElementById('endTripBtn'),
    };
    const STATUS = { dot: document.getElementById('statusDot'), text: document.getElementById('statusText') };
    const TOAST = document.getElementById('toast');

    function showToast(msg, type = 'info') {
        TOAST.className = 'toast ' + type;
        TOAST.textContent = msg;
        TOAST.style.display = 'block';
        clearTimeout(TOAST._hide);
        TOAST._hide = setTimeout(() => { TOAST.style.display = 'none'; }, 4000);
    }

    function setStatus(active, text) {
        STATUS.dot.className = 'status-dot ' + (active ? 'active' : tripActive ? 'error' : 'inactive');
        STATUS.text.textContent = text;
    }

    function formatTime(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) return h + 'h ' + m + 'm';
        return m + 'm ' + s + 's';
    }

    function updateDashboard() {
        if (!startTime) return;
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        document.getElementById('statTime').textContent = formatTime(elapsed);
    }

    function updateProgress() {
        const completed = parseInt(document.getElementById('completedStops')?.dataset?.count || '0');
        const total = parseInt(document.getElementById('totalStops')?.dataset?.count || '0');
        if (total > 0) {
            const pct = Math.round((completed / total) * 100);
            document.getElementById('progressFill').style.width = pct + '%';
            document.getElementById('progressText').textContent = completed + ' / ' + total;
            document.getElementById('statStops').textContent = completed + '/' + total;
        }
    }

    // Init map
    function initMap(stops) {
        if (map) return;
        map = L.map('tripMap', { zoomControl: false, attributionControl: false }).setView([30.0444, 31.2357], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(map);

        const bounds = [];
        const driverCoords = [];

        stops.forEach((s, i) => {
            const lat = parseFloat(s.dataset.lat);
            const lng = parseFloat(s.dataset.lng);
            if (!lat || !lng) return;

            const isCompleted = document.getElementById('dot-' + s.dataset.stopId)?.classList.contains('completed');
            const color = isCompleted ? '#22c55e' : '#3b82f6';

            const marker = L.circleMarker([lat, lng], {
                radius: 10, fillColor: color, color: '#fff', weight: 2, fillOpacity: 0.9
            }).addTo(map);
            marker.bindPopup('<b>Stop #' + (i + 1) + '</b><br>' + (s.querySelector('.store-name')?.textContent || ''));
            mapMarkers.push(marker);
            bounds.push([lat, lng]);
            driverCoords.push([lat, lng]);
        });

        if (bounds.length > 0) {
            map.fitBounds(bounds, { padding: [40, 40] });
        }

        // Draw polyline for the route
        if (driverCoords.length > 1) {
            L.polyline(driverCoords, { color: '#3b82f6', weight: 3, opacity: 0.6, dashArray: '8, 8' }).addTo(map);
        }
    }

    function updateDriverMarker(lat, lng) {
        if (!map) return;
        if (driverMarker) {
            driverMarker.setLatLng([lat, lng]);
        } else {
            driverMarker = L.circleMarker([lat, lng], {
                radius: 10, fillColor: '#ef4444', color: '#fff', weight: 3, fillOpacity: 1
            }).addTo(map);
            driverMarker.bindPopup('<b>You</b>');
        }
        map.panTo([lat, lng], { animate: true, duration: 0.5 });
    }

    function callStore(phone) {
        window.location.href = 'tel:' + phone;
    }

    function navigateTo(lat, lng) {
        const url = 'https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng;
        window.open(url, '_blank');
    }

    // --- Trip Lifecycle ---

    @if ($activeTrip)
        tripActive = true;
        tripId = {{ $activeTrip->id }};
        startTime = new Date('{{ $activeTrip->started_at }}').getTime();
        BUTTONS.start.disabled = true;
        BUTTONS.end.disabled = false;
        document.getElementById('statsDashboard').classList.remove('hidden');
        document.getElementById('progressSection').classList.remove('hidden');
        document.getElementById('completedStops')?.setAttribute('data-count', '{{ $activeTrip->completed_stops }}');
        document.getElementById('totalStops')?.setAttribute('data-count', '{{ $activeTrip->total_stops }}');
        updateProgress();
        updateDashboard();
        timerInterval = setInterval(updateDashboard, 1000);
        if (navigator.geolocation) startLocationTracking();
        setStatus(true, 'Tracking active');
        document.getElementById('gpsBar').classList.remove('hidden');
        // Init map with existing stops
        window.addEventListener('load', () => {
            const items = document.querySelectorAll('.timeline-item');
            initMap(items);
        });
    @else
        setStatus(false, 'Ready to start');
        // Init map with stops even before trip starts
        window.addEventListener('load', () => {
            const items = document.querySelectorAll('.timeline-item');
            if (items.length > 0) initMap(items);
        });
    @endif

    function startTrip() {
        showToast('Starting trip...', 'info');
        BUTTONS.start.disabled = true;
        BUTTONS.start.innerHTML = '<span class="loading-spinner"></span> Starting...';

        fetch('{{ route('driver.trip.start') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        })
        .then(r => r.json())
        .then(data => {
            if (data.trip) {
                tripActive = true;
                tripId = data.trip.id;
                startTime = Date.now();
                BUTTONS.start.innerHTML = '<span>▶</span><span>START TRIP</span>';
                BUTTONS.end.disabled = false;
                document.getElementById('statsDashboard').classList.remove('hidden');
                document.getElementById('progressSection').classList.remove('hidden');
                document.getElementById('totalStops').setAttribute('data-count', data.trip.total_stops || 0);
                document.getElementById('completedStops').setAttribute('data-count', '0');
                updateProgress();
                timerInterval = setInterval(updateDashboard, 1000);
                document.getElementById('gpsBar').classList.remove('hidden');

                if (navigator.geolocation) {
                    startLocationTracking();
                } else {
                    setStatus(false, 'GPS not available');
                    showToast('GPS is not available on this device', 'error');
                }
                showToast('Trip started!', 'success');
            } else {
                BUTTONS.start.disabled = false;
                BUTTONS.start.innerHTML = '<span>▶</span><span>START TRIP</span>';
                showToast(data.message || 'Failed to start trip', 'error');
            }
        })
        .catch(err => {
            BUTTONS.start.disabled = false;
            BUTTONS.start.innerHTML = '<span>▶</span><span>START TRIP</span>';
            showToast('Error: ' + (err.message || 'Connection failed'), 'error');
        });
    }

    function endTrip() {
        if (!confirm('End this trip?')) return;

        showToast('Ending trip...', 'info');
        BUTTONS.end.disabled = true;
        BUTTONS.end.innerHTML = '<span class="loading-spinner"></span> Ending...';

        fetch('{{ route('driver.trip.end') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        })
        .then(r => r.json())
        .then(data => {
            tripActive = false;
            tripId = null;
            stopLocationTracking();
            BUTTONS.start.disabled = false;
            BUTTONS.end.disabled = true;
            BUTTONS.end.innerHTML = '<span>⏹</span><span>END TRIP</span>';
            clearInterval(timerInterval);
            setStatus(false, 'Trip ended');
            document.getElementById('gpsBar').classList.add('hidden');
            showToast('Trip ended successfully', 'success');
            setTimeout(() => location.reload(), 1500);
        })
        .catch(err => {
            BUTTONS.end.disabled = false;
            BUTTONS.end.innerHTML = '<span>⏹</span><span>END TRIP</span>';
            showToast('Error: ' + (err.message || 'Failed'), 'error');
        });
    }

    // --- GPS ---

    function sendLocation(lat, lng, speed, accuracy) {
        const now = Date.now();
        if (now - lastSendTime < 9000) return;
        lastSendTime = now;

        document.getElementById('gpsAccuracy').textContent = (accuracy ? accuracy + 'm' : '--');
        document.getElementById('gpsCoords').textContent = lat.toFixed(4) + ', ' + lng.toFixed(4);

        // Calculate distance
        if (prevLat !== null && prevLng !== null) {
            const d = haversine(prevLat, prevLng, lat, lng);
            totalDistance += d;
        }
        prevLat = lat;
        prevLng = lng;

        document.getElementById('statSpeed').textContent = speed ? speed.toFixed(0) : '0';
        document.getElementById('statDist').textContent = totalDistance > 1 ? totalDistance.toFixed(1) + 'km' : (totalDistance * 1000).toFixed(0) + 'm';

        // Update driver marker on map
        updateDriverMarker(lat, lng);

        setStatus(true, 'Sending location...');

        fetch('{{ route('api.driver.location') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ latitude: lat, longitude: lng, speed_kmh: speed || 0, accuracy: accuracy || 0 }),
        })
        .then(r => {
            if (!r.ok && r.status !== 429) throw new Error('Server error');
            return r.json();
        })
        .then(data => {
            if (data.message === 'No active trip') {
                tripActive = false;
                stopLocationTracking();
                setStatus(false, 'Trip no longer active');
                BUTTONS.start.disabled = false;
                BUTTONS.end.disabled = true;
                showToast('Trip was ended remotely', 'info');
            } else {
                setStatus(true, 'Location sharing active');
                document.getElementById('gpsAge').textContent = 'Just now';
            }
        })
        .catch(err => {
            if (err.message !== 'Too frequent') {
                setStatus(true, 'GPS active (send pending)');
            }
        });
    }

    function haversine(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    function startLocationTracking() {
        if (!navigator.geolocation) return;

        setStatus(true, 'Requesting GPS...');

        // Get initial position immediately
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const { latitude, longitude, speed, accuracy } = pos.coords;
                updateDriverMarker(latitude, longitude);
            },
            () => {},
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
        );

        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                const { latitude, longitude, speed, accuracy } = pos.coords;
                sendLocation(latitude, longitude, speed, accuracy);
            },
            (err) => {
                let msg = 'GPS error: ';
                switch (err.code) {
                    case err.PERMISSION_DENIED: msg += 'Permission denied'; break;
                    case err.POSITION_UNAVAILABLE: msg += 'Signal unavailable'; break;
                    case err.TIMEOUT: msg += 'Timed out'; break;
                    default: msg += 'Unknown';
                }
                setStatus(false, msg);
                showToast(msg, 'error');
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
        );
    }

    function stopLocationTracking() {
        if (watchId) { navigator.geolocation.clearWatch(watchId); watchId = null; }
        if (locationInterval) { clearInterval(locationInterval); locationInterval = null; }
    }

    // --- Stop Management ---

    function completeStop(stopId, btn) {
        if (btn.disabled || btn.classList.contains('completed')) return;
        btn.disabled = true;
        btn.innerHTML = '⏳ ...';

        fetch('/api/driver/stop/' + stopId + '/complete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        })
        .then(r => r.json())
        .then(data => {
            // Update timeline dot
            const dot = document.getElementById('dot-' + stopId);
            if (dot) {
                dot.className = 'timeline-dot completed';
                dot.textContent = '✓';
            }
            btn.className = 'btn-sm btn-complete-stop completed';
            btn.innerHTML = '✓ Done';
            btn.disabled = true;

            // Update progress
            const completed = parseInt(document.getElementById('completedStops')?.dataset?.count || '0');
            document.getElementById('completedStops').setAttribute('data-count', (completed + 1).toString());
            updateProgress();

            showToast('Stop completed!', 'success');

            // Mark next stop as current
            const items = document.querySelectorAll('.timeline-item');
            let foundCurrent = false;
            items.forEach(item => {
                const dot = item.querySelector('.timeline-dot');
                if (dot && dot.classList.contains('pending') && !foundCurrent) {
                    dot.className = 'timeline-dot current';
                    dot.textContent = dot.textContent;
                    foundCurrent = true;
                    item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '✓ Complete';
            showToast('Error: ' + (err.message || 'Failed'), 'error');
        });
    }

    // Set greeting based on time of day
    (function setGreeting() {
        const h = new Date().getHours();
        let g = 'Good evening';
        if (h < 12) g = 'Good morning';
        else if (h < 17) g = 'Good afternoon';
        document.getElementById('greeting').textContent = g;
    })();
</script>
@endsection