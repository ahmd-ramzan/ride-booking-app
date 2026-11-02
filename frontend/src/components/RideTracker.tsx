import { useState } from 'react';
import { useRide, useRideAction } from '../hooks/rides';
import { formatDuration, isActiveStatus } from '../lib/format';
import { MapPlaceholder } from './MapPlaceholder';
import { RideFacts, RideRoute, RideStatusBadge } from './RideSummary';
import { Button, Card, ErrorAlert, Skeleton, Spinner } from './ui/primitives';

interface RideTrackerProps {
  rideId: string;
  onDone: () => void;
}

/** Live status of the rider's ride (polled), with cancellation while it is permitted. */
export function RideTracker({ rideId, onDone }: RideTrackerProps) {
  const ride = useRide(rideId);
  const action = useRideAction();
  const [confirmingCancel, setConfirmingCancel] = useState(false);

  if (ride.isPending) {
    return (
      <Card>
        <div className="space-y-3" role="status" aria-label="Loading ride">
          <Skeleton className="h-6 w-40" />
          <Skeleton className="h-16" />
          <Skeleton className="h-10" />
        </div>
      </Card>
    );
  }

  if (ride.isError) {
    return <ErrorAlert title="Couldn’t load your ride" error={ride.error} onRetry={() => void ride.refetch()} />;
  }

  const current = ride.data;
  const driver = current.driver;
  const canCancel = current.allowed_actions.includes('cancel');
  const active = isActiveStatus(current.status);

  return (
    <div className="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
      <Card className="space-y-5">
        <div className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-xl font-bold">Your ride</h1>
            <p className="text-xs text-slate-500">
              {current.category === 'comfort' ? 'Comfort' : 'Economy'} · #{current.id.slice(-6).toUpperCase()}
            </p>
          </div>
          <div aria-live="polite">
            <RideStatusBadge status={current.status} />
          </div>
        </div>

        {current.status === 'requested' && (
          <p className="flex items-center gap-2 text-sm text-slate-600">
            <Spinner className="size-4 text-brand-600" /> Looking for a nearby driver…
          </p>
        )}

        {driver && current.status !== 'cancelled' && (
          <div className="rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
            <p className="font-semibold">{driver.name}</p>
            <p className="text-sm text-slate-600">
              {driver.vehicle.model} · <span className="font-mono">{driver.vehicle.plate}</span>
            </p>
            {current.status === 'accepted' && driver.pickup_eta_seconds !== null && (
              <p className="mt-2 text-sm font-medium text-blue-700">Arriving in about {formatDuration(driver.pickup_eta_seconds)}</p>
            )}
          </div>
        )}

        <RideRoute ride={current} />
        <RideFacts ride={current} />

        {current.status === 'cancelled' && current.cancellation?.reason && (
          <p className="text-sm text-slate-600">Reason: {current.cancellation.reason}</p>
        )}

        {ride.isRefetchError && (
          <p role="status" className="text-xs text-amber-700">
            Live updates paused — reconnecting…
          </p>
        )}

        {action.error && <ErrorAlert title="Couldn’t cancel the ride" error={action.error} />}

        {canCancel && !confirmingCancel && (
          <Button variant="danger" className="w-full" onClick={() => setConfirmingCancel(true)}>
            Cancel ride
          </Button>
        )}

        {canCancel && confirmingCancel && (
          <div className="space-y-3 rounded-xl bg-red-50 p-4 ring-1 ring-red-200">
            <p className="text-sm font-medium text-red-900">Cancel this ride?</p>
            <div className="flex gap-3">
              <Button
                variant="danger"
                className="flex-1"
                loading={action.isPending}
                loadingText="Cancelling…"
                onClick={() =>
                  action.mutate(
                    { rideId: current.id, action: 'cancel', reason: 'Cancelled by rider' },
                    { onSettled: () => setConfirmingCancel(false) },
                  )
                }
              >
                Yes, cancel
              </Button>
              <Button variant="secondary" className="flex-1" disabled={action.isPending} onClick={() => setConfirmingCancel(false)}>
                Keep ride
              </Button>
            </div>
          </div>
        )}

        {!active && (
          <Button className="w-full" onClick={onDone}>
            Book another ride
          </Button>
        )}
      </Card>

      <div>
        <MapPlaceholder pickup={current.pickup} dropoff={current.dropoff} driver={active ? driver?.location : null} />
      </div>
    </div>
  );
}
