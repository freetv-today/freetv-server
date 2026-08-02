import { useState, useEffect } from 'preact/hooks';
import { capitalizeFirstLetter } from '@/utils/utils';

/**
 * DeleteReportedProblemModal - Modal for confirming deletion of a durable report row
 * @param {Object} props
 * @param {boolean} props.show - Whether the modal is visible
 * @param {(reason: 'cancel' | 'save') => void} props.onClose - Function to close the modal
 * @param {Object} props.showData - The show/problem object to delete
 * @param {boolean} props.deleting - Whether the delete is in progress
 * @param {string|null} props.error - Error message, if any
 * @param {() => Promise<boolean>} props.onDeleteConfirm - Deletes the selected report
 */

export function DeleteReportedProblemModal({
  show,
  onClose,
  showData,
  deleting = false,
  error = null,
  onDeleteConfirm
}) {

  const [thumbnailSrc, setThumbnailSrc] = useState('/assets/vintage-tv.png');

  useEffect(() => {
    if (showData && showData.imdb) {
      const img = new window.Image();
      img.src = `/thumbs/${showData.imdb}.jpg`;
      img.onload = () => setThumbnailSrc(img.src);
      img.onerror = () => setThumbnailSrc('/assets/vintage-tv.png');
    } else {
      setThumbnailSrc('/assets/vintage-tv.png');
    }
  }, [showData]);

  if (!show || !showData) return null;

  async function handleDelete() {
    const deleted = await onDeleteConfirm();
    if (deleted) {
      onClose('save');
    }
  }
  
  return (
    <div className={`modal fade${show ? ' show d-block' : ''}`} tabIndex={-1} style={show ? { backgroundColor: 'rgba(0,0,0,0.5)' } : {}}>
      <div className="modal-dialog">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Delete Reported Problem</h5>
            <button type="button" className="btn-close" aria-label="Close" onClick={() => onClose('cancel')}></button>
          </div>
          <div className="modal-body">
            <p>Are you sure you want to delete this report? The show will remain in the playlist.</p>
            <div className="d-flex flex-row gap-2 mb-4 justify-content-center align-middle">
              <div className="m-0">
                <img src={thumbnailSrc} width="100" alt={`${showData.title} Thumbnail`} />
              </div>
              <div className="flex-grow-1 p-2">
                <ul>
                  <li><strong>Title:</strong> {showData.title}</li>
                  <li><strong>Category:</strong> {capitalizeFirstLetter(showData.category)}</li>
                  <li><strong>IMDB:</strong> {showData.imdb}</li>
                </ul>
              </div>
            </div>
            {error && <div className="alert alert-danger">{error}</div>}
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={() => onClose('cancel')} disabled={deleting}>Cancel</button>
            <button type="button" className="btn btn-danger" onClick={handleDelete} disabled={deleting}>
              {deleting ? 'Deleting...' : 'Delete'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
