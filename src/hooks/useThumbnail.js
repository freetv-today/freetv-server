import { useState, useEffect } from 'preact/hooks';

/**
 * useThumbnail - Custom hook for managing show thumbnails, including fetching, saving, and searching.
 * @returns {Object} - Thumbnail state, actions, and statistics.
 */

export function useThumbnail() {

  const [shows, setShows] = useState([]);
  const [thumbnails, setThumbnails] = useState([]);
  const [withThumbnails, setWithThumbnails] = useState([]);
  const [missingThumbnailsList, setMissingThumbnailsList] = useState([]);
  const [selectedShow, setSelectedShow] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  // Search state
  const [searching, setSearching] = useState(false);
  const [searchResults, setSearchResults] = useState([]);
  const [searchError, setSearchError] = useState(null);

    // Load show data from localStorage (current playlist)
    useEffect(() => {
      try {
        const showData = window.localStorage.getItem('showData');
        if (showData) {
          const parsed = JSON.parse(showData);
          setShows(parsed.shows || []);
        }
      } catch {
        setError('Failed to load show data');
      }
    }, []);

    // Fetch thumbnail list from backend (POST showData)
    useEffect(() => {
      async function fetchThumbnails() {
        try {
          const showData = window.localStorage.getItem('showData');
          let showsArr = [];
          if (showData) {
            const parsed = JSON.parse(showData);
            showsArr = parsed.shows || [];
          }
          const res = await fetch('/api/admin/list_thumbnails.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ shows: showsArr })
          });
          const data = await res.json();
          setThumbnails(data.thumbnails || []);
          setWithThumbnails(data.with_thumbnails || []);
          setMissingThumbnailsList(data.missing_thumbnails || []);
        } catch {
          setError('Failed to load thumbnails');
        }
      }
      fetchThumbnails();
    }, [shows]);

    // Fetch thumbnail from backend
    async function fetchThumbnail(imdb, isUserAction = false) {
      setLoading(true);
      setError(null);
      setSuccess(null);
      try {
        const res = await fetch(`/api/admin/fetch_thumbnail.php?imdb=${imdb}`);
        let text = await res.text();
        let data;
        try {
          data = JSON.parse(text);
        } catch {
          setError('Invalid server response');
          return;
        }
        if (res.ok && data.status === 'success') {
          // Show a more accurate message if triggered by user
          if (isUserAction) {
            setSuccess('The thumbnail image was fetched successfully.');
          } else {
            setSuccess('Thumbnail fetched and saved!');
          }
          return data.image_url;
        } else {
          setError(data.message || 'Failed to fetch thumbnail');
        }
      } catch {
        setError('Network error');
      } finally {
        setLoading(false);
      }
    }

    // Upload thumbnail (dummy, to be implemented)
    // async function uploadThumbnail(file, imdb) {
    //   setLoading(true);
    //   setError(null);
    //   setSuccess(null);
    //   // TODO: Implement upload logic
    //   setTimeout(() => {
    //     setSuccess('Thumbnail uploaded!');
    //     setLoading(false);
    //   }, 1000);
    // }

    // Save thumbnail: move from /temp to /thumbs and refresh lists
    async function saveThumbnail(imdb) {
      setLoading(true);
      setError(null);
      setSuccess(null);
      try {
        const res = await fetch('/api/admin/save_thumbnail.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ imdb })
        });
        const data = await res.json();
        if (res.ok && data.status === 'success') {
          setSuccess('New thumbnail image has been saved!');
          // Refresh thumbnail list after save
          const showData = window.localStorage.getItem('showData');
          let showsArr = [];
          if (showData) {
            const parsed = JSON.parse(showData);
            showsArr = parsed.shows || [];
          }
          const res2 = await fetch('/api/admin/list_thumbnails.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ shows: showsArr })
          });
          const data2 = await res2.json();
          setThumbnails(data2.thumbnails || []);
          setWithThumbnails(data2.with_thumbnails || []);
          setMissingThumbnailsList(data2.missing_thumbnails || []);
          return data.thumb_url;
        } else {
          setError(data.message || 'Failed to save thumbnail');
        }
      } catch {
        setError('Network error');
      } finally {
        setLoading(false);
      }
    }

  // Search thumbnails by title or imdb
  async function searchThumbnails(query) {
    setSearching(true);
    setSearchError(null);
    setSearchResults([]);
    try {
      const showData = window.localStorage.getItem('showData');
      let showsArr = [];
      if (showData) {
        const parsed = JSON.parse(showData);
        showsArr = parsed.shows || [];
      }
      const res = await fetch('/api/admin/search_thumbnails.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query, shows: showsArr })
      });
      const data = await res.json();
      if (res.ok && data.status === 'success') {
        setSearchResults(data.results || []);
      } else {
        setSearchError(data.message || 'Search failed');
      }
    } catch {
      setSearchError('Network error');
    } finally {
      setSearching(false);
    }
  }

  // Calculate totals
  const totalShows = shows.length;
  const imdbCounts = {};
  shows.forEach(s => {
    if (s.imdb) {
      imdbCounts[s.imdb] = (imdbCounts[s.imdb] || 0) + 1;
    }
  });
  const uniqueImdbSet = new Set(Object.keys(imdbCounts));
  // IMDBs that are shared by more than one show
  const sharedImdbs = Object.keys(imdbCounts).filter(imdb => imdbCounts[imdb] > 1);
  const totalThumbnails = withThumbnails.length;
  const missingThumbnails = missingThumbnailsList.length;
  const showsSharingThumbnail = totalShows - uniqueImdbSet.size;

    // For list rendering
    const validThumbnails = withThumbnails.map(imdb => {
      return thumbnails.find(t => t.replace('.jpg','') === imdb);
    }).filter(Boolean);
    const missingThumbnailShows = shows.filter(s => missingThumbnailsList.includes(s.imdb));

    return {
      shows,
      thumbnails: validThumbnails,
      missingThumbnailShows,
      selectedShow,
      setSelectedShow,
      loading,
      error,
      success,
      fetchThumbnail,
      saveThumbnail,
      totalShows,
      totalThumbnails,
      missingThumbnails,
      showsSharingThumbnail,
      sharedImdbs,
      // Search
      searching,
      searchResults,
      searchError,
      searchThumbnails,
    };
}
