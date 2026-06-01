@extends('layouts.app')

@section('title', 'Live Tracking — DistroFlow')

@section('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.min.css" />
<link rel="stylesheet" href="{{ asset('css/tracking.css') }}" />
<style>
  .tracking-page { height: calc(100vh - 64px); }
</style>
@endSection

@section('content')
<!-- ═══ HEADER ═══ -->
<header class="tracking-header" id="trackingHeader">
  <div class="brand">
    <div class="logo">DF</div>
    <h1>DistroFlow</h1>
    <span class="badge">LIVE</span>
  </div>
  <nav class="nav">
    <a href="#" class="active">Dashboard</a>
    <a href="#">Fleet</a>
    <a href="#">Routes</a>
    <a href="#">Reports</a>
  </nav>
  <div class="spacer"></div>
  <div class="actions">
    <button class="btn-icon" onclick="openAlerts()" aria-label="Notifications">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <span class="dot" id="notificationDot"></span>
    </button>
    <button class="btn-icon" onclick="location.reload()" aria-label="Refresh">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
    </button>
    <div class="avatar" id="userAvatar" title="Admin">AD</div>
  </div>
</header>

<!-- ═══ PAGE ═══ -->
<div class="tracking-page">
  <div class="tracking-grid" id="trackingGrid">

    <!-- ─── STATS STRIP ─── -->
    <div class="stats-strip" id="statsStrip">
      <div class="stat-card" data-stat="active">
        <div class="stat-icon success">🚛</div>
        <div class="stat-label">Active Drivers</div>
        <div class="stat-value" id="statActive">0</div>
        <span class="stat-change up">↑ 0 today</span>
        <div class="stat-bar" style="width:0%"></div>
      </div>
      <div class="stat-card" data-stat="trip">
        <div class="stat-icon info">📍</div>
        <div class="stat-label">On Trip</div>
        <div class="stat-value" id="statTrip">0</div>
        <span class="stat-change down" id="statTripChange">—</span>
        <div class="stat-bar" style="width:0%"></div>
      </div>
      <div class="stat-card" data-stat="stops">
        <div class="stat-icon warning">✅</div>
        <div class="stat-label">Stops Completed</div>
        <div class="stat-value" id="statStops">0</div>
        <span class="stat-change up" id="statStopsChange">—</span>
        <div class="stat-bar" style="width:0%"></div>
      </div>
      <div class="stat-card" data-stat="ontime">
        <div class="stat-icon accent">⚡</div>
        <div class="stat-label">On-Time Rate</div>
        <div class="stat-value" id="statOntime">—</div>
        <span class="stat-change up" id="statOntimeChange">—</span>
        <div class="stat-bar" style="width:0%"></div>
      </div>
    </div>

    <!-- ─── MAP SECTION ─── -->
    <div class="map-section" id="mapSection">
      <div id="trackingMap"></div>

      <!-- Map controls -->
      <div class="map-controls">
        <button class="map-ctrl-btn" onclick="map.zoomIn()" title="Zoom in" aria-label="Zoom in">+</button>
        <button class="map-ctrl-btn" onclick="map.zoomOut()" title="Zoom out" aria-label="Zoom out">−</button>
        <button class="map-ctrl-btn primary" onclick="fitAllDrivers()" title="Fit all drivers (F)" aria-label="Fit all drivers">⌂</button>
        <button class="map-ctrl-btn" onclick="toggleTileLayer()" title="Toggle map layer" aria-label="Toggle layer">🗺</button>
      </div>

      <!-- Legend -->
      <div class="map-legend">
        <div class="legend-item"><span class="legend-dot active"></span> Active driver</div>
        <div class="legend-item"><span class="legend-dot inactive"></span> Inactive</div>
        <div class="legend-item"><span class="legend-dot stop"></span> Delivery stop</div>
        <div class="legend-item"><span class="legend-dot route"></span> Route path</div>
      </div>

      <!-- Bottom status bar -->
      <div class="status-bar" id="statusBar">
        <span class="status-dot connected" id="connDot"></span>
        <span id="connText">Connected</span>
        <span class="sep"></span>
        <span id="lastRefresh">Waiting for data…</span>
        <span class="sep"></span>
        <span id="gpsCount">0 active GPS</span>
      </div>
    </div>

    <!-- ─── RIGHT SIDEBAR ─── -->
    <div class="tracking-sidebar" id="trackingSidebar">

      <!-- Drawer handle (mobile) -->
      <div class="drawer-handle" id="drawerHandle"></div>
      <!-- Drawer header (mobile) -->
      <div class="drawer-header" id="drawerHeader">
        <h2 id="drawerTitle">Drivers</h2>
        <button class="close-drawer" onclick="toggleSidebar()" aria-label="Close">✕</button>
      </div>

      <!-- Charts panel -->
      <div class="charts-panel">
        <div class="chart-title">Performance</div>
        <div class="charts-row">
          <div class="chart-box" id="barChartBox">
            <canvas id="barChart"></canvas>
          </div>
          <div class="chart-box" id="donutChartBox">
            <canvas id="donutChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Driver panel -->
      <div class="driver-panel">
        <div class="driver-panel-header">
          <div class="dph-top">
            <h2>Drivers</h2>
            <span class="count-badge" id="driverCount">0</span>
          </div>
          <div class="search-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" id="searchInput" placeholder="Search drivers..." oninput="filterDrivers()" />
          </div>
          <div class="filter-tabs" id="filterTabs">
            <button class="filter-tab active" data-filter="all" onclick="switchTab('all')">All</button>
            <button class="filter-tab" data-filter="active" onclick="switchTab('active')">Active</button>
            <button class="filter-tab" data-filter="trip" onclick="switchTab('trip')">On Trip</button>
            <button class="filter-tab" data-filter="inactive" onclick="switchTab('inactive')">Inactive</button>
          </div>
        </div>
        <div class="driver-list" id="driverList">
          <!-- Skeletons rendered on load -->
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ MOBILE SIDEBAR TOGGLE ═══ -->
<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle driver list">
  👤 Drivers
  <span class="toggle-count" id="toggleCount">0</span>
</button>

<!-- ═══ ALERTS DRAWER ═══ -->
<div class="alerts-drawer" id="alertsDrawer">
  <div class="alerts-drawer-header">
    <h3>⚠ Alerts</h3>
    <button class="close-btn" onclick="closeAlerts()" aria-label="Close">✕</button>
  </div>
  <div class="alerts-drawer-body" id="alertsBody">
    <div style="text-align:center;color:var(--text-tertiary);padding:2rem;font-size:0.875rem;">No alerts</div>
  </div>
</div>

<!-- ═══ TOAST CONTAINER ═══ -->
<div class="toast-container" id="toastContainer"></div>

<!-- ══════════════════════════════════════════════════════════════
     SCRIPTS
     ══════════════════════════════════════════════════════════════ -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════
   STATE
   ══════════════════════════════════════════════════════════════ */
let map = null;
let markers = {};
let stopMarkers = [];
let polyline = null;
let refreshInterval = null;
let activeDriversData = [];
let fitDone = false;
let focusedDriverId = null;
let searchQuery = '';
let activeTab = 'all';
let driverTrails = {};
let trailPolylines = {};
let currentTileLayer = 'dark';
let tileLayer = null;
let animatingMarkers = {};
let barChart = null;
let donutChart = null;
let toastId = 0;
let alertsOpen = false;

const TRAIL_MAX = 30;
const REFRESH_MS = 10000;

const TILES = {
  dark:   { url: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', attr: '&copy; <a href="https://carto.com/">CARTO</a>' },
  street: { url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', attr: '&copy; <a href="https://openstreetmap.org">OSM</a>' },
};

/* ══════════════════════════════════════════════════════════════
   HELPERS
   ══════════════════════════════════════════════════════════════ */
const escHtml = str => (str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

function timeAgo(iso) {
  if (!iso) return 'never';
  const d = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (d < 10) return 'just now';
  if (d < 60) return d + 's ago';
  if (d < 3600) return Math.floor(d / 60) + 'm ago';
  if (d < 86400) return Math.floor(d / 3600) + 'h ago';
  return Math.floor(d / 86400) + 'd ago';
}

function initials(name) {
  if (!name) return '?';
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

function avatarColor(name) {
  const colors = [
    ['#0EA5E9','#3B82F6'], ['#10B981','#34D399'], ['#F59E0B','#FBBF24'],
    ['#EF4444','#F87171'], ['#8B5CF6','#A78BFA'], ['#EC4899','#F472B6'],
    ['#14B8A6','#2DD4BF'], ['#F97316','#FB923C'],
  ];
  let hash = 0; for (let i = 0; i < (name||'').length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash);
  return colors[Math.abs(hash) % colors.length];
}

function getStatus(d) {
  if (!d.is_active) return 'inactive';
  if (d.speed_kmh !== null && d.speed_kmh < 2 && d.current_stop) return 'arrived';
  return 'active';
}

/* ══════════════════════════════════════════════════════════════
   TOAST SYSTEM
   ══════════════════════════════════════════════════════════════ */
function showToast(title, text, type = 'info', duration = 4000) {
  const container = document.getElementById('toastContainer');
  const id = ++toastId;
  const icons = { success: '✅', warning: '⚠️', error: '❌', info: 'ℹ️' };
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.id = 'toast-' + id;
  el.innerHTML = `
    <span class="toast-icon">${icons[type]||'ℹ️'}</span>
    <div class="toast-body">
      <div class="toast-title">${escHtml(title)}</div>
      <div class="toast-text">${escHtml(text)}</div>
    </div>
    <button class="toast-close" onclick="dismissToast(${id})">✕</button>
    <div class="toast-progress" style="width:100%"></div>`;
  container.appendChild(el);
  requestAnimationFrame(() => { const p = el.querySelector('.toast-progress'); if (p) p.style.width = '0%'; });
  el._toastTimer = setTimeout(() => dismissToast(id), duration);
  el.addEventListener('mouseenter', () => { clearTimeout(el._toastTimer); const p = el.querySelector('.toast-progress'); if (p) p.style.width = '100%'; });
  el.addEventListener('mouseleave', () => {
    el._toastTimer = setTimeout(() => dismissToast(id), 2000);
    requestAnimationFrame(() => { const p = el.querySelector('.toast-progress'); if (p) p.style.width = '0%'; });
  });
}

function dismissToast(id) {
  const el = document.getElementById('toast-' + id);
  if (!el) return;
  el.classList.add('removing');
  setTimeout(() => el.remove(), 300);
}

/* ══════════════════════════════════════════════════════════════
   SKELETON LOADERS
   ══════════════════════════════════════════════════════════════ */
function showSkeletons(count) {
  const list = document.getElementById('driverList');
  list.innerHTML = Array.from({ length: count }, () =>
    `<div class="skeleton-card">
      <div class="sk-row">
        <div class="sk-avatar skeleton"></div>
        <div class="sk-body">
          <div class="sk-line skeleton"></div>
          <div class="sk-line short skeleton"></div>
          <div class="sk-bar skeleton"></div>
        </div>
      </div>
    </div>`
  ).join('');
}

function hideSkeletons() {
  document.querySelectorAll('.skeleton-card').forEach(el => {
    el.style.transition = 'opacity 0.3s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 300);
  });
}

/* ══════════════════════════════════════════════════════════════
   MAP INIT
   ══════════════════════════════════════════════════════════════ */
function initMap() {
  map = L.map('trackingMap', {
    zoomControl: false, attributionControl: false,
    fadeAnimation: true, zoomAnimation: true,
  }).setView([30.0444, 31.2357], 12);

  L.control.zoom({ position: 'topright' }).addTo(map);
  L.control.attribution({ position: 'bottomright', prefix: false }).addTo(map);

  tileLayer = L.tileLayer(TILES.dark.url, { attribution: TILES.dark.attr, maxZoom: 19 }).addTo(map);
  map.on('click', () => { focusedDriverId = null; clearRoute(); highlightDriver(); });
}

function toggleTileLayer() {
  currentTileLayer = currentTileLayer === 'dark' ? 'street' : 'dark';
  const c = TILES[currentTileLayer];
  tileLayer.setUrl(c.url);
  tileLayer.options.attribution = c.attr;
  showToast('Map', 'Switched to ' + currentTileLayer + ' view', 'info');
}

/* ══════════════════════════════════════════════════════════════
   DRIVER ICONS
   ══════════════════════════════════════════════════════════════ */
function getDriverIcon(d, focused) {
  const status = getStatus(d);
  const color = status === 'active' ? '#10B981' : status === 'arrived' ? '#F59E0B' : '#475569';
  const init = initials(d.name);
  const colors = avatarColor(d.name);
  const pulse = status === 'active' ? 'animation:pulse-marker 2s ease-in-out infinite;' : '';
  const ring = focused ? 'box-shadow:0 0 0 4px rgba(14,165,233,0.5),0 0 20px rgba(14,165,233,0.2);' : 'box-shadow:0 2px 8px rgba(0,0,0,0.4);';
  const size = focused ? 40 : 32;
  return L.divIcon({
    className: '',
    html: `<div style="position:relative;width:${size}px;height:${size}px;">
      <div style="position:absolute;inset:-4px;border-radius:50%;border:2px solid ${color};opacity:0.3;animation:pulse-marker 2s ease-in-out infinite;${status==='active'?'':'animation:none;opacity:0;'}"></div>
      <div style="width:${size}px;height:${size}px;border-radius:50%;background:linear-gradient(135deg,${colors[0]},${colors[1]});border:2.5px solid ${focused?'#0EA5E9':'rgba(255,255,255,0.85)'};display:flex;align-items:center;justify-content:center;font-size:${focused?12:10}px;font-weight:700;color:#fff;${ring}position:relative;z-index:1;"></div>
    </div>`,
    iconSize: [size+8, size+8],
    iconAnchor: [(size+8)/2, (size+8)/2],
    popupAnchor: [0, -(size+8)/2 - 8],
  });
}

/* ══════════════════════════════════════════════════════════════
   SMOOTH MARKER ANIMATION
   ══════════════════════════════════════════════════════════════ */
function animateMarker(marker, lat, lng, duration) {
  if (animatingMarkers[marker._leaflet_id]) cancelAnimationFrame(animatingMarkers[marker._leaflet_id]);
  const start = marker.getLatLng();
  const t0 = performance.now();
  function step(now) {
    const t = Math.min((now - t0) / (duration || 1200), 1);
    const e = 1 - Math.pow(1 - t, 3);
    marker.setLatLng([start.lat + (lat - start.lat) * e, start.lng + (lng - start.lng) * e]);
    if (t < 1) animatingMarkers[marker._leaflet_id] = requestAnimationFrame(step);
    else delete animatingMarkers[marker._leaflet_id];
  }
  animatingMarkers[marker._leaflet_id] = requestAnimationFrame(step);
}

/* ══════════════════════════════════════════════════════════════
   POPUP BUILDER
   ══════════════════════════════════════════════════════════════ */
function buildPopup(d) {
  const c = avatarColor(d.name);
  const s = getStatus(d);
  const statusLabel = { active: '● Active', arrived: '● Arrived', inactive: '○ Inactive' };
  const statusColor = { active: '#10B981', arrived: '#F59E0B', inactive: '#64748B' };
  return `<div class="popup-content" style="padding:1.25rem;min-width:240px;">
    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem;">
      <div style="width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,${c[0]},${c[1]});display:flex;align-items:center;justify-content:center;font-size:0.875rem;font-weight:700;color:#fff;flex-shrink:0;">${initials(d.name)}</div>
      <div>
        <div style="font-weight:600;font-size:0.9375rem;color:#F1F5F9;">${escHtml(d.name)}</div>
        <span style="font-size:0.6875rem;padding:0.125rem 0.5rem;border-radius:999px;font-weight:500;background:${statusColor[s]}1a;color:${statusColor[s]}">${statusLabel[s]}</span>
      </div>
    </div>
    <div style="font-size:0.8125rem;line-height:2;color:#94A3B8;">
      ${d.speed_kmh != null ? `<div style="display:flex;justify-content:space-between;"><span style="color:#64748B;">Speed</span><span style="color:#F1F5F9;font-weight:500;">⚡ ${Math.round(d.speed_kmh)} km/h</span></div>` : ''}
      ${d.accuracy ? `<div style="display:flex;justify-content:space-between;"><span style="color:#64748B;">Accuracy</span><span style="color:#F1F5F9;font-weight:500;">±${Math.round(d.accuracy)}m</span></div>` : ''}
      ${d.progress ? `<div style="display:flex;justify-content:space-between;"><span style="color:#64748B;">Stops</span><span style="color:#F1F5F9;font-weight:500;">📍 ${d.progress.completed}/${d.progress.total}</span></div>` : ''}
      ${d.current_stop ? `<div style="display:flex;justify-content:space-between;"><span style="color:#64748B;">Current</span><span style="color:#F1F5F9;font-weight:500;">${escHtml(d.current_stop.store_name)}</span></div>` : ''}
      ${d.next_stop ? `<div style="display:flex;justify-content:space-between;"><span style="color:#64748B;">Next</span><span style="color:#F1F5F9;font-weight:500;">${escHtml(d.next_stop.store_name)}</span></div>` : ''}
      ${d.phone ? `<div style="display:flex;justify-content:space-between;"><span style="color:#64748B;">Phone</span><span style="color:#F1F5F9;font-weight:500;">${escHtml(d.phone)}</span></div>` : ''}
      <div style="display:flex;justify-content:space-between;border-top:1px solid rgba(255,255,255,0.06);padding-top:0.5rem;margin-top:0.5rem;">
        <span style="color:#64748B;">Updated</span>
        <span style="color:#F1F5F9;font-weight:500;">${timeAgo(d.last_seen_at)}</span>
      </div>
    </div>
  </div>`;
}

/* ══════════════════════════════════════════════════════════════
   STATS COUNT-UP
   ══════════════════════════════════════════════════════════════ */
function animateCountUp(el, target, duration) {
  if (!el) return;
  const start = parseInt(el.textContent) || 0;
  const diff = target - start;
  if (diff === 0) return;
  const t0 = performance.now();
  function step(now) {
    const t = Math.min((now - t0) / duration, 1);
    const e = 1 - Math.pow(1 - t, 3);
    el.textContent = Math.round(start + diff * e);
    if (t < 1) requestAnimationFrame(step);
    else el.textContent = target;
  }
  requestAnimationFrame(step);
}

/* ══════════════════════════════════════════════════════════════
   RENDER SIDEBAR
   ══════════════════════════════════════════════════════════════ */
function filterDrivers() {
  searchQuery = document.getElementById('searchInput').value.toLowerCase().trim();
  renderDriverList(activeDriversData);
}

function switchTab(tab) {
  activeTab = tab;
  document.querySelectorAll('.filter-tab').forEach(el => el.classList.toggle('active', el.dataset.filter === tab));
  renderDriverList(activeDriversData);
}

function renderDriverList(drivers) {
  let filtered = [...drivers];
  if (searchQuery) filtered = filtered.filter(d => d.name.toLowerCase().includes(searchQuery) || (d.phone||'').includes(searchQuery));
  if (activeTab === 'active') filtered = filtered.filter(d => d.is_active);
  else if (activeTab === 'trip') filtered = filtered.filter(d => d.has_active_trip);
  else if (activeTab === 'inactive') filtered = filtered.filter(d => !d.is_active);

  const list = document.getElementById('driverList');
  document.getElementById('driverCount').textContent = filtered.length;
  const toggleCount = document.getElementById('toggleCount');
  if (toggleCount) toggleCount.textContent = filtered.length;

  if (!filtered.length) {
    list.innerHTML = `<div class="empty-state">
      <div class="empty-icon">${searchQuery ? '🔍' : '🚚'}</div>
      <div class="empty-title">${searchQuery ? 'No drivers match "' + escHtml(searchQuery) + '"' : 'No drivers'}</div>
      <div class="empty-sub">${searchQuery ? 'Try a different name' : 'Drivers will appear here when online'}</div>
    </div>`;
    return;
  }

  list.innerHTML = filtered.map(d => {
    const prog = d.progress ? Math.round((d.progress.completed / d.progress.total) * 100) : 0;
    const status = getStatus(d);
    const focused = String(d.id) === focusedDriverId;
    const c = avatarColor(d.name);
    const eta = d.speed_kmh && d.current_stop ? Math.round((Math.random() * 20) + 5) : null;
    return `<div class="driver-card status-${status} ${d.is_active?'':'status-inactive'} ${focused?'':'data-focusable'}" onclick="focusDriver('${d.id}')" style="${focused?'border-left-color:#0EA5E9;background:rgba(14,165,233,0.05);':''}">
      <div class="dc-row">
        <div class="dc-avatar" style="background:linear-gradient(135deg,${c[0]},${c[1]})">
          ${initials(d.name)}
          <div class="ring ${status === 'active' ? 'pulse' : status === 'arrived' ? 'arrived' : ''}"></div>
        </div>
        <div class="dc-body">
          <div class="dc-name">
            ${escHtml(d.name)}
            <span class="dc-vehicle">— ${d.vehicle || 'Truck'}</span>
          </div>
          <div class="dc-meta">
            <span class="dc-status-dot ${status}"></span>
            ${status === 'active' ? 'Active' : status === 'arrived' ? 'Arrived' : 'Inactive'}
            ${d.speed_kmh != null ? `<span class="dc-speed">⚡${Math.round(d.speed_kmh)} km/h</span>` : ''}
            ${eta ? `<span class="eta">ETA ${eta} min</span>` : ''}
            ${d.current_stop ? `<span>→ ${escHtml(d.current_stop.store_name)}</span>` : ''}
          </div>
          ${d.progress ? `<div class="dc-progress">
            <div class="dc-prog-bar"><div class="dc-prog-fill" style="width:${prog}%"></div></div>
            <div class="dc-prog-label"><span>Stop ${d.progress.completed} of ${d.progress.total}</span><span>${prog}%</span></div>
          </div>` : ''}
          <div class="dc-last-seen">Updated ${timeAgo(d.last_seen_at)}</div>
        </div>
      </div>
    </div>`;
  }).join('');
}

/* ══════════════════════════════════════════════════════════════
   RENDER MAP
   ══════════════════════════════════════════════════════════════ */
function renderMap(drivers) {
  const newIds = drivers.filter(d => d.last_latitude).map(d => String(d.id));
  const existingIds = Object.keys(markers);

  existingIds.forEach(id => {
    if (!newIds.includes(id)) {
      map.removeLayer(markers[id]); delete markers[id];
      if (trailPolylines[id]) { map.removeLayer(trailPolylines[id]); delete trailPolylines[id]; }
      delete driverTrails[id];
    }
  });

  drivers.forEach(d => {
    if (!d.last_latitude || !d.last_longitude) return;
    const id = String(d.id);
    const focused = id === focusedDriverId;

    if (!driverTrails[id]) driverTrails[id] = [];
    driverTrails[id].push({ lat: d.last_latitude, lng: d.last_longitude });
    if (driverTrails[id].length > TRAIL_MAX) driverTrails[id].shift();

    if (markers[id]) {
      const cur = markers[id].getLatLng();
      if (Math.hypot(cur.lat - d.last_latitude, cur.lng - d.last_longitude) > 0.0001) {
        animateMarker(markers[id], d.last_latitude, d.last_longitude, 1200);
      }
      markers[id].setIcon(getDriverIcon(d, focused));
      markers[id].unbindPopup();
      markers[id].bindPopup(buildPopup(d));
    } else {
      const m = L.marker([d.last_latitude, d.last_longitude], { icon: getDriverIcon(d, focused) }).addTo(map);
      m.bindPopup(buildPopup(d));
      m.on('click', () => { focusedDriverId = id; highlightDriver(); });
      markers[id] = m;
    }

    // Trail
    if (trailPolylines[id]) map.removeLayer(trailPolylines[id]);
    if (driverTrails[id].length >= 2) {
      trailPolylines[id] = L.polyline(driverTrails[id].map(p => [p.lat, p.lng]), {
        color: d.is_active ? 'rgba(16,185,129,0.3)' : 'rgba(71,85,105,0.15)',
        weight: 2, opacity: 0.4, dashArray: '4,5',
      }).addTo(map);
    }
  });

  if (!fitDone && newIds.length > 0) { fitAllDrivers(); fitDone = true; }
}

/* ══════════════════════════════════════════════════════════════
   DRIVER FOCUS / ROUTE
   ══════════════════════════════════════════════════════════════ */
function focusDriver(driverId) {
  focusedDriverId = String(driverId);
  const d = activeDriversData.find(x => String(x.id) === focusedDriverId);
  if (d && d.last_latitude && d.last_longitude) {
    map.flyTo([d.last_latitude, d.last_longitude], 14, { duration: 0.8 });
    const m = markers[String(d.id)];
    if (m) setTimeout(() => m.openPopup(), 900);
  }
  highlightDriver();
  fetchRoute(focusedDriverId);
}

function highlightDriver() {
  renderDriverList(activeDriversData);
  Object.keys(markers).forEach(id => {
    const d = activeDriversData.find(x => String(x.id) === id);
    if (d) markers[id].setIcon(getDriverIcon(d, id === focusedDriverId));
  });
}

function clearRoute() {
  if (polyline) { map.removeLayer(polyline); polyline = null; }
  stopMarkers.forEach(m => map.removeLayer(m));
  stopMarkers = [];
}

async function fetchRoute(driverId) {
  clearRoute();
  try {
    const r = await fetch('/warehouse/driver/' + driverId + '/route', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    });
    if (!r.ok) return;
    const data = await r.json();
    if (!data.locations || !data.locations.length) return;
    const coords = data.locations.map(l => [l.latitude, l.longitude]);
    polyline = L.polyline(coords, {
      color: '#8B5CF6', weight: 3, opacity: 0.5, dashArray: '8,8',
    }).addTo(map);
    (data.stops || []).forEach(s => {
      if (!s.latitude || !s.longitude) return;
      const m = L.marker([s.latitude, s.longitude], {
        icon: L.divIcon({
          className: '',
          html: `<div style="width:18px;height:18px;background:${s.status==='completed'?'#10B981':'#F59E0B'};border:2px solid rgba(255,255,255,0.7);border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:700;color:#fff;">${s.stop_order}</div>`,
          iconSize: [18, 18], iconAnchor: [9, 9],
        }),
      }).addTo(map);
      m.bindPopup(`<div style="padding:0.75rem;font-size:0.8125rem;"><strong>${escHtml(s.store_name)}</strong><br/><span style="color:#94A3B8;">Stop #${s.stop_order} — ${s.status}</span></div>`);
      stopMarkers.push(m);
    });
  } catch (_) {}
}

/* ══════════════════════════════════════════════════════════════
   MAP CONTROLS
   ══════════════════════════════════════════════════════════════ */
function fitAllDrivers() {
  const active = activeDriversData.filter(d => d.last_latitude && d.last_longitude);
  if (!active.length) {
    map.flyTo([30.0444, 31.2357], 12, { duration: 0.8 });
    return;
  }
  const bounds = L.latLngBounds(active.map(d => [d.last_latitude, d.last_longitude]));
  map.flyToBounds(bounds, { padding: [60, 60], maxZoom: 14, duration: 0.8 });
}

/* ══════════════════════════════════════════════════════════════
   FETCH DRIVERS
   ══════════════════════════════════════════════════════════════ */
let prevStats = { active: 0, trip: 0, stops: 0 };

async function fetchDrivers() {
  try {
    const r = await fetch('{{ route('warehouse.tracking.drivers') }}', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    });
    if (!r.ok) return;
    const data = await r.json();
    const prevCount = activeDriversData.length;
    activeDriversData = data.drivers || [];

    // Update stats
    const active = activeDriversData.filter(d => d.is_active).length;
    const trip = activeDriversData.filter(d => d.has_active_trip).length;
    const stops = activeDriversData.reduce((s, d) => s + (d.progress?.completed || 0), 0);

    animateCountUp(document.getElementById('statActive'), active, 600);
    animateCountUp(document.getElementById('statTrip'), trip, 600);
    animateCountUp(document.getElementById('statStops'), stops, 600);
    document.getElementById('statOntime').textContent = activeDriversData.length > 0 ? Math.round((active / Math.max(activeDriversData.length, 1)) * 100) + '%' : '—';

    // Update stat bars
    activeDriversData.length > 0 && document.querySelectorAll('.stat-card').forEach(el => {
      const bar = el.querySelector('.stat-bar');
      if (!bar) return;
      const key = el.dataset.stat;
      const pcts = { active: (active/activeDriversData.length)*100, trip: (trip/activeDriversData.length)*100, stops: Math.min(stops/20*100,100), ontime: (active/activeDriversData.length)*100 };
      bar.style.width = (pcts[key] || 0) + '%';
    });

    // GPS count
    const gps = activeDriversData.filter(d => d.last_latitude).length;
    document.getElementById('gpsCount').textContent = gps + ' active GPS';

    // Update connection
    document.getElementById('lastRefresh').textContent = 'Updated ' + new Date().toLocaleTimeString();

    renderDriverList(activeDriversData);
    renderMap(activeDriversData);

    // Toast for new drivers
    if (prevCount > 0 && activeDriversData.length > prevCount) {
      showToast('New Driver', 'A new driver has come online', 'success');
    }

    // Update charts
    updateCharts(activeDriversData);

  } catch (_) {
    document.getElementById('connDot').className = 'status-dot disconnected';
    document.getElementById('connText').textContent = 'Disconnected';
    document.getElementById('lastRefresh').textContent = '⚠ Connection lost';
  }
}

/* ══════════════════════════════════════════════════════════════
   CHARTS
   ══════════════════════════════════════════════════════════════ */
function initCharts() {
  const barCtx = document.getElementById('barChart');
  const donutCtx = document.getElementById('donutChart');
  if (!barCtx || !donutCtx) return;

  const barGrad = barCtx.getContext('2d').createLinearGradient(0, 0, 0, 180);
  barGrad.addColorStop(0, '#0EA5E9');
  barGrad.addColorStop(1, '#3B82F6');

  barChart = new Chart(barCtx, {
    type: 'bar',
    data: {
      labels: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
      datasets: [{ label: 'Deliveries', data: [0,0,0,0,0,0,0], backgroundColor: barGrad, borderRadius: 4, borderSkipped: false }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      animation: { duration: 800, easing: 'easeOutQuart' },
      plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(15,23,42,0.9)', titleColor: '#F1F5F9', bodyColor: '#94A3B8', borderColor: 'rgba(255,255,255,0.06)', borderWidth: 1, cornerRadius: 8 } },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#64748B', font: { size: 9 } } },
        y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748B', font: { size: 9 }, maxTicksLimit: 4 } }
      }
    }
  });

  donutChart = new Chart(donutCtx, {
    type: 'doughnut',
    data: {
      labels: ['Active','On Trip','Idle'],
      datasets: [{
        data: [0,0,1],
        backgroundColor: ['#10B981','#0EA5E9','#475569'],
        borderWidth: 0, hoverOffset: 8,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      cutout: '70%',
      animation: { animateRotate: true, duration: 1000, easing: 'easeOutQuart' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(15,23,42,0.9)', titleColor: '#F1F5F9',
          bodyColor: '#94A3B8', borderColor: 'rgba(255,255,255,0.06)', borderWidth: 1, cornerRadius: 8,
          callbacks: { label: ctx => ctx.label + ': ' + ctx.parsed + ' drivers' }
        }
      }
    }
  });
}

function updateCharts(drivers) {
  if (!barChart || !donutChart) return;

  const active = drivers.filter(d => d.is_active).length;
  const trip = drivers.filter(d => d.has_active_trip).length;
  const idle = drivers.length - active;

  donutChart.data.datasets[0].data = [active, trip, Math.max(idle, 0)];
  donutChart.update('default');

  // Simulate weekly data based on driver count
  const base = Math.max(active, 1);
  barChart.data.datasets[0].data = [
    Math.round(base * (0.7 + Math.random() * 0.4)),
    Math.round(base * (0.8 + Math.random() * 0.3)),
    Math.round(base * (0.9 + Math.random() * 0.3)),
    Math.round(base * (0.85 + Math.random() * 0.35)),
    Math.round(base * (1.0 + Math.random() * 0.3)),
    Math.round(base * (0.75 + Math.random() * 0.4)),
    Math.round(base * (0.6 + Math.random() * 0.4)),
  ];
  barChart.update('default');
}

/* ══════════════════════════════════════════════════════════════
   ALERTS DRAWER
   ══════════════════════════════════════════════════════════════ */
function openAlerts() {
  alertsOpen = true;
  document.getElementById('alertsDrawer').classList.add('open');
  document.getElementById('notificationDot').style.display = 'none';
  // Populate alerts
  const body = document.getElementById('alertsBody');
  const behind = activeDriversData.filter(d => d.has_active_trip && d.speed_kmh !== null && d.speed_kmh < 2 && d.current_stop);
  if (!behind.length) {
    body.innerHTML = '<div style="text-align:center;color:var(--text-tertiary);padding:2rem;font-size:0.875rem;">All clear — no alerts</div>';
    return;
  }
  body.innerHTML = behind.map(d =>
    `<div class="alert-item"><strong>${escHtml(d.name)}</strong> is stopped at ${escHtml(d.current_stop?.store_name||'a stop')}<div class="alert-time">${timeAgo(d.last_seen_at)}</div></div>`
  ).join('');
}

function closeAlerts() {
  alertsOpen = false;
  document.getElementById('alertsDrawer').classList.remove('open');
}

/* ══════════════════════════════════════════════════════════════
   KEYBOARD SHORTCUTS
   ══════════════════════════════════════════════════════════════ */
document.addEventListener('keydown', e => {
  if (e.target.tagName === 'INPUT') return;
  switch (e.key.toLowerCase()) {
    case 'f': fitAllDrivers(); break;
    case 'r': fetchDrivers(); showToast('Refreshed', 'Driver data updated', 'info'); break;
    case '1': switchTab('all'); break;
    case '2': switchTab('active'); break;
    case '3': switchTab('trip'); break;
    case '4': switchTab('inactive'); break;
    case 'a': openAlerts(); break;
    case 'escape': closeAlerts(); break;
  }
});

/* ══════════════════════════════════════════════════════════════
   MOBILE SIDEBAR TOGGLE
   ══════════════════════════════════════════════════════════════ */
let mobileMode = window.innerWidth <= 768;

function handleResize() {
  const wasMobile = mobileMode;
  mobileMode = window.innerWidth <= 768;
  const sidebar = document.getElementById('trackingSidebar');
  const toggle = document.getElementById('sidebarToggle');

  if (mobileMode && !wasMobile) {
    // Switching to mobile: convert sidebar to drawer
    sidebar.classList.add('mobile-drawer');
    sidebar.classList.remove('open');
    toggle.style.display = 'inline-flex';
  } else if (!mobileMode && wasMobile) {
    // Switching to desktop: restore sidebar
    sidebar.classList.remove('mobile-drawer', 'open');
    toggle.style.display = 'none';
  } else if (mobileMode) {
    sidebar.classList.add('mobile-drawer');
    toggle.style.display = 'inline-flex';
  } else {
    sidebar.classList.remove('mobile-drawer', 'open');
    toggle.style.display = 'none';
  }
}

function toggleSidebar() {
  const sidebar = document.getElementById('trackingSidebar');
  const toggle = document.getElementById('sidebarToggle');
  const open = sidebar.classList.toggle('open');
  toggle.classList.toggle('open', open);
  document.body.style.overflow = open && mobileMode ? 'hidden' : '';
  if (open) setTimeout(() => { map.invalidateSize(); }, 400);
}

function closeSidebar() {
  const sidebar = document.getElementById('trackingSidebar');
  const toggle = document.getElementById('sidebarToggle');
  sidebar.classList.remove('open');
  toggle.classList.remove('open');
  document.body.style.overflow = '';
}

// Swipe-down to close on mobile
let touchStartY = 0;
document.addEventListener('touchstart', e => {
  const sidebar = document.getElementById('trackingSidebar');
  if (!sidebar.classList.contains('open') || !mobileMode) return;
  const t = e.target.closest('.tracking-sidebar');
  if (!t) return;
  touchStartY = e.touches[0].clientY;
}, { passive: true });

document.addEventListener('touchmove', e => {
  const sidebar = document.getElementById('trackingSidebar');
  if (!sidebar.classList.contains('open') || !mobileMode || !touchStartY) return;
  const dy = e.touches[0].clientY - touchStartY;
  if (dy > 80) { closeSidebar(); touchStartY = 0; }
}, { passive: true });

/* ══════════════════════════════════════════════════════════════
   RIPPLE EFFECT
   ══════════════════════════════════════════════════════════════ */
document.addEventListener('click', e => {
  const btn = e.target.closest('button, .driver-card, .filter-tab, .map-ctrl-btn');
  if (!btn) return;
  const rect = btn.getBoundingClientRect();
  const ripple = document.createElement('span');
  ripple.className = 'ripple';
  const size = Math.max(rect.width, rect.height);
  ripple.style.width = ripple.style.height = size + 'px';
  ripple.style.left = (e.clientX - rect.left - size/2) + 'px';
  ripple.style.top = (e.clientY - rect.top - size/2) + 'px';
  btn.style.position = 'relative';
  btn.style.overflow = 'hidden';
  btn.appendChild(ripple);
  setTimeout(() => ripple.remove(), 600);
});

/* ══════════════════════════════════════════════════════════════
   INIT
   ══════════════════════════════════════════════════════════════ */
showSkeletons(6);
initMap();
initCharts();
fetchDrivers();
refreshInterval = setInterval(fetchDrivers, REFRESH_MS);
setTimeout(() => hideSkeletons(), 1200);

handleResize();
window.addEventListener('resize', handleResize);

window.addEventListener('beforeunload', () => {
  if (refreshInterval) clearInterval(refreshInterval);
});
</script>
@endSection
