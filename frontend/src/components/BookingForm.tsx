import { useState, type FormEvent } from 'react';
import type { Place, Ride, RideCategory } from '../api/types';
import { useFareEstimate, usePlaces, useRequestRide } from '../hooks/rides';
import { MapPlaceholder } from './MapPlaceholder';
import { PlaceSelect } from './PlaceSelect';
import { RideOptions } from './RideOptions';
import { Button, Card, ErrorAlert, Skeleton } from './ui/primitives';

interface BookingFormProps {
  onRequested: (ride: Ride) => void;
  onActiveRideExists: () => void;
}

export function BookingForm({ onRequested, onActiveRideExists }: BookingFormProps) {
  const places = usePlaces();
  const [pickup, setPickup] = useState<Place | null>(null);
  const [dropoff, setDropoff] = useState<Place | null>(null);
  const [category, setCategory] = useState<RideCategory>('economy');

  const samePlace = pickup !== null && dropoff !== null && pickup.name === dropoff.name;
  const hasRoute = pickup !== null && dropoff !== null && !samePlace;
  const estimate = useFareEstimate(hasRoute ? pickup : null, hasRoute ? dropoff : null);
  const requestRide = useRequestRide();

  const selectedOption = estimate.data?.options.find((option) => option.category === category);
  const canRequest = hasRoute && selectedOption !== undefined && !estimate.isFetching;

  function submit(event: FormEvent) {
    event.preventDefault();
    requestSelectedRide();
  }

  function requestSelectedRide() {
    if (!canRequest || pickup === null || dropoff === null) return;

    requestRide.mutate(
      {
        category,
        pickup: { address: pickup.name, latitude: pickup.latitude, longitude: pickup.longitude },
        dropoff: { address: dropoff.name, latitude: dropoff.latitude, longitude: dropoff.longitude },
      },
      { onSuccess: onRequested },
    );
  }

  function swap() {
    setPickup(dropoff);
    setDropoff(pickup);
  }

  const requestError = requestRide.error;

  return (
    <div className="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
      <Card className="order-2 md:order-1">
        <form onSubmit={submit} className="space-y-5" noValidate>
          <h1 className="text-xl font-bold">Book a ride</h1>

          {places.isPending ? (
            <div className="space-y-3" role="status" aria-label="Loading places">
              <Skeleton className="h-16" />
              <Skeleton className="h-16" />
            </div>
          ) : places.isError ? (
            <ErrorAlert title="Couldn’t load places" error={places.error} onRetry={() => void places.refetch()} />
          ) : (
            <div className="space-y-3">
              <PlaceSelect id="pickup" label="Pickup" places={places.data} value={pickup} onChange={setPickup} disabled={requestRide.isPending} />
              <div className="-my-2 flex justify-end">
                <Button
                  variant="ghost"
                  onClick={swap}
                  disabled={requestRide.isPending || (pickup === null && dropoff === null)}
                  className="min-h-8 px-2 py-1 text-xs"
                >
                  <span aria-hidden="true">⇅</span> Swap locations
                </Button>
              </div>
              <PlaceSelect
                id="dropoff"
                label="Destination"
                places={places.data}
                value={dropoff}
                onChange={setDropoff}
                disabled={requestRide.isPending}
                error={samePlace ? 'Pickup and destination must be different.' : requestError?.firstFieldError('dropoff')}
              />
            </div>
          )}

          <RideOptions
            estimate={estimate.data}
            isLoading={estimate.isPending}
            error={estimate.error}
            onRetry={() => void estimate.refetch()}
            hasRoute={hasRoute}
            selected={category}
            onSelect={setCategory}
            disabled={requestRide.isPending}
          />

          {requestError && (
            <ErrorAlert
              title="Ride not requested"
              error={requestError}
              onRetry={
                requestError.code === 'active_ride_exists'
                  ? onActiveRideExists
                  : requestError.isRetryable
                    ? requestSelectedRide
                    : undefined
              }
            />
          )}

          <Button type="submit" className="w-full" disabled={!canRequest} loading={requestRide.isPending} loadingText="Requesting ride…">
            {selectedOption ? `Request ${selectedOption.label}` : 'Request ride'}
          </Button>
        </form>
      </Card>

      <div className="order-1 md:order-2">
        <MapPlaceholder pickup={pickup} dropoff={dropoff} />
      </div>
    </div>
  );
}
