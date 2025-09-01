import { useState, useEffect } from 'preact/hooks';
import { ConfigProvider } from '@/context/ConfigContext';
import { PlaylistProvider } from '@/context/PlaylistContext';
import { App } from '@components/App';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { ErrorPage } from '@components/UI/ErrorPage';
import { OfflinePage } from '@components/UI/OfflinePage';
import { shouldUpdateData, enforceMinLoadingTime, formatDateTime } from '@/utils';

export function AppLoader() {

    const [config, setConfig] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    useEffect(() => {
    async function loadConfig() {
        // Storage check
        try {
            localStorage.setItem('test', 'test');
            localStorage.removeItem('test');
        } catch (e) {
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
            const response = await fetch('/config.json');
            if (!response.ok) throw new Error('Failed to fetch config');
            fetchedConfig = await response.json();
            // If no config in storage or config is outdated, update it
            if (!storedConfig || shouldUpdateData(storedConfig, fetchedConfig)) {
                needsUpdate = true;
                configData = fetchedConfig;
                localStorage.setItem('configData', JSON.stringify(fetchedConfig));
                if (configData.debugmode) {
                    // if debug mode is true, start logging
                    console.log(`Welcome to ${configData.name} (version ${configData.version})`);
                    console.log('DEBUG MODE: %cON', 'font-weight: bold; color: lime;');
                } else {
                    // if debug mode is false, show app info
                    console.groupCollapsed(`Application Info`);
                    console.log(`Name: ${configData.name}`);
                    console.log(`Version: ${configData.version}`);
                    console.log(`Author: ${configData.author}`);
                    console.log(`Last Updated: ${formatDateTime(configData.lastupdated)}`);
                    console.groupEnd();
                }
            }
        } catch (err) {
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
            // Show spinner for at least 1.2s if loading data
            const minLoadingTime = 1200;
            const startTime = Date.now();
            await enforceMinLoadingTime(startTime, minLoadingTime);
        }
        setConfig(configData);
        setLoading(false);
    }
    loadConfig();
    }, []);

    if (loading) return <SpinnerLoadingAppData />;
    if (error) return <ErrorPage type={error.type} message={error.message} />;
    if (config && config.offline) return <OfflinePage />;

    return (
    <ConfigProvider config={config}>
        <PlaylistProvider>
            <App />
        </PlaylistProvider>
    </ConfigProvider>
    );
}