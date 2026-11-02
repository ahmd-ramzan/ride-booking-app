# RideLahore: ride booking for Lahore

A simplified ride-hailing system. A rider picks a pickup and a destination in Lahore, compares **Economy** and
**Comfort** fares and pickup ETAs, requests a ride and follows it live. Drivers go online, see nearby requests,
accept them, and start and complete trips.

* **Backend**: Laravel 13 (PHP 8.4) REST API, PostgreSQL, Sanctum bearer tokens, OpenAPI 3.1 docs
* **Frontend**: React 19 + TypeScript SPA (Vite, TanStack Query, Tailwind CSS) in [`frontend/`](frontend)
* **Tests**: Pest (feature, unit, multi-process concurrency), Vitest + Testing Library + MSW, Playwright end-to-end

Design notes, diagrams and answers to the architecture questions: **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)**.

---

## Local setup

Requirements: PHP 8.4 with `pdo_pgsql`, Composer 2, Node 22, PostgreSQL 15+.
[Laravel Herd](https://herd.laravel.com) (Pro, for its PostgreSQL service) provides all of these; plain
`php artisan serve` works as well.

```bash
# 1. Backend
cp .env.example .env                     # set DB_* for your PostgreSQL
composer install
php artisan key:generate
php artisan migrate --seed               # schema + demo accounts

# Herd: link the folder (it is served at https://<folder>.test). Without Herd:
php artisan serve                        # http://localhost:8000

# 2. Frontend (second terminal)
cd frontend
cp .env.example .env.local               # set VITE_API_PROXY_TARGET to the API origin
npm install
npm run dev                              # http://localhost:5173
```

`VITE_API_PROXY_TARGET` is `https://ride-booking-app.test` with Herd, or `http://localhost:8000` with
`artisan serve`. The Vite dev server proxies `/api` to it, so the SPA and the API share an origin and need no CORS setup.

| What | URL (Herd) |
| --- | --- |
| Web app (SPA) | http://localhost:5173 |
| API docs (Swagger UI) | https://ride-booking-app.test/docs/index.html |
| API base | https://ride-booking-app.test/api/v1 |

To reset the demo data: `php artisan migrate:fresh --seed`.

### Demo accounts (password: `password`)

| Email | Role |
| --- | --- |
| `rider@example.com`, `rider2@example.com` | Riders |
| `driver@example.com`, `driver2@example.com` | Economy drivers (online, Gulberg) |
| `driver3@example.com`, `driver4@example.com` | Comfort drivers (online) |
| `driver5@example.com` | Economy driver (offline) |

To see the whole flow, open the app in two browser windows (one normal, one private): sign in as the rider in one
and as `driver@example.com` in the other.


## Running the tests

```bash
# Backend: needs a PostgreSQL database named ride_booking_app_testing (see phpunit.xml)
createdb ride_booking_app_testing
php artisan test                         # unit + feature + concurrency
php artisan test --testsuite=Concurrency # only the multi-process race tests
vendor/bin/pint --test                   # code style
vendor/bin/phpstan analyse               # static analysis (Larastan, level 7)

# Frontend
cd frontend
npm test                                 # Vitest component/integration tests
npm run typecheck
npm run build

# End-to-end (real browser + real API + real database)
# Prerequisites: API running and seeded, VITE_API_PROXY_TARGET set in frontend/.env.local
npx playwright install chromium
npm run test:e2e                         # desktop + mobile viewports
```

What is covered:

| Requirement | Where |
| --- | --- |
| Successful ride creation | `tests/Feature/Api/V1/RequestRideTest.php` |
| Invalid input | `RequestRideTest` (dataset), `FareEstimateTest`, `DriverTest`, `AuthTokenTest` |
| Driver availability & assignment | `AcceptRideTest`, `Driver/DriverTest`, `FareEstimateTest` |
| Concurrent driver acceptance | `tests/Concurrency/ConcurrentRideOperationsTest.php` (6 OS processes race on one ride) |
| Invalid state transitions | `RideLifecycleTest` (dataset), `tests/Unit/Enums/RideStatusTest.php` |
| Duplicate requests / retries | `IdempotencyTest`, concurrent duplicate ride requests, frontend retry-reuses-key test |
| DB-level invariants | `tests/Feature/Database/RideConstraintsTest.php` |
| Frontend loading/error/empty states | `frontend/src/pages/*.test.tsx` |
| End-to-end | `frontend/e2e/ride-flow.spec.ts` (rider + driver in two browsers) |

The concurrency tests start several PHP processes (`tests/Concurrency/worker.php`) that wait for a shared start
time and then hit PostgreSQL at the same instant, so they exercise real row locking rather than simulating it.

## API

Full reference: [`public/docs/openapi.yaml`](public/docs/openapi.yaml), rendered at `/docs/index.html`.

| Method | Endpoint | Who | Purpose |
| --- | --- | --- | --- |
| POST | `/api/v1/auth/tokens` | anyone | Log in, get a bearer token |
| DELETE | `/api/v1/auth/tokens/current` | user | Log out |
| GET | `/api/v1/me` | user | Current user (+ driver profile) |
| GET | `/api/v1/places` | user | Known Lahore places (geocoding stand-in) |
| GET | `/api/v1/fare-estimates` | user | Fare + pickup ETA per category |
| GET | `/api/v1/rides?status=active` | user | Own rides (rider) / assigned rides (driver) |
| POST | `/api/v1/rides` | rider | Request a ride (**`Idempotency-Key` required**) |
| GET | `/api/v1/rides/{id}` | rider / driver | Ride details incl. driver, ETA, `allowed_actions` |
| POST | `/api/v1/rides/{id}/accept` | driver | Accept and get assigned |
| POST | `/api/v1/rides/{id}/start` | assigned driver | Rider picked up |
| POST | `/api/v1/rides/{id}/complete` | assigned driver | Finish, settle fare, release driver |
| POST | `/api/v1/rides/{id}/cancel` | rider | Cancel while `requested`/`accepted` |
| PUT | `/api/v1/driver/availability` | driver | Go online (with location) / offline |
| GET | `/api/v1/driver/ride-offers` | driver | Eligible nearby requests, nearest first |

Changes from the brief: added `start` (pickup) so `complete` is only possible for a trip that actually started;
added auth, fare estimates, driver availability and ride offers. Everything is versioned under `/api/v1`.

### Example session

```bash
API=http://localhost:8000/api/v1
TOKEN=$(curl -s -X POST $API/auth/tokens -H 'Content-Type: application/json' \
  -d '{"email":"rider@example.com","password":"password"}' | jq -r .token)

curl -s "$API/fare-estimates?pickup_latitude=31.5102&pickup_longitude=74.3441&dropoff_latitude=31.4711&dropoff_longitude=74.3555" \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' | jq

curl -s -X POST $API/rides -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -H 'Accept: application/json' -H "Idempotency-Key: $(uuidgen)" \
  -d '{"category":"economy",
       "pickup":{"address":"Liberty Market","latitude":31.5102,"longitude":74.3441},
       "dropoff":{"address":"Packages Mall","latitude":31.4711,"longitude":74.3555}}' | jq
```

### Error format

Every error has the same shape. Clients branch on `code` and show `message`. The `request_id` is also returned in
the `X-Request-Id` header and is attached to every log line written for that request.

```json
{
  "message": "This ride has already been accepted by another driver.",
  "code": "ride_already_assigned",
  "request_id": "5a24658e-a10d-4edb-9595-8b7ebe9a5175"
}
```

| Status | Codes |
| --- | --- |
| 400 | `idempotency_key_required`, `idempotency_key_invalid` |
| 401 | `unauthenticated` |
| 403 | `forbidden` (authenticated but not allowed, e.g. a rider accepting a ride) |
| 404 | `not_found` (also returned for rides the user has no access to, so ids can't be probed) |
| 409 | `active_ride_exists`, `ride_already_assigned`, `invalid_ride_transition`, `driver_not_eligible`, `driver_on_trip`, `idempotency_request_in_progress` |
| 422 | `validation_failed` (with `errors`), `idempotency_key_reused` |
| 429 | `too_many_requests` |
| 500 | `server_error` (no internals leaked; details are in the logs under the `request_id`) |

## Project structure

```
app/
  Actions/Rides/        RequestRide, AcceptRide, StartRide, CompleteRide, CancelRide, EstimateRideOptions
  Actions/Drivers/      UpdateDriverAvailability
  Services/             RideLifecycle (state machine writes + audit), DriverMatcher, FareCalculator, RouteEstimator
  Enums/                RideStatus (transition table), RideCategory, DriverStatus, UserRole
  Http/Controllers/Api/V1, Http/Requests/Api/V1, Http/Resources/Api/V1
  Http/Middleware/      AssignRequestId, EnsureIdempotency
  Exceptions/           ApiException hierarchy + ApiExceptionRenderer (single error envelope)
  Policies/RidePolicy.php
config/rides.php        Service area, tariffs, matching radius, idempotency settings, known places
database/migrations/    Schema incl. CHECK constraints and partial unique indexes
frontend/src/
  api/                  Typed client (ApiError, timeouts, idempotency keys) and endpoints
  hooks/                TanStack Query hooks (polling, idempotent mutations)
  components/, pages/   Rider booking + tracking, driver console, login
public/docs/            OpenAPI spec + Swagger UI
```

## Configuration & secrets

* All configuration comes from environment variables, read only in `config/*.php` (`config/rides.php` holds the
  domain settings). `.env` is git-ignored; `.env.example` contains no secrets.
* Tokens are Sanctum personal access tokens (hashed in the database), expiring after 7 days
  (`SANCTUM_TOKEN_EXPIRATION_MINUTES`); expired tokens are pruned daily.
* Passwords are bcrypt-hashed. Failed logins are rate-limited per email + IP (5/min); the API is rate-limited per user.
* Demo credentials exist only in the seeder for local use.

## Known limitations

* **Matching is pull-based**: drivers see nearby requests and accept; there is no push dispatch, offer timeout, or
  automatic re-dispatch. Drivers cannot cancel an accepted ride (only the rider can).
* **Geography is approximated**: a static list of places instead of geocoding; road distance = straight line × 1.3;
  ETA from an average speed of 22 km/h; nearby drivers via bounding box + haversine (no PostGIS).
* **Live updates use polling** (every 3 s) rather than WebSockets.
* **Upfront pricing only**: the final fare equals the estimate; no surge, tolls, waiting time or payments.
* **Driver location** is only updated when going online and on trip completion; there is no location stream and
  no stale-location expiry.
* Single city; no admin tooling; no localisation (English only).

See [docs/ARCHITECTURE.md § Q5–Q8](docs/ARCHITECTURE.md#q5-how-would-you-scale-to-multiple-cities-and-thousands-of-concurrent-users)
for how each would be addressed.

## AI tools disclosure

This project was built with **Claude Code** (Anthropic) as an AI pair-programmer, which was used to scaffold,
write and refactor code, tests and documentation under my direction. I verified the output by:

* running the full automated suites (Pest incl. multi-process concurrency tests, Vitest, Playwright e2e on desktop
  and mobile viewports), plus Larastan (level 7), Pint and `tsc --strict`;
* **mutation-testing the critical guarantee**: temporarily removing the row lock in `AcceptRide` made the
  concurrency test fail (6 of 6 drivers "won"), confirming the test really detects double assignment;
* exercising the API manually with `curl` (idempotent replay, conflicts, invalid transitions, error envelope)
  and reviewing UI screenshots on desktop and mobile, which surfaced and fixed two real UI bugs;
* reviewing every file for correctness, security (authorization paths, mass assignment, SQL bindings, rate limits)
  and consistency with Laravel conventions.
