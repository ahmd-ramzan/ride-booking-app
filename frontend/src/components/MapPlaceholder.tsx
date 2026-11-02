import type { LatLng } from '../api/types';

/** Lahore service-area bounds (mirrors config/rides.php), shown when nothing is selected. */
const SERVICE_AREA = { minLat: 31.2, maxLat: 31.75, minLng: 74.05, maxLng: 74.6 };
const WIDTH = 400;
const HEIGHT = 240;
/** Smallest span (degrees) the view zooms to, and the margin kept around the points. */
const MIN_SPAN = 0.03;
const PADDING = 0.25;

type Bounds = typeof SERVICE_AREA;

/** Fits the view around the given points, keeping the SVG aspect ratio. */
function fitBounds(points: LatLng[]): Bounds {
  if (points.length === 0) return SERVICE_AREA;

  const lats = points.map((point) => point.latitude);
  const lngs = points.map((point) => point.longitude);
  const centerLat = (Math.min(...lats) + Math.max(...lats)) / 2;
  const centerLng = (Math.min(...lngs) + Math.max(...lngs)) / 2;

  let lngSpan = Math.max(MIN_SPAN, Math.max(...lngs) - Math.min(...lngs)) * (1 + 2 * PADDING);
  let latSpan = Math.max(MIN_SPAN, Math.max(...lats) - Math.min(...lats)) * (1 + 2 * PADDING);
  const aspect = WIDTH / HEIGHT;
  if (lngSpan / latSpan < aspect) lngSpan = latSpan * aspect;
  else latSpan = lngSpan / aspect;

  return {
    minLat: centerLat - latSpan / 2,
    maxLat: centerLat + latSpan / 2,
    minLng: centerLng - lngSpan / 2,
    maxLng: centerLng + lngSpan / 2,
  };
}

function projector(bounds: Bounds) {
  return (point: LatLng) => ({
    x: ((point.longitude - bounds.minLng) / (bounds.maxLng - bounds.minLng)) * WIDTH,
    y: HEIGHT - ((point.latitude - bounds.minLat) / (bounds.maxLat - bounds.minLat)) * HEIGHT,
  });
}

interface MapPlaceholderProps {
  pickup?: LatLng | null;
  dropoff?: LatLng | null;
  driver?: LatLng | null;
}

/**
 * A schematic stand-in for a real map: plots pickup, destination and driver,
 * zoomed to fit the trip. Swap for Mapbox/Google Maps in production.
 */
export function MapPlaceholder({ pickup, dropoff, driver }: MapPlaceholderProps) {
  const points = [pickup, dropoff, driver].filter((point): point is LatLng => point != null);
  const project = projector(fitBounds(points));

  const a = pickup ? project(pickup) : null;
  const b = dropoff ? project(dropoff) : null;
  const d = driver ? project(driver) : null;

  return (
    <figure className="overflow-hidden rounded-2xl bg-emerald-50 ring-1 ring-slate-200">
      <svg viewBox={`0 0 ${WIDTH} ${HEIGHT}`} className="block h-auto max-h-64 w-full md:max-h-none" role="img" aria-label="Schematic map of the trip">
        <defs>
          <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse">
            <path d="M 20 0 L 0 0 0 20" fill="none" stroke="#a7f3d0" strokeWidth="0.6" />
          </pattern>
        </defs>
        <rect width={WIDTH} height={HEIGHT} fill="url(#grid)" />
        {a && b && <line x1={a.x} y1={a.y} x2={b.x} y2={b.y} stroke="#0f172a" strokeWidth="2.5" strokeDasharray="6 5" />}
        {d && a && <line x1={d.x} y1={d.y} x2={a.x} y2={a.y} stroke="#2563eb" strokeWidth="2" strokeDasharray="2 4" />}
        {d && <circle cx={d.x} cy={d.y} r="12" fill="#2563eb" fillOpacity="0.25" stroke="#2563eb" strokeWidth="2.5" />}
        {a && <circle cx={a.x} cy={a.y} r="8" fill="#059669" stroke="white" strokeWidth="2.5" />}
        {b && <rect x={b.x - 7} y={b.y - 7} width="14" height="14" fill="#0f172a" stroke="white" strokeWidth="2.5" />}
        {points.length === 0 && (
          <text x={WIDTH / 2} y={HEIGHT / 2} textAnchor="middle" className="fill-slate-500 text-[13px]">
            Lahore
          </text>
        )}
      </svg>
      <figcaption className="flex flex-wrap gap-x-4 gap-y-1 px-4 py-2 text-xs text-slate-600">
        <span className="inline-flex items-center gap-1.5"><span className="size-2.5 rounded-full bg-emerald-600" />Pickup</span>
        <span className="inline-flex items-center gap-1.5"><span className="size-2.5 bg-slate-900" />Destination</span>
        {driver && <span className="inline-flex items-center gap-1.5"><span className="size-2.5 rounded-full bg-blue-600" />Driver</span>}
        <span className="ml-auto">Map preview (schematic)</span>
      </figcaption>
    </figure>
  );
}
