import { useState } from 'preact/hooks';

const USERNAME_PATTERN = /^[A-Za-z0-9._-]+$/;

export function DataInitializationPage({ onInitialized }) {
  const [showForm, setShowForm] = useState(false);
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');

  async function handleSubmit(event) {
    event.preventDefault();
    setError('');

    const normalizedUsername = username.trim();
    if (!normalizedUsername || normalizedUsername.length > 100 || !USERNAME_PATTERN.test(normalizedUsername)) {
      setError('Username must be 1-100 characters using letters, numbers, dots, dashes, or underscores.');
      return;
    }
    if (!password.trim() || password.length < 6) {
      setError('Password must be at least 6 characters.');
      return;
    }
    if (password !== passwordConfirmation) {
      setError('Password confirmation does not match.');
      return;
    }

    setSubmitting(true);

    try {
      const response = await fetch('/api/admin/initialize.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          username: normalizedUsername,
          password,
          password_confirmation: passwordConfirmation
        })
      });
      const data = await response.json().catch(() => null);

      if (!response.ok || data?.success !== true) {
        setError(data?.message || 'FreeTV initialization failed. Please try again.');
        return;
      }

      await onInitialized();
    } catch {
      setError('FreeTV initialization could not reach the PHP backend. Please try again.');
    } finally {
      setSubmitting(false);
    }
  }

  if (showForm) {
    return (
      <div className="container py-5" style={{ maxWidth: 680 }}>
        <div className="card shadow">
          <div className="card-body p-4 p-md-5">
            <h1 className="h2 mb-3">Set Up Your FreeTV Library</h1>
            <p className="text-muted">
              Start Fresh creates the first Administrator account and one empty default playlist
              named Playlist One. It does not add any shows.
            </p>

            {error && <div className="alert alert-danger" role="alert">{error}</div>}

            <form onSubmit={handleSubmit}>
              <div className="mb-3">
                <label className="form-label" htmlFor="initial-admin-username">
                  Administrator Username
                </label>
                <input
                  id="initial-admin-username"
                  className="form-control"
                  type="text"
                  value={username}
                  onInput={event => setUsername(event.currentTarget.value)}
                  autoComplete="username"
                  maxLength={100}
                  disabled={submitting}
                  autoFocus
                  required
                />
              </div>
              <div className="mb-3">
                <label className="form-label" htmlFor="initial-admin-password">Password</label>
                <input
                  id="initial-admin-password"
                  className="form-control"
                  type="password"
                  value={password}
                  onInput={event => setPassword(event.currentTarget.value)}
                  autoComplete="new-password"
                  minLength={6}
                  disabled={submitting}
                  required
                />
              </div>
              <div className="mb-4">
                <label className="form-label" htmlFor="initial-admin-password-confirmation">
                  Confirm Password
                </label>
                <input
                  id="initial-admin-password-confirmation"
                  className="form-control"
                  type="password"
                  value={passwordConfirmation}
                  onInput={event => setPasswordConfirmation(event.currentTarget.value)}
                  autoComplete="new-password"
                  minLength={6}
                  disabled={submitting}
                  required
                />
              </div>
              <div className="d-flex gap-2 flex-wrap">
                <button type="submit" className="btn btn-primary" disabled={submitting}>
                  {submitting ? 'Creating FreeTV Library...' : 'Create FreeTV Library'}
                </button>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  onClick={() => { setShowForm(false); setError(''); }}
                  disabled={submitting}
                >
                  Back
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="container py-5">
      <div className="text-center mb-5">
        <h1>Set Up Your FreeTV Library</h1>
        <p className="lead text-muted">Choose how you want to initialize your MariaDB-backed library.</p>
      </div>

      <div className="row g-4 justify-content-center">
        <div className="col-md-4">
          <div className="card h-100 border-primary shadow-sm">
            <div className="card-body d-flex flex-column">
              <h2 className="h4 card-title">Start Fresh</h2>
              <p className="card-text">
                Create the first FreeTV Administrator account and one empty default playlist named
                Playlist One. No shows will be added.
              </p>
              <button className="btn btn-primary mt-auto" type="button" onClick={() => setShowForm(true)}>
                Start Fresh
              </button>
            </div>
          </div>
        </div>

        <div className="col-md-4">
          <div className="card h-100 shadow-sm text-muted">
            <div className="card-body d-flex flex-column">
              <h2 className="h4 card-title">Sample Data</h2>
              <p className="card-text">Initialize FreeTV with a small example library.</p>
              <button className="btn btn-secondary mt-auto" type="button" disabled>
                Coming Soon
              </button>
            </div>
          </div>
        </div>

        <div className="col-md-4">
          <div className="card h-100 shadow-sm text-muted">
            <div className="card-body d-flex flex-column">
              <h2 className="h4 card-title">Official Data</h2>
              <p className="card-text">Initialize FreeTV with the official library data.</p>
              <button className="btn btn-secondary mt-auto" type="button" disabled>
                Coming Soon
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
