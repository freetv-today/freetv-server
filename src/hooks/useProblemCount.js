import { useState, useEffect } from 'preact/hooks';
import { playlistSignal } from '@signals/playlistSignal';

const PROBLEM_COUNT_REFRESH_EVENT = 'freetv:problem-count-refresh';

export function requestProblemCountRefresh() {
  window.dispatchEvent(new window.Event(PROBLEM_COUNT_REFRESH_EVENT));
}

/**
 * useProblemCount - Custom hook to get the count of reported problems and disabled items for the current playlist.
 * @returns {number} Total count of reported and disabled items.
 */

export function useProblemCount(enabled = true) {

  const [count, setCount] = useState(0);
  const [refreshVersion, setRefreshVersion] = useState(0);
  const { currentPlaylist, showData } = playlistSignal.value;

  useEffect(() => {
    if (!enabled) return undefined;
    const refresh = () => setRefreshVersion(version => version + 1);
    window.addEventListener(PROBLEM_COUNT_REFRESH_EVENT, refresh);
    return () => window.removeEventListener(PROBLEM_COUNT_REFRESH_EVENT, refresh);
  }, [enabled]);

  useEffect(() => {
    const controller = new window.AbortController();
    let cancelled = false;

    if (!enabled || typeof currentPlaylist !== 'string' || currentPlaylist === '') {
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
  }, [enabled, currentPlaylist, showData, refreshVersion]);

  return count;
}
