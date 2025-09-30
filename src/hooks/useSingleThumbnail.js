import { useState, useEffect, useCallback } from 'preact/hooks';

/**
 * useSingleThumbnail - Manage thumbnail for a single show by IMDB ID
 * @param {string} imdb - IMDB ID of the show
 * @returns {Object} Thumbnail state and actions
 */
export function useSingleThumbnail(imdb) {

  // Track if a thumbnail has been fetched or saved in this session (for Add mode UX)
  const [hasFetched, setHasFetched] = useState(false);
  const [thumbnailUrl, setThumbnailUrl] = useState(null);
  const [tempThumbnailUrl, setTempThumbnailUrl] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  // Load current thumbnail from /thumbs/ and /temp/ if exists, but do NOT set hasFetched here
  useEffect(() => {
    if (!imdb) {
      setThumbnailUrl(null);
      setTempThumbnailUrl(null);
      setHasFetched(false);
      return;
    }
    // Always reset hasFetched to false on imdb change
    setHasFetched(false);
    // Check if thumbnail exists in /thumbs/
    const url = `/thumbs/${imdb}.jpg`;
    fetch(url, { method: 'HEAD' })
      .then(res => {
        if (res.ok) {
          setThumbnailUrl(url);
        } else {
          setThumbnailUrl(null);
        }
      })
      .catch(() => setThumbnailUrl(null));
    // Check if temp thumbnail exists in /temp/
    const tempUrl = `/temp/${imdb}.jpg`;
    fetch(tempUrl, { method: 'HEAD' })
      .then(res => {
        if (res.ok) {
          setTempThumbnailUrl(tempUrl);
        } else {
          setTempThumbnailUrl(null);
        }
      })
      .catch(() => setTempThumbnailUrl(null));
  }, [imdb]);

  // Fetch thumbnail from backend (saves to /temp/)
  const fetchThumbnail = useCallback(async () => {
    if (!imdb) return;
    setLoading(true);
    setError(null);
    setSuccess(null);
    try {
      const res = await fetch(`/api/admin/fetch_thumbnail.php?imdb=${imdb}`);
      const text = await res.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch {
        setError('Invalid server response');
        return;
      }
      if (res.ok && data.status === 'success') {
        setTempThumbnailUrl(data.image_url || `/temp/${imdb}.jpg`);
        setHasFetched(true);
        setSuccess('Thumbnail fetched successfully.');
        console.log('[useSingleThumbnail] fetchThumbnail: fetched and set hasFetched=true');
      } else {
        setError(data.message || 'Failed to fetch thumbnail');
      }
    } catch {
      setError('Network error');
    } finally {
      setLoading(false);
    }
  }, [imdb]);

  // Save thumbnail (move from /temp/ to /thumbs/)
  const saveThumbnail = useCallback(async () => {
    if (!imdb) return;
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
        setThumbnailUrl(data.thumb_url || `/thumbs/${imdb}.jpg`);
        setTempThumbnailUrl(null);
        setHasFetched(true);
        setSuccess('Thumbnail saved successfully.');
        console.log('[useSingleThumbnail] saveThumbnail: saved and set hasFetched=true');
      } else {
        setError(data.message || 'Failed to save thumbnail');
      }
    } catch {
      setError('Network error');
    } finally {
      setLoading(false);
    }
  }, [imdb]);

  // Delete thumbnail from /thumbs/
  const deleteThumbnail = useCallback(async () => {
    if (!imdb) return;
    setLoading(true);
    setError(null);
    setSuccess(null);
    try {
      const res = await fetch('/api/admin/delete_thumbnail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ imdb })
      });
      const data = await res.json();
      if (res.ok && data.status === 'success') {
        setThumbnailUrl(null);
        setSuccess('Thumbnail deleted successfully.');
      } else {
        setError(data.message || 'Failed to delete thumbnail');
      }
    } catch {
      setError('Network error');
    } finally {
      setLoading(false);
    }
  }, [imdb]);

  /**
   * getPreviewUrl - Returns the correct preview URL based on mode
   * @param {string} mode - 'add' or 'edit'
   * @returns {string|null}
   */
  function getPreviewUrl(mode) {
    if (!imdb) return null;
    if (mode === 'edit') {
      // In edit mode, prefer saved thumbnail, then temp, else null
      return thumbnailUrl || tempThumbnailUrl || null;
    } else {
      // In add mode, only show preview if a thumbnail was fetched or saved
      if (hasFetched) {
        const url = tempThumbnailUrl || thumbnailUrl || null;
        console.log('[useSingleThumbnail] getPreviewUrl (add mode): hasFetched=true, returning', url);
        return url;
      } else {
        console.log('[useSingleThumbnail] getPreviewUrl (add mode): hasFetched=false, returning null');
        return null;
      }
    }
  }

  // For backward compatibility, default previewUrl to add-mode logic
  const previewUrl = getPreviewUrl;

  return {
    imdb,
    loading,
    error,
    success,
    thumbnailUrl,
    tempThumbnailUrl,
    previewUrl,
    getPreviewUrl,
    fetchThumbnail,
    saveThumbnail,
    deleteThumbnail,
    setError,
    setSuccess,
  };
}
