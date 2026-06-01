import { useEffect } from 'react'
import { MapContainer, TileLayer, Marker, Popup, Polyline } from 'react-leaflet'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useTranslation } from 'react-i18next'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
})

const stopIcons = {
  pickup: L.divIcon({
    className: 'custom-marker',
    html: '<div style="background:#22c55e;color:white;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3)">P</div>',
    iconSize: [28, 28],
    iconAnchor: [14, 14],
  }),
  delivery: L.divIcon({
    className: 'custom-marker',
    html: '<div style="background:#3b82f6;color:white;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3)">D</div>',
    iconSize: [28, 28],
    iconAnchor: [14, 14],
  }),
  warehouse: L.divIcon({
    className: 'custom-marker',
    html: '<div style="background:#f59e0b;color:white;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;border:3px solid white;box-shadow:0 2px 6px rgba(0,0,0,0.3)">W</div>',
    iconSize: [28, 28],
    iconAnchor: [14, 14],
  }),
}

export default function RouteMap({ stops = [], center = [30.0444, 31.2357], zoom = 12, height = '400px' }) {
  const { t } = useTranslation()
  const validStops = stops.filter(s => s.latitude && s.longitude)

  const positions = validStops.map(s => [s.latitude, s.longitude])

  useEffect(() => {
    // Fix leaflet default icon issue
  }, [])

  if (validStops.length === 0) {
    return (
      <div className="flex items-center justify-center bg-gray-100 rounded-lg" style={{ height }}>
        <p className="text-gray-500">{t('routeMap:noStops')}</p>
      </div>
    )
  }

  return (
    <div className="rounded-lg overflow-hidden border border-gray-200" style={{ height }}>
      <MapContainer center={center} zoom={zoom} style={{ height: '100%', width: '100%' }}>
        <TileLayer
          attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
          url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
        />
        <Polyline positions={positions} color="#3b82f6" weight={3} opacity={0.7} />
        {validStops.map((stop, idx) => (
          <Marker
            key={stop.id || idx}
            position={[stop.latitude, stop.longitude]}
            icon={stopIcons[stop.type] || stopIcons.delivery}
          >
            <Popup>
              <div className="text-sm">
                <p className="font-semibold">{stop.name || t('routeMap:stopNumber', { number: idx + 1 })}</p>
                <p className="text-gray-600">{stop.address}</p>
                <p className="text-xs text-gray-500 mt-1">
                  {stop.type === 'pickup' ? t('routeMap:pickup') : stop.type === 'warehouse' ? t('routeMap:warehouse') : t('routeMap:delivery')}
                  {stop.arrival_time ? ` | ${t('routeMap:eta')}: ${stop.arrival_time}` : ''}
                </p>
              </div>
            </Popup>
          </Marker>
        ))}
      </MapContainer>
    </div>
  )
}
