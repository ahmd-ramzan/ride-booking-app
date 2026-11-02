import { useQueryClient } from '@tanstack/react-query';
import { createContext, use, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { setAuthToken, setUnauthenticatedHandler } from '../api/client';
import { api } from '../api/endpoints';
import type { User } from '../api/types';

const STORAGE_KEY = 'ride-booking.session';

interface Session {
  token: string;
  user: User;
}

interface AuthContextValue {
  user: User | null;
  sessionExpired: boolean;
  login: (email: string, password: string) => Promise<User>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function readStoredSession(): Session | null {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as Session) : null;
  } catch {
    return null;
  }
}

function storeSession(session: Session | null): void {
  try {
    if (session) window.localStorage.setItem(STORAGE_KEY, JSON.stringify(session));
    else window.localStorage.removeItem(STORAGE_KEY);
  } catch {
    // Storage can be unavailable (private mode); the session then lasts for this tab only.
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [session, setSession] = useState<Session | null>(() => {
    const stored = readStoredSession();
    setAuthToken(stored?.token ?? null);
    return stored;
  });
  const [sessionExpired, setSessionExpired] = useState(false);

  const clearSession = useCallback(() => {
    setAuthToken(null);
    storeSession(null);
    setSession(null);
    queryClient.clear();
  }, [queryClient]);

  useEffect(() => {
    setUnauthenticatedHandler(() => {
      setSessionExpired(true);
      clearSession();
    });
    return () => setUnauthenticatedHandler(null);
  }, [clearSession]);

  const login = useCallback(async (email: string, password: string) => {
    const result = await api.login(email, password);
    const next = { token: result.token, user: result.user };
    setAuthToken(next.token);
    storeSession(next);
    setSessionExpired(false);
    setSession(next);
    return result.user;
  }, []);

  const logout = useCallback(async () => {
    try {
      await api.logout();
    } catch {
      // The token is discarded locally either way.
    }
    clearSession();
  }, [clearSession]);

  const value = useMemo(
    () => ({ user: session?.user ?? null, sessionExpired, login, logout }),
    [session, sessionExpired, login, logout],
  );

  return <AuthContext value={value}>{children}</AuthContext>;
}

export function useAuth(): AuthContextValue {
  const context = use(AuthContext);
  if (!context) throw new Error('useAuth must be used inside <AuthProvider>');
  return context;
}
