import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import api from '../services/api';

const AuthContext = createContext(null);

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return ctx;
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  // Restore the session on first load.
  useEffect(() => {
    const init = async () => {
      const token = localStorage.getItem('token');
      const stored = localStorage.getItem('user');

      if (!token) {
        setLoading(false);
        return;
      }

      // Optimistically restore the cached user so the UI is responsive.
      if (stored) {
        try {
          setUser(JSON.parse(stored));
        } catch {
          /* ignore corrupted cache */
        }
      }

      // Then verify the token against the server.
      try {
        const { data } = await api.get('/auth/me');
        const me = data.data || data.user;
        if (me) {
          setUser(me);
          localStorage.setItem('user', JSON.stringify(me));
        }
      } catch (err) {
        // Only force a logout when the token is genuinely rejected.
        if (err.response?.status === 401) {
          localStorage.removeItem('token');
          localStorage.removeItem('user');
          setUser(null);
        }
      } finally {
        setLoading(false);
      }
    };

    init();
  }, []);

  const login = useCallback(async (civilId, password) => {
    const { data } = await api.post('/auth/login', { civilId, password });
    localStorage.setItem('token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));
    setUser(data.user);
    return data.user;
  }, []);

  const logout = useCallback(() => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    setUser(null);
  }, []);

  const value = {
    user,
    isAuthenticated: !!user,
    loading,
    login,
    logout,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export default AuthContext;
