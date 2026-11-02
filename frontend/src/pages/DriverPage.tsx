import { useState } from 'react';
import type { Place, Ride, RideAction } from '../api/types';
import { MapPlaceholder } from '../components/MapPlaceholder';
import { PlaceSelect } from '../components/PlaceSelect';
import { RideFacts, RideRoute, RideStatusBadge } from '../components/RideSummary';
import { Button, Card, EmptyState, ErrorAlert, Skeleton } from '../components/ui/primitives';
import { useActiveRides, useMe, usePlaces, useRideAction, useRideOffers, useUpdateAvailability } from '../hooks/rides';
import { formatDistance, formatMoney } from '../lib/format';

const ACTION_LABELS: Partial<Record<RideAction, { label: string; pending: string }>> = {
  accept: { label: 'Accept ride', pending: 'Accepting…' },
  start: { label: 'Start trip (rider picked up)', pending: 'Starting…' },
  complete: { label: 'Complete trip', pending: 'Completing…' },
};

/**
 * Driver console used to exercise the dispatch side of the system:
 * availability, the offer feed, and the ride lifecycle.
 */
export function DriverPage() {
  const me = useMe();
  const activeRides = useActiveRides({ poll: true });
  const driver = me.data?.driver ?? null;
  const currentRide = activeRides.data?.[0] ?? null;
  const offers = useRideOffers(driver?.status === 'available' && currentRide === null);

  if (me.isPending || activeRides.isPending) {
    return (
      <Card>
        <div className="space-y-3" role="status" aria-label="Loading">
          <Skeleton className="h-6 w-48" />
          <Skeleton className="h-24" />
        </div>
      </Card>
    );
  }

  if (me.isError || activeRides.isError) {
    return (
      <ErrorAlert
        title="Couldn’t load your driver profile"
        error={me.error ?? activeRides.error}
        onRetry={() => void Promise.all([me.refetch(), activeRides.refetch()])}
      />
    );
  }

  if (!driver) {
    return <EmptyState title="No driver profile">This account is not registered as a driver.</EmptyState>;
  }

  return (
    <div className="space-y-5">
      <AvailabilityCard status={driver.status} vehicle={`${driver.vehicle.model} · ${driver.vehicle.plate}`} category={driver.vehicle.category} />

      {currentRide ? (
        <DriverRideCard ride={currentRide} title="Current ride" />
      ) : driver.status !== 'available' ? (
        <EmptyState title="You are offline">Go online to start receiving ride requests.</EmptyState>
      ) : (
        <section aria-labelledby="offers-heading" className="space-y-3">
          <h2 id="offers-heading" className="text-lg font-bold">
            Ride requests near you
          </h2>
          {offers.isPending ? (
            <Skeleton className="h-32" />
          ) : offers.isError ? (
            <ErrorAlert title="Couldn’t load ride requests" error={offers.error} onRetry={() => void offers.refetch()} />
          ) : offers.data.length === 0 ? (
            <EmptyState title="No ride requests yet">New requests appear here automatically.</EmptyState>
          ) : (
            offers.data.map((ride) => <DriverRideCard key={ride.id} ride={ride} />)
          )}
        </section>
      )}
    </div>
  );
}

function AvailabilityCard({ status, vehicle, category }: { status: string; vehicle: string; category: string }) {
  const places = usePlaces();
  const updateAvailability = useUpdateAvailability();
  const [location, setLocation] = useState<Place | null>(null);
  const online = status === 'available';
  const onTrip = status === 'on_trip';

  return (
    <Card className="space-y-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h1 className="text-xl font-bold">Driver console</h1>
          <p className="text-sm text-slate-600">
            {vehicle} · <span className="capitalize">{category}</span>
          </p>
        </div>
        <span
          className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
            online ? 'bg-emerald-100 text-emerald-800' : onTrip ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-200 text-slate-700'
          }`}
        >
          {online ? 'Online' : onTrip ? 'On a trip' : 'Offline'}
        </span>
      </div>

      {!onTrip && !online && (
        <PlaceSelect
          id="driver-location"
          label="Your current location"
          places={places.data ?? []}
          value={location}
          onChange={setLocation}
          disabled={places.isPending || updateAvailability.isPending}
          error={updateAvailability.error?.firstFieldError('latitude')}
        />
      )}

      {updateAvailability.error && !updateAvailability.error.firstFieldError('latitude') && (
        <ErrorAlert title="Couldn’t update availability" error={updateAvailability.error} />
      )}

      {!onTrip &&
        (online ? (
          <Button variant="secondary" className="w-full" loading={updateAvailability.isPending} onClick={() => updateAvailability.mutate({ status: 'offline' })}>
            Go offline
          </Button>
        ) : (
          <Button
            className="w-full"
            disabled={location === null}
            loading={updateAvailability.isPending}
            loadingText="Going online…"
            onClick={() => location && updateAvailability.mutate({ status: 'available', location })}
          >
            Go online
          </Button>
        ))}
    </Card>
  );
}

function DriverRideCard({ ride, title }: { ride: Ride; title?: string }) {
  const action = useRideAction();
  const nextAction = ride.allowed_actions.find((candidate) => candidate in ACTION_LABELS);
  const labels = nextAction ? ACTION_LABELS[nextAction] : undefined;

  return (
    <Card className="space-y-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          {title && <h2 className="text-lg font-bold">{title}</h2>}
          <p className="text-sm font-semibold">{formatMoney(ride.fare.final ?? ride.fare.estimated, ride.fare.currency)}</p>
          {ride.distance_to_pickup_meters !== undefined && (
            <p className="text-xs text-slate-500">{formatDistance(ride.distance_to_pickup_meters)} to pickup</p>
          )}
        </div>
        <RideStatusBadge status={ride.status} />
      </div>

      <RideRoute ride={ride} />
      {title && (
        <>
          <RideFacts ride={ride} />
          <MapPlaceholder pickup={ride.pickup} dropoff={ride.dropoff} />
        </>
      )}

      {action.error && <ErrorAlert title="Action failed" error={action.error} />}

      {nextAction && labels && (
        <Button
          className="w-full"
          loading={action.isPending}
          loadingText={labels.pending}
          onClick={() => action.mutate({ rideId: ride.id, action: nextAction })}
        >
          {labels.label}
        </Button>
      )}
    </Card>
  );
}
