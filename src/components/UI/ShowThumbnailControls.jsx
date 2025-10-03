import { useSingleThumbnail } from '@/hooks/useSingleThumbnail';
import { useEffect } from 'preact/hooks';
import { createPath } from '@/utils/env';

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
      window.open(`https://www.imdb.com/find?q=${query}&s=tt`, 'imdbSearch', 'width=640,height=480');
    }
  }

  function handleCheckIMDB() {
    if (imdb) {
      window.open(`https://www.imdb.com/title/${imdb}`, 'imdbShow', 'width=640,height=480');
    }
  }

  // UI
  return (
  <div class="accordion" id="thumbnailControls">
    <div class="accordion-item">
      <h2 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
          <span className="fw-bold">Thumbnail Controls</span>
        </button>
      </h2>
      <div id="collapseOne" class="accordion-collapse collapse" data-bs-parent="#thumbnailControls">
        <div class="accordion-body">

          <div className="mb-4 p-3 border rounded bg-light">
            <div className="d-flex flex-row flex-column flex-md-row align-items-center gap-2 mb-2">
              <button type="button" className="btn btn-outline-primary btn-sm text-nowrap external-link-btn" onClick={handleSearchIMDB} disabled={!title}>
                Search IMDB by Title
                <img src={createPath('/assets/external-link.svg')} className="ms-2" width="14" />
              </button>
              <button type="button" className="btn btn-outline-primary btn-sm text-nowrap external-link-btn" onClick={handleCheckIMDB} disabled={!imdb}>
                View IMDB Page
                <img src={createPath('/assets/external-link.svg')} className="ms-2" width="14" />
              </button>
            </div>
            <div className="d-flex justify-content-center justify-content-md-start align-items-center gap-2 mb-2">        
              <button type="button" className="btn btn-warning btn-sm" onClick={fetchThumbnail} disabled={!imdb || loading}>Fetch Thumbnail</button>
              <button type="button" className="btn btn-primary btn-sm" onClick={saveThumbnail} disabled={!imdb || loading || !previewUrl || previewUrl.startsWith('/thumbs/')}>Save Thumbnail</button>
              {mode === 'edit' && (
                <button type="button" className="btn btn-danger btn-sm" onClick={deleteThumbnail} disabled={!imdb || loading || !previewUrl || previewUrl.startsWith('/temp/')}>Delete Thumbnail</button>
              )}
            </div>
            <div className="d-flex justify-content-center justify-content-md-start align-items-center">
              {(!title || !imdb) && (
                <span className="small text-secondary xsmall">
                  (Type Title and IMDB values to enable buttons)
                </span>
              )}
            </div>
            <div className="my-2">
              {loading && <span className="text-info">Loading...</span>}
              {error && <span className="text-danger ms-2">{error}</span>}
              {success && <span className="text-success ms-2">{success}</span>}
            </div>
            <div className="text-center mt-4">
              <img
                src={previewUrl || '/assets/vintage-tv.png'}
                alt="Thumbnail Preview"
                style={{ maxHeight: 180, border: '2px dashed #888', borderRadius: 8, background: '#fff' }}
              />
              <div className="small text-muted mt-1">Preview: {previewUrl ? (previewUrl.startsWith('/temp/') ? 'Temporary' : previewUrl.startsWith('/thumbs/') ? 'Saved' : 'Unknown') : 'None'}</div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
  );
}
