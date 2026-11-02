import { useState } from 'react';
import { BookingForm } from '../components/BookingForm';
import { RideTracker } from '../components/RideTracker';
import { Card, ErrorAlert, Skeleton } from '../components/ui/primitives';
import { useActiveRides } from '../hooks/rides';

export function RiderPage() {
  const activeRides = useActiveRides();
  // The ride on screen stays pinned after it finishes (so the rider sees "completed"/"cancelled")
  // until they choose to book another.
  const [trackedRideId, setTrackedRideId] = useState<string | null>(null);
  const [dismissedRideId, setDismissedRideId] = useState<string | null>(null);

  const listedRideId = activeRides.data?.[0]?.id ?? null;
  const activeRideId = listedRideId !== dismissedRideId ? listedRideId : null;
  if (trackedRideId === null && activeRideId !== null) {
    setTrackedRideId(activeRideId);
  }

  if (activeRides.isPending) {
    return (
      <Card>
        <div className="space-y-3" role="status" aria-label="Loading">
          <Skeleton className="h-6 w-48" />
          <Skeleton className="h-12" />
          <Skeleton className="h-12" />
        </div>
      </Card>
    );
  }

  if (activeRides.isError) {
    return <ErrorAlert title="Couldn’t load your rides" error={activeRides.error} onRetry={() => void activeRides.refetch()} />;
  }

  const rideId = trackedRideId ?? activeRideId;

  if (rideId) {
    return (
      <RideTracker
        key={rideId}
        rideId={rideId}
        onDone={() => {
          setDismissedRideId(rideId);
          setTrackedRideId(null);
          void activeRides.refetch();
        }}
      />
    );
  }

  return <BookingForm onRequested={(ride) => setTrackedRideId(ride.id)} onActiveRideExists={() => void activeRides.refetch()} />;
}
