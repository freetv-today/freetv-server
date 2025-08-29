import { useEffect, useState, useContext } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { useAdminSession } from '@/hooks/useAdminSession.js';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { AdminDashboardTable } from '@/components/Admin/AdminDashboardTable.jsx';
import { AdminDashboardFilters } from '@/components/Admin/AdminDashboardFilters.jsx';
import { useLocalStorage } from '@/hooks/useLocalStorage';
import { NavbarSubNavAdmin } from '@/components/Navigation/NavbarSubNavAdmin.jsx';
import { AdminDeleteShowModal } from '@/components/Admin/AdminDeleteShowModal.jsx';
import { AdminPlaylistMetaModal } from '@/components/Admin/AdminPlaylistMetaModal.jsx';
import { AdminTestVideoModal } from '@/components/Admin/AdminTestVideoModal.jsx';
import { AdminMessage } from '@/components/Admin/AdminMessage.jsx';

export function Dashboard() {
    const user = useAdminSession();
    const { showData, playlists, currentPlaylist, changePlaylist, currentPlaylistData } = useContext(PlaylistContext);

    // Get current playlist meta info from full playlist data
    const playlistMeta = currentPlaylistData || null;
    const playlistName = playlistMeta ? playlistMeta.dbtitle : '';

    // State for sorting/filtering
    const [sortBy, setSortBy] = useState('title');
    const [sortOrder, setSortOrder] = useState('asc');
    const [filterCategory, setFilterCategory] = useState(null);
    const [hideDisabled, setHideDisabled] = useState(false);

    // State for delete modal and alert
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [showToDelete, setShowToDelete] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState(null);
    // AdminMessage now handles alert messages via localStorage

    // State for playlist meta modal
    const [showMetaModal, setShowMetaModal] = useState(false);
    const [metaSaving, setMetaSaving] = useState(false);
    const [metaError, setMetaError] = useState(null);

    // State for test video modal
    const [showTestModal, setShowTestModal] = useState(false);
    const [testShow, setTestShow] = useState(null);

    const location = useLocation();

    // Handle sorting when header is clicked
    function handleSort(column) {
        if (sortBy === column) {
            setSortOrder(prev => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortBy(column);
            setSortOrder('asc');
        }
    }

    function handleEdit(show) {
        location.route(`/dashboard/edit/${show.imdb}`);
    }

    function handleDelete(show) {
        setShowToDelete(show);
        setShowDeleteModal(true);
        setDeleteError(null);
    }

    // For AdminMessage
    const [adminMsg, setAdminMsg] = useLocalStorage('adminMsg', null);

    async function handleStatusToggle(show) {
        if (!currentPlaylist || !show || !show.imdb) {
            console.error('Function handleStatusToggle requires both a show and imdb value');
            return; 
        }
        try {
            // Use the correct endpoint
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
                console.log('[handleStatusToggle] Raw response:', text);
                data = JSON.parse(text);
            } catch (jsonErr) {
                setAdminMsg({ type: 'danger', text: 'Status change failed: Invalid server response.' });
                return;
            }
            
            // Log for debugging
            console.log('[handleStatusToggle] Response:', res.status, data);

            if (res.ok && data && data.success) {
                // Success: update context and show message
                await changePlaylist(currentPlaylist, true, false);
                const newStatus = data.status === 'active' ? 'Active' : 'Disabled';
                setAdminMsg({ type: 'success', text: `Status for "${show.title}" has been changed to ${newStatus}.` });
            } else {
                // Error: show error message from backend
                setAdminMsg({ type: 'danger', text: data && data.message ? data.message : 'Status change failed.' });
            }
        } catch (err) {
            setAdminMsg({ type: 'danger', text: 'Status change failed: Network or server error.' });
        }
    }

    function handleTest(show) {
        setTestShow(show);
        setShowTestModal(true);
    }

    async function handleDeleteConfirm() {
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
                setDeleteError(data && data.message ? data.message : 'Delete failed.');
            } else {
                setShowDeleteModal(false);
                setShowToDelete(null);
                // Rebuild the playlist index after successful delete
                await fetch('/api/admin/playlist_utils.php', { method: 'POST' });
                // Success message handled by AdminMessage in modal
            }
        } catch (err) {
            setDeleteError('Delete failed.');
        } finally {
            setDeleting(false);
        }
    }

    function handleOpenMetaModal() {
        setShowMetaModal(true);
        setMetaError(null);
    }

    async function handleSaveMeta(updatedMeta) {
        setMetaSaving(true);
        setMetaError(null);
        try {
            const res = await fetch('/api/admin/update-meta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    playlist: currentPlaylist,
                    meta: updatedMeta
                })
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                setMetaError(data && data.message ? data.message : 'Save failed.');
            } else {
                setShowMetaModal(false);
                // Success message handled by AdminMessage in modal
            }
        } catch (err) {
            setMetaError('Save failed.');
        } finally {
            setMetaSaving(false);
        }
    }

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard";
    }, []);

    if (!user) return null;

    return (
        <div className="container mt-3">
            <h1 class="text-center mb-2">Admin Dashboard</h1>
            <AdminMessage />
            <NavbarSubNavAdmin onMetaClick={handleOpenMetaModal} />
            <AdminDashboardFilters
                shows={showData || []}
                filterCategory={filterCategory}
                setFilterCategory={setFilterCategory}
                hideDisabled={hideDisabled}
                setHideDisabled={setHideDisabled}
                playlistName={playlistName}
            />
            <AdminDashboardTable
                shows={showData || []}
                onEdit={handleEdit}
                onDelete={handleDelete}
                onStatusToggle={handleStatusToggle}
                onTest={handleTest}
                sortBy={sortBy}
                sortOrder={sortOrder}
                filterCategory={filterCategory}
                hideDisabled={hideDisabled}
                onSort={handleSort}
            />
            <AdminTestVideoModal
                show={showTestModal}
                onClose={() => { setShowTestModal(false); setTestShow(null); }}
                showData={testShow}
            />
            <AdminDeleteShowModal
                show={showDeleteModal}
                onClose={() => { setShowDeleteModal(false); setShowToDelete(null); }}
                showData={showToDelete}
                deleting={deleting}
                error={deleteError}
                onDeleteConfirm={handleDeleteConfirm}
            />
            <AdminPlaylistMetaModal
                show={showMetaModal}
                onClose={() => setShowMetaModal(false)}
                meta={playlistMeta}
                saving={metaSaving}
                error={metaError}
                onSave={handleSaveMeta}
            />
        </div>
    );
}