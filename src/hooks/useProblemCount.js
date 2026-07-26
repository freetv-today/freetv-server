import { useState, useEffect } from 'preact/hooks';
import { playlistSignal } from '@signals/playlistSignal';

/**
 * useProblemCount - Custom hook to get the count of reported problems and disabled items for the current playlist.
 * @returns {number} Total count of reported and disabled items.
 */

export function useProblemCount() {

  const [count, setCount] = useState(0);
  const { currentPlaylist, showData } = playlistSignal.value;

  useEffect(() => {
    const controller = new AbortController();
    let cancelled = false;

    if (typeof currentPlaylist !== 'string' || currentPlaylist === '') {
      setCount(0);
      return () => {
        cancelled = true;
        controller.abort();
      };
    }

    setCount(0);

    async function fetchCounts() {
      try {
        const encodedPlaylist = encodeURIComponent(currentPlaylist);
        const res = await fetch(
          `/api/admin/problem-count.php?playlist=${encodedPlaylist}&t=${Date.now()}`,
          { signal: controller.signal }
        );
        const responseText = await res.text();
        let data = null;

        try {
          data = JSON.parse(responseText);
        } catch {
          data = null;
        }

        if (
          !res.ok
          || data?.success !== true
          || !Number.isInteger(data.total)
          || data.total < 0
        ) {
          throw new Error('Failed to load problem count');
        }

        if (!cancelled) {
          setCount(data.total);
        }
      } catch (error) {
        if (!cancelled && error?.name !== 'AbortError') {
          setCount(0);
        }
      }
    }

    fetchCounts();

    return () => {
      cancelled = true;
      controller.abort();
    };
  }, [currentPlaylist, showData]);

  return count;
}
