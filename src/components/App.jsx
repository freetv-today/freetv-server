// src/App.jsx
import { Router, Route } from 'preact-iso';
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

export function App() {
  return (
    <AlertProvider>
      <main>
        <Router>
          <Route path="/" component={HomeRoute} />
          <Route path="/recent" component={RecentRoute} />
          <Route path="/category/:name" component={CategoryRoute} />
          <Route path="/search" component={SearchRoute} />
          <Route path="/help" component={HelpRoute} />
          <Route path="/admin" component={AdminRoute} />
          <Route path="/nowplaying" component={NowPlayingRoute} />
          <Route default component={NotFoundRoute} />
        </Router>
      </main>
    </AlertProvider>
  );
}