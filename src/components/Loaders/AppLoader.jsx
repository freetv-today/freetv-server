// src/components/Loaders/AppLoader.jsx
import { useState, useEffect, useRef } from 'preact/hooks';
import { useLocalStorage } from '@hooks/useLocalStorage';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData.jsx';
import { App } from '@components/App.jsx';
import { ErrorPage } from '@components/UI/ErrorPage.jsx';
import { OfflinePage } from '@components/UI/OfflinePage.jsx';
import { generateNewCode, shouldUpdateData, enforceMinLoadingTime } from '@/utils.js';
import { useConfig } from '@context/ConfigContext.jsx';

export function AppLoader() {
  const [showData, setShowData] = useLocalStorage('showData', null);
  const [visitData, setVisitData] = useLocalStorage('visitData', null);
  const [error, setError] = useState(null);
  const config = useConfig();
  const hasUpdatedVisitData = useRef(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function initialize() {
      const minLoadingTime = 1200;
      const startTime = Date.now();

      // If config.offline, always show spinner and load empty data
      if (config.offline) {
        setShowData({ shows: [], lastupdated: null });
        await enforceMinLoadingTime(startTime, minLoadingTime);
        setLoading(false);
        return;
      }

      // Otherwise, show spinner and fetch new show data
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
      try {
        const response = await fetch(databaseUrl);
        if (!response.ok) {
          throw new Error('Failed to fetch show data');
        }
        const data = await response.json();

        if (!Array.isArray(data.shows)) {
          throw new Error('Invalid show data: application cannot load');
        }

        setShowData(data);
        try {
          localStorage.setItem('showData', JSON.stringify(data));
        } catch (e) {}
      } catch (error) {
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
        const updatedVisitData = { ...visitData, lastVisit: new Date().toJSON() };
        setVisitData(updatedVisitData);
        hasUpdatedVisitData.current = true;
      }

      await enforceMinLoadingTime(startTime, minLoadingTime);
      setLoading(false);
    }
    initialize();
  }, [config]);

  if (loading) {
    return <SpinnerLoadingAppData />;
  }

  if (error) {
    return <ErrorPage type={error.type} message={error.message} />;
  }

  if (config.offline) {
    return <OfflinePage />;
  }

  return <App />;
}