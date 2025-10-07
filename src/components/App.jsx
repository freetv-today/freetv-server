import { Router, Route } from 'preact-iso';
import { LayoutAdmin } from '@components/Layouts/LayoutAdmin';
import { LayoutFullpageBlank } from '@components/Layouts/LayoutFullpageBlank';
import { AdminLogin } from '@pages/index';
import { Dashboard } from '@pages/dashboard';
import { useEffect } from 'preact/hooks';
import { loadPlaylists } from '@signals/playlistSignal';
import { AdminSearch } from '@pages/search';
import { AdminProblems } from '@pages/problems';
import { AdminSettings } from '@pages/settings';
import { AdminUsers } from '@pages/users';
import { AdminThumbnails } from '@/pages/thumbnails';
import { EditShow } from '@pages/EditShow';
import { AddShow } from '@pages/AddShow';
import { AddPlaylist } from '@pages/AddPlaylist';
import { NotFound } from '@pages/_404';
import { createPath } from '@/utils/env';
import '@/style.css'
import '@/admin.css';
import '@/utils/utils';

// Predefined route components:
const AdminLoginRoute = () => <LayoutFullpageBlank><AdminLogin /></LayoutFullpageBlank>;
const DashboardRoute = () => <LayoutAdmin><Dashboard /></LayoutAdmin>;
const EditShowRoute = () => <LayoutAdmin><EditShow /></LayoutAdmin>;
const AddShowRoute = () => <LayoutAdmin><AddShow /></LayoutAdmin>;
const AddPlaylistRoute = () => <LayoutAdmin><AddPlaylist /></LayoutAdmin>;
const AdminSearchRoute = () => <LayoutAdmin><AdminSearch /></LayoutAdmin>;
const AdminProblemsRoute = () => <LayoutAdmin><AdminProblems /></LayoutAdmin>;
const AdminSettingsRoute = () => <LayoutAdmin><AdminSettings /></LayoutAdmin>;
const AdminUsersRoute = () => <LayoutAdmin><AdminUsers /></LayoutAdmin>;
const AdminThumbsRoute = () => <LayoutAdmin><AdminThumbnails /></LayoutAdmin>;
const NotFoundRoute = () => <LayoutFullpageBlank><NotFound /></LayoutFullpageBlank>;

export function App() {

  useEffect(() => { loadPlaylists(); }, []);

  return (
    <main>
      <Router>
        <Route path={createPath("/")} component={AdminLoginRoute} />
        <Route path={createPath("/dashboard")} component={DashboardRoute} />
        <Route path={createPath("/dashboard/edit/:identifier")} component={EditShowRoute} />
        <Route path={createPath("/dashboard/add")} component={AddShowRoute} />
        <Route path={createPath("/dashboard/playlist")} component={AddPlaylistRoute} />
        <Route path={createPath("/dashboard/search")} component={AdminSearchRoute} />
        <Route path={createPath("/dashboard/problems")} component={AdminProblemsRoute} />
        <Route path={createPath("/dashboard/settings")} component={AdminSettingsRoute} />
        <Route path={createPath("/dashboard/users")} component={AdminUsersRoute} />
        <Route path={createPath("/dashboard/thumbnails")} component={AdminThumbsRoute} />
        <Route default component={NotFoundRoute} />
      </Router>
    </main>
  );
}