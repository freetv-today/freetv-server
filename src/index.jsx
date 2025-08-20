import { render } from 'preact';
import { LocationProvider } from 'preact-iso';
import { AppLoader } from './components/Loaders/AppLoader.jsx';

render(
  <LocationProvider>
    <AppLoader />
  </LocationProvider>,
  document.getElementById('app')
);