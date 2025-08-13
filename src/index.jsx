// src/index.jsx
import { render } from 'preact';
import { LocationProvider } from 'preact-iso';
import { LoadConfig } from '@components/Loaders/LoadConfig';
import { PlaylistProvider } from '@/context/PlaylistContext.jsx';

render(
  <LocationProvider>
    <PlaylistProvider>
      <LoadConfig />
    </PlaylistProvider>
  </LocationProvider>,
  document.getElementById('app')
);