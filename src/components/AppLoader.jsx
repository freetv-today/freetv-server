// src/components/AppLoader.jsx
import { useState, useEffect } from 'preact/hooks';
import { useLocalStorage } from '../hooks/useLocalStorage';
import { SpinnerLoadingAppData } from './SpinnerLoadingAppData.jsx';
import { App } from './App.jsx';
import { generateNewCode } from '../utils.js';
import { ConfigProvider } from '../context/ConfigContext.jsx';
import { ShowDataProvider } from '../context/ShowDataContext.jsx';

export function AppLoader() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showData, setShowData] = useState(null);
  const [configData, setConfigData] = useLocalStorage('configData', null);
  const [visitData, setVisitData] = useLocalStorage('visitData', null);

  useEffect(() => {
    async function initialize() {
      const minLoadingTime = 1200; // 1.2 seconds
      const startTime = Date.now();

      if (configData?.debugmode) {
        console.log('Starting app initialization...');
      }

      // Check for existing data
      if (showData && configData && visitData) {
        if (configData.debugmode) {
          console.log('Using cached data, skipping fetch');
        }
        // Update lastVisit timestamp (like freetv.js)
        const updatedVisitData = { ...visitData, lastVisit: new Date().toJSON() };
        setVisitData(updatedVisitData);
        if (configData.debugmode) {
          console.log('Visit data updated:', updatedVisitData);
        }
        // Ensure minimum spinner time
        const elapsedTime = Date.now() - startTime;
        const remainingTime = minLoadingTime - elapsedTime;
        if (remainingTime > 0) {
          if (configData.debugmode) {
            console.log(`Waiting ${remainingTime}ms to meet minimum spinner time`);
          }
          await new Promise((resolve) => setTimeout(resolve, remainingTime));
        }
        setLoading(false);
        return;
      }

      // Load config
      let config = configData;
      try {
        const response = await fetch('/config.json');
        if (!response.ok) throw new Error('Failed to fetch config');
        const newConfig = await response.json();

        const storedDate = config?.lastupdated ? new Date(config.lastupdated) : null;
        const newDate = newConfig.lastupdated ? new Date(newConfig.lastupdated) : null;

        if (!config || !storedDate || (newDate && newDate > storedDate)) {
          if (newConfig.debugmode) {
            console.log(
              !config
                ? 'No config data in local storage, using new config'
                : 'Newer config detected, updating from /config.json'
            );
          }
          config = newConfig;
          setConfigData(config);
          if (newConfig.debugmode) {
            console.log('Config data loaded and saved to local storage:', config);
          }
        } else {
          if (config.debugmode) {
            console.log('Using existing config data from local storage:', config);
          }
        }
      } catch (error) {
        if (config?.debugmode) {
          console.error('Error fetching config:', error);
        }
        setError('Failed to load configuration');
        setLoading(false);
        return;
      }

      // Check offline mode (like freetv.js)
      if (config.offline) {
        if (config.debugmode) {
          console.log('Offline mode enabled, stopping initialization');
        }
        setError('App is in offline mode');
        setLoading(false);
        return;
      }

      // Load showData
      const databaseUrl = config.database;
      if (config.debugmode) {
        console.log(`Fetching show data from ${databaseUrl}`);
      }
      try {
        const response = await fetch(databaseUrl);
        if (!response.ok) throw new Error('Failed to fetch show data');
        const data = await response.json();

        // Check lastupdated (like freetv.js checkForUpdates)
        const storedShowData = showData || JSON.parse(localStorage.getItem('showData') || '{}');
        const storedShowDate = storedShowData.lastupdated ? new Date(storedShowData.lastupdated) : null;
        const newShowDate = data.lastupdated ? new Date(data.lastupdated) : null;

        if (!storedShowData.shows || !storedShowDate || (newShowDate && newShowDate > storedShowDate)) {
          if (config.debugmode) {
            console.log('No show data or outdated, using new data');
          }
          setShowData(data);
          // Optionally save to localStorage if size permits
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
          setShowData(storedShowData);
        }
        if (config.debugmode) {
          console.log('Show data loaded successfully');
        }
      } catch (error) {
        if (config.debugmode) {
          console.error('Error fetching show data:', error);
        }
        setError('Failed to load show data');
        setLoading(false);
        return;
      }

      // Initialize visitData
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
        if (config.debugmode) {
          console.log('New visit data created and saved:', vdo);
        }
      } else {
        if (config.debugmode) {
          console.log('Updating existing visit data');
        }
        const updatedVisitData = { ...visitData, lastVisit: new Date().toJSON() };
        setVisitData(updatedVisitData);
        if (config.debugmode) {
          console.log('Visit data updated:', updatedVisitData);
        }
      }

      // Ensure minimum spinner time
      const elapsedTime = Date.now() - startTime;
      const remainingTime = minLoadingTime - elapsedTime;
      if (remainingTime > 0) {
        if (config.debugmode) {
          console.log(`Waiting ${remainingTime}ms to meet minimum spinner time`);
        }
        await new Promise((resolve) => setTimeout(resolve, remainingTime));
      }

      if (config.debugmode) {
        console.log('Initialization complete, rendering App');
      }
      setLoading(false);
    }

    initialize();
  }, []); // Empty dependency array to run only on mount

  if (error) {
    return <div>Error: {error}</div>;
  }

  if (loading) {
    return <SpinnerLoadingAppData />;
  }

  return (
    <ConfigProvider config={configData}>
      <ShowDataProvider showData={showData}>
        <App />
      </ShowDataProvider>
    </ConfigProvider>
  );
}