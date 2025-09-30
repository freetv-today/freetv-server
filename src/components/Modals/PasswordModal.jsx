import { useState, useEffect } from 'preact/hooks';

export function PasswordModal({ show, onClose, onSubmit, user }) {
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  useEffect(() => {
    setPassword('');
    setError('');
    setShowPassword(false);
  }, [user, show]);

  function handleSubmit(e) {
    e.preventDefault();
    if (!password) {
      setError('Password is required.');
      return;
    }
    if (password.length < 6) {
      setError('Password must be at least 6 characters.');
      return;
    }
    onSubmit({ id: user?.id, password });
  }

  if (!show) return null;

  return (
    <div className="modal d-block" tabIndex={-1} style={{ background: 'rgba(0,0,0,0.3)' }}>
      <div className="modal-dialog">
        <form className="modal-content" onSubmit={handleSubmit}>
          <div className="modal-header">
            <h5 className="modal-title">Change Password</h5>
            <button type="button" className="btn-close" onClick={onClose}></button>
          </div>
          <div className="modal-body">
            {error && <div className="alert alert-danger">{error}</div>}
            <div className="mb-3">
              <label className="form-label">New Password</label>
              <input
                className="form-control"
                type={showPassword ? "text" : "password"}
                value={password}
                onInput={e => setPassword(e.currentTarget.value)}
              />
              <div className="form-check mt-2">
                <input
                  className="form-check-input"
                  type="checkbox"
                  id="showPassword"
                  checked={showPassword}
                  onChange={e => setShowPassword(e.currentTarget.checked)}
                />
                <label className="form-check-label" htmlFor="showPassword">
                  Show password
                </label>
              </div>
            </div>
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={onClose}>Cancel</button>
            <button type="submit" className="btn btn-primary">Change</button>
          </div>
        </form>
      </div>
    </div>
  );
}
