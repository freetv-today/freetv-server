import { useEffect } from 'preact/hooks';
import { initializePlaylists } from '@signals/playlistSignal';

/**
 * Initializes the shared Admin playlist state after authentication succeeds.
 */
export function usePlaylistInitialization(user) {
  useEffect(() => {
    if (user !== null && user !== false) {
      initializePlaylists(600);
    }
  }, [user]);
}
