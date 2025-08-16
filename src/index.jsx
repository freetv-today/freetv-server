// src/index.jsx
import { render } from 'preact';
import { LocationProvider } from 'preact-iso';
import { PlaylistProvider } from '@/context/PlaylistContext.jsx';
import { App } from '@components/App.jsx';
import { ConfigProvider } from '@context/ConfigContext.jsx';

// Helper to get and parse localStorage JSON
function getLocalStorageJson(key) {
  try {
    const val = localStorage.getItem(key);
    return val ? JSON.parse(val) : null;
  } catch {
    return null;
  }
}

const configData = getLocalStorageJson('configData') || { offline: true };

const Root = (
  <LocationProvider>
    <ConfigProvider config={configData}>
      <PlaylistProvider>
        <App />
      </PlaylistProvider>
    </ConfigProvider>
  </LocationProvider>
);

render(Root, document.getElementById('app'));