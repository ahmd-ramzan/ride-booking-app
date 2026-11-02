import type { Ride } from '../api/types';
import { formatDistance, formatDuration, formatMoney, RIDE_STATUS_LABELS } from '../lib/format';

const STATUS_STYLES: Record<Ride['status'], string> = {
  requested: 'bg-amber-100 text-amber-800',
  accepted: 'bg-blue-100 text-blue-800',
  in_progress: 'bg-indigo-100 text-indigo-800',
  completed: 'bg-emerald-100 text-emerald-800',
  cancelled: 'bg-slate-200 text-slate-700',
};

export function RideStatusBadge({ status }: { status: Ride['status'] }) {
  return (
    <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ${STATUS_STYLES[status]}`}>
      {RIDE_STATUS_LABELS[status]}
    </span>
  );
}

export function RideRoute({ ride }: { ride: Pick<Ride, 'pickup' | 'dropoff'> }) {
  return (
    <ol className="space-y-2 text-sm">
      <li className="flex gap-3">
        <span className="mt-1.5 size-2.5 shrink-0 rounded-full bg-emerald-600" aria-hidden="true" />
        <span>
          <span className="sr-only">Pickup: </span>
          {ride.pickup.address}
        </span>
      </li>
      <li className="flex gap-3">
        <span className="mt-1.5 size-2.5 shrink-0 bg-slate-900" aria-hidden="true" />
        <span>
          <span className="sr-only">Destination: </span>
          {ride.dropoff.address}
        </span>
      </li>
    </ol>
  );
}

export function RideFacts({ ride }: { ride: Ride }) {
  const fare = ride.fare.final ?? ride.fare.estimated;

  return (
    <dl className="grid grid-cols-3 gap-3 text-sm">
      <div>
        <dt className="text-slate-500">{ride.fare.final !== null ? 'Fare' : 'Est. fare'}</dt>
        <dd className="font-semibold">{formatMoney(fare, ride.fare.currency)}</dd>
      </div>
      <div>
        <dt className="text-slate-500">Distance</dt>
        <dd className="font-semibold">{formatDistance(ride.route.distance_meters)}</dd>
      </div>
      <div>
        <dt className="text-slate-500">Trip time</dt>
        <dd className="font-semibold">~{formatDuration(ride.route.duration_seconds)}</dd>
      </div>
    </dl>
  );
}
