import { useSingleThumbnail } from '@/hooks/useSingleThumbnail';
import { useEffect, useRef, useState } from 'preact/hooks';
import { createPath } from '@/utils/env';

function pluralize(count, singular, plural) {
  return `${count} ${count === 1 ? singular : plural}`;
}

/**
 * Manual JPEG upload controls for one show's IMDb thumbnail.
 * @param {Object} props
 * @param {string} props.imdb IMDb ID
 * @param {boolean} [props.showPreview=true] Whether to render the inline preview
 * @param {function} [props.onThumbnailChange] Called when the canonical thumbnail changes
 */
export function ShowThumbnailControls({ imdb, showPreview = true, onThumbnailChange }) {
  const {
    isValidImdb,
    exists,
    thumbnailUrl,
    globalUsage,
    statusLoaded,
    loading,
    error,
    success,
    canUndo,
    uploadThumbnail,
    undoThumbnail,
  } = useSingleThumbnail(imdb);
  const [selectedFile, setSelectedFile] = useState(null);
  const fileInput = useRef(null);

  const isShared = globalUsage.show_count > 1 || globalUsage.playlist_count > 1;
  const usageText = `Used by ${pluralize(globalUsage.show_count, 'show', 'shows')} across ${pluralize(globalUsage.playlist_count, 'playlist', 'playlists')}`;

  useEffect(() => {
    setSelectedFile(null);
    if (fileInput.current) fileInput.current.value = '';
  }, [imdb]);

  const handleUpload = async () => {
    if (!selectedFile || !isValidImdb || !statusLoaded) return;

    const operation = exists ? 'replace' : 'upload';
    if (operation === 'replace') {
      const sharedWarning = isShared
        ? `\n\nIt is used by ${pluralize(globalUsage.show_count, 'show', 'shows')} across ${pluralize(globalUsage.playlist_count, 'playlist', 'playlists')}.\nReplacing it will change the thumbnail for all of them.`
        : '';
      if (!window.confirm(`Replace this thumbnail?${sharedWarning}`)) return;
    }

    const result = await uploadThumbnail(selectedFile, operation);
    if (result) {
      setSelectedFile(null);
      if (fileInput.current) fileInput.current.value = '';
      if (typeof onThumbnailChange === 'function') {
        onThumbnailChange(result.thumbnail_url, result);
      }
    }
  };

  const handleUndo = async () => {
    const result = await undoThumbnail();
    if (result && typeof onThumbnailChange === 'function') {
      onThumbnailChange(result.thumbnail_url, result);
    }
  };

  const controlsDisabled = !isValidImdb || !statusLoaded || loading;
  const previewUrl = exists && thumbnailUrl
    ? thumbnailUrl
    : createPath('/assets/vintage-tv.png');

  return (
    <div className="accordion" id="thumbnailControls">
      <div className="accordion-item">
        <h2 className="accordion-header">
          <button className="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
            <img src="/assets/edit.svg" className="me-3" height="30" />
            <span className="fw-bold">Live Thumbnail Editor</span>
          </button>
        </h2>
        <div id="collapseOne" className="accordion-collapse collapse" data-bs-parent="#thumbnailControls">
          <div className="accordion-body">
            <div className="mb-4 p-3 border rounded bg-light">
              <div className="alert alert-warning alert-dismissible fade show small" role="note">
                Image changes take effect immediately and aren't related to editing show data.
                <button type="button" className="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>
              {showPreview && (
                <div className="text-center mt-2">
                  <img
                    src={previewUrl}
                    alt={exists ? 'Current thumbnail' : 'Thumbnail placeholder'}
                    style={{ maxHeight: 180, maxWidth: '100%', border: '2px dashed #888', borderRadius: 8, background: '#fff' }}
                  />
                  <div className="small text-muted mt-1">
                    {exists ? 'Current thumbnail' : 'No thumbnail uploaded'}
                  </div>
                </div>
              )}
              {exists && isShared && (
                <div className={`small text-warning-emphasis mt-1${showPreview ? ' text-center' : ''}`}>
                  {usageText}
                </div>
              )}

              <div className="mt-3">
                <label className="form-label small fw-bold" htmlFor="thumbnailImage">
                  {exists ? 'Replace Current Thumbnail Image:' : 'Add Thumbnail Image:'}
                </label>
                <input
                  ref={fileInput}
                  id="thumbnailImage"
                  type="file"
                  className="form-control form-control-sm"
                  accept="image/jpeg,.jpg,.jpeg"
                  onChange={event => setSelectedFile(event.currentTarget.files?.[0] || null)}
                  disabled={controlsDisabled}
                />
                <div className="form-text">JPEG only, up to 10 MB. Images wider than 1000px are resized.</div>
              </div>

              <div className="d-flex align-items-center gap-2 mt-3">
                <button
                  type="button"
                  className={`btn btn-sm ${exists ? 'btn-warning' : 'btn-primary'}`}
                  onClick={handleUpload}
                  disabled={controlsDisabled || !selectedFile}
                >
                  {loading ? 'Working...' : exists ? 'Upload and Replace Thumbnail' : 'Upload JPG'}
                </button>
                {canUndo && (
                  <button
                    type="button"
                    className="btn btn-outline-secondary btn-sm"
                    onClick={handleUndo}
                    disabled={loading}
                  >
                    Undo
                  </button>
                )}
                {!isValidImdb && (
                  <span className="small text-secondary">Enter a valid IMDb ID to enable thumbnail upload.</span>
                )}
                {isValidImdb && loading && <span className="small text-info">Loading...</span>}
              </div>

              <div className="mt-2">
                {error && <span className="text-danger small">{error}</span>}
                {success && <span className="text-success small">{success}</span>}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
