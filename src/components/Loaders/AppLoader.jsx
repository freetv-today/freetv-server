// src/components/Loaders/AppLoader.jsx
import { useState, useEffect, useRef } from 'preact/hooks';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData.jsx';
import { App } from '@components/App.jsx';
import { ErrorPage } from '@components/UI/ErrorPage.jsx';
import { OfflinePage } from '@components/UI/OfflinePage.jsx';
import { generateNewCode, shouldUpdateData, enforceMinLoadingTime } from '@/utils.js';
import { ShowDataProvider } from '@context/ShowDataContext.jsx';
import { useConfig } from '@context/ConfigContext.jsx';

export function AppLoader() {
  const [loading, setLoading] = useState(true);
  const [showData, setShowData] = useLocalStorage('showData', null);
  const [visitData, setVisitData] = useLocalStorage('visitData', null);
  const [error, setError] = useState(null);
  const config = useConfig();
  const hasUpdatedVisitData = useRef(false);
  const hasInitialized = useRef(false);

  useEffect(() => {
    if (hasInitialized.current) {
      if (config.debugmode) {
        console.log('Skipping duplicate initialization in AppLoader');
      }
      return;
    }
    hasInitialized.current = true;

    async function initialize() {
      const minLoadingTime = 1200;
      const startTime = Date.now();

      // Check if we have valid cached data
      if (showData && visitData && !config.offline) {
        if (config.debugmode) {
          console.log('Using cached showData and visitData, skipping fetch');
        }
        const updatedVisitData = { ...visitData, lastVisit: new Date().toJSON() };
        setVisitData(updatedVisitData);
        hasUpdatedVisitData.current = true;
        await enforceMinLoadingTime(startTime, minLoadingTime);
        setLoading(false);
        return;
      }

      // Check offline mode
      if (config.offline) {
        if (config.debugmode) {
          console.log('Offline mode enabled, using empty showData');
        }
        setShowData({ shows: [], lastupdated: null });
        await enforceMinLoadingTime(startTime, minLoadingTime);
        setLoading(false);
        return;
      }

      // Check localStorage availability
      try {
        localStorage.setItem('test', 'test');
        localStorage.removeItem('test');
      } catch (e) {
        setError({
          type: 'Storage Error',
          message: 'Local storage is required. Please enable it in your browser.',
        });
        await enforceMinLoadingTime(startTime, minLoadingTime);
        setLoading(false);
        return;
      }

      // Fetch show data
      const databaseUrl = config.database;
      if (config.debugmode) {
        console.log(`Fetching show data from ${databaseUrl}`);
      }
      try {
        const response = await fetch(databaseUrl);
        if (!response.ok) {
          throw new Error('Failed to fetch show data');
        }
        const data = await response.json();

        if (!Array.isArray(data.shows)) {
          throw new Error('Invalid show data: shows must be an array');
        }

        if (shouldUpdateData(showData, data)) {
          if (config.debugmode) {
            console.log('No show data or outdated, using new data');
          }
          setShowData(data);
          try {
            localStorage.setItem('showData', JSON.stringify(data));
          } catch (e) {
            if (config.debugmode) {
              console.warn('Failed to save showData to localStorage, continuing with state:', e);
            }
          }
        } else {
          if (config.debugmode) {
            console.log('Using cached show data');
          }
        }
      } catch (error) {
        if (config.debugmode) {
          console.error('Error fetching show data:', error);
        }
        setError({
          type: 'Data Loading Error',
          message: 'Unable to load show data. Please try again later.',
        });
        await enforceMinLoadingTime(startTime, minLoadingTime);
        setLoading(false);
        return;
      }

      // Handle visit data
      if (!visitData) {
        if (config.debugmode) {
          console.log('No visit data found, creating new visit data');
        }
        const tstamp = new Date().toJSON();
        const code = generateNewCode();
        const vdo = {
          lastVisit: tstamp,
          start: tstamp,
          end: '',
          token: code,
        };
        setVisitData(vdo);
        sessionStorage.setItem('token', code);
        hasUpdatedVisitData.current = true;
      } else if (!hasUpdatedVisitData.current) {
        if (config.debugmode) {
          console.log('Updating existing visit data');
        }
        const updatedVisitData = { ...visitData, lastVisit: new Date().toJSON() };
        setVisitData(updatedVisitData);
        hasUpdatedVisitData.current = true;
      }

      await enforceMinLoadingTime(startTime, minLoadingTime);
      if (config.debugmode) {
        console.log('Initialization complete, rendering App');
      }
      setLoading(false);
    }

    initialize();
  }, [config]); // Add config as dependency to handle changes in offline mode

  if (loading) {
    return <SpinnerLoadingAppData />;
  }

  if (error) {
    return <ErrorPage type={error.type} message={error.message} />;
  }

  if (config.offline) {
    return <OfflinePage />;
  }

  return (
    <ShowDataProvider showData={showData || { shows: [], lastupdated: null }}>
      <App />
    </ShowDataProvider>
  );
}