import { apiRequest } from './client';
import type { AuthSession, Driver, FareEstimate, LatLng, NewRide, Place, Ride, RideAction, User } from './types';

interface Data<T> {
  data: T;
}

export const api = {
  login: (email: string, password: string) =>
    apiRequest<AuthSession>('/auth/tokens', { method: 'POST', body: { email, password, device_name: 'web' } }),

  logout: () => apiRequest<void>('/auth/tokens/current', { method: 'DELETE' }),

  me: async () => (await apiRequest<Data<User>>('/me')).data,

  places: async () => (await apiRequest<Data<Place[]>>('/places')).data,

  fareEstimate: async (pickup: LatLng, dropoff: LatLng, signal?: AbortSignal) =>
    (
      await apiRequest<Data<FareEstimate>>('/fare-estimates', {
        query: {
          pickup_latitude: pickup.latitude,
          pickup_longitude: pickup.longitude,
          dropoff_latitude: dropoff.latitude,
          dropoff_longitude: dropoff.longitude,
        },
        signal,
      })
    ).data,

  activeRides: async () => (await apiRequest<Data<Ride[]>>('/rides', { query: { status: 'active' } })).data,

  ride: async (id: string, signal?: AbortSignal) => (await apiRequest<Data<Ride>>(`/rides/${encodeURIComponent(id)}`, { signal })).data,

  requestRide: async (ride: NewRide, idempotencyKey: string) =>
    (await apiRequest<Data<Ride>>('/rides', { method: 'POST', body: ride, idempotencyKey })).data,

  rideAction: async (id: string, action: RideAction, idempotencyKey: string, body?: { reason?: string }) =>
    (
      await apiRequest<Data<Ride>>(`/rides/${encodeURIComponent(id)}/${action}`, {
        method: 'POST',
        body: body ?? {},
        idempotencyKey,
      })
    ).data,

  updateAvailability: async (status: 'available' | 'offline', location?: LatLng) =>
    (await apiRequest<Data<Driver>>('/driver/availability', { method: 'PUT', body: { status, ...location } })).data,

  rideOffers: async () => (await apiRequest<Data<Ride[]>>('/driver/ride-offers')).data,
};
