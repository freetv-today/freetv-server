import { useState, useEffect, useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';
import { capitalizeFirstLetter } from '@/utils';
import { setAdminMsg } from '@/signals/adminMessageSignal';

/**
 * AdminDeleteShowModal - Modal for confirming deletion of a show
 * @param {Object} props
 * @param {boolean} props.show - Whether the modal is visible
 * @param {(reason: 'cancel' | 'save') => void} props.onClose - Function to close the modal
 * @param {Object} props.showData - The show object to delete
 * @param {boolean} props.deleting - Whether the delete is in progress
 * @param {string|null} props.error - Error message, if any
 * @param {() => void} props.onDeleteConfirm - Called when user confirms delete
 */
export function AdminDeleteShowModal({ show, onClose, showData, deleting, error, onDeleteConfirm }) {

  const [thumbnailSrc, setThumbnailSrc] = useState('/assets/vintage-tv.png');
  const { currentPlaylist, changePlaylist } = useContext(PlaylistContext);

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

  // Wrap onDeleteConfirm to set adminMsg on success, refresh playlist, and ensure index is rebuilt
  async function handleDelete() {
    // 1. Delete the show (calls update-show.php)
    const deleteResult = await onDeleteConfirm();
    // 2. Rebuild index.json (wait for it to finish)
    await fetch('/api/admin/playlist_utils.php', { method: 'POST' });
    // 3. Refresh playlist data in context (wait for it to finish)
    if (typeof changePlaylist === 'function' && currentPlaylist) {
      await changePlaylist(currentPlaylist, true, false);
    }
    // 4. Set adminMsg (short message) and close modal
    setAdminMsg({ type: 'success', text: 'Show deleted successfully.' });
    onClose('save');
  }

  return (
    <div className={`modal fade${show ? ' show d-block' : ''}`} tabIndex={-1} style={show ? { backgroundColor: 'rgba(0,0,0,0.5)' } : {}}>
      <div className="modal-dialog">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Delete Show</h5>
            <button type="button" className="btn-close" aria-label="Close" onClick={() => onClose('cancel')}></button>
          </div>
          <div className="modal-body">
            <p>Are you sure you want to delete the following show?</p>
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
