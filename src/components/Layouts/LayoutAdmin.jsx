import { NavbarAdmin } from '@components/Navigation/NavbarAdmin';
import { useProblemCount } from '@hooks/useProblemCount';
import { useAdminSession } from '@hooks/useAdminSession';
import { usePlaylistInitialization } from '@hooks/usePlaylistInitialization';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { playlistSignal } from '@signals/playlistSignal';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { useEffect } from 'preact/hooks';

export function LayoutAdmin({ children }) {

  const problemCount = useProblemCount();
  const user = useAdminSession();
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

  let content = children;
  if (!playlistsInitialized || playlistsInitializing || playlistsLoading) {
    content = <SpinnerLoadingAppData />;
  } else if (playlistError) {
    content = <div className="alert alert-danger mt-4">{playlistError}</div>;
  }

  return (
    <>
      <NavbarAdmin problemCount={problemCount} />
      {content}
    </>
  );
}
