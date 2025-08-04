// src/context/ConfigContext.jsx
import { createContext } from 'preact';
import { useContext } from 'preact/hooks';

// Create a context with a default value (null for now)
const ConfigContext = createContext(null);

// Hook to access the context easily
export function useConfig() {
  const config = useContext(ConfigContext);
  if (!config) {
    throw new Error('useConfig must be used within a ConfigProvider');
  }
  return config;
}

// Provider component to wrap the app
export function ConfigProvider({ config, children }) {
  return (
    <ConfigContext.Provider value={config}>
      {children}
    </ConfigContext.Provider>
  );
}