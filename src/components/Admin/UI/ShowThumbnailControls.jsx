import { useSingleThumbnail } from '@/hooks/useSingleThumbnail';
import { useEffect } from 'preact/hooks';

/**
 * ShowThumbnailControls - UI and logic for managing a single show's thumbnail
 * @param {Object} props
 * @param {string} props.imdb - IMDB ID
 * @param {string} props.title - Show title (for IMDB search)
 * @param {string} props.mode - 'add' or 'edit'
 * @param {function} [props.onThumbnailChange] - Optional callback when thumbnail changes
 */
export function ShowThumbnailControls({ imdb, title, mode, onThumbnailChange }) {

  const {
    loading,
    error,
    success,
    getPreviewUrl,
    fetchThumbnail,
    saveThumbnail,
    deleteThumbnail,
    setError,
    setSuccess,
  } = useSingleThumbnail(imdb);

  // Compute preview URL based on mode
  const previewUrl = getPreviewUrl(mode);

  // Notify parent if thumbnail changes
  useEffect(() => {
    if (typeof onThumbnailChange === 'function') {
      onThumbnailChange(previewUrl);
    }
  }, [previewUrl, onThumbnailChange]);

  // Handlers
  function handleSearchIMDB() {
    if (title) {
      const query = encodeURIComponent(title);
      window.open(`https://www.imdb.com/find?q=${query}&s=tt`, 'imdbSearch', 'width=900,height=700');
    }
  }

  function handleCheckIMDB() {
    if (imdb) {
      window.open(`https://www.imdb.com/title/${imdb}`, 'imdbShow', 'width=640,height=480');
    }
  }

  // UI
  return (
    <div className="mb-4 p-3 border rounded bg-light">
      <div className="fw-bold mb-2">Thumbnail Controls</div>
      <div className="d-flex align-items-center gap-2 mb-2">
        <button type="button" className="btn btn-outline-secondary btn-sm external-link-btn" onClick={handleSearchIMDB} disabled={!title}>Search IMDB by Title<img src="/src/assets/external-link.svg" className="ms-2" width="14" /></button>
        <button type="button" className="btn btn-outline-secondary btn-sm external-link-btn" onClick={handleCheckIMDB} disabled={!imdb}>View IMDB Page<img src="/src/assets/external-link.svg" className="ms-2" width="14" /></button>
      </div>
      <div className="d-flex align-items-center gap-2 mb-2">
        <button type="button" className="btn btn-warning btn-sm" onClick={fetchThumbnail} disabled={!imdb || loading}>Fetch Thumbnail</button>
        <button type="button" className="btn btn-primary btn-sm" onClick={saveThumbnail} disabled={!imdb || loading || !previewUrl || previewUrl.startsWith('/thumbs/')}>Save Thumbnail</button>
        {mode === 'edit' && (
          <button type="button" className="btn btn-danger btn-sm" onClick={deleteThumbnail} disabled={!imdb || loading || !previewUrl || previewUrl.startsWith('/temp/')}>Delete Thumbnail</button>
        )}
      </div>
      <div className="my-2">
        {loading && <span className="text-info">Loading...</span>}
        {error && <span className="text-danger ms-2">{error}</span>}
        {success && <span className="text-success ms-2">{success}</span>}
      </div>
      <div className="text-center mt-2">
        <img
          src={previewUrl || '/src/assets/vintage-tv.png'}
          alt="Thumbnail Preview"
          style={{ maxHeight: 180, border: '2px dashed #888', borderRadius: 8, background: '#fff' }}
        />
        <div className="small text-muted mt-1">Preview: {previewUrl ? (previewUrl.startsWith('/temp/') ? 'Temporary' : previewUrl.startsWith('/thumbs/') ? 'Saved' : 'Unknown') : 'None'}</div>
      </div>
    </div>
  );
}
