import { useEffect, useState } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { playlistSignal, loadPlaylists } from '@signals/playlistSignal';
import { AdminTestVideoModal } from '@components/Modals/AdminTestVideoModal';
import { AdminDeleteShowModal } from '@components/Modals/AdminDeleteShowModal';
import { capitalizeFirstLetter, formatDateTime } from '@/utils/utils';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { AdminMessage } from '@/components/UI/AdminMessage';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { requestProblemCountRefresh } from '@hooks/useProblemCount';

export function AdminProblems() {
    
    const log = useDebugLog();
    const { currentPlaylist, showData, loading: playlistLoading, error: playlistError } = playlistSignal.value;
    const [reportedProblems, setReportedProblems] = useState([]);
    const [disabledItems, setDisabledItems] = useState([]);
    const [testModal, setTestModal] = useState(null);
    const [deleteModal, setDeleteModal] = useState(null);
    const [markingOk, setMarkingOk] = useState(false);
    const [deleteAllModal, setDeleteAllModal] = useState(false);
    const [reportsLoading, setReportsLoading] = useState(true);
    const [reportsError, setReportsError] = useState(null);
    const [reportsRefreshVersion, setReportsRefreshVersion] = useState(0);

    useEffect(() => {
        document.title = 'Free TV: Admin Dashboard - Problems';
        log('Rendered Admin Problems page (pages/problems.jsx)');
        // On unmount, remove adminMsg from localStorage
        return () => {
            localStorage.removeItem('adminMsg');
        };
    }, []);

    useEffect(() => {
        const disabled = Array.isArray(showData)
            ? showData.filter(show => show.status === 'disabled')
            : [];
        disabled.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
        setDisabledItems(disabled);
    }, [showData]);

    useEffect(() => {
        const controller = new window.AbortController();
        let cancelled = false;

        if (typeof currentPlaylist !== 'string' || currentPlaylist === '') {
            setReportedProblems([]);
            setReportsError(null);
            setReportsLoading(false);
            return () => controller.abort();
        }

        setReportedProblems([]);
        setReportsError(null);
        setReportsLoading(true);

        async function fetchReportedProblems() {
            try {
                const encodedPlaylist = encodeURIComponent(currentPlaylist);
                const response = await fetch(
                    `/api/admin/reported-problems.php?playlist=${encodedPlaylist}&t=${Date.now()}`,
                    { signal: controller.signal }
                );
                const responseText = await response.text();
                let result = null;

                try {
                    result = JSON.parse(responseText);
                } catch {
                    result = null;
                }

                if (!response.ok || result?.success !== true || !Array.isArray(result.reports)) {
                    throw new Error(result?.message || 'Failed to load reported problems');
                }

                if (!cancelled) {
                    setReportedProblems(result.reports);
                }
            } catch (error) {
                if (!cancelled && error?.name !== 'AbortError') {
                    setReportedProblems([]);
                    setReportsError(error?.message || 'Failed to load reported problems');
                }
            } finally {
                if (!cancelled) {
                    setReportsLoading(false);
                }
            }
        }

        fetchReportedProblems();

        return () => {
            cancelled = true;
            controller.abort();
        };
    }, [currentPlaylist, reportsRefreshVersion]);

    if (playlistLoading || reportsLoading) return <SpinnerLoadingAppData />;
    if (playlistError) return <div className="alert alert-danger mt-4">{playlistError}</div>;

    const refreshDisabledItems = () => {
        // Update disabled items from current show data (which should be fresh from loadPlaylists)
        const { showData: currentShowData } = playlistSignal.value;
        if (currentShowData && Array.isArray(currentShowData)) {
            const disabled = currentShowData.filter(s => s.status === 'disabled');
            disabled.sort((a, b) => (a.title || '').localeCompare(b.title || ''));
            setDisabledItems(disabled);
        }
    };

    const refreshReportedProblemsAndBadge = () => {
        setReportsRefreshVersion(version => version + 1);
        requestProblemCountRefresh();
    };

    // Action handlers
    const handleMarkAsOk = async (item) => {
        const selectedPlaylist = currentPlaylist;
        setMarkingOk(true);
        try {
            const response = await fetch('/api/admin/mark-problem-addressed.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    playlist: selectedPlaylist,
                    reportId: item.id
                })
            });
            const result = await response.json().catch(() => null);
            if (response.ok && result?.success === true) {
                setAdminMsg({ type: 'success', text: 'Problem marked as OK' });
                refreshReportedProblemsAndBadge();
            } else {
                setAdminMsg({ type: 'danger', text: result?.message || 'Error marking problem as OK' });
            }
        } catch {
            setAdminMsg({ type: 'danger', text: 'Network error' });
        }
        setMarkingOk(false);
    };

    const handleDeleteShow = async (item) => {
        try {
            const response = await fetch('/api/admin/delete-show.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    playlist: currentPlaylist,
                    identifier: item.identifier
                })
            });
            
            const result = await response.json();
            if (result.success) {
                setAdminMsg({ type: 'success', text: 'Show deleted' });
                // Reload playlist data to get updated state
                await loadPlaylists(600);
                refreshDisabledItems();
                return true;
            } else {
                setAdminMsg({ type: 'danger', text: result.message || 'Error deleting show' });
                return false;
            }
        } catch {
            setAdminMsg({ type: 'danger', text: 'Network error' });
            return false;
        }
    };

    const handleDeleteAllDisabled = async () => {
        if (!showData || !Array.isArray(showData)) return;
        const toDelete = showData.filter(s => s.status === 'disabled');
        let errorMsg = null;
        for (const item of toDelete) {
            try {
                const response = await fetch('/api/admin/delete-show.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
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
        // Reload playlist data to get updated state
        await loadPlaylists(600);
        // Refresh data
        refreshDisabledItems();
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
            {reportsError && <div className="alert alert-danger">{reportsError}</div>}
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
                        {reportsError ? null : reportedProblems.length === 0 ? (
                            <tr><td colSpan={4} className="text-center">No reported problems.</td></tr>
                        ) : (
                            reportedProblems.map(item => (
                                <tr key={item.id}>
                                    <td>{capitalizeFirstLetter(item.category)}</td>
                                    <td>{item.title}</td>
                                    <td>{formatDateTime(item.lastReportedAt)}</td>
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
