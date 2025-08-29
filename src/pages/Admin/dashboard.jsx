import { useEffect, useState, useContext } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { useAdminSession } from '@/hooks/useAdminSession.js';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { AdminDashboardTable } from '@/components/Admin/AdminDashboardTable.jsx';
import { AdminDashboardFilters } from '@/components/Admin/AdminDashboardFilters.jsx';
import { NavbarSubNavAdmin } from '@/components/Navigation/NavbarSubNavAdmin.jsx';
import { AdminMessage } from '@/components/Admin/AdminMessage.jsx';
import { AdminTestVideoModal } from '@/components/Admin/AdminTestVideoModal.jsx';
import { AdminDeleteShowModal } from '@/components/Admin/AdminDeleteShowModal.jsx';
import { AdminPlaylistMetaModal } from '@/components/Admin/AdminPlaylistMetaModal.jsx';
import { useAdminShowActions } from '@/hooks/useAdminShowActions.js';

export function Dashboard() {
    const user = useAdminSession();
    const { showData, currentPlaylist, changePlaylist, currentPlaylistData } = useContext(PlaylistContext);
    const playlistMeta = currentPlaylistData || null;
    const playlistName = playlistMeta ? playlistMeta.dbtitle : '';

    // State for sorting/filtering
    const [sortBy, setSortBy] = useState('title');
    const [sortOrder, setSortOrder] = useState('asc');
    const [filterCategory, setFilterCategory] = useState(null);
    const [hideDisabled, setHideDisabled] = useState(false);

    // Admin show actions and modal state
    const {
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
    } = useAdminShowActions();

    // State for playlist meta modal
    const [showMetaModal, setShowMetaModal] = useState(false);
    const [metaSaving, setMetaSaving] = useState(false);
    const [metaError, setMetaError] = useState(null);

    // Handle sorting when header is clicked
    function handleSort(column) {
        if (sortBy === column) {
            setSortOrder(prev => (prev === 'asc' ? 'desc' : 'asc'));
        } else {
            setSortBy(column);
            setSortOrder('asc');
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
            // Use the playlist filename, not dbtitle
            const res = await fetch('/api/admin/update-meta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    playlist: currentPlaylist, // this should be the filename, e.g., 'freetv.json'
                    meta: updatedMeta
                })
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                setMetaError(data && data.message ? data.message : 'Save failed.');
            } else {
                // Optionally, rebuild index and refresh context
                await fetch('/api/admin/playlist_utils.php', { method: 'POST' });
                if (typeof changePlaylist === 'function' && currentPlaylist) {
                    await changePlaylist(currentPlaylist, true, false);
                }
                setShowMetaModal(false);
                // Optionally, set a success message via AdminMessage/localStorage
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
                onClose={closeTestModal}
                showData={testShow}
            />
            <AdminDeleteShowModal
                show={showDeleteModal}
                onClose={closeDeleteModal}
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