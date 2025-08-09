// src/index.jsx
import { render } from 'preact';
import { LocationProvider } from 'preact-iso';
import { LoadConfig } from '@components/Loaders/LoadConfig';

render(
  <LocationProvider>
    <LoadConfig />
  </LocationProvider>,
  document.getElementById('app')
);