import { useEffect, useState, useContext } from 'preact/hooks';
import { useAdminSession } from '@hooks/Admin/useAdminSession';
import { PlaylistContext } from '@/context/PlaylistContext';
import { AdminDashboardTable } from '@/components/Admin/UI/AdminDashboardTable';
import { AdminDashboardFilters } from '@/components/Admin/UI/AdminDashboardFilters';
import { NavbarSubNavAdmin } from '@/components/Admin/Navigation/NavbarSubNavAdmin';
import { AdminInfoModal } from '@/components/Admin/Modals/AdminInfoModal';
import AdminSortJsonModal from '@/components/Admin/Modals/AdminSortJsonModal';
import { AdminMessage } from '@/components/Admin/UI/AdminMessage';
import { AdminTestVideoModal } from '@/components/Admin/Modals/AdminTestVideoModal';
import { AdminDeleteShowModal } from '@/components/Admin/Modals/AdminDeleteShowModal';
import { AdminPlaylistMetaModal } from '@/components/Admin/Modals/AdminPlaylistMetaModal';
import { useAdminShowActions } from '@hooks/Admin/useAdminShowActions';
import { useDebugLog } from '@/hooks/useDebugLog';

export function Dashboard() {

    const log = useDebugLog();
    const user = useAdminSession();
    const { showData, currentPlaylist, currentPlaylistData } = useContext(PlaylistContext);
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

    // State for sort modal
    const [showSortModal, setShowSortModal] = useState(false);

    // Generic modal close handler
    function handleCloseModal(modal, reason) {
        switch (modal) {
            case 'meta':
                if (reason === 'cancel') log('Playlist Meta Data operation was cancelled');
                setShowMetaModal(false);
                break;
            case 'deleteShow':
                if (reason === 'cancel') log('Delete Show operation was cancelled');
                closeDeleteModal();
                break;
            case 'info':
                if (reason === 'cancel') log('Playlist Information operation was cancelled');
                setShowInfoModal(false);
                break;
            case 'sort':
                if (reason === 'cancel') log('Sort Playlist operation was cancelled');
                setShowSortModal(false);
                break;
            default:
                break;
        }
    }

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
        log('Editing Playlist Meta Data');
        setShowMetaModal(true);
        setMetaError(null);
    }

    function handleOpenInfoModal() {
        log('Viewing Playlist Information');
        setShowInfoModal(true);
    }

    function handleOpenSortModal() {
        log('Opening Sort Playlist Modal');
        setShowSortModal(true);
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
            }
        } catch (err) {
            setMetaError('Save failed.');
        } finally {
            setMetaSaving(false);
        }
    }

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard";
        log('Rendered Dashboard page (pages/Admin/dashboard.jsx)');
    }, []);

    if (!user) return null;

    // Calculate stats for info modal
    const totalShows = showData ? showData.length : 0;
    const activeShows = showData ? showData.filter(s => s.status === 'active').length : 0;
    const disabledShows = showData ? showData.filter(s => s.status === 'disabled').length : 0;
    const totalPlaylists = (Array.isArray(currentPlaylistData?.playlists) ? currentPlaylistData.playlists.length : undefined) || (Array.isArray((useContext(PlaylistContext).playlists)) ? useContext(PlaylistContext).playlists.length : 0);

    return (
        <div className="container mt-3">
            <h1 className="text-center fw-bold mb-2">Admin Dashboard</h1>
            <AdminMessage />
            <NavbarSubNavAdmin 
                onMetaClick={handleOpenMetaModal} 
                onInfoClick={handleOpenInfoModal}
                onSortClick={handleOpenSortModal}
            />
            <hr/>
            <AdminDashboardFilters
                shows={showData || []}
                filterCategory={filterCategory}
                setFilterCategory={setFilterCategory}
                hideDisabled={hideDisabled}
                setHideDisabled={setHideDisabled}
                playlistName={playlistName}
            />
            <hr/>
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
                onClose={reason => handleCloseModal('deleteShow', reason)}
                showData={showToDelete}
                deleting={deleting}
                error={deleteError}
                onDeleteConfirm={handleDeleteConfirm}
            />
            <AdminPlaylistMetaModal
                show={showMetaModal}
                onClose={reason => handleCloseModal('meta', reason)}
                saving={metaSaving}
                error={metaError}
                onSave={handleSaveMeta}
            />
                        <AdminInfoModal
                                show={showInfoModal}
                                onClose={reason => handleCloseModal('info', reason)}
                                stats={{
                                    totalShows,
                                    activeShows,
                                    disabledShows,
                                    totalPlaylists
                                }}
                        />
            <AdminSortJsonModal
                show={showSortModal}
                onClose={reason => handleCloseModal('sort', reason)}
                playlistFilename={currentPlaylist}
            />
        </div>
    );
}