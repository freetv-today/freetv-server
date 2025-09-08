import { useState, useEffect, useContext } from 'preact/hooks';
import { useLocalStorage } from '@/hooks/useLocalStorage';
import { PlaylistContext } from '@/context/PlaylistContext';
import { capitalizeFirstLetter } from '@/utils';

/**
 * DeleteReportedProblemModal - Modal for confirming deletion of a reported problem (removes from playlist and errors.json)
 * @param {Object} props
 * @param {boolean} props.show - Whether the modal is visible
 * @param {(reason: 'cancel' | 'save') => void} props.onClose - Function to close the modal
 * @param {Object} props.showData - The show/problem object to delete
 * @param {boolean} props.deleting - Whether the delete is in progress
 * @param {string|null} props.error - Error message, if any
 * @param {() => void} props.onDeleteConfirm - Called when user confirms delete (optional, not used here)
 */
export function DeleteReportedProblemModal({
  show,
  onClose,
  showData,
  deleting = false,
  error = null,
  onDeleteConfirm // not used, but accepted for compatibility
}) {
  const [adminMsg, setAdminMsg] = useLocalStorage('adminMsg', null);
  const [thumbnailSrc, setThumbnailSrc] = useState('/src/assets/vintage-tv.png');
  const { currentPlaylist, changePlaylist } = useContext(PlaylistContext);
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);

  useEffect(() => {
    if (showData && showData.imdb) {
      const img = new window.Image();
      img.src = `/thumbs/${showData.imdb}.jpg`;
      img.onload = () => setThumbnailSrc(img.src);
      img.onerror = () => setThumbnailSrc('/src/assets/vintage-tv.png');
    } else {
      setThumbnailSrc('/src/assets/vintage-tv.png');
    }
  }, [showData]);

  if (!show || !showData) return null;

  async function handleDelete() {
    setIsDeleting(true);
    setDeleteError(null);
    try {
      // Call the new backend endpoint
      const res = await fetch('/api/admin/delete-reported-problem.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          playlist: currentPlaylist,
          identifier: showData.identifier,
        })
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Failed to delete problem');
      }
      // Rebuild index.json (wait for it to finish)
      await fetch('/api/admin/playlist_utils.php', { method: 'POST' });
      // Refresh playlist data in context
      if (typeof changePlaylist === 'function' && currentPlaylist) {
        await changePlaylist(currentPlaylist, true, false);
      }
      setAdminMsg({ type: 'success', text: 'Problem deleted successfully.' });
      setIsDeleting(false);
      onClose('save');
    } catch (err) {
      setDeleteError(err.message || 'Error deleting problem');
      setIsDeleting(false);
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
            <p>Are you sure you want to delete the following reported problem? This will remove the show from the playlist and from the error log.</p>
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
            {(error || deleteError) && <div className="alert alert-danger">{error || deleteError}</div>}
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={() => onClose('cancel')} disabled={isDeleting}>Cancel</button>
            <button type="button" className="btn btn-danger" onClick={handleDelete} disabled={isDeleting}>
              {isDeleting ? 'Deleting...' : 'Delete'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
