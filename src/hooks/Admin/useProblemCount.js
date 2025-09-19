import { useState, useEffect, useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';



/**
 * useProblemCount - Custom hook to get the count of reported problems and disabled items for the current playlist.
 * @returns {number} Total count of reported and disabled items.
 */

export function useProblemCount() {
  
  const { currentPlaylist, currentPlaylistData } = useContext(PlaylistContext);
  const [count, setCount] = useState(0);

  useEffect(() => {
    let isMounted = true;
    async function fetchCounts() {
      let reported = 0;
      let disabled = 0;
      try {
        // Fetch errors.json
        const res = await fetch('/logs/errors.json');
        const data = await res.json();
        if (Array.isArray(data.reports) && currentPlaylist) {
          reported = data.reports.filter(r => r.status === 'reported' && r.playlist === currentPlaylist).length;
        }
      } catch {
        // Ignore errors fetching errors.json
      }
      // Count disabled in currentPlaylistData
      if (currentPlaylistData && Array.isArray(currentPlaylistData.shows)) {
        disabled = currentPlaylistData.shows.filter(s => s.status === 'disabled').length;
      }
      if (isMounted) setCount(reported + disabled);
    }
    fetchCounts();
    return () => { isMounted = false; };
  }, [currentPlaylist, currentPlaylistData]);
  return count;
}
