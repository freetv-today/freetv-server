import { useEffect, useRef, useState } from 'preact/hooks';
import { playlistSignal } from '@signals/playlistSignal';

const EMPTY_SUMMARY = {
  number_of_shows: 0,
  number_of_thumbnails: 0,
  missing_thumbnails: 0,
  shared_thumbnails: 0,
};

function isValidSummary(summary) {
  return summary
    && Object.keys(EMPTY_SUMMARY).every(key => Number.isInteger(summary[key]) && summary[key] >= 0);
}

/**
 * Load selected-playlist thumbnail state derived by the backend from MariaDB
 * and the thumbnail filesystem.
 */
export function useThumbnail() {
  const currentPlaylist = playlistSignal.value.currentPlaylist;
  const currentPlaylistRef = useRef(currentPlaylist);
  currentPlaylistRef.current = currentPlaylist;
  const [existing, setExisting] = useState([]);
  const [missing, setMissing] = useState([]);
  const [shared, setShared] = useState([]);
  const [summary, setSummary] = useState(EMPTY_SUMMARY);
  const [selectedShow, setSelectedShow] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [searching, setSearching] = useState(false);
  const [searchResults, setSearchResults] = useState([]);
  const [searchError, setSearchError] = useState(null);

  useEffect(() => {
    let cancelled = false;

    setSelectedShow(null);
    setSearching(false);
    setSearchResults([]);
    setSearchError(null);

    async function loadThumbnails() {
      if (!currentPlaylist) {
        setExisting([]);
        setMissing([]);
        setShared([]);
        setSummary(EMPTY_SUMMARY);
        setError('No playlist selected');
        setLoading(false);
        return;
      }

      setLoading(true);
      setError(null);

      try {
        const response = await fetch(
          `/api/admin/list_thumbnails.php?playlist=${encodeURIComponent(currentPlaylist)}`
        );
        const data = await response.json();

        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Failed to load thumbnails');
        }
        if (
          !Array.isArray(data.existing)
          || !Array.isArray(data.missing)
          || !Array.isArray(data.shared)
          || !isValidSummary(data.summary)
        ) {
          throw new Error('Invalid thumbnail response');
        }

        if (!cancelled) {
          setExisting(data.existing);
          setMissing(data.missing);
          setShared(data.shared);
          setSummary(data.summary);
        }
      } catch (requestError) {
        if (!cancelled) {
          setExisting([]);
          setMissing([]);
          setShared([]);
          setSummary(EMPTY_SUMMARY);
          setError(requestError.message || 'Failed to load thumbnails');
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    loadThumbnails();
    return () => {
      cancelled = true;
    };
  }, [currentPlaylist]);

  async function searchThumbnails(query) {
    const searchQuery = query.trim();
    const requestedPlaylist = currentPlaylist;
    setSearchResults([]);
    setSearchError(null);

    if (!requestedPlaylist) {
      setSearchError('No playlist selected');
      return;
    }
    if (!searchQuery) {
      return;
    }

    setSearching(true);
    try {
      const response = await fetch(
        '/api/admin/search_thumbnails.php'
        + `?playlist=${encodeURIComponent(requestedPlaylist)}`
        + `&query=${encodeURIComponent(searchQuery)}`
      );
      const data = await response.json();

      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Thumbnail search failed');
      }
      if (!Array.isArray(data.results)) {
        throw new Error('Invalid thumbnail search response');
      }

      if (currentPlaylistRef.current === requestedPlaylist) {
        setSearchResults(data.results);
      }
    } catch (requestError) {
      if (currentPlaylistRef.current === requestedPlaylist) {
        setSearchError(requestError.message || 'Thumbnail search failed');
      }
    } finally {
      if (currentPlaylistRef.current === requestedPlaylist) {
        setSearching(false);
      }
    }
  }

  return {
    currentPlaylist,
    existing,
    missing,
    shared,
    summary,
    selectedShow,
    setSelectedShow,
    loading,
    error,
    searching,
    searchResults,
    searchError,
    searchThumbnails,
    totalShows: summary.number_of_shows,
    totalThumbnails: summary.number_of_thumbnails,
    missingThumbnails: summary.missing_thumbnails,
    sharedThumbnails: summary.shared_thumbnails,
  };
}
