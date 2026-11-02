import { screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { expect, it } from 'vitest';
import { renderApp } from '../test/render';
import { API, apiError, driverUser, makeRide, server } from '../test/server';

it('lists nearby ride requests and accepts one', async () => {
  const offer = makeRide({ allowed_actions: ['accept'], distance_to_pickup_meters: 650 });
  let accepted = false;
  server.use(
    http.get(`${API}/driver/ride-offers`, () => HttpResponse.json({ data: accepted ? [] : [offer] })),
    http.get(`${API}/rides`, () =>
      HttpResponse.json({ data: accepted ? [makeRide({ status: 'accepted', allowed_actions: ['start'] })] : [] }),
    ),
    http.post(`${API}/rides/:id/accept`, () => {
      accepted = true;
      return HttpResponse.json({ data: makeRide({ status: 'accepted', allowed_actions: ['start'] }) });
    }),
  );
  const { user } = renderApp({ route: '/drive', user: driverUser });

  expect(await screen.findByText('650 m to pickup')).toBeInTheDocument();
  await user.click(screen.getByRole('button', { name: 'Accept ride' }));

  expect(await screen.findByRole('heading', { name: 'Current ride' })).toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Start trip (rider picked up)' })).toBeInTheDocument();
});

it('explains when another driver accepted the ride first', async () => {
  server.use(
    http.get(`${API}/driver/ride-offers`, () => HttpResponse.json({ data: [makeRide({ allowed_actions: ['accept'] })] })),
    http.post(`${API}/rides/:id/accept`, () => apiError(409, 'ride_already_assigned', 'This ride has already been accepted by another driver.')),
  );
  const { user } = renderApp({ route: '/drive', user: driverUser });

  await user.click(await screen.findByRole('button', { name: 'Accept ride' }));

  expect(await screen.findByRole('alert')).toHaveTextContent('This ride has already been accepted by another driver.');
});

it('shows an empty state while waiting for requests', async () => {
  renderApp({ route: '/drive', user: driverUser });

  expect(await screen.findByText('No ride requests yet')).toBeInTheDocument();
});
