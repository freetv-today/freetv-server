import { useEffect, useState } from 'preact/hooks';
import { AdminDashboardTable } from '@/components/UI/AdminDashboardTable';
import { AdminDashboardFilters } from '@/components/UI/AdminDashboardFilters';
import { NavbarSubNavAdmin } from '@/components/Navigation/NavbarSubNavAdmin';
import { AdminInfoModal } from '@/components/Modals/AdminInfoModal';
import { AdminSortJsonModal } from '@/components/Modals/AdminSortJsonModal';
import { AdminMessage } from '@/components/UI/AdminMessage';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { AdminTestVideoModal } from '@/components/Modals/AdminTestVideoModal';
import { AdminDeleteShowModal } from '@/components/Modals/AdminDeleteShowModal';
import { AdminPlaylistMetaModal } from '@/components/Modals/AdminPlaylistMetaModal';
import { useAdminShowActions } from '@hooks/useAdminShowActions';
import { useDebugLog } from '@/hooks/useDebugLog';
import { useDataValidation } from '@/hooks/useDataValidation';
import { playlistSignal, loadPlaylists } from '@signals/playlistSignal';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { DataSetupPage } from '@/pages/DataSetupPage';

export function Dashboard() {

    const log = useDebugLog();
    const dataValidation = useDataValidation();
    const [initialized, setInitialized] = useState(false);

    // Check if we need to show data setup page
    if (dataValidation.loading) {
        return <SpinnerLoadingAppData />;
    }

    if (!dataValidation.canProceed) {
        return <DataSetupPage dataState={dataValidation} onRetry={dataValidation.revalidate} />;
    }

    useEffect(() => {
        document.title = "Admin Dashboard";
        log('Rendered Dashboard page (pages/dashboard.jsx)');
        // Set loading to true
        playlistSignal.value = { ...playlistSignal.value, loading: true };
        // Reload playlists when dashboard mounts (with shorter spinner time)
        loadPlaylists(600).then(() => setInitialized(true));
    }, []);

    // useState for sorting/filtering
    const [sortBy, setSortBy] = useState('title');
    const [sortOrder, setSortOrder] = useState('asc');
    const [filterCategory, setFilterCategory] = useState(null);
    const [hideDisabled, setHideDisabled] = useState(false);

    // Use playlist state from signal
    const { playlists, currentPlaylist, showData, loading, error } = playlistSignal.value;
    // Admin show actions and modal state (now pass currentPlaylist)
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
    } = useAdminShowActions(currentPlaylist);

    // State for playlist meta modal
    const [showMetaModal, setShowMetaModal] = useState(false);
    const [metaSaving, setMetaSaving] = useState(false);
    const [metaError, setMetaError] = useState(null);

    // State for info modal
    const [showInfoModal, setShowInfoModal] = useState(false);

    // State for sort modal
    const [showSortModal, setShowSortModal] = useState(false);

    const totalShows = showData ? showData.length : 0;
    const activeShows = showData ? showData.filter(s => s.status === 'active').length : 0;
    const disabledShows = showData ? showData.filter(s => s.status === 'disabled').length : 0;
    const totalPlaylists = Array.isArray(playlists) ? playlists.length : 0;

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
            setAdminMsg({ type: 'success', text: data.message || 'Meta data updated' });
            setShowMetaModal(false);
        } catch (err) {
            setMetaError('Save failed.');
        } finally {
            setMetaSaving(false);
        }
    }

    // Helper to get the display name (dbtitle) of current playlist
    function getCurrentPlaylistTitle() {
        const found = playlists.find(p => p.filename === currentPlaylist);
        return found ? found.dbtitle : currentPlaylist;
    }

    if (!initialized || loading) return <SpinnerLoadingAppData />;
    if (error) return <div className="alert alert-danger mt-4">{error}</div>;

    // Extract meta data for current playlist from loaded playlist JSON
    const [currentPlaylistMeta, setCurrentPlaylistMeta] = useState(null);
    useEffect(() => {
        async function fetchMeta() {
            if (!currentPlaylist) return;
            try {
                const res = await fetch(`/playlists/${currentPlaylist}`);
                if (!res.ok) return;
                const data = await res.json();
                setCurrentPlaylistMeta({
                    dbtitle: data.dbtitle || '',
                    dbversion: data.dbversion || '',
                    author: data.author || '',
                    email: data.email || '',
                    link: data.link || '',
                    lastupdated: data.lastupdated || ''
                });
            } catch {
                setCurrentPlaylistMeta(null);
            }
        }
        fetchMeta();
    }, [currentPlaylist, showMetaModal]);

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
                playlistName={getCurrentPlaylistTitle()}
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
                meta={currentPlaylistMeta}
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