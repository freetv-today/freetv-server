// src/App.jsx
import { Router, Route } from 'preact-iso';
import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData.jsx';
// Layout templates 
import { LayoutDefault } from '@components/Layouts/LayoutDefault.jsx';
import { LayoutSubnav } from '@components/Layouts/LayoutSubnav.jsx';
import { LayoutFullpage } from '@components/Layouts/LayoutFullpage.jsx';
import { LayoutFullpageBlank } from '@components/Layouts/LayoutFullpageBlank.jsx';
import { LayoutSearch } from '@components/Layouts/LayoutSearch.jsx';
import { LayoutVidviewer } from '@components/Layouts/LayoutVidviewer.jsx';
import { LayoutAdmin } from '@components/Layouts/LayoutAdmin.jsx';
// Front-end pages
import { Home } from '@pages/Home';
import { Recent } from '@pages/Recent';
import { Search } from '@pages/Search';
import { Help } from '@pages/Help';
import { Category } from '@pages/Category';
import { NowPlaying } from '@pages/NowPlaying';
// Back-end pages
import { AdminLogin } from '@pages/Admin/index.jsx';
import { Dashboard } from '@pages/Admin/dashboard.jsx';
import { AdminSearch } from '@pages/Admin/search.jsx';
import { AdminProblems } from '@pages/Admin/problems.jsx';
import { AdminSettings } from '@pages/Admin/settings.jsx';
import { AdminUsers } from '@pages/Admin/users.jsx';
import { EditShow } from '@pages/Admin/EditShow.jsx';
import { AddShow } from '@pages/Admin/AddShow.jsx';
// Other pages
import { NotFound } from '@pages/_404.jsx';
import { TestPage } from '@/pages/TestPage.jsx';
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
  const ctx = useContext(PlaylistContext);
  const { playlistSwitching, currentPlaylist, currentPlaylistData } = ctx;

  // Only render spinner in LayoutFullpage during playlist switching, nothing else
  if (playlistSwitching) {
    return <LayoutFullpage><SpinnerLoadingAppData /></LayoutFullpage>;
  }

  // Only render the app when not switching playlists
  return (
      <main>
        <Router>
          <Route path="/" component={HomeRoute} exact />
          <Route path="/recent" component={RecentRoute} />
          <Route path="/category/:name" component={CategoryRoute} />
          <Route path="/search" component={SearchRoute} />
          <Route path="/help" component={HelpRoute} />
          <Route path="/admin" component={AdminLoginRoute} />
          <Route path="/dashboard" component={DashboardRoute} />
          <Route path="/dashboard/edit/:imdb" component={EditShowRoute} />
          <Route path="/dashboard/add" component={AddShowRoute} />
          <Route path="/dashboard/search" component={AdminSearchRoute} />
          <Route path="/dashboard/problems" component={AdminProblemsRoute} />
          <Route path="/dashboard/settings" component={AdminSettingsRoute} />
          <Route path="/dashboard/users" component={AdminUsersRoute} />
          <Route path="/nowplaying" component={NowPlayingRoute} />
          <Route path="/test" component={TestPageRoute} />
          <Route default component={NotFoundRoute} />
        </Router>
      </main>
  );
}