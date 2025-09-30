import { useEffect, useState } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { playlistSignal, loadPlaylists } from '@signals/playlistSignal';
import { AdminTestVideoModal } from '@components/Modals/AdminTestVideoModal';
import { AdminDeleteShowModal } from '@components/Modals/AdminDeleteShowModal';
import { DeleteReportedProblemModal } from '@components/Modals/DeleteReportedProblemModal';
import { capitalizeFirstLetter, formatDateTime } from '@/utils';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { AdminMessage } from '@/components/UI/AdminMessage';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';

export function AdminProblems() {
    
    const log = useDebugLog();
    const { currentPlaylist, showData, loading: playlistLoading, error: playlistError } = playlistSignal.value;
    const [reportedProblems, setReportedProblems] = useState([]);
    const [disabledItems, setDisabledItems] = useState([]);
    const [testModal, setTestModal] = useState(null);
    const [deleteModal, setDeleteModal] = useState(null);
    const [markingOk, setMarkingOk] = useState(false);
    const [deleteAllModal, setDeleteAllModal] = useState(false);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Problems";
        log('Rendered Admin Problems page (pages/problems.jsx)');
        async function fetchData() {
            if (!currentPlaylist || !showData) {
                setLoading(false);
                return;
            }
            setLoading(true);
            // Fetch errors.json
            let errors = [];
            try {
                const res = await fetch('/logs/errors.json');
                const data = await res.json();
                if (Array.isArray(data.reports)) {
                    errors = data.reports.filter(r => r.status === 'reported' && r.playlist === currentPlaylist);
                }
            } catch {}
            // Sort reported problems alphabetically by title
            errors.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
            setReportedProblems(errors);
            // Disabled items from playlist
            let disabled = [];
            if (showData && Array.isArray(showData)) {
                disabled = showData.filter(s => s.status === 'disabled');
            }
            // Sort disabled items alphabetically by title
            disabled.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
            setDisabledItems(disabled);
            setLoading(false);
        }
        fetchData();
        // On unmount, remove adminMsg from localStorage
        return () => {
            localStorage.removeItem('adminMsg');
        };
    }, [currentPlaylist, showData, markingOk]);

    if (playlistLoading || loading) return <SpinnerLoadingAppData />;
    if (playlistError) return <div className="alert alert-danger mt-4">{playlistError}</div>;

    // Helper function to refresh data after operations
    const refreshData = async () => {
        // Re-fetch errors.json for reported problems
        try {
            const res = await fetch('/logs/errors.json');
            const data = await res.json();
            if (Array.isArray(data.reports) && currentPlaylist) {
                const errors = data.reports.filter(r => r.status === 'reported' && r.playlist === currentPlaylist);
                errors.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
                setReportedProblems(errors);
            }
        } catch {}
        
        // Update disabled items from current show data (which should be fresh from loadPlaylists)
        const { showData: currentShowData } = playlistSignal.value;
        if (currentShowData && Array.isArray(currentShowData)) {
            const disabled = currentShowData.filter(s => s.status === 'disabled');
            disabled.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
            setDisabledItems(disabled);
        }
    };

    // Action handlers
    const handleMarkAsOk = async (item) => {
        setMarkingOk(true);
        try {
            const response = await fetch('/api/admin/manage-problem-item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'mark-ok',
                    playlist: currentPlaylist,
                    identifier: item.identifier
                })
            });
            
            const result = await response.json();
            if (result.success) {
                setAdminMsg({ type: 'success', text: 'Problem marked as OK' });
                // Reload playlist data to get updated state
                await loadPlaylists(600);
                await refreshData();
            } else {
                setAdminMsg({ type: 'danger', text: result.message || 'Error marking problem as OK' });
            }
        } catch {
            setAdminMsg({ type: 'danger', text: 'Network error' });
        }
        setMarkingOk(false);
    };

    const handleDeleteShow = async (item) => {
        try {
            const response = await fetch('/api/admin/manage-problem-item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    playlist: currentPlaylist,
                    identifier: item.identifier
                })
            });
            
            const result = await response.json();
            if (result.success) {
                setAdminMsg({ type: 'success', text: 'Show deleted' });
                // Reload playlist data to get updated state
                await loadPlaylists(600);
                await refreshData();
                return true;
            } else {
                setAdminMsg({ type: 'danger', text: result.message || 'Error deleting show' });
                return false;
            }
        } catch {
            setAdminMsg({ type: 'danger', text: 'Network error' });
            return false;
        }
        setDeleteModal(null);
    };

    const handleDeleteAllDisabled = async () => {
        if (!showData || !Array.isArray(showData)) return;
        const toDelete = showData.filter(s => s.status === 'disabled');
        let errorMsg = null;
        for (const item of toDelete) {
            try {
                const response = await fetch('/api/admin/manage-problem-item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        playlist: currentPlaylist,
                        identifier: item.identifier
                    })
                });
                const result = await response.json();
                if (!result.success) {
                    errorMsg = result.message || 'Error deleting one or more items';
                    break;
                }
            } catch {
                errorMsg = 'Network error';
                break;
            }
        }
        // Rebuild index.json (already done by manage-problem-item.php, but just in case)
        await fetch('/api/admin/playlist_utils.php', { method: 'POST' });
        // Reload playlist data to get updated state
        await loadPlaylists(600);
        // Refresh data
        await refreshData();
        setDeleteAllModal(false);
        if (errorMsg) {
            setAdminMsg({ type: 'danger', text: errorMsg });
        } else {
            setAdminMsg({ type: 'success', text: 'All disabled items deleted' });
        }
    };

    // Table components
    const renderActionsReported = (item) => (
        <>
            <button type="button" className="btn tinybtn btn-warning p-1 me-2" onClick={() => setTestModal({ item, type: 'reported' })}>Test</button>
            <button type="button" className="btn tinybtn btn-success p-1 me-2" onClick={() => handleMarkAsOk(item)} disabled={markingOk}>Mark as OK</button>
            <button type="button" className="btn tinybtn btn-danger p-1 me-2" onClick={() => setDeleteModal({ item, type: 'reported' })}>Delete</button>
        </>
    );
    const renderActionsDisabled = (item) => (
        <>
            <button type="button" className="btn tinybtn btn-warning p-1 me-2" onClick={() => setTestModal({ item, type: 'disabled' })}>Test</button>
            <button type="button" className="btn tinybtn btn-danger p-1 me-2" onClick={() => setDeleteModal({ item, type: 'disabled' })}>Delete</button>
        </>
    );

    return (
        <div className="container mt-5">

            <h2 className="text-center mb-4">Problems Which Need To Be Fixed</h2>

            <AdminMessage />

            {/* Reported Problems Table */}
            <h4>Reported Problems</h4>
            <div className="table-responsive mb-5">
                <table className="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {reportedProblems.length === 0 ? (
                            <tr><td colSpan={4} className="text-center">No reported problems.</td></tr>
                        ) : (
                            reportedProblems.map(item => (
                                <tr key={item.identifier}>
                                    <td>{capitalizeFirstLetter(item.category)}</td>
                                    <td>{item.title}</td>
                                    <td>{formatDateTime(item.date)}</td>
                                    <td>{renderActionsReported(item)}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {/* Disabled Items Table */}
            <h4>Disabled Items</h4>
            <div className="table-responsive mb-3">
                <table className="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Title</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {disabledItems.length === 0 ? (
                            <tr><td colSpan={4} className="text-center">No disabled items.</td></tr>
                        ) : (
                            disabledItems.map(item => (
                                <tr key={item.identifier}>
                                    <td>{capitalizeFirstLetter(item.category)}</td>
                                    <td>{item.title}</td>
                                    <td>{item.status}</td>
                                    <td>{renderActionsDisabled(item)}</td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
                    {disabledItems.length > 0 && (
                        <div className="mb-5 text-center">
                            <button type="button" className="btn btn-sm btn-danger" onClick={() => setDeleteAllModal(true)}>Delete All Disabled Items</button>
                        </div>
                    )}
            <br/>
            {/* Modals */}
            {testModal && (
                <AdminTestVideoModal
                    show={!!testModal}
                    onClose={() => setTestModal(null)}
                    showData={testModal.item}
                />
            )}
            {deleteModal && deleteModal.type === 'reported' && (
                <DeleteReportedProblemModal
                    show={!!deleteModal}
                    onClose={() => setDeleteModal(null)}
                    showData={deleteModal.item}
                    deleting={false}
                    error={null}
                    onDeleteConfirm={() => {}}
                />
            )}
            {deleteModal && deleteModal.type === 'disabled' && (
                <AdminDeleteShowModal
                    show={!!deleteModal}
                    onClose={() => setDeleteModal(null)}
                    showData={deleteModal.item}
                    deleting={false}
                    error={null}
                    onDeleteConfirm={() => handleDeleteShow(deleteModal.item)}
                />
            )}
            {deleteAllModal && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)' }}>
                    <div className="modal-dialog">
                        <div className="modal-content">
                            <div className="modal-header">
                                <h5 className="modal-title">Delete All Disabled Items</h5>
                                <button type="button" className="btn-close" aria-label="Close" onClick={() => setDeleteAllModal(false)}></button>
                            </div>
                            <div className="modal-body">
                                <p>Are you sure you want to delete all disabled items from the current playlist?</p>
                                    <ul>
                                        {disabledItems.map(item => (
                                            <li key={item.identifier}>{item.title}</li>
                                        ))}
                                    </ul>
                            </div>
                            <div className="modal-footer">
                                <button type="button" className="btn btn-secondary" onClick={() => setDeleteAllModal(false)}>Cancel</button>
                                <button type="button" className="btn btn-danger" onClick={handleDeleteAllDisabled}>Delete All</button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}