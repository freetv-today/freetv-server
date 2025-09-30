import { useState, useEffect } from 'preact/hooks';

/**
 * useAdminPlaylistData - Hook for loading and switching playlists in the admin dashboard
 *
 * Returns: {
 *   playlists: Array of playlist meta from index.json
 *   currentPlaylist: filename of the current playlist
 *   showData: array of shows from the current playlist
 *   loading: boolean (true while fetching data)
 *   switchPlaylist: function to change playlists
 *   error: error message (if any)
 * }
 */
export function useAdminPlaylistData() {

  const [playlists, setPlaylists] = useState([]);
  const [currentPlaylist, setCurrentPlaylist] = useState(null);
  const [showData, setShowData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Load playlists and default playlist on mount
  useEffect(() => {
    async function load() {
      setLoading(true);
      setError(null);
      try {
        const indexRes = await fetch('/playlists/index.json');
        if (!indexRes.ok) throw new Error('Failed to load playlist index');
        const indexData = await indexRes.json();
        setPlaylists(indexData.playlists || []);
        let playlistName = localStorage.getItem('adminCurrentPlaylist') || indexData.default;
        setCurrentPlaylist(playlistName);
        const playlistRes = await fetch(`/playlists/${playlistName}`);
        if (!playlistRes.ok) throw new Error('Failed to load playlist data');
        const playlistData = await playlistRes.json();
        setShowData(playlistData.shows || []);
      } catch (err) {
        setError(err.message || 'Error loading playlists');
        setShowData([]);
      } finally {
        setLoading(false);
      }
    }
    load();
  }, []);

  // Switch playlist
  async function switchPlaylist(filename) {
    setLoading(true);
    setError(null);
    setCurrentPlaylist(filename);
    localStorage.setItem('adminCurrentPlaylist', filename);
    try {
      const playlistRes = await fetch(`/playlists/${filename}`);
      if (!playlistRes.ok) throw new Error('Failed to load playlist data');
      const playlistData = await playlistRes.json();
      setShowData(playlistData.shows || []);
    } catch (err) {
      setError(err.message || 'Error loading playlist');
      setShowData([]);
    } finally {
      setLoading(false);
    }
  }

  // Get the display title (dbtitle) of the current playlist
  function getCurrentPlaylistTitle() {
    const found = playlists.find(p => p.filename === currentPlaylist);
    return found ? found.dbtitle : currentPlaylist;
  }

  return { playlists, currentPlaylist, showData, loading, switchPlaylist, error, getCurrentPlaylistTitle };
}
