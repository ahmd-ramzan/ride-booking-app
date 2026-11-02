import type { RideStatus } from '../api/types';

export function formatMoney(amount: number, currency = 'PKR'): string {
  return `${currency === 'PKR' ? 'Rs' : currency} ${amount.toLocaleString('en-PK')}`;
}

export function formatDuration(seconds: number): string {
  const minutes = Math.max(1, Math.round(seconds / 60));
  if (minutes < 60) return `${minutes} min`;

  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  return rest === 0 ? `${hours} h` : `${hours} h ${rest} min`;
}

export function formatDistance(meters: number): string {
  return meters < 1000 ? `${Math.round(meters)} m` : `${(meters / 1000).toFixed(1)} km`;
}

export const RIDE_STATUS_LABELS: Record<RideStatus, string> = {
  requested: 'Finding your driver',
  accepted: 'Driver on the way',
  in_progress: 'On trip',
  completed: 'Trip completed',
  cancelled: 'Ride cancelled',
};

export function isActiveStatus(status: RideStatus): boolean {
  return status === 'requested' || status === 'accepted' || status === 'in_progress';
}
