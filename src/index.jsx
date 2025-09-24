import { render } from 'preact';
import { LocationProvider } from 'preact-iso';
import { AppLoader } from '@components/Loaders/AppLoader';
import { triggerAdReload } from '@/signals/adSignal';

// Trigger initial ad load after a short delay
setTimeout(() => triggerAdReload(), 500);

render(
  <LocationProvider>
    <AppLoader />
  </LocationProvider>,
  document.getElementById('app')
);