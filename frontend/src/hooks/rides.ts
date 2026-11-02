import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { ApiError } from '../api/client';
import { api } from '../api/endpoints';
import type { LatLng, NewRide, RideAction } from '../api/types';
import { isActiveStatus } from '../lib/format';
import { useIdempotentMutation } from './useIdempotentMutation';

/** How often an in-flight ride is refreshed. A push channel would replace this in production. */
export const RIDE_POLL_INTERVAL_MS = 3_000;

export const rideKeys = {
  all: ['rides'] as const,
  active: ['rides', 'active'] as const,
  detail: (id: string) => ['rides', 'detail', id] as const,
  offers: ['rides', 'offers'] as const,
};

export function usePlaces() {
  return useQuery({ queryKey: ['places'], queryFn: api.places, staleTime: Infinity });
}

export function useFareEstimate(pickup: LatLng | null, dropoff: LatLng | null) {
  return useQuery({
    queryKey: ['fare-estimate', pickup?.latitude, pickup?.longitude, dropoff?.latitude, dropoff?.longitude],
    queryFn: ({ signal }) => api.fareEstimate(pickup as LatLng, dropoff as LatLng, signal),
    enabled: pickup !== null && dropoff !== null,
    placeholderData: keepPreviousData,
    staleTime: 30_000,
  });
}

/** The current user's unfinished rides (riders: requested; drivers: assigned). */
export function useActiveRides(options: { poll?: boolean } = {}) {
  return useQuery({
    queryKey: rideKeys.active,
    queryFn: api.activeRides,
    refetchInterval: options.poll ? RIDE_POLL_INTERVAL_MS : false,
  });
}

export function useRide(id: string) {
  return useQuery({
    queryKey: rideKeys.detail(id),
    queryFn: ({ signal }) => api.ride(id, signal),
    refetchInterval: (query) => {
      const status = query.state.data?.status;
      return status === undefined || isActiveStatus(status) ? RIDE_POLL_INTERVAL_MS : false;
    },
  });
}

export function useRequestRide() {
  const queryClient = useQueryClient();

  return useIdempotentMutation((ride: NewRide, key) => api.requestRide(ride, key), {
    onSuccess: (ride) => {
      queryClient.setQueryData(rideKeys.detail(ride.id), ride);
      return queryClient.invalidateQueries({ queryKey: rideKeys.all });
    },
  });
}

interface RideActionVariables {
  rideId: string;
  action: RideAction;
  reason?: string;
}

export function useRideAction() {
  const queryClient = useQueryClient();

  return useIdempotentMutation(
    ({ rideId, action, reason }: RideActionVariables, key) =>
      api.rideAction(rideId, action, key, reason ? { reason } : undefined),
    {
      onSuccess: (ride) => {
        queryClient.setQueryData(rideKeys.detail(ride.id), ride);
        return queryClient.invalidateQueries({ queryKey: rideKeys.all });
      },
      onError: (error) => {
        // A 409 means our view of the ride is stale (e.g. another driver won): refresh everything ride-related.
        if (error.status === 409) {
          return queryClient.invalidateQueries({ queryKey: rideKeys.all });
        }
      },
    },
  );
}

export function useRideOffers(enabled: boolean) {
  return useQuery({
    queryKey: rideKeys.offers,
    queryFn: api.rideOffers,
    enabled,
    refetchInterval: enabled ? RIDE_POLL_INTERVAL_MS : false,
  });
}

export function useMe() {
  return useQuery({ queryKey: ['me'], queryFn: api.me });
}

export function useUpdateAvailability() {
  const queryClient = useQueryClient();

  return useMutation<unknown, ApiError, { status: 'available' | 'offline'; location?: LatLng }>({
    mutationFn: ({ status, location }) => api.updateAvailability(status, location),
    onSettled: () =>
      Promise.all([
        queryClient.invalidateQueries({ queryKey: ['me'] }),
        queryClient.invalidateQueries({ queryKey: rideKeys.offers }),
      ]),
  });
}
