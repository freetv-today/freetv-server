import { useState, useCallback } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { switchPlaylist } from '@signals/playlistSignal';
import { createPath } from '@/utils/env'; 

/**
 * useAdminShowActions - shared admin show actions for dashboard/search
 * @param {string} currentPlaylist - current playlist filename
 * @param {function} [setMessage] - function to set admin message (optional, defaults to global)
 * @param {function} [onDataChanged] - callback after data changes (optional)
 */

export function useAdminShowActions(currentPlaylist, setMessage = setAdminMsg, onDataChanged) {
  
  const location = useLocation();
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [showToDelete, setShowToDelete] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState(null);
  const [showTestModal, setShowTestModal] = useState(false);
  const [testShow, setTestShow] = useState(null);

  // Edit handler (navigate to edit page)
  const handleEdit = useCallback((show) => {
    location.route(createPath(`/dashboard/edit/${show.identifier}`));
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
    if (!show || !currentPlaylist) return;
    try {
      const res = await fetch('/api/admin/toggle-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          playlist: currentPlaylist,
          identifier: show.identifier
        })
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        setMessage({ type: 'danger', text: data && data.message ? data.message : 'Status update failed.' });
        return;
      }
      await switchPlaylist(currentPlaylist);
      setMessage({ type: 'success', text: 'Show status updated.' });
      if (onDataChanged) onDataChanged();
    } catch {
      setMessage({ type: 'danger', text: 'Status update failed.' });
    }
  }, [currentPlaylist, setMessage, onDataChanged]);

  // Confirm delete handler
  const handleDeleteConfirm = useCallback(async () => {
    if (!showToDelete || !currentPlaylist) return false;
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
        setDeleteError(data && data.message ? data.message : 'Delete failed.');
        setDeleting(false);
        return false;
      }
      await switchPlaylist(currentPlaylist);
      setDeleting(false);
      setShowDeleteModal(false);
      setShowToDelete(null);
      if (onDataChanged) onDataChanged();
      return true;
    } catch {
      setDeleteError('Delete failed.');
      setDeleting(false);
      return false;
    }
  }, [showToDelete, currentPlaylist, setMessage, onDataChanged]);

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
