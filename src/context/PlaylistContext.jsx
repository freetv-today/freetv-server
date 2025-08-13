/**
 * @typedef {Object} PlaylistContextValue
 * @property {Array} playlists
 * @property {string|null} currentPlaylist
 * @property {Function} changePlaylist
 * @property {boolean} loading
 * @property {Array} showData
 */

/** @type {import('preact').Context<PlaylistContextValue>} */

import { useLocation } from 'preact-iso';
import { createContext } from 'preact';
import { useState, useEffect } from 'preact/hooks';

export const PlaylistContext = createContext(
  /** @type {PlaylistContextValue} */ ({
    playlists: [],
    currentPlaylist: null,
    changePlaylist: () => {},
    loading: false,
    showData: [],
  })
);

export function PlaylistProvider({ children }) {
  const [playlists, setPlaylists] = useState([]);
  const [currentPlaylist, setCurrentPlaylist] = useState(null);
  const [showData, setShowData] = useState([]);
  const [loading, setLoading] = useState(false);
  const { route } = useLocation();

  // Load playlists index on mount
  useEffect(() => {
    fetch('/playlists/index.json')
      .then(res => res.json())
      .then(data => {
        setPlaylists(data.playlists || []);
        // Get default playlist from localStorage or index.json
        const saved = localStorage.getItem('playlist');
        const defaultPlaylist = saved || data.default || (data.playlists[0] && data.playlists[0].filename);
        if (defaultPlaylist) {
          changePlaylist(defaultPlaylist, false);
        }
      });
  }, []);

  // Change playlist and load its data
  function changePlaylist(filename, showSpinner = true) {
    setLoading(showSpinner);
    fetch(`/playlists/${filename}`)
      .then(res => res.json())
      .then(data => {
        setCurrentPlaylist(filename);
        setShowData(data.shows || []);
        localStorage.setItem('playlist', filename);
        localStorage.setItem('showData', JSON.stringify(data.shows || []));
        setLoading(false);
        // Redirect to home after playlist change
        route('/');
      });
  }

  // On mount, restore showData from localStorage if available
  useEffect(() => {
    const savedData = localStorage.getItem('showData');
    if (savedData) setShowData(JSON.parse(savedData));
  }, []);

  return (
    <PlaylistContext.Provider value={{
      playlists,
      currentPlaylist,
      showData,
      loading,
      changePlaylist,
    }}>
      {children}
    </PlaylistContext.Provider>
  );
}