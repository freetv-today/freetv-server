import { useState, useEffect, useContext } from 'preact/hooks';
import { useLocalStorage } from '@/hooks/useLocalStorage.jsx';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { capitalizeFirstLetter } from '@/utils';

/**
 * AdminDeleteShowModal - Modal for confirming deletion of a show
 * @param {Object} props
 * @param {boolean} props.show - Whether the modal is visible
 * @param {() => void} props.onClose - Function to close the modal
 * @param {Object} props.showData - The show object to delete
 * @param {boolean} props.deleting - Whether the delete is in progress
 * @param {string|null} props.error - Error message, if any
 * @param {() => void} props.onDeleteConfirm - Called when user confirms delete
 */
export function AdminDeleteShowModal({ show, onClose, showData, deleting, error, onDeleteConfirm }) {
  const [adminMsg, setAdminMsg] = useLocalStorage('adminMsg', null);
  const [thumbnailSrc, setThumbnailSrc] = useState('/src/assets/vintage-tv.png');
  const { currentPlaylist, changePlaylist } = useContext(PlaylistContext);

  useEffect(() => {
    if (showData && showData.imdb) {
      const img = new window.Image();
      img.src = `/src/assets/thumbs/${showData.imdb}.jpg`;
      img.onload = () => setThumbnailSrc(img.src);
      img.onerror = () => setThumbnailSrc('/src/assets/vintage-tv.png');
    } else {
      setThumbnailSrc('/src/assets/vintage-tv.png');
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
    onClose();
  }

  return (
    <div class={`modal fade${show ? ' show d-block' : ''}`} tabIndex={-1} style={show ? { backgroundColor: 'rgba(0,0,0,0.5)' } : {}}>
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Delete Show</h5>
            <button type="button" class="btn-close" aria-label="Close" onClick={onClose}></button>
          </div>
          <div class="modal-body">
            <p>Are you sure you want to delete the following show?</p>
            <div class="d-flex flex-row gap-2 mb-4 justify-content-center align-middle">
              <div class="m-0">
                <img src={thumbnailSrc} width="100" alt={`${showData.title} Thumbnail`} />
              </div>
              <div class="flex-grow-1 p-2">
                <ul>
                  <li><strong>Title:</strong> {showData.title}</li>
                  <li><strong>Category:</strong> {capitalizeFirstLetter(showData.category)}</li>
                  <li><strong>IMDB:</strong> {showData.imdb}</li>
                </ul>
              </div>
            </div>
            {error && <div class="alert alert-danger">{error}</div>}
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onClick={onClose} disabled={deleting}>Cancel</button>
            <button type="button" class="btn btn-danger" onClick={handleDelete} disabled={deleting}>
              {deleting ? 'Deleting...' : 'Delete'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
