import { useEffect, useState, useContext } from 'preact/hooks';
import { useAdminSession } from '@/hooks/useAdminSession';
import { PlaylistContext } from '@/context/PlaylistContext';
import { AdminDashboardTable } from '@/components/Admin/AdminDashboardTable';
import { AdminDashboardFilters } from '@/components/Admin/AdminDashboardFilters';
import { NavbarSubNavAdmin } from '@/components/Navigation/NavbarSubNavAdmin';
import { AdminInfoModal } from '@/components/Admin/AdminInfoModal';
import { AdminMessage } from '@/components/Admin/AdminMessage';
import { AdminTestVideoModal } from '@/components/Admin/AdminTestVideoModal';
import { AdminDeleteShowModal } from '@/components/Admin/AdminDeleteShowModal';
import { AdminPlaylistMetaModal } from '@/components/Admin/AdminPlaylistMetaModal';
import { useAdminShowActions } from '@/hooks/useAdminShowActions';

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

    // State for info modal
    const [showInfoModal, setShowInfoModal] = useState(false);

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

    // Calculate stats for info modal
    const totalShows = showData ? showData.length : 0;
    const activeShows = showData ? showData.filter(s => s.status === 'active').length : 0;
    const disabledShows = showData ? showData.filter(s => s.status === 'disabled').length : 0;
    const totalPlaylists = (Array.isArray(currentPlaylistData?.playlists) ? currentPlaylistData.playlists.length : undefined) || (Array.isArray((useContext(PlaylistContext).playlists)) ? useContext(PlaylistContext).playlists.length : 0);

    return (
        <div className="container mt-3">
            <h1 class="text-center mb-2">Admin Dashboard</h1>
            <AdminMessage />
            <NavbarSubNavAdmin onMetaClick={handleOpenMetaModal} onInfoClick={() => setShowInfoModal(true)} />
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
            <AdminInfoModal
                show={showInfoModal}
                onClose={() => setShowInfoModal(false)}
                stats={{
                  totalShows,
                  activeShows,
                  disabledShows,
                  totalPlaylists
                }}
            />
        </div>
    );
}