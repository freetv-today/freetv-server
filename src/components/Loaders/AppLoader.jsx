import { useState, useEffect } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { ConfigProvider } from '@/context/ConfigContext';
import { PlaylistProvider } from '@/context/PlaylistContext';
import { App } from '@components/App';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { ErrorPage } from '@pages/ErrorPage';
import { OfflinePage } from '@pages/OfflinePage';
import { shouldUpdateData, enforceMinLoadingTime, formatDateTime } from '@/utils';

// Loads config and show data from JSON then loads App and shows default page
export function AppLoader() {

    const infoFile = '/assets/app.nfo';
    const configFile = '/config.json';
    const minLoadingTime = 1200;  // show spinner for 1.2 seconds (minimum)

    const [config, setConfig] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const location = useLocation();

    useEffect(() => {
        async function loadConfig() {
            // Storage check
            try {
                localStorage.setItem('test', 'test');
                localStorage.removeItem('test');
            } catch {
                let msg = 'Storage test failed! Please enable storage on your device and reload.';
                setError({
                    type: 'Storage Error',
                    message: msg,
                });
                console.error(msg);
                setLoading(false);
                return;
            }
            // Try to get config from localStorage
            let storedConfig = null;
            try {
                const val = localStorage.getItem('configData');
                storedConfig = val ? JSON.parse(val) : null;
            } catch {}
            // Always fetch /config.json to check for updates
            let configData = storedConfig;
            let needsUpdate = false;
            let fetchedConfig = null;
            try {

                // Fetch config data
                const response = await fetch(configFile);
                if (!response.ok) throw new Error('Failed to fetch config file');
                fetchedConfig = await response.json();
                
                // Fetch app info data
                let fetchedInfo = null
                const appinfo = await fetch(infoFile);
                if (!appinfo.ok) throw new Error('Failed to fetch app info');
                fetchedInfo = await appinfo.json();
                localStorage.setItem('appInfo', JSON.stringify(fetchedInfo));  

                // If no config in storage or config is outdated, update it
                if (!storedConfig || shouldUpdateData(storedConfig, fetchedConfig)) {
                    needsUpdate = true;
                    configData = fetchedConfig;
                    localStorage.setItem('configData', JSON.stringify(fetchedConfig));
                    // DEV NOTE: we're using console.log here instead of hooks/useDebugLog.jsx because
                    // the hook depends on config data which hasn't been saved to local storage yet
                    if (configData.debugmode) {
                        // if debug mode is true, start logging
                        if (fetchedInfo) {
                            console.log(`Welcome to ${fetchedInfo.name} (version ${fetchedInfo.version})`);
                            console.log('DEBUG MODE: %cON', 'font-weight: bold; color: lime;');
                        }
                    } else {
                        // if debug mode is false, show app info in console instead
                        if (fetchedInfo) {
                            console.groupCollapsed(`Application Info`);
                            console.log(`Name: ${fetchedInfo.name}`);
                            console.log(`Version: ${fetchedInfo.version}`);
                            console.log(`Author: ${fetchedInfo.author}`);
                            console.log(`Email: ${fetchedInfo.email}`);
                            console.log(`Last Updated: ${formatDateTime(configData.lastupdated)}`);
                            console.groupEnd();                       
                        }
                    }
                }
            } catch {
                let msg = 'Unable to load configuration file. Please try again. If the problem persists, contact: support@freetv.today.';
                setError({
                    type: 'Configuration Error',
                    message: msg,
                });
                console.error(msg);
                setLoading(false);
                return;
            }
            if (needsUpdate) {
                if (configData.debugmode) {
                    console.log('Loading configuration data...');
                }
                const startTime = Date.now();
                await enforceMinLoadingTime(startTime, minLoadingTime);
            }
            setConfig(configData);
            setLoading(false);
        }
        loadConfig();
    }, []);

    // Only show OfflinePage if not on admin/dashboard routes
    const isAdminRoute = location && (
        location.url.startsWith('/admin') ||
        location.url.startsWith('/dashboard')
    );

    if (loading) return <SpinnerLoadingAppData />;
    if (error) return <ErrorPage type={error.type} message={error.message} />;
    if (config && config.offline && !isAdminRoute) return <OfflinePage />;

    return (
        <ConfigProvider config={config}>
            <PlaylistProvider>
                <App />
            </PlaylistProvider>
        </ConfigProvider>
    );
}