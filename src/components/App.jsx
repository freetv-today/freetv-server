// src/App.jsx
import { Router, Route } from 'preact-iso';
import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData.jsx';
import { AlertProvider } from '@context/AlertContext';
import { LayoutDefault } from '@components/Layouts/LayoutDefault.jsx';
import { LayoutSubnav } from '@components/Layouts/LayoutSubnav.jsx';
import { LayoutFullpage } from '@components/Layouts/LayoutFullpage.jsx';
import { LayoutSearch } from '@components/Layouts/LayoutSearch.jsx';
import { LayoutVidviewer } from '@components/Layouts/LayoutVidviewer.jsx';
import { Home } from '@pages/Home';
import { Recent } from '@pages/Recent';
import { Search } from '@pages/Search';
import { Help } from '@pages/Help';
import { Admin } from '@pages/Admin';
import { Category } from '@pages/Category';
import { NowPlaying } from '@pages/NowPlaying';
import { NotFound } from '@pages/_404.jsx';
import TestAlerts from '@pages/TestAlerts';
import '@/style.css';

// Predefined route components
const HomeRoute = () => <LayoutDefault><Home /></LayoutDefault>;
const RecentRoute = () => <LayoutSubnav><Recent /></LayoutSubnav>;
const CategoryRoute = () => <LayoutSubnav><Category /></LayoutSubnav>;
const SearchRoute = () => <LayoutSearch><Search /></LayoutSearch>;
const HelpRoute = () => <LayoutFullpage><Help /></LayoutFullpage>;
const AdminRoute = () => <LayoutFullpage><Admin /></LayoutFullpage>;
const NowPlayingRoute = () => <LayoutVidviewer><NowPlaying /></LayoutVidviewer>;
const NotFoundRoute = () => <LayoutFullpage><NotFound /></LayoutFullpage>;
const TestAlertsRoute = () => <LayoutFullpage><TestAlerts /></LayoutFullpage>;

export function App() {
  const { playlistSwitching } = useContext(PlaylistContext);

  // Only render spinner in LayoutFullpage during playlist switching, nothing else
  if (playlistSwitching) {
    return <LayoutFullpage><SpinnerLoadingAppData /></LayoutFullpage>;
  }

  // Only render the app when not switching playlists
  return (
    <AlertProvider>
      <main>
        <Router>
          <Route path="/" component={HomeRoute} exact />
          <Route path="/recent" component={RecentRoute} />
          <Route path="/category/:name" component={CategoryRoute} />
          <Route path="/search" component={SearchRoute} />
          <Route path="/help" component={HelpRoute} />
          <Route path="/admin" component={AdminRoute} />
          <Route path="/nowplaying" component={NowPlayingRoute} />
          <Route path="/test-alerts" component={TestAlertsRoute} />
          <Route default component={NotFoundRoute} />
        </Router>
      </main>
    </AlertProvider>
  );
}