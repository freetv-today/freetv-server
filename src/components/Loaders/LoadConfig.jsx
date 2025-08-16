// src/components/Loaders/LoadConfig.jsx
import { useState, useEffect, useRef, useMemo } from 'preact/hooks';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { ConfigProvider } from '@context/ConfigContext.jsx';
import { AppLoader } from '@components/Loaders/AppLoader.jsx';
import { ErrorPage } from '@components/UI/ErrorPage.jsx';
import { shouldUpdateData } from '@/utils.js';

export function LoadConfig() {

  const [configData, setConfigData] = useLocalStorage('configData', null);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function fetchConfig() {
      try {
        localStorage.setItem('test', 'test');
        localStorage.removeItem('test');
      } catch (e) {
        setError({
          type: 'Storage Error',
          message: 'Device storage is required. Please enable local storage in your browser.',
        });
        return;
      }

      try {
        const response = await fetch('/config.json');
        if (!response.ok) {
          throw new Error('Failed to fetch config');
        }
        const newConfig = await response.json();

        const validatedConfig = {
          database: newConfig.database || '',
          offline: newConfig.offline ?? false,
          showads: newConfig.showads ?? false,
          appdata: newConfig.appdata ?? false,
          modules: newConfig.modules ?? false,
          debugmode: newConfig.debugmode ?? false,
          name: newConfig.name || 'Free TV',
          version: newConfig.version || 'Unknown',
          lastupdated: newConfig.lastupdated || new Date().toJSON(),
        };

        if (!validatedConfig.database && !validatedConfig.offline) {
          throw new Error('Invalid configuration: database URL is required');
        }

        setConfigData(validatedConfig);
      } catch (error) {
        setError({
          type: 'Configuration Error',
          message: 'Unable to load configuration. Please try again later.',
        });
        return;
      }
    }
    fetchConfig();
  }, []);

  if (error) {
    return <ErrorPage type={error.type} message={error.message} />;
  }

  // Always provide config context and AppLoader (which will only run if needed)
  return (
    <ConfigProvider config={configData || { offline: true }}>
      <AppLoader />
    </ConfigProvider>
  );
}