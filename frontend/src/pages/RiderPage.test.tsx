import { screen, waitFor, within } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { renderApp } from '../test/render';
import { API, apiError, fareEstimate, makeRide, riderUser, server } from '../test/server';

async function chooseRoute(user: ReturnType<typeof renderApp>['user']) {
  await user.selectOptions(await screen.findByLabelText('Pickup'), 'Liberty Market, Gulberg III');
  await user.selectOptions(screen.getByLabelText('Destination'), 'Packages Mall, Walton Road');
}

describe('booking a ride', () => {
  it('shows loading placeholders, then fares and pickup ETAs once a route is chosen', async () => {
    const { user } = renderApp({ route: '/ride', user: riderUser });

    expect(screen.getByRole('status', { name: 'Loading' })).toBeInTheDocument();
    expect(await screen.findByText('Where to?')).toBeInTheDocument();

    await chooseRoute(user);

    expect(await screen.findByText('Rs 420')).toBeInTheDocument();
    expect(screen.getByText('Pickup in 2 min')).toBeInTheDocument();
    expect(screen.getByText('No drivers nearby right now')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Request Economy' })).toBeEnabled();
  });

  it('requests the ride with an Idempotency-Key and shows its live status', async () => {
    let request: { body: unknown; key: string | null } | null = null;
    server.use(
      http.post(`${API}/rides`, async ({ request: incoming }) => {
        request = { body: await incoming.json(), key: incoming.headers.get('Idempotency-Key') };
        return HttpResponse.json({ data: makeRide() }, { status: 201 });
      }),
      http.get(`${API}/rides/:id`, () => HttpResponse.json({ data: makeRide() })),
    );
    const { user } = renderApp({ route: '/ride', user: riderUser });

    await chooseRoute(user);
    await user.click(await screen.findByRole('button', { name: 'Request Economy' }));

    expect(await screen.findByText('Finding your driver')).toBeInTheDocument();
    expect(screen.getByText('Looking for a nearby driver…')).toBeInTheDocument();
    expect(request).toMatchObject({
      body: { category: 'economy', pickup: { address: 'Liberty Market, Gulberg III' }, dropoff: { address: 'Packages Mall, Walton Road' } },
    });
    expect(request!.key).toMatch(/^[0-9a-f-]{36}$/);
  });

  it('retries a request that failed in transit with the same Idempotency-Key', async () => {
    const keys: (string | null)[] = [];
    server.use(
      http.post(`${API}/rides`, ({ request }) => {
        keys.push(request.headers.get('Idempotency-Key'));
        return keys.length === 1 ? HttpResponse.error() : HttpResponse.json({ data: makeRide() }, { status: 201 });
      }),
      http.get(`${API}/rides/:id`, () => HttpResponse.json({ data: makeRide() })),
    );
    const { user } = renderApp({ route: '/ride', user: riderUser });

    await chooseRoute(user);
    await user.click(await screen.findByRole('button', { name: 'Request Economy' }));

    expect(await screen.findByText('Finding your driver')).toBeInTheDocument();
    expect(keys).toHaveLength(2);
    expect(keys[0]).toBe(keys[1]);
  });

  it('shows the server message when the rider already has an active ride', async () => {
    server.use(
      http.post(`${API}/rides`, () =>
        apiError(409, 'active_ride_exists', 'You already have an active ride. Cancel or finish it before requesting another.'),
      ),
    );
    const { user } = renderApp({ route: '/ride', user: riderUser });

    await chooseRoute(user);
    await user.click(await screen.findByRole('button', { name: 'Request Economy' }));

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('You already have an active ride');
    expect(alert).toHaveTextContent('Reference: req-test-123');
  });

  it('shows an error with a working retry when fares fail to load', async () => {
    let calls = 0;
    server.use(
      http.get(`${API}/fare-estimates`, () => {
        calls += 1;
        return calls <= 3 ? apiError(503, 'server_error', 'Something went wrong on our side. Please try again.') : HttpResponse.json({ data: fareEstimate });
      }),
    );
    const { user } = renderApp({ route: '/ride', user: riderUser });

    await chooseRoute(user);

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Couldn’t load fares');
    expect(calls).toBe(3); // first attempt + 2 automatic retries

    await user.click(within(alert).getByRole('button', { name: 'Try again' }));

    expect(await screen.findByText('Rs 420')).toBeInTheDocument();
  });

  it('prevents choosing the same place for pickup and destination', async () => {
    const { user } = renderApp({ route: '/ride', user: riderUser });

    await user.selectOptions(await screen.findByLabelText('Pickup'), 'Liberty Market, Gulberg III');
    await user.selectOptions(screen.getByLabelText('Destination'), 'Liberty Market, Gulberg III');

    expect(screen.getByText('Pickup and destination must be different.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Request ride' })).toBeDisabled();
  });
});

describe('tracking a ride', () => {
  it('shows the assigned driver and ETA, and cancels after confirmation', async () => {
    const accepted = makeRide({
      status: 'accepted',
      allowed_actions: ['cancel'],
      driver: {
        id: 7,
        name: 'Usman Tariq',
        phone: null,
        status: 'on_trip',
        vehicle: { category: 'economy', model: 'Suzuki Cultus', plate: 'LEA-1234' },
        location: { latitude: 31.515, longitude: 74.34, updated_at: null },
        pickup_eta_seconds: 180,
      },
    });
    let current = accepted;
    server.use(
      http.get(`${API}/rides`, () => HttpResponse.json({ data: current.status === 'cancelled' ? [] : [current] })),
      http.get(`${API}/rides/:id`, () => HttpResponse.json({ data: current })),
      http.post(`${API}/rides/:id/cancel`, () => {
        current = makeRide({ status: 'cancelled', allowed_actions: [], cancellation: { cancelled_by: 'rider', reason: 'Cancelled by rider' } });
        return HttpResponse.json({ data: current });
      }),
    );
    const { user } = renderApp({ route: '/ride', user: riderUser });

    expect(await screen.findByText('Driver on the way')).toBeInTheDocument();
    expect(screen.getByText('Arriving in about 3 min')).toBeInTheDocument();
    expect(screen.getByText('LEA-1234')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Cancel ride' }));
    await user.click(screen.getByRole('button', { name: 'Yes, cancel' }));

    expect(await screen.findByText('Ride cancelled')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Cancel ride' })).not.toBeInTheDocument();
    await waitFor(() => expect(screen.getByRole('button', { name: 'Book another ride' })).toBeInTheDocument());
  });

  it('hides cancellation once the trip has started', async () => {
    const inProgress = makeRide({ status: 'in_progress', allowed_actions: [] });
    server.use(
      http.get(`${API}/rides`, () => HttpResponse.json({ data: [inProgress] })),
      http.get(`${API}/rides/:id`, () => HttpResponse.json({ data: inProgress })),
    );
    renderApp({ route: '/ride', user: riderUser });

    expect(await screen.findByText('On trip')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Cancel ride' })).not.toBeInTheDocument();
  });
});
