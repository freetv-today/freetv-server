import { NavbarAdmin } from '@components/Navigation/NavbarAdmin';
import { useProblemCount } from '@hooks/useProblemCount';
import { useAdminSession } from '@hooks/useAdminSession';
import { usePlaylistInitialization } from '@hooks/usePlaylistInitialization';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { playlistSignal } from '@signals/playlistSignal';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { useEffect } from 'preact/hooks';
import { AdminSessionProvider, hasMinimumRole } from '@context/AdminSessionContext';
import { ErrorPage } from '@pages/ErrorPage';

export function LayoutAdmin({ children, minimumRole = 'viewer' }) {

  const user = useAdminSession();
  const canManageReports = hasMinimumRole(user, 'editor');
  const problemCount = useProblemCount(canManageReports);
  usePlaylistInitialization(user);
  const {
    initialized: playlistsInitialized,
    initializing: playlistsInitializing,
    loading: playlistsLoading,
    error: playlistError,
  } = playlistSignal.value;

  useEffect(() => {
    if (user === false) {
      setAdminMsg({ type: 'danger', text: 'Your session has expired!' });
    }
  }, [user]);

  if (user === null || user === false) return null;

  const forbidden = !hasMinimumRole(user, minimumRole);
  let content = children;
  if (forbidden) {
    content = (
      <ErrorPage
        type="403 Forbidden"
        message="You do not have permission to access this page."
        homePath="/dashboard"
        homeLabel="Back to Admin"
      />
    );
  } else if (!playlistsInitialized || playlistsInitializing || playlistsLoading) {
    content = <SpinnerLoadingAppData />;
  } else if (playlistError) {
    content = <div className="alert alert-danger mt-4">{playlistError}</div>;
  }

  return (
    <AdminSessionProvider user={user}>
      <NavbarAdmin problemCount={problemCount} />
      {content}
    </AdminSessionProvider>
  );
}
