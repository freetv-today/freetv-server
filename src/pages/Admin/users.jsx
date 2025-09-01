import { useEffect, useState } from 'preact/hooks';
import { useDebugLog } from '@/hooks/useDebugLog';
import { formatDateTime } from '@/utils';
import { useAdminSession } from '@hooks/Admin/useAdminSession';
import { UserModal } from '@/components/Admin/UI/UserModal';
import { PasswordModal } from '@/components/Admin/UI/PasswordModal';
import { ConfirmDeleteModal } from '@/components/Admin/UI/ConfirmDeleteModal';

export function AdminUsers() {
    const log = useDebugLog();
    const user = useAdminSession();
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [selectedUser, setSelectedUser] = useState(null);
    const [modalType, setModalType] = useState(null); // 'add' | 'edit' | 'changepass' | 'delete' | null

    useEffect(() => {
        document.title = "Free TV: Admin Dashboard - User Manager";
        log('Rendered Admin User Manager page (pages/Admin/users.jsx)');
    }, []);

    useEffect(() => {
        fetchUsers();
    }, []);

    async function fetchUsers() {
        setLoading(true);
        setError('');
        try {
            const res = await fetch('/api/admin/userman.php?action=list');
            const data = await res.json();
            if (data.success) {
                setUsers(data.users);
            } else {
                setError(data.message || 'Failed to fetch users.');
            }
        } catch (e) {
            setError('Network error.');
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
            setError('');
            setSuccess('');
        }

        // Backend actions
        async function handleUserModalSubmit(data) {
            setLoading(true);
            setError('');
            setSuccess('');
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
                    setSuccess(result.message);
                    closeModal();
                    fetchUsers();
                } else {
                    setError(result.message || 'Operation failed.');
                }
            } catch (e) {
                setError('Network error.');
            }
            setLoading(false);
        }

        async function handlePasswordModalSubmit(data) {
            setLoading(true);
            setError('');
            setSuccess('');
            try {
                const res = await fetch('/api/admin/userman.php?action=changepass', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                if (result.success) {
                    setSuccess(result.message);
                    closeModal();
                    fetchUsers();
                } else {
                    setError(result.message || 'Operation failed.');
                }
            } catch (e) {
                setError('Network error.');
            }
            setLoading(false);
        }

        async function handleDeleteConfirm(user) {
            setLoading(true);
            setError('');
            setSuccess('');
            try {
                const res = await fetch('/api/admin/userman.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: user.id })
                });
                const result = await res.json();
                if (result.success) {
                    setSuccess(result.message);
                    closeModal();
                    fetchUsers();
                } else {
                    setError(result.message || 'Operation failed.');
                }
            } catch (e) {
                setError('Network error.');
            }
            setLoading(false);
        }

    if (!user) return null;

        return (
            <div className="container py-4">
                <h2>User Manager</h2>
                {error && <div className="alert alert-danger">{error}</div>}
                {success && <div className="alert alert-success">{success}</div>}
                <div className="mb-3 text-end">
                    <button className="btn btn-primary" onClick={handleAddUser}>Add User</button>
                </div>
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
                            {users.map(user => (
                                <tr key={user.id}>
                                    <td>{user.username}</td>
                                    <td>{user.role}</td>
                                    <td>{user.status}</td>
                                    <td>{formatDateTime(user.created)}</td>
                                    <td>{formatDateTime(user.lastLogin)}</td>
                                    <td>
                                        <button className="btn btn-sm btn-secondary me-1" onClick={() => handleEditUser(user)} disabled={user.role === 'admin'}>Edit</button>
                                        <button className="btn btn-sm btn-warning me-1" onClick={() => handleChangePass(user)}>Change Password</button>
                                        <button className="btn btn-sm btn-danger" onClick={() => handleDeleteUser(user)} disabled={user.role === 'admin'}>Delete</button>
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