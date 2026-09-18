import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

import { render } from 'preact';
import { LocationProvider } from 'preact-iso';
import { App } from '@components/App';

render(
  <LocationProvider>
    <App />
  </LocationProvider>,
  document.getElementById('app')
);