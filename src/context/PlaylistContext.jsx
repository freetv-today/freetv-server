import { useLocation } from 'preact-iso';
import { createContext } from 'preact';
import { useState, useEffect, useRef } from 'preact/hooks';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { useDebugLog } from '@/hooks/useDebugLog';
import { toastSignal } from '@/signals/toastSignal';
import { useConfig } from '@/context/ConfigContext';
import { useLocalStorage } from '@/hooks/useLocalStorage';

/**
 * PlaylistContext - Provides playlist data and actions to components.
 * Use PlaylistProvider to wrap your app or relevant subtree.
 * Access playlist state and actions with useContext(PlaylistContext).
 */

/**
 * @type {import('preact').Context<PlaylistContextValue>}
 * @typedef {Object} PlaylistContextValue
 * @property {Array} playlists
 * @property {string|null} currentPlaylist
 * @property {Function} changePlaylist  // (filename, showSpinner, isInitial, suppressRoute)
 * @property {boolean} loading
 * @property {Array} showData
 * @property {Object|null} currentPlaylistData
 * @property {boolean} playlistSwitching
 * @property {Function} refreshPlaylists
 */

export const PlaylistContext = createContext({
    playlists: [],
    currentPlaylist: null,
    changePlaylist: () => {},
    refreshPlaylists: () => {},
    loading: false,
    showData: [],
    currentPlaylistData: null,
    playlistSwitching: false,
});

/**
 * PlaylistProvider - Context provider for playlist state and actions.
 * @param {Object} props
 * @param {import('preact').ComponentChildren} props.children
 */

export function PlaylistProvider({ children }) {

  const log = useDebugLog();
  const { route, path } = useLocation();
  const hasInitialized = useRef(false);

  // Use useLocalStorage for playlist and showData
  const [playlists, setPlaylists] = useState([]);
  const [currentPlaylist, setCurrentPlaylist] = useLocalStorage('playlist', null);
  const [showData, setShowData] = useLocalStorage('showData', { shows: [] });
  const [loading, setLoading] = useState(false);
  const [playlistSwitching, setPlaylistSwitching] = useState(false);
  const [initializing, setInitializing] = useState(true);

  // Initialization effect (runs once)
  useEffect(() => {
    if (hasInitialized.current) return;
    hasInitialized.current = true;
    fetch('/playlists/index.json')
          .then(res => res.json())
          .then(data => {
            setPlaylists(data.playlists || []);
            const saved = currentPlaylist || data.default || (data.playlists[0] && data.playlists[0].filename);
            let playlistLS = currentPlaylist || saved;
            const playlistEntry = (data.playlists || []).find(p => p.filename === playlistLS);
            const indexLastUpdated = playlistEntry ? playlistEntry.lastupdated : null;
            if (
              !showData ||
              !showData.lastupdated ||
              !indexLastUpdated ||
              showData.lastupdated !== indexLastUpdated ||
              !playlistLS
            ) {
              if (path !== '/') { route('/'); }
              changePlaylist(saved, true, true); // isInitial = true, show spinner
            } else {
              setCurrentPlaylist(playlistLS);
              setInitializing(false);
            }
          });
        // eslint-disable-next-line
  }, []);

  // Route change effect: re-check data on navigation (for client-side nav)
  useEffect(() => {
      if (initializing) return;
      fetch('/playlists/index.json')
        .then(res => res.json())
        .then(data => {
          const saved = currentPlaylist || data.default || (data.playlists[0] && data.playlists[0].filename);
          let playlistLS = currentPlaylist || saved;
          const playlistEntry = (data.playlists || []).find(p => p.filename === playlistLS);
          const indexLastUpdated = playlistEntry ? playlistEntry.lastupdated : null;
          if (
            !showData ||
            !showData.lastupdated ||
            !indexLastUpdated ||
            showData.lastupdated !== indexLastUpdated ||
            !playlistLS
          ) {
            toastSignal.value = { ...toastSignal.value, show: false };
            if (path !== '/') { route('/'); }
            changePlaylist(saved, true, true); // isInitial = true, show spinner
          }
        });
      // eslint-disable-next-line
  }, [path]);

  /**
   * Change playlist and load its data
   * @param {string} filename
   * @param {boolean} [showSpinner=true]
   * @param {boolean} [isInitial=false]
   * @param {boolean} [suppressRoute=false] - If true, do not perform any route() navigation after loading
   */

  function changePlaylist(filename, showSpinner = true, isInitial = false, suppressRoute = false) {
    if (isInitial) {
      setInitializing(true);
      log('Loading default playlist data');
    } else {
      setPlaylistSwitching(true);
      log('Switching playlists...');
    }
    setLoading(showSpinner);
    const minLoadingTime = 1200;
    const startTime = Date.now();
    fetch(`/playlists/${filename}`)
      .then(res => res.json())
      .then(data => {
        setCurrentPlaylist(filename);
        setShowData(data);
        log(`Current playlist is: ${filename}`);
        const elapsed = Date.now() - startTime;
        const wait = Math.max(0, minLoadingTime - elapsed);
        setTimeout(() => {
          setLoading(false);
          if (!suppressRoute) {
            const isDashboardEdit = path && path.startsWith('/dashboard/edit/');
            if (isInitial) {
              setInitializing(false);
              if (isDashboardEdit) {
                route('/dashboard');
              } else if (path && path.startsWith('/dashboard')) {
                route(path);
              } else {
                route('/');
              }
            } else {
              setPlaylistSwitching(false);
              if (isDashboardEdit) {
                route('/dashboard');
              } else if (path && path.startsWith('/dashboard')) {
                route(path);
              } else {
                route('/');
              }
            }
          } else {
            if (isInitial) setInitializing(false);
            else setPlaylistSwitching(false);
          }
        }, wait);
      });
  }

  // Refresh playlists from index.json
  function refreshPlaylists() {
    fetch('/playlists/index.json')
      .then(res => res.json())
      .then(data => {
        setPlaylists(data.playlists || []);
      });
  }

  // Render logic: block rendering until initialization check is complete
  if (initializing) return <SpinnerLoadingAppData />;
  return (
    <PlaylistContext.Provider value={{
      playlists,
      currentPlaylist,
      showData: showData.shows || [],
      currentPlaylistData: showData,
      loading,
      changePlaylist,
      refreshPlaylists,
      playlistSwitching,
    }}>
      {children}
    </PlaylistContext.Provider>
  );
}