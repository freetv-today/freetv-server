import { useState, useEffect } from 'preact/hooks';
import { playlistSignal } from '@signals/playlistSignal';

/**
 * useProblemCount - Custom hook to get the count of reported problems and disabled items for the current playlist.
 * @returns {number} Total count of reported and disabled items.
 */

export function useProblemCount() {
  
  const [count, setCount] = useState(0);

  useEffect(() => {
    let isMounted = true;
    async function fetchCounts() {
      const { currentPlaylist, showData } = playlistSignal.value;
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
      
      // Count disabled in current showData
      if (showData && Array.isArray(showData)) {
        disabled = showData.filter(s => s.status === 'disabled').length;
      }
      
      if (isMounted) setCount(reported + disabled);
    }
    
    fetchCounts();
    return () => { isMounted = false; };
  }, [playlistSignal.value.currentPlaylist, playlistSignal.value.showData]);
  
  return count;
}
