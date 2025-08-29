import { useState, useEffect } from 'preact/hooks';

export function UserModal({ show, onClose, onSubmit, user, mode }) {
  // mode: 'add' or 'edit'
  const [username, setUsername] = useState(user?.username || '');
  const [role, setRole] = useState(user?.role || 'editor');
  const [status, setStatus] = useState(user?.status || 'active');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    setUsername(user?.username || '');
    setRole(user?.role || 'editor');
    setStatus(user?.status || 'active');
    setPassword('');
    setError('');
  }, [user, show]);

  function handleSubmit(e) {
    e.preventDefault();
    if (!username.trim()) {
      setError('Username is required.');
      return;
    }
    if (mode === 'add' && !password) {
      setError('Password is required.');
      return;
    }
    if (mode === 'add' && password.length < 6) {
      setError('Password must be at least 6 characters.');
      return;
    }
    onSubmit({
      id: user?.id,
      username: username.trim(),
      role,
      status,
      password: password || undefined
    });
  }

  if (!show) return null;

  return (
    <div className="modal d-block" tabIndex={-1} style={{ background: 'rgba(0,0,0,0.3)' }}>
      <div className="modal-dialog">
        <form className="modal-content" onSubmit={handleSubmit}>
          <div className="modal-header">
            <h5 className="modal-title">{mode === 'add' ? 'Add User' : 'Edit User'}</h5>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body">
            {error && <div className="alert alert-danger">{error}</div>}
            <div className="mb-3">
              <label className="form-label">Username</label>
              <input className="form-control" value={username} onInput={e => setUsername(e.currentTarget.value)} disabled={user?.role === 'admin'} />
            </div>
            <div className="mb-3">
              <label className="form-label">Role</label>
              <select className="form-select" value={role} onInput={e => setRole(e.currentTarget.value)} disabled={user?.role === 'admin'}>
                <option value="editor">Editor</option>
                <option value="viewer">Viewer</option>
                <option value="guest">Guest</option>
                {user?.role === 'admin' && <option value="admin">Admin</option>}
              </select>
            </div>
            <div className="mb-3">
              <label className="form-label">Status</label>
              <select className="form-select" value={status} onInput={e => setStatus(e.currentTarget.value)}>
                <option value="active">Active</option>
                <option value="disabled">Disabled</option>
              </select>
            </div>
            {mode === 'add' && (
              <div className="mb-3">
                <label className="form-label">Password</label>
                <input className="form-control" type="password" value={password} onInput={e => setPassword(e.currentTarget.value)} />
              </div>
            )}
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>Cancel</button>
            <button type="submit" className="btn btn-primary">{mode === 'add' ? 'Add' : 'Save'}</button>
          </div>
        </form>
      </div>
    </div>
  );
}
