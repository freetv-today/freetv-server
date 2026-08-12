import { useEffect, useMemo, useState } from 'preact/hooks';
import { AdminDashboardTable } from '@/components/UI/AdminDashboardTable';
import { AdminDashboardFilters } from '@/components/UI/AdminDashboardFilters';
import { NavbarSubNavAdmin } from '@/components/Navigation/NavbarSubNavAdmin';
import { AdminInfoModal } from '@/components/Modals/AdminInfoModal';
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

    useEffect(() => {
        document.title = 'Admin Dashboard';
        log('Rendered Dashboard page (pages/dashboard.jsx)');
    }, []);

    // useState for sorting/filtering
    const [sortBy, setSortBy] = useState('title');
    const [sortOrder, setSortOrder] = useState('asc');
    const [filterCategory, setFilterCategory] = useState(null);
    const [hideDisabled, setHideDisabled] = useState(false);

    // Use playlist state from signal
    const {
        playlists,
        currentPlaylist,
        currentPlaylistData,
        showData,
        loading,
        error
    } = playlistSignal.value;
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

            const responseText = await res.text();
            let data = null;
            try {
                data = JSON.parse(responseText);
            } catch {
                data = null;
            }

            if (!res.ok || data?.success !== true) {
                const errorMessage = data?.message
                    || (res.ok ? 'Unexpected response from server.' : `Save failed (HTTP ${res.status}).`);
                setMetaError(errorMessage);
                return;
            }

            const refreshed = await loadPlaylists(0);
            const refreshedPlaylistState = playlistSignal.value;
            const selectedPlaylistWasRefreshed = refreshed
                && refreshedPlaylistState.currentPlaylist === currentPlaylist
                && refreshedPlaylistState.currentPlaylistData?.filename === currentPlaylist;

            if (!selectedPlaylistWasRefreshed) {
                const refreshDetail = refreshedPlaylistState.error
                    || 'The selected playlist was not returned by the refresh.';
                const refreshMessage = `Meta data was updated, but the refreshed playlist data could not be loaded: ${refreshDetail}`;
                playlistSignal.value = {
                    ...refreshedPlaylistState,
                    error: refreshMessage
                };
                setMetaError(refreshMessage);
                setAdminMsg({ type: 'warning', text: refreshMessage });
                return;
            }

            setAdminMsg({ type: 'success', text: data.message || 'Meta data updated' });
            setShowMetaModal(false);
        } catch {
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

    const currentPlaylistMeta = useMemo(() => {
        if (!currentPlaylistData) return null;

        return {
            dbtitle: currentPlaylistData.dbtitle ?? '',
            dbversion: currentPlaylistData.dbversion ?? '',
            author: currentPlaylistData.author ?? '',
            email: currentPlaylistData.email ?? '',
            link: currentPlaylistData.link ?? '',
            lastupdated: currentPlaylistData.lastupdated ?? '',
            is_default: currentPlaylistData.is_default === true
        };
    }, [currentPlaylistData]);

    // Keep all hooks above these conditional render paths.
    if (dataValidation.loading) {
        return <SpinnerLoadingAppData />;
    }

    if (!dataValidation.canProceed) {
        return <DataSetupPage dataState={dataValidation} onRetry={dataValidation.revalidate} />;
    }

    if (loading) return <SpinnerLoadingAppData />;
    if (error) return <div className="alert alert-danger mt-4">{error}</div>;

    return (
        <div className="container mt-3">
            <h1 className="text-center fw-bold mb-2">Admin Dashboard</h1>
            <AdminMessage />
            <NavbarSubNavAdmin />
            <hr/>
            <AdminDashboardFilters
                shows={showData || []}
                filterCategory={filterCategory}
                setFilterCategory={setFilterCategory}
                hideDisabled={hideDisabled}
                setHideDisabled={setHideDisabled}
                playlistName={getCurrentPlaylistTitle()}
                onMetaClick={handleOpenMetaModal}
                onInfoClick={handleOpenInfoModal}
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
        </div>
    );
}
