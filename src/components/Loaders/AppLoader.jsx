import { useState, useEffect } from 'preact/hooks';
import { ConfigProvider } from '@/context/ConfigContext.jsx';
import { PlaylistProvider } from '@/context/PlaylistContext.jsx';
import { App } from '@components/App.jsx';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData.jsx';
import { ErrorPage } from '@components/UI/ErrorPage.jsx';
import { OfflinePage } from '@components/UI/OfflinePage.jsx';
import { shouldUpdateData, enforceMinLoadingTime } from '@/utils.js';

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
        setError({
            type: 'Storage Error',
            message: 'Device storage is required. Please enable local storage in your browser.',
        });
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
        }
        } catch (err) {
        setError({
            type: 'Configuration Error',
            message: 'Unable to load configuration. Please try again later.',
        });
        setLoading(false);
        return;
        }

        if (needsUpdate) {
        // Show spinner for at least 1.2s if updating config
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