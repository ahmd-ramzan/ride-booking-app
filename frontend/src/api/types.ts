export type RideCategory = 'economy' | 'comfort';

export type RideStatus = 'requested' | 'accepted' | 'in_progress' | 'completed' | 'cancelled';

export type RideAction = 'accept' | 'start' | 'complete' | 'cancel';

export type DriverStatus = 'offline' | 'available' | 'on_trip';

export type UserRole = 'rider' | 'driver';

export interface LatLng {
  latitude: number;
  longitude: number;
}

export interface Place extends LatLng {
  name: string;
}

export interface Driver {
  id: number;
  name: string;
  phone: string | null;
  status: DriverStatus;
  vehicle: { category: RideCategory; model: string; plate: string };
  location: (LatLng & { updated_at: string | null }) | null;
}

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  driver?: Driver | null;
}

export interface FareBreakdown {
  base: number;
  distance: number;
  time: number;
  minimum_fare_applied: boolean;
}

export interface Ride {
  id: string;
  status: RideStatus;
  category: RideCategory;
  pickup: LatLng & { address: string };
  dropoff: LatLng & { address: string };
  route: { distance_meters: number; duration_seconds: number };
  fare: { estimated: number; final: number | null; currency: string; breakdown: FareBreakdown };
  driver: (Driver & { pickup_eta_seconds: number | null }) | null;
  cancellation: { cancelled_by: string | null; reason: string | null } | null;
  distance_to_pickup_meters?: number;
  allowed_actions: RideAction[];
  requested_at: string;
  accepted_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  cancelled_at: string | null;
}

export interface RideOption {
  category: RideCategory;
  label: string;
  fare: { amount: number; currency: string; breakdown: FareBreakdown };
  pickup_eta_seconds: number | null;
  drivers_available: boolean;
}

export interface FareEstimate {
  route: { distance_meters: number; duration_seconds: number };
  options: RideOption[];
}

export interface NewRide {
  category: RideCategory;
  pickup: LatLng & { address: string };
  dropoff: LatLng & { address: string };
}

export interface AuthSession {
  token: string;
  token_type: 'Bearer';
  expires_at: string | null;
  user: User;
}

/** The error envelope every API error uses. */
export interface ApiErrorBody {
  message: string;
  code: string;
  request_id?: string | null;
  errors?: Record<string, string[]>;
  details?: Record<string, unknown>;
}
