import { useLocation } from 'preact-iso';
import { createContext } from 'preact';
import { useState, useEffect, useRef } from 'preact/hooks';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { useDebugLog } from '@/hooks/useDebugLog';
import { toastSignal } from '@/signals/toastSignal';
import { generateNewCode } from '@/utils';

/**
 * @type {import('preact').Context<PlaylistContextValue>}
 * @typedef {Object} PlaylistContextValue
 * @property {Array} playlists
 * @property {string|null} currentPlaylist
 * @property {Function} changePlaylist
 * @property {boolean} loading
 * @property {Array} showData
 * @property {Object|null} currentPlaylistData
 * @property {boolean} playlistSwitching
 */

export const PlaylistContext = createContext(
  /** @type {PlaylistContextValue} */ ({
    playlists: [],
    currentPlaylist: null,
    changePlaylist: () => {},
    loading: false,
    showData: [],
    currentPlaylistData: null,
    playlistSwitching: false,
  })
);

export function PlaylistProvider({ children }) {
  const log = useDebugLog();

  // Synchronously hydrate from localStorage
  function getLocalStorageJson(key) {
    try {
      const val = localStorage.getItem(key);
      return val ? JSON.parse(val) : null;
    } catch {
      return null;
    }
  }

  const [playlists, setPlaylists] = useState([]);
  const [currentPlaylist, setCurrentPlaylist] = useState(() => {
    const val = localStorage.getItem('playlist') || null;
    return val;
  });
  const [showData, setShowData] = useState(() => {
    const data = getLocalStorageJson('showData');
    const shows = data && data.shows ? data.shows : [];
    return shows;
  });
  const [loading, setLoading] = useState(false);
  const [playlistSwitching, setPlaylistSwitching] = useState(false);
  const [initializing, setInitializing] = useState(true);
  const [currentPlaylistData, setCurrentPlaylistData] = useState(() => {
    const data = getLocalStorageJson('showData');
    return data || null;
  });
  const { route, path } = useLocation();
  const hasInitialized = useRef(false);

  // On mount, fetch playlists index and only fetch playlist if missing/stale
  // Initialization effect (runs once)
  useEffect(() => {
    if (hasInitialized.current) return;
    hasInitialized.current = true;
    // Restore configData
    function getConfigFromLocalStorage() {
      const config = getLocalStorageJson('configData');
      if (config && config.lastupdated) {
        log('Using saved data from local storage');
        return config; 
      } 
      return null;
    }
    // Restore visitData or create if missing
    function ensureVisitData() {
      let visitData = getLocalStorageJson('visitData');
      if (!visitData) {
        log('Creating new visitData timestamp...');
        const tstamp = new Date().toJSON();
        const code = generateNewCode();
        visitData = { lastVisit: tstamp, start: tstamp, end: '', token: code };
        localStorage.setItem('visitData', JSON.stringify(visitData));
        sessionStorage.setItem('token', code);
        log('Saving visitData to local storage');
      } else {
        visitData.lastVisit = new Date().toJSON();
        localStorage.setItem('visitData', JSON.stringify(visitData));
        log('Updating visitData timestamp');
      }
    }
    ensureVisitData();
    // Fetch config if missing
    let configData = getConfigFromLocalStorage();
    if (!configData) {
      fetch('/config.json')
        .then(res => res.json())
        .then(cfg => {
          localStorage.setItem('configData', JSON.stringify(cfg));
          log('Saving configuration data to local storage');
        });
    }
    fetch('/playlists/index.json')
      .then(res => res.json())
      .then(data => {
        setPlaylists(data.playlists || []);
        const saved = localStorage.getItem('playlist');
        const defaultPlaylist = saved || data.default || (data.playlists[0] && data.playlists[0].filename);
        let showDataLS = getLocalStorageJson('showData');
        let playlistLS = localStorage.getItem('playlist') || defaultPlaylist;
        // Find the correct playlist entry in index.json
        const playlistEntry = (data.playlists || []).find(p => p.filename === playlistLS);
        const indexLastUpdated = playlistEntry ? playlistEntry.lastupdated : null;
        if (
          !showDataLS ||
          !showDataLS.lastupdated ||
          !indexLastUpdated ||
          showDataLS.lastupdated !== indexLastUpdated ||
          !playlistLS
        ) {
          // If data is missing/stale, always reset to home and show initial spinner
          if (path !== '/') { route('/'); } 
          changePlaylist(defaultPlaylist, true, true); // isInitial = true, show spinner
        } else {
          setCurrentPlaylist(playlistLS);
          setShowData(showDataLS.shows || []);
          setCurrentPlaylistData(showDataLS);
          setInitializing(false);
        }
      });
    // eslint-disable-next-line
  }, []);

  // Route change effect: re-check data on navigation (for client-side nav)
  useEffect(() => {
    if (initializing) return;
    // On every route change, check if showData is missing/stale
    fetch('/playlists/index.json')
      .then(res => res.json())
      .then(data => {
        const saved = localStorage.getItem('playlist');
        const defaultPlaylist = saved || data.default || (data.playlists[0] && data.playlists[0].filename);
        let showDataLS = getLocalStorageJson('showData');
        let playlistLS = localStorage.getItem('playlist') || defaultPlaylist;
        const playlistEntry = (data.playlists || []).find(p => p.filename === playlistLS);
        const indexLastUpdated = playlistEntry ? playlistEntry.lastupdated : null;
        if (
          !showDataLS ||
          !showDataLS.lastupdated ||
          !indexLastUpdated ||
          showDataLS.lastupdated !== indexLastUpdated ||
          !playlistLS
        ) {
          // If data is missing/stale, always reset to home and show initial spinner
          toastSignal.value = { ...toastSignal.value, show: false };
          if (path !== '/') { route('/'); } 
          changePlaylist(defaultPlaylist, true, true); // isInitial = true, show spinner
        }
      });
    // eslint-disable-next-line
  }, [path]);

  // Change playlist and load its data
  function changePlaylist(filename, showSpinner = true, isInitial = false) {
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
        setShowData(data.shows || []);
        setCurrentPlaylistData(data);
        localStorage.setItem('playlist', filename);
        log(`Current playlist is: ${filename}`);
        localStorage.setItem('showData', JSON.stringify(data));
        const elapsed = Date.now() - startTime;
        const wait = Math.max(0, minLoadingTime - elapsed);
        setTimeout(() => {
          setLoading(false);
          // If on /dashboard/edit/:imdb, always redirect to /dashboard after playlist change
          const isDashboardEdit = path && path.startsWith('/dashboard/edit/');
          if (isInitial) {
            setInitializing(false);
            if (isDashboardEdit) {
              route('/dashboard');
            } else if (path && path.startsWith('/dashboard')) {
              route(path); // stay on other dashboard subpages
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
        }, wait);
      });
  }

  // Render logic: block rendering until initialization check is complete
  if (initializing) return <SpinnerLoadingAppData />;
  return (
    <PlaylistContext.Provider value={{
      playlists,
      currentPlaylist,
      showData,
      currentPlaylistData,
      loading,
      changePlaylist,
      playlistSwitching,
    }}>
      {children}
    </PlaylistContext.Provider>
  );
}