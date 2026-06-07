import { createContext, useContext, useState, useCallback } from 'react';

const SettingsContext = createContext(null);

export const useSettings = () => {
  const ctx = useContext(SettingsContext);
  if (!ctx) {
    throw new Error('useSettings must be used within a SettingsProvider');
  }
  return ctx;
};

// Sensible defaults used until the admin settings are loaded from the server.
const DEFAULT_SETTINGS = {
  schoolName: 'نظام إدارة المدرسة',
  currentPeriod: 'first',
  workEnabled: true,
  examEnabled: true,
  workMax: 40,
  examMax: 60,
  allowHalf: true,
};

export const SettingsProvider = ({ children }) => {
  const [settings, setSettings] = useState(DEFAULT_SETTINGS);

  const updateSettings = useCallback((patch) => {
    setSettings((prev) => ({ ...prev, ...patch }));
  }, []);

  const value = { settings, updateSettings };

  return <SettingsContext.Provider value={value}>{children}</SettingsContext.Provider>;
};

export default SettingsContext;
