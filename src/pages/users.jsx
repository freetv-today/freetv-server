import { useEffect, useState } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { useDebugLog } from '@/hooks/useDebugLog';
import { formatDateTime } from '@/utils/utils';
import { UserModal } from '@/components/Modals/UserModal';
import { PasswordModal } from '@/components/Modals/PasswordModal';
import { ConfirmDeleteModal } from '@/components/Modals/ConfirmDeleteModal';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { playlistSignal } from '@signals/playlistSignal';
import { AdminMessage } from '@/components/UI/AdminMessage';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';

export function AdminUsers() {

    const log = useDebugLog();
    const { loading: playlistLoading, error: playlistError } = playlistSignal.value;
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selectedUser, setSelectedUser] = useState(null);
    const [modalType, setModalType] = useState(null); // 'add' | 'edit' | 'changepass' | 'delete' | null
    const { route } = useLocation();

    // Show loading spinner when playlist is loading
    if (playlistLoading) return <SpinnerLoadingAppData />;
    if (playlistError) return <div className="alert alert-danger mt-4">{playlistError}</div>;

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - User Manager";
        log('Rendered Admin User Manager page (pages/users.jsx)');
    }, []);

    useEffect(() => {
        fetchUsers();
    }, []);

    async function fetchUsers() {
        setLoading(true);
        try {
            const res = await fetch('/api/admin/userman.php?action=list');
            const data = await res.json();
            if (data.success) {
                setUsers(data.users);
            } else {
                setAdminMsg({ type: 'danger', text: 'Failed to fetch users.' });
            }
        } catch (e) {
            setAdminMsg({ type: 'danger', text: 'Network error.' });
        }
        setLoading(false);
    }

    // Modal handlers
    function handleAddUser() {
        setSelectedUser(null);
        setModalType('add');
    }
    function handleEditUser(user) {
        setSelectedUser(user);
        setModalType('edit');
    }
    function handleChangePass(user) {
        setSelectedUser(user);
        setModalType('changepass');
    }
    function handleDeleteUser(user) {
        setSelectedUser(user);
        setModalType('delete');
    }
    function closeModal() {
        setSelectedUser(null);
        setModalType(null);
    }

    // Backend actions
    async function handleUserModalSubmit(data) {
        setLoading(true);
        try {
            const action = modalType === 'add' ? 'add' : 'edit';
            const res = await fetch(`/api/admin/userman.php?action=${action}`,
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
            const result = await res.json();
            if (result.success) {
                setAdminMsg({ type: 'success', text: result.message })
                closeModal();
                fetchUsers();
            } else {
                setAdminMsg({ type: 'danger', text: result.message || 'Operation failed.' })
            }
        } catch (e) {
            setAdminMsg({ type: 'danger', text: 'Network error.' })
        }
        setLoading(false);
    }

    async function handlePasswordModalSubmit(data) {
        setLoading(true);
        try {
            const res = await fetch('/api/admin/userman.php?action=changepass', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            if (result.success) {
                setAdminMsg({ type: 'success', text: result.message });
                closeModal();
                fetchUsers();
            } else {
                setAdminMsg({ type: 'danger', text: result.message || 'Operation failed.' })
            }
        } catch (e) {
            setAdminMsg({ type: 'danger', text: 'Network error.' })
        }
        setLoading(false);
    }

    async function handleDeleteConfirm(user) {
        setLoading(true);
        try {
            const res = await fetch('/api/admin/userman.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: user.id })
            });
            const result = await res.json();
            if (result.success) {
                setAdminMsg({ type: 'success', text: result.message });
                closeModal();
                fetchUsers();
            } else {
                setAdminMsg({ type: 'danger', text: result.message || 'Operation failed.' })
            }
        } catch (e) {
            setAdminMsg({ type: 'danger', text: 'Network error.' })
        }
        setLoading(false);
    }

    return (
        <div className="container py-4">

            <h2 className="mb-5">User Manager</h2>

            <AdminMessage />

            {/*
            <div className="mb-3 text-end d-flex justify-content-end gap-2">
                <button className="btn btn-secondary" onClick={() => route('/dashboard')}>Cancel</button>
                 <button className="btn btn-primary" onClick={handleAddUser}>Add User</button>
            </div> 
            */}
            {loading ? (
                <div className="text-center my-5"><div className="spinner-border" /></div>
            ) : (
                <table className="table table-striped table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.map(u => (
                            <tr key={u.id}>
                                <td>{u.username}</td>
                                <td>{u.role}</td>
                                <td>{u.status}</td>
                                <td>{formatDateTime(u.created)}</td>
                                <td>{formatDateTime(u.lastLogin)}</td>
                                <td>
                                    {u.role === 'admin' ? (
                                        <button className="btn btn-sm btn-warning" onClick={() => handleChangePass(u)}>
                                            Change Password
                                        </button>
                                    ) : (
                                        <>
                                            <button className="btn btn-sm btn-secondary me-1" onClick={() => handleEditUser(u)}>Edit</button>
                                            <button className="btn btn-sm btn-warning me-1" onClick={() => handleChangePass(u)}>Change Password</button>
                                            <button className="btn btn-sm btn-danger" onClick={() => handleDeleteUser(u)}>Delete</button>
                                        </>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            {/* Modals for add/edit/changepass/delete */}
            <UserModal
                show={modalType === 'add' || modalType === 'edit'}
                onClose={closeModal}
                onSubmit={handleUserModalSubmit}
                user={modalType === 'edit' ? selectedUser : null}
                mode={modalType}
            />
            <PasswordModal
                show={modalType === 'changepass'}
                onClose={closeModal}
                onSubmit={handlePasswordModalSubmit}
                user={selectedUser}
            />
            <ConfirmDeleteModal
                show={modalType === 'delete'}
                onClose={closeModal}
                onConfirm={handleDeleteConfirm}
                user={selectedUser}
            />
        </div>
    );
}