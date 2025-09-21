import { render } from 'preact';
import { LocationProvider } from 'preact-iso';
import { loadAdsense } from '@/adsense.js';
import { AppLoader } from '@components/Loaders/AppLoader';

loadAdsense();

render(
  <LocationProvider>
    <AppLoader />
  </LocationProvider>,
  document.getElementById('app')
);