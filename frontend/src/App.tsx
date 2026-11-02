import { QueryClientProvider, type QueryClient } from '@tanstack/react-query';
import { useState, type ReactNode } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router';
import { AuthProvider, useAuth } from './auth/AuthContext';
import { Button } from './components/ui/primitives';
import { createQueryClient } from './lib/queryClient';
import { DriverPage } from './pages/DriverPage';
import { LoginPage } from './pages/LoginPage';
import { RiderPage } from './pages/RiderPage';

export function AppProviders({ children, queryClient }: { children: ReactNode; queryClient?: QueryClient }) {
  const [client] = useState(() => queryClient ?? createQueryClient());

  return (
    <QueryClientProvider client={client}>
      <AuthProvider>{children}</AuthProvider>
    </QueryClientProvider>
  );
}

function Shell({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();

  return (
    <div className="min-h-dvh">
      <header className="border-b border-slate-200 bg-white">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
          <span className="font-bold">
            <span aria-hidden="true">🚕 </span>RideLahore
          </span>
          {user && (
            <div className="flex items-center gap-3 text-sm">
              <span className="hidden text-slate-600 sm:inline">
                {user.name} · <span className="capitalize">{user.role}</span>
              </span>
              <Button variant="ghost" className="min-h-9 py-1.5" onClick={() => void logout()}>
                Sign out
              </Button>
            </div>
          )}
        </div>
      </header>
      <main className="mx-auto max-w-5xl px-4 py-6">{children}</main>
    </div>
  );
}

export function AppRoutes() {
  const { user } = useAuth();
  const home = user?.role === 'driver' ? '/drive' : '/ride';

  return (
    <Shell>
      <Routes>
        <Route path="/login" element={user ? <Navigate to={home} replace /> : <LoginPage />} />
        <Route path="/ride" element={!user ? <Navigate to="/login" replace /> : user.role === 'rider' ? <RiderPage /> : <Navigate to={home} replace />} />
        <Route path="/drive" element={!user ? <Navigate to="/login" replace /> : user.role === 'driver' ? <DriverPage /> : <Navigate to={home} replace />} />
        <Route path="*" element={<Navigate to={user ? home : '/login'} replace />} />
      </Routes>
    </Shell>
  );
}

export default function App() {
  return (
    <AppProviders>
      <BrowserRouter>
        <AppRoutes />
      </BrowserRouter>
    </AppProviders>
  );
}
