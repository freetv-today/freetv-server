import { useEffect, useState, useContext } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { useAdminSession } from '@/hooks/useAdminSession.js';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { AdminDashboardTable } from '@/components/Admin/AdminDashboardTable.jsx';
import { AdminDashboardFilters } from '@/components/Admin/AdminDashboardFilters.jsx';

import { NavbarSubNavAdmin } from '@/components/Navigation/NavbarSubNavAdmin.jsx';
import { AdminDeleteShowModal } from '@/components/Admin/AdminDeleteShowModal.jsx';
import { AdminPlaylistMetaModal } from '@/components/Admin/AdminPlaylistMetaModal.jsx';
import { AdminTestVideoModal } from '@/components/Admin/AdminTestVideoModal.jsx';

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
    const [alertMsg, setAlertMsg] = useState(null);
    const [alertType, setAlertType] = useState('success');

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
        // Navigate to edit page for this show
        location.route(`/dashboard/edit/${show.imdb}`);
    }

    function handleDelete(show) {
        setShowToDelete(show);
        setShowDeleteModal(true);
        setDeleteError(null);
    }

    async function handleStatusToggle(show) {
        if (!currentPlaylist || !show || !show.imdb) return;
        try {
            const res = await fetch('/api/admin/toggle-status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    playlist: currentPlaylist,
                    imdb: show.imdb
                })
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                setAlertType('danger');
                setAlertMsg(data && data.message ? data.message : 'Failed to toggle status.');
            } else {
                const newStatus = data.status === 'active' ? 'Active' : 'Disabled';
                setAlertType('success');
                setAlertMsg(`Status for "${show.title}" has been changed to ${newStatus}. Playlist data must be reloaded...`);
            }
        } catch (err) {
            setAlertType('danger');
            setAlertMsg('Failed to toggle status.');
        }
    }

    function handleTest(show) {
        setTestShow(show);
        setShowTestModal(true);
    }

    // Delete show API call
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
                setAlertType('danger');
                setAlertMsg(data && data.message ? data.message : 'Delete failed.');
            } else {
                setShowDeleteModal(false);
                setShowToDelete(null);
                setAlertType('success');
                setAlertMsg('Show deleted successfully. Page will be reloaded to refresh playlist data.');
                // Do NOT reload playlist data yet; wait for alert dismiss
            }
        } catch (err) {
            setDeleteError('Delete failed.');
            setAlertType('danger');
            setAlertMsg('Delete failed.');
        } finally {
            setDeleting(false);
        }
    }


    // Handler for Playlist Meta Data button (from NavbarSubNavAdmin)
    function handleOpenMetaModal() {
        setShowMetaModal(true);
        setMetaError(null);
    }

    // Handler for saving meta data
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
                setAlertType('danger');
                setAlertMsg(data && data.message ? data.message : 'Save failed.');
            } else {
                setShowMetaModal(false);
                setAlertType('success');
                setAlertMsg('Playlist meta data updated successfully. Page will be reloaded to refresh playlist data.');
            }
        } catch (err) {
            setMetaError('Save failed.');
            setAlertType('danger');
            setAlertMsg('Save failed.');
        } finally {
            setMetaSaving(false);
        }
    }

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard";
    }, []);

    if (!user) return <div className="container mt-5">Loading...</div>;

    return (
        <div className="container mt-3">
            <h1 class="text-center mb-2">Admin Dashboard</h1>
            <NavbarSubNavAdmin onMetaClick={handleOpenMetaModal} />
            <AdminDashboardFilters
                shows={showData || []}
                filterCategory={filterCategory}
                setFilterCategory={setFilterCategory}
                hideDisabled={hideDisabled}
                setHideDisabled={setHideDisabled}
                playlistName={playlistName}
            />
            {alertMsg && (
                <div className={`alert alert-${alertType} mt-2`} role="alert">
                    {alertMsg}
                    <button
                        type="button"
                        className="btn-close float-end"
                        aria-label="Close"
                        onClick={() => {
                            setAlertMsg(null);
                            if (alertType === 'success') {
                                changePlaylist(currentPlaylist, true, false);
                            }
                        }}
                    ></button>
                </div>
            )}
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