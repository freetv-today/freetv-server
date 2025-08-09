// src/components/Loaders/LoadConfig.jsx
import { useState, useEffect, useRef, useMemo } from 'preact/hooks';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { ConfigProvider } from '@context/ConfigContext.jsx';
import { AppLoader } from '@components/Loaders/AppLoader.jsx';
import { ErrorPage } from '@components/UI/ErrorPage.jsx';
import { shouldUpdateData } from '@/utils.js';

export function LoadConfig() {
  const [loading, setLoading] = useState(true);
  const [configData, setConfigData] = useLocalStorage('configData', null);
  const [error, setError] = useState(null);
  const hasInitialized = useRef(false);

  useEffect(() => {
    if (configData?.debugmode) {
      console.log('Rendering LoadConfig with configData:', configData);
    }
  }, [configData]);

  useEffect(() => {
    if (hasInitialized.current) {
      if (configData?.debugmode) {
        console.log('Skipping duplicate initialization in LoadConfig');
      }
      return;
    }
    hasInitialized.current = true;

    async function fetchConfig() {
      let config = configData;

      try {
        localStorage.setItem('test', 'test');
        localStorage.removeItem('test');
      } catch (e) {
        setError({
          type: 'Storage Error',
          message: 'Local storage is required. Please enable it in your browser.',
        });
        setLoading(false);
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
          showadmin: newConfig.showadmin ?? false,
          showads: newConfig.showads ?? false,
          appdata: newConfig.appdata ?? false,
          modules: newConfig.modules ?? false,
          updates: newConfig.updates ?? false,
          debugmode: newConfig.debugmode ?? false,
          name: newConfig.name || 'Free TV',
          version: newConfig.version || 'Unknown',
          lastupdated: newConfig.lastupdated || new Date().toJSON(),
        };

        if (!validatedConfig.database && !validatedConfig.offline) {
          throw new Error('Invalid config: database URL is required when not in offline mode');
        }

        if (shouldUpdateData(config, validatedConfig)) {
          if (validatedConfig.debugmode) {
            console.log(
              !config
                ? 'No config data in local storage, using new config'
                : 'Newer config detected, updating from /config.json'
            );
          }
          config = validatedConfig;
          setConfigData(config);
          if (validatedConfig.debugmode) {
            console.log('Config data loaded and saved to local storage:', config);
          }
        } else if (config?.debugmode) {
          console.log('Using existing config data from local storage:', config);
        }
      } catch (error) {
        if (config?.debugmode) {
          console.error('Error fetching config:', error);
        }
        setError({
          type: 'Configuration Error',
          message: 'Unable to load configuration. Please try again later.',
        });
        setLoading(false);
        return;
      }

      setLoading(false);
    }

    fetchConfig();
  }, []); // Empty dependency array

  // Memoize configData to prevent unnecessary re-renders
  const memoizedConfig = useMemo(() => configData || { offline: true, showadmin: false }, [configData]);

  if (loading) {
    return null;
  }

  if (error) {
    return <ErrorPage type={error.type} message={error.message} />;
  }

  return (
    <ConfigProvider config={memoizedConfig}>
      <AppLoader />
    </ConfigProvider>
  );
}