'use client';

import { useEffect } from 'react';
import { MapContainer, Marker, Popup, TileLayer, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export interface MapPoint {
  id: string;
  lat: number;
  lng: number;
  title: string;
  subtitle?: string;
  kind: 'branch' | 'warehouse' | 'customer' | 'supplier';
  href?: string;
}

const COLORS: Record<MapPoint['kind'], string> = {
  branch: '#4f46e5',
  warehouse: '#0d9488',
  customer: '#d97706',
  supplier: '#0284c7',
};

// SVG pin icons (no external image assets; Leaflet's default icon paths break under bundlers).
function icon(kind: MapPoint['kind']) {
  const c = COLORS[kind];
  return L.divIcon({
    className: '',
    iconSize: [28, 36],
    iconAnchor: [14, 34],
    popupAnchor: [0, -30],
    html: `<svg width="28" height="36" viewBox="0 0 28 36" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M14 1C7 1 1.5 6.4 1.5 13.2 1.5 22.5 14 35 14 35s12.5-12.5 12.5-21.8C26.5 6.4 21 1 14 1z" fill="${c}" stroke="#fff" stroke-width="2"/><circle cx="14" cy="13" r="4.5" fill="#fff"/></svg>`,
  });
}

function FitBounds({ points }: { points: MapPoint[] }) {
  const map = useMap();
  useEffect(() => {
    if (points.length === 0) return;
    if (points.length === 1) {
      map.setView([points[0]!.lat, points[0]!.lng], 12);
      return;
    }
    map.fitBounds(L.latLngBounds(points.map((p) => [p.lat, p.lng] as [number, number])), { padding: [40, 40], maxZoom: 13 });
  }, [map, points]);
  return null;
}

/**
 * Leaflet + OpenStreetMap tiles. OSM's tile usage policy requires visible
 * attribution and forbids heavy/bulk use — for production traffic configure a
 * commercial tile provider via NEXT_PUBLIC_MAP_TILE_URL.
 */
export default function LocationsMap({ points, height = 520 }: { points: MapPoint[]; height?: number }) {
  const tileUrl = process.env.NEXT_PUBLIC_MAP_TILE_URL ?? 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
  const attribution =
    process.env.NEXT_PUBLIC_MAP_ATTRIBUTION ?? '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

  return (
    <MapContainer center={[20, 0]} zoom={2} style={{ height, width: '100%', borderRadius: 12 }} scrollWheelZoom>
      <TileLayer url={tileUrl} attribution={attribution} />
      <FitBounds points={points} />
      {points.map((p) => (
        <Marker key={p.id} position={[p.lat, p.lng]} icon={icon(p.kind)} title={p.title} alt={p.title}>
          <Popup>
            <strong>{p.title}</strong>
            {p.subtitle && <div style={{ fontSize: 12, opacity: 0.75 }}>{p.subtitle}</div>}
            {p.href && (
              <div style={{ marginTop: 6 }}>
                <a href={p.href}>Open →</a>
              </div>
            )}
          </Popup>
        </Marker>
      ))}
    </MapContainer>
  );
}

export const MAP_COLORS = COLORS;
