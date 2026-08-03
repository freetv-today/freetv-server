import { Router, Route } from 'preact-iso';
import { LayoutAdmin } from '@components/Layouts/LayoutAdmin';
import { LayoutFullpageBlank } from '@components/Layouts/LayoutFullpageBlank';
import { AdminLogin } from '@pages/index';
import { Dashboard } from '@pages/dashboard';
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
const DashboardRoute = () => <LayoutAdmin minimumRole={'viewer'}><Dashboard /></LayoutAdmin>;
const EditShowRoute = () => <LayoutAdmin minimumRole={'editor'}><EditShow /></LayoutAdmin>;
const AddShowRoute = () => <LayoutAdmin minimumRole={'editor'}><AddShow /></LayoutAdmin>;
const AddPlaylistRoute = () => <LayoutAdmin minimumRole={'editor'}><AddPlaylist /></LayoutAdmin>;
const AdminSearchRoute = () => <LayoutAdmin minimumRole={'viewer'}><AdminSearch /></LayoutAdmin>;
const AdminProblemsRoute = () => <LayoutAdmin minimumRole={'editor'}><AdminProblems /></LayoutAdmin>;
const AdminSettingsRoute = () => <LayoutAdmin minimumRole={'admin'}><AdminSettings /></LayoutAdmin>;
const AdminUsersRoute = () => <LayoutAdmin minimumRole={'admin'}><AdminUsers /></LayoutAdmin>;
const AdminThumbsRoute = () => <LayoutAdmin minimumRole={'editor'}><AdminThumbnails /></LayoutAdmin>;
const NotFoundRoute = () => <LayoutFullpageBlank><NotFound /></LayoutFullpageBlank>;

export function App() {

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
