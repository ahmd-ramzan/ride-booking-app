import { http, HttpResponse } from 'msw';
import { setupServer } from 'msw/node';
import type { FareEstimate, Place, Ride, User } from '../api/types';

export const API = '*/api/v1';

export const riderUser: User = { id: 1, name: 'Ayesha Khan', email: 'rider@example.com', phone: null, role: 'rider' };

export const driverUser: User = {
  id: 2,
  name: 'Usman Tariq',
  email: 'driver@example.com',
  phone: null,
  role: 'driver',
  driver: {
    id: 7,
    name: 'Usman Tariq',
    phone: null,
    status: 'available',
    vehicle: { category: 'economy', model: 'Suzuki Cultus', plate: 'LEA-1234' },
    location: { latitude: 31.515, longitude: 74.34, updated_at: null },
  },
};

export const places: Place[] = [
  { name: 'Liberty Market, Gulberg III', latitude: 31.5102, longitude: 74.3441 },
  { name: 'Packages Mall, Walton Road', latitude: 31.4711, longitude: 74.3555 },
];

export const fareEstimate: FareEstimate = {
  route: { distance_meters: 5824, duration_seconds: 953 },
  options: [
    {
      category: 'economy',
      label: 'Economy',
      fare: { amount: 420, currency: 'PKR', breakdown: { base: 120, distance: 221, time: 79, minimum_fare_applied: false } },
      pickup_eta_seconds: 140,
      drivers_available: true,
    },
    {
      category: 'comfort',
      label: 'Comfort',
      fare: { amount: 676, currency: 'PKR', breakdown: { base: 200, distance: 349, time: 127, minimum_fare_applied: false } },
      pickup_eta_seconds: null,
      drivers_available: false,
    },
  ],
};

export function makeRide(overrides: Partial<Ride> = {}): Ride {
  return {
    id: '01jride0000000000000000001',
    status: 'requested',
    category: 'economy',
    pickup: { address: 'Liberty Market, Gulberg III', latitude: 31.5102, longitude: 74.3441 },
    dropoff: { address: 'Packages Mall, Walton Road', latitude: 31.4711, longitude: 74.3555 },
    route: { distance_meters: 5824, duration_seconds: 953 },
    fare: { estimated: 420, final: null, currency: 'PKR', breakdown: fareEstimate.options[0]!.fare.breakdown },
    driver: null,
    cancellation: null,
    allowed_actions: ['cancel'],
    requested_at: '2026-11-01T08:00:00+00:00',
    accepted_at: null,
    started_at: null,
    completed_at: null,
    cancelled_at: null,
    ...overrides,
  };
}

export function apiError(status: number, code: string, message: string, extra: Record<string, unknown> = {}) {
  return HttpResponse.json({ message, code, request_id: 'req-test-123', ...extra }, { status });
}

/** Default happy-path handlers; tests override individual endpoints with server.use(). */
export const handlers = [
  http.get(`${API}/places`, () => HttpResponse.json({ data: places })),
  http.get(`${API}/fare-estimates`, () => HttpResponse.json({ data: fareEstimate })),
  http.get(`${API}/rides`, () => HttpResponse.json({ data: [] })),
  http.get(`${API}/me`, () => HttpResponse.json({ data: driverUser })),
  http.get(`${API}/driver/ride-offers`, () => HttpResponse.json({ data: [] })),
];

export const server = setupServer(...handlers);
