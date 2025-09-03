import { Router, Route } from 'preact-iso';
import { useContext, useEffect } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { handleKeyPress } from '@/utils';
// Layout templates 
import { LayoutDefault } from '@components/Layouts/LayoutDefault';
import { LayoutSubnav } from '@components/Layouts/LayoutSubnav';
import { LayoutFullpage } from '@components/Layouts/LayoutFullpage';
import { LayoutFullpageBlank } from '@components/Layouts/LayoutFullpageBlank';
import { LayoutSearch } from '@components/Layouts/LayoutSearch';
import { LayoutVidviewer } from '@components/Layouts/LayoutVidviewer';
import { LayoutAdmin } from '@components/Admin/Layouts/LayoutAdmin';
// Front-end pages
import { Home } from '@pages/Home';
import { Recent } from '@pages/Recent';
import { Search } from '@pages/Search';
import { Help } from '@pages/Help';
import { Category } from '@pages/Category';
import { NowPlaying } from '@pages/NowPlaying';
// Back-end pages
import { AdminLogin } from '@pages/Admin/index';
import { Dashboard } from '@pages/Admin/dashboard';
import { AdminSearch } from '@pages/Admin/search';
import { AdminProblems } from '@pages/Admin/problems';
import { AdminSettings } from '@pages/Admin/settings';
import { AdminUsers } from '@pages/Admin/users';
import { EditShow } from '@pages/Admin/EditShow';
import { AddShow } from '@pages/Admin/AddShow';
// Other pages
import { NotFound } from '@pages/_404';
import { TestPage } from '@/pages/TestPage';
// Default style sheet
import '@/style.css';

// Predefined route components
const HomeRoute = () => <LayoutDefault><Home /></LayoutDefault>;
const RecentRoute = () => <LayoutSubnav><Recent /></LayoutSubnav>;
const CategoryRoute = () => <LayoutSubnav><Category /></LayoutSubnav>;
const SearchRoute = () => <LayoutSearch><Search /></LayoutSearch>;
const HelpRoute = () => <LayoutFullpage><Help /></LayoutFullpage>;
const AdminLoginRoute = () => <LayoutFullpageBlank><AdminLogin /></LayoutFullpageBlank>;
const DashboardRoute = () => <LayoutAdmin><Dashboard /></LayoutAdmin>;
const EditShowRoute = () => <LayoutAdmin><EditShow /></LayoutAdmin>;
const AddShowRoute = () => <LayoutAdmin><AddShow /></LayoutAdmin>;
const AdminSearchRoute = () => <LayoutAdmin><AdminSearch /></LayoutAdmin>;
const AdminProblemsRoute = () => <LayoutAdmin><AdminProblems /></LayoutAdmin>;
const AdminSettingsRoute = () => <LayoutAdmin><AdminSettings /></LayoutAdmin>;
const AdminUsersRoute = () => <LayoutAdmin><AdminUsers /></LayoutAdmin>;
const NowPlayingRoute = () => <LayoutVidviewer><NowPlaying /></LayoutVidviewer>;
const TestPageRoute = () => <LayoutFullpage><TestPage /></LayoutFullpage>;
const NotFoundRoute = () => <LayoutFullpage><NotFound /></LayoutFullpage>;

export function App() {

  useEffect(() => {
    window.addEventListener('keydown', handleKeyPress);
    return () => window.removeEventListener('keydown', handleKeyPress);
  }, []);

  const ctx = useContext(PlaylistContext);
  const { playlistSwitching } = ctx;

  // Render spinner during playlist switching
  if (playlistSwitching) {
    return <LayoutFullpage><SpinnerLoadingAppData /></LayoutFullpage>;
  }

  // Render the app when not switching playlists
  return (
      <main>
        <Router>
          {/* Front-end */}
          <Route path="/" component={HomeRoute} exact />
          <Route path="/recent" component={RecentRoute} />
          <Route path="/category/:name" component={CategoryRoute} />
          <Route path="/search" component={SearchRoute} />
          <Route path="/help" component={HelpRoute} />
          <Route path="/nowplaying" component={NowPlayingRoute} />
          {/* Back-end */}
          <Route path="/admin" component={AdminLoginRoute} />
          <Route path="/dashboard" component={DashboardRoute} />
          <Route path="/dashboard/edit/:imdb" component={EditShowRoute} />
          <Route path="/dashboard/add" component={AddShowRoute} />
          <Route path="/dashboard/search" component={AdminSearchRoute} />
          <Route path="/dashboard/problems" component={AdminProblemsRoute} />
          <Route path="/dashboard/settings" component={AdminSettingsRoute} />
          <Route path="/dashboard/users" component={AdminUsersRoute} />
          {/* Other routes */}
          <Route path="/test" component={TestPageRoute} />
          <Route default component={NotFoundRoute} />
        </Router>
      </main>
  );
}