import { createContext } from 'preact';
import { useContext } from 'preact/hooks';

/**
 * ConfigContext - Provides global app configuration to all components.
 * Use the ConfigProvider to wrap your app and pass the config object.
 * Access config anywhere in the tree with the useConfig() hook.
 */

// Create a context with a default value (null for now)
const ConfigContext = createContext(null);

/**
 * useConfig - Hook to access the global app config.
 * @returns {Object} config - The app configuration object.
 * @throws {Error} If used outside of a ConfigProvider.
 */

export function useConfig() {
  const config = useContext(ConfigContext);
  if (!config) {
    throw new Error('useConfig must be used within a ConfigProvider');
  }
  return config;
}

/**
 * ConfigProvider - Context provider for app configuration.
 * @param {Object} props
 * @param {Object} props.config - The configuration object.
 * @param {import('preact').ComponentChildren} props.children
 */

export function ConfigProvider({ config, children }) {
  return (
    <ConfigContext.Provider value={config}>
      {children}
    </ConfigContext.Provider>
  );
}