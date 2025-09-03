import { useEffect, useState, useContext } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { useAdminSession } from '@hooks/Admin/useAdminSession';
import { PlaylistContext } from '@/context/PlaylistContext';
import { AdminTestVideoModal } from '@components/Admin/UI/AdminTestVideoModal';
import { AdminDeleteShowModal } from '@components/Admin/UI/AdminDeleteShowModal';
import { DeleteReportedProblemModal } from '@components/Admin/UI/DeleteReportedProblemModal';
import { capitalizeFirstLetter, formatDateTime } from '@/utils';

export function AdminProblems() {
    const log = useDebugLog();
    const user = useAdminSession();
    const { currentPlaylist, currentPlaylistData, changePlaylist } = useContext(PlaylistContext);
    const [reportedProblems, setReportedProblems] = useState([]);
    const [disabledItems, setDisabledItems] = useState([]);
    const [testModal, setTestModal] = useState(null); // {item, type}
    const [deleteModal, setDeleteModal] = useState(null); // {item, type}
    const [markingOk, setMarkingOk] = useState(false);
    const [deleteAllModal, setDeleteAllModal] = useState(false);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - Problems";
        log('Rendered Admin Problems page (pages/Admin/problems.jsx)');
        async function fetchData() {
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
            setReportedProblems(errors);
            // Disabled items from playlist
            let disabled = [];
            if (currentPlaylistData && Array.isArray(currentPlaylistData.shows)) {
                disabled = currentPlaylistData.shows.filter(s => s.status === 'disabled');
            }
            setDisabledItems(disabled);
            setLoading(false);
        }
        fetchData();
        // On unmount, remove adminMsg from localStorage
        return () => {
            localStorage.removeItem('adminMsg');
        };
    }, [currentPlaylist, currentPlaylistData, markingOk]);

    if (!user) return null;
    if (loading) return <div class="text-center mt-5">Loading...</div>;

    // Action handlers
    const handleMarkAsOk = async (item) => {
        setMarkingOk(true);
        try {
            const res = await fetch('/logs/errors.json');
            const data = await res.json();
            if (Array.isArray(data.reports)) {
                const idx = data.reports.findIndex(r => r.identifier === item.identifier && r.status === 'reported' && r.playlist === currentPlaylist);
                if (idx !== -1) {
                    data.reports[idx].status = 'addressed';
                    await fetch('/api/admin/update-errors-log.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    // Force context and badge to update
                    if (typeof changePlaylist === 'function' && currentPlaylist) {
                        await changePlaylist(currentPlaylist, false, false);
                    }
                }
            }
        } catch {}
        setMarkingOk(false);
    };

    const handleDeleteShow = async (item) => {
        // Use existing delete-show.php endpoint
        await fetch('/api/admin/delete-show.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ identifier: item.identifier, playlist: currentPlaylist })
        });
        // Refresh playlistData (assume PlaylistContext will update or force reload)
        setDeleteModal(null);
    };

    const handleDeleteAllDisabled = async () => {
        if (!currentPlaylistData || !Array.isArray(currentPlaylistData.shows)) return;
        const toDelete = currentPlaylistData.shows.filter(s => s.status === 'disabled');
        for (const item of toDelete) {
            await fetch('/api/admin/delete-show.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ identifier: item.identifier, playlist: currentPlaylist })
            });
        }
        // Rebuild index.json
        await fetch('/api/admin/playlist_utils.php', { method: 'POST' });
        // Refresh playlist data in context
        if (typeof changePlaylist === 'function' && currentPlaylist) {
            await changePlaylist(currentPlaylist, true, false);
        }
        setDeleteAllModal(false);
    };

    // Table components
    const renderActionsReported = (item) => (
        <>
            <button type="button" className="btn tinybtn btn-warning p-1 me-2" onClick={() => setTestModal({ item, type: 'reported' })}>Test</button>
            <button type="button" className="btn tinybtn btn-danger p-1 me-2" onClick={() => setDeleteModal({ item, type: 'reported' })}>Delete</button>
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
        <div class="container mt-5">
            <h2 class="text-center mb-4">Problems Which Need To Be Fixed</h2>
            {/* Reported Problems Table */}
            <h4>Reported Problems</h4>
            <div class="table-responsive mb-5">
                <table class="table table-bordered table-striped">
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
                            <tr><td colSpan={4} class="text-center">No reported problems.</td></tr>
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
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-striped">
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
                            <tr><td colSpan={4} class="text-center">No disabled items.</td></tr>
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
                        <div className="mb-5 text-end">
                            <button type="button" className="btn btn-sm btn-danger" onClick={() => setDeleteAllModal(true)}>Delete All Disabled Items</button>
                        </div>
                    )}

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