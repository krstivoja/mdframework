import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import { api, setCsrf } from './api.js';

const AuthCtx = createContext(null);

export function AuthProvider({ children }) {
  const [state, setState] = useState({ status: 'loading', user: null });

  const refresh = useCallback(async () => {
    try {
      const me = await api.get('/me');
      setCsrf(me.csrf);
      setState({ status: 'ready', user: me.user });
    } catch {
      setState({ status: 'ready', user: null });
    }
  }, []);

  useEffect(() => { refresh(); }, [refresh]);

  const login = useCallback(async (username, password) => {
    const res = await api.post('/login', { username, password });
    setCsrf(res.csrf);
    setState({ status: 'ready', user: res.user });
  }, []);

  const logout = useCallback(async () => {
    try { await api.post('/logout'); } catch { /* ignore */ }
    setCsrf('');
    setState({ status: 'ready', user: null });
    await refresh();
  }, [refresh]);

  return (
    <AuthCtx.Provider value={{ ...state, login, logout, refresh }}>
      {children}
    </AuthCtx.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthCtx);
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>');
  return ctx;
}
