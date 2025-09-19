import { useState, useCallback, useContext } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { PlaylistContext } from '@/context/PlaylistContext';
import { useLocalStorage } from '@hooks/useLocalStorage';

/**
 * useAdminShowActions - Custom hook providing admin show action handlers and modal state for Admin Dashboard and Search.
 * @returns {Object} Handlers and modal state:
 *   - handleEdit: Navigate to edit page
 *   - handleDelete: Open delete modal
 *   - handleTest: Open test modal
 *   - handleStatusToggle: Toggle show status
 *   - showDeleteModal, showToDelete, deleting, deleteError, handleDeleteConfirm, closeDeleteModal, showTestModal, testShow, closeTestModal
 */

export function useAdminShowActions() {
  
  const location = useLocation();
  const { currentPlaylist, changePlaylist } = useContext(PlaylistContext);
  // Modal state
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [showToDelete, setShowToDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);
  const [showTestModal, setShowTestModal] = useState(false);
  const [testShow, setTestShow] = useState(null);
  const [, setAdminMsg] = useLocalStorage('adminMsg', null);

  // Edit handler (navigate to edit page)
  const handleEdit = useCallback((show) => {
    location.route(`/dashboard/edit/${show.imdb}`);
  }, [location]);

  // Delete handler (open modal)
  const handleDelete = useCallback((show) => {
    setShowToDelete(show);
    setShowDeleteModal(true);
    setDeleteError(null);
  }, []);

  // Test handler (open modal)
  const handleTest = useCallback((show) => {
    setTestShow(show);
    setShowTestModal(true);
  }, []);

  // Status toggle handler
  const handleStatusToggle = useCallback(async (show) => {
    if (!currentPlaylist || !show || !show.imdb) {
      console.error('Function handleStatusToggle requires both a show and imdb value');
      return;
    }
    try {
      const res = await fetch('/api/admin/toggle-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          playlist: currentPlaylist,
          imdb: show.imdb
        })
      });
      let text, data;
      try {
        text = await res.text();
        data = JSON.parse(text);
      } catch {
        setAdminMsg({ type: 'danger', text: 'Status change failed: Invalid server response.' });
        return;
      }
      if (res.ok && data && data.success) {
        await changePlaylist(currentPlaylist, true, false);
      } else {
        setAdminMsg({ type: 'danger', text: data && data.error ? data.error : 'Status change failed.' });
      }
    } catch {
      setAdminMsg({ type: 'danger', text: 'Status change failed: Network or server error.' });
    }
  }, [currentPlaylist, changePlaylist, setAdminMsg]);

  // Confirm delete handler
  const handleDeleteConfirm = useCallback(async () => {
    if (!showToDelete || !currentPlaylist) return;
    setDeleting(true);
    setDeleteError(null);
    try {
      const res = await fetch('/api/admin/delete-show.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          playlist: currentPlaylist,
          identifier: showToDelete.identifier
        })
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        setDeleteError(data && data.error ? data.error : 'Delete failed.');
      } else {
        setShowDeleteModal(false);
        setShowToDelete(null);
        setAdminMsg({ type: 'success', text: 'Show deleted successfully.' });
        await changePlaylist(currentPlaylist, true, false);
      }
    } catch {
      setDeleteError('Delete failed.');
    } finally {
      setDeleting(false);
    }
  }, [showToDelete, currentPlaylist, changePlaylist, setAdminMsg]);

  // Modal close handlers
  const closeDeleteModal = useCallback(() => {
    setShowDeleteModal(false);
    setShowToDelete(null);
    setDeleteError(null);
  }, []);
  const closeTestModal = useCallback(() => {
    setShowTestModal(false);
    setTestShow(null);
  }, []);

  return {
    handleEdit,
    handleDelete,
    handleTest,
    handleStatusToggle,
    // Modal state/handlers
    showDeleteModal,
    showToDelete,
    deleting,
    deleteError,
    handleDeleteConfirm,
    closeDeleteModal,
    showTestModal,
    testShow,
    closeTestModal,
  };
}
