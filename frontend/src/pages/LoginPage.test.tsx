import { screen } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { expect, it } from 'vitest';
import { renderApp } from '../test/render';
import { API, apiError, riderUser, server } from '../test/server';

it('signs in and lands on the booking screen', async () => {
  server.use(http.post(`${API}/auth/tokens`, () => HttpResponse.json({ token: 't', token_type: 'Bearer', expires_at: null, user: riderUser }, { status: 201 })));
  const { user } = renderApp({ route: '/' });

  await user.type(screen.getByLabelText('Email'), 'rider@example.com');
  await user.type(screen.getByLabelText('Password'), 'password');
  await user.click(screen.getByRole('button', { name: 'Sign in' }));

  expect(await screen.findByRole('heading', { name: 'Book a ride' })).toBeInTheDocument();
});

it('shows the validation message for wrong credentials', async () => {
  server.use(
    http.post(`${API}/auth/tokens`, () =>
      apiError(422, 'validation_failed', 'These credentials do not match our records.', {
        errors: { email: ['These credentials do not match our records.'] },
      }),
    ),
  );
  const { user } = renderApp({ route: '/login' });

  await user.type(screen.getByLabelText('Email'), 'rider@example.com');
  await user.type(screen.getByLabelText('Password'), 'wrong');
  await user.click(screen.getByRole('button', { name: 'Sign in' }));

  expect(await screen.findByText('These credentials do not match our records.')).toBeInTheDocument();
  expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
});

it('returns to sign-in with a notice when the session has expired', async () => {
  server.use(http.get(`${API}/rides`, () => apiError(401, 'unauthenticated', 'Authentication is required.')));
  renderApp({ route: '/ride', user: riderUser });

  expect(await screen.findByText('Your session has expired. Please sign in again.')).toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Sign in' })).toBeInTheDocument();
});

it('explains a network failure instead of failing silently', async () => {
  server.use(http.post(`${API}/auth/tokens`, () => HttpResponse.error()));
  const { user } = renderApp({ route: '/login' });

  await user.click(screen.getByRole('button', { name: 'Continue as Rider (Ayesha)' }));

  expect(await screen.findByRole('alert')).toHaveTextContent('Could not reach the server');
});
