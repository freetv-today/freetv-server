import { useCallback, useEffect, useRef, useState } from 'preact/hooks';

const IMDB_PATTERN = /^tt\d+$/;

async function readJson(response) {
  const text = await response.text();
  try {
    return JSON.parse(text);
  } catch {
    throw new Error('Invalid server response');
  }
}

/**
 * Load and mutate the canonical thumbnail for one strict IMDb ID.
 * @param {string} imdb IMDb ID of the show
 * @returns {Object} Thumbnail state and actions
 */
export function useSingleThumbnail(imdb) {
  const isValidImdb = typeof imdb === 'string' && IMDB_PATTERN.test(imdb);
  const [exists, setExists] = useState(false);
  const [statusImdb, setStatusImdb] = useState(null);
  const [thumbnailUrl, setThumbnailUrl] = useState(null);
  const [globalUsage, setGlobalUsage] = useState({ show_count: 0, playlist_count: 0 });
  const [statusLoaded, setStatusLoaded] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [undoToken, setUndoToken] = useState(null);
  const [refreshToken, setRefreshToken] = useState(0);
  const requestId = useRef(0);

  useEffect(() => {
    setError(null);
    setSuccess(null);
    setUndoToken(null);
  }, [imdb]);

  useEffect(() => {
    const currentRequest = ++requestId.current;

    if (!isValidImdb) {
      setStatusImdb(null);
      setExists(false);
      setThumbnailUrl(null);
      setGlobalUsage({ show_count: 0, playlist_count: 0 });
      setStatusLoaded(false);
      setLoading(false);
      return;
    }

    setStatusLoaded(false);
    setLoading(true);
    fetch(`/api/admin/thumbnail-status.php?imdb=${encodeURIComponent(imdb)}`)
      .then(async response => {
        const data = await readJson(response);
        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Could not load thumbnail status');
        }
        if (requestId.current !== currentRequest) return;

        setExists(data.exists === true);
        setStatusImdb(imdb);
        setThumbnailUrl(data.exists ? data.thumbnail_url : null);
        setGlobalUsage(data.global_usage || { show_count: 0, playlist_count: 0 });
        setStatusLoaded(true);
      })
      .catch(fetchError => {
        if (requestId.current !== currentRequest) return;
        setExists(false);
        setStatusImdb(null);
        setThumbnailUrl(null);
        setGlobalUsage({ show_count: 0, playlist_count: 0 });
        setStatusLoaded(false);
        setError(fetchError.message || 'Could not load thumbnail status');
      })
      .finally(() => {
        if (requestId.current === currentRequest) setLoading(false);
      });
  }, [imdb, isValidImdb, refreshToken]);

  const refreshStatus = useCallback(() => {
    if (isValidImdb) setRefreshToken(token => token + 1);
  }, [isValidImdb]);

  const uploadThumbnail = useCallback(async (image, operation) => {
    if (!isValidImdb || !image) return false;

    const currentRequest = ++requestId.current;
    const requestImdb = imdb;
    setLoading(true);
    setError(null);
    setSuccess(null);

    const body = new window.FormData();
    body.append('imdb', requestImdb);
    body.append('operation', operation);
    body.append('image', image);
    if (undoToken) body.append('previous_undo_token', undoToken);

    try {
      const response = await fetch('/api/admin/upload-thumbnail.php', {
        method: 'POST',
        body,
      });
      const data = await readJson(response);
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Could not save the thumbnail');
      }
      if (typeof data.undo_token !== 'string') {
        throw new Error('Invalid server response');
      }
      if (requestId.current !== currentRequest) return false;

      setExists(true);
      setStatusImdb(requestImdb);
      setThumbnailUrl(data.thumbnail_url);
      setGlobalUsage(data.global_usage || { show_count: 0, playlist_count: 0 });
      setStatusLoaded(true);
      setUndoToken(data.undo_token);
      setSuccess(operation === 'replace'
        ? 'Thumbnail replaced successfully.'
        : 'Thumbnail uploaded successfully.');
      setRefreshToken(token => token + 1);
      return data;
    } catch (uploadError) {
      if (requestId.current === currentRequest) {
        setError(uploadError.message || 'Could not save the thumbnail');
      }
      return false;
    } finally {
      if (requestId.current === currentRequest) setLoading(false);
    }
  }, [imdb, isValidImdb, undoToken]);

  const undoThumbnail = useCallback(async () => {
    if (!isValidImdb || !undoToken) return false;

    const currentRequest = ++requestId.current;
    const requestImdb = imdb;
    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const response = await fetch('/api/admin/undo-thumbnail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ undo_token: undoToken }),
      });
      const data = await readJson(response);
      if (!response.ok || !data.success) {
        if (requestId.current === currentRequest
          && (response.status === 404 || response.status === 409)
        ) {
          setUndoToken(null);
        }
        throw new Error(data.message || 'Could not undo the thumbnail change');
      }
      if (data.imdb !== requestImdb || typeof data.exists !== 'boolean') {
        throw new Error('Invalid server response');
      }
      if (requestId.current !== currentRequest) return false;

      setExists(data.exists);
      setStatusImdb(requestImdb);
      setThumbnailUrl(data.exists ? data.thumbnail_url : null);
      setGlobalUsage(data.global_usage || { show_count: 0, playlist_count: 0 });
      setStatusLoaded(true);
      setUndoToken(null);
      setSuccess(data.exists ? 'Thumbnail restored.' : 'Thumbnail upload undone.');
      setRefreshToken(token => token + 1);
      return data;
    } catch (undoError) {
      if (requestId.current === currentRequest) {
        setError(undoError.message || 'Could not undo the thumbnail change');
      }
      return false;
    } finally {
      if (requestId.current === currentRequest) setLoading(false);
    }
  }, [imdb, isValidImdb, undoToken]);

  const isCurrentStatus = statusImdb === imdb;

  return {
    isValidImdb,
    exists: isCurrentStatus && exists,
    thumbnailUrl: isCurrentStatus ? thumbnailUrl : null,
    globalUsage: isCurrentStatus
      ? globalUsage
      : { show_count: 0, playlist_count: 0 },
    statusLoaded: isCurrentStatus && statusLoaded,
    loading,
    error,
    success,
    canUndo: Boolean(undoToken),
    refreshStatus,
    uploadThumbnail,
    undoThumbnail,
  };
}
