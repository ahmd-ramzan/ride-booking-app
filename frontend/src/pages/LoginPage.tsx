import { useState, type FormEvent } from 'react';
import { ApiError } from '../api/client';
import { useAuth } from '../auth/AuthContext';
import { Button, Card, ErrorAlert } from '../components/ui/primitives';

const DEMO_ACCOUNTS = [
  { label: 'Rider (Ayesha)', email: 'rider@example.com' },
  { label: 'Economy driver (Usman)', email: 'driver@example.com' },
  { label: 'Comfort driver (Sana)', email: 'driver3@example.com' },
];

export function LoginPage() {
  const { login, sessionExpired } = useAuth();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<unknown>(null);
  const [submitting, setSubmitting] = useState(false);

  async function signIn(credentials: { email: string; password: string }) {
    setSubmitting(true);
    setError(null);
    try {
      await login(credentials.email, credentials.password);
    } catch (caught) {
      setError(caught);
    } finally {
      setSubmitting(false);
    }
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    void signIn({ email, password });
  }

  const fieldError = error instanceof ApiError ? error.firstFieldError('email') : undefined;

  return (
    <div className="mx-auto max-w-md space-y-5 py-8">
      <div className="text-center">
        <p className="text-3xl" aria-hidden="true">🚕</p>
        <h1 className="mt-2 text-2xl font-bold">RideLahore</h1>
        <p className="text-sm text-slate-600">Sign in to book or drive.</p>
      </div>

      {sessionExpired && (
        <p role="status" className="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 ring-1 ring-amber-200">
          Your session has expired. Please sign in again.
        </p>
      )}

      <Card>
        <form onSubmit={submit} className="space-y-4" noValidate>
          <div>
            <label htmlFor="email" className="block text-sm font-medium text-slate-700">
              Email
            </label>
            <input
              id="email"
              type="email"
              autoComplete="username"
              required
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              aria-invalid={fieldError ? true : undefined}
              aria-describedby={fieldError ? 'email-error' : undefined}
              className="mt-1 block min-h-11 w-full rounded-xl border-0 px-3 py-2 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-600 focus:outline-none"
            />
            {fieldError && (
              <p id="email-error" className="mt-1 text-xs text-red-700">
                {fieldError}
              </p>
            )}
          </div>
          <div>
            <label htmlFor="password" className="block text-sm font-medium text-slate-700">
              Password
            </label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              required
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              className="mt-1 block min-h-11 w-full rounded-xl border-0 px-3 py-2 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-brand-600 focus:outline-none"
            />
          </div>

          {error !== null && !fieldError && <ErrorAlert title="Sign in failed" error={error} />}

          <Button type="submit" className="w-full" loading={submitting} loadingText="Signing in…" disabled={!email || !password}>
            Sign in
          </Button>
        </form>
      </Card>

      <Card>
        <h2 className="text-sm font-semibold">Demo accounts</h2>
        <p className="text-xs text-slate-500">Seeded locally; password is “password”.</p>
        <div className="mt-3 grid gap-2">
          {DEMO_ACCOUNTS.map((account) => (
            <Button key={account.email} variant="secondary" disabled={submitting} onClick={() => void signIn({ email: account.email, password: 'password' })}>
              Continue as {account.label}
            </Button>
          ))}
        </div>
      </Card>
    </div>
  );
}
