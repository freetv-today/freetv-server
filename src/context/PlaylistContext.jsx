/**
 * @typedef {Object} PlaylistContextValue
 * @property {Array} playlists
 * @property {string|null} currentPlaylist
 * @property {Function} changePlaylist
 * @property {boolean} loading
 * @property {Array} showData
 * @property {Object|null} currentPlaylistData
 * @property {boolean} playlistSwitching
 */

/** @type {import('preact').Context<PlaylistContextValue>} */

import { useLocation } from 'preact-iso';
import { createContext } from 'preact';
import { useState, useEffect, useRef } from 'preact/hooks';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData.jsx';

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
    console.log('[PlaylistProvider] INIT currentPlaylist:', val);
    return val;
  });
  const [showData, setShowData] = useState(() => {
    const data = getLocalStorageJson('showData');
    const shows = data && data.shows ? data.shows : [];
    console.log('[PlaylistProvider] INIT showData:', shows);
    return shows;
  });
  const [loading, setLoading] = useState(false);
  const [playlistSwitching, setPlaylistSwitching] = useState(false);
  const [initializing, setInitializing] = useState(true);
  const [currentPlaylistData, setCurrentPlaylistData] = useState(() => {
    const data = getLocalStorageJson('showData');
    console.log('[PlaylistProvider] INIT currentPlaylistData:', data);
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
      if (config && config.lastupdated) return config;
      return null;
    }
    // Restore visitData or create if missing
    function ensureVisitData() {
      let visitData = getLocalStorageJson('visitData');
      if (!visitData) {
        const tstamp = new Date().toJSON();
        const code = Math.random().toString(36).slice(2) + Date.now().toString(36);
        visitData = { lastVisit: tstamp, start: tstamp, end: '', token: code };
        localStorage.setItem('visitData', JSON.stringify(visitData));
        sessionStorage.setItem('token', code);
      } else {
        visitData.lastVisit = new Date().toJSON();
        localStorage.setItem('visitData', JSON.stringify(visitData));
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
        console.log('[PlaylistProvider] useEffect (init):', {playlistLS, showDataLS, indexLastUpdated});
        if (
          !showDataLS ||
          !showDataLS.lastupdated ||
          !indexLastUpdated ||
          showDataLS.lastupdated !== indexLastUpdated ||
          !playlistLS
        ) {
          // If data is missing/stale, always reset to home and show initial spinner
          if (path !== '/') route('/');
          changePlaylist(defaultPlaylist, true, true); // isInitial = true, show spinner
        } else {
          setCurrentPlaylist(playlistLS);
          setShowData(showDataLS.shows || []);
          setCurrentPlaylistData(showDataLS);
          setInitializing(false);
          console.log('[PlaylistProvider] set state (init):', {playlistLS, showData: showDataLS.shows, currentPlaylistData: showDataLS});
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
          if (path !== '/') route('/');
          changePlaylist(defaultPlaylist, true, true); // isInitial = true, show spinner
        } else {
          console.log('[PlaylistProvider] useEffect (route change):', {playlistLS, showDataLS, indexLastUpdated});
        }
      });
    // eslint-disable-next-line
  }, [path]);

  // Change playlist and load its data
  function changePlaylist(filename, showSpinner = true, isInitial = false) {
    console.log('[PlaylistProvider] changePlaylist called:', { filename, showSpinner, isInitial });
    if (isInitial) {
      setInitializing(true);
    } else {
      setPlaylistSwitching(true);
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
        localStorage.setItem('showData', JSON.stringify(data));
        console.log('[PlaylistProvider] changePlaylist loaded:', { filename, data });
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