import { useState } from 'preact/hooks';
import { ErrorPage } from '@/pages/ErrorPage';

const USERNAME_PATTERN = /^[A-Za-z0-9._-]+$/;

const INITIALIZATION_MODES = [
  {
    mode: 'fresh',
    title: 'Start Fresh',
    description: 'Create an empty FreeTV library.',
    button: 'Start Fresh',
    current: false
  },
  {
    mode: 'baseline',
    title: 'Baseline Sample Data',
    description: 'Initialize with the bundled sample library.',
    button: 'Use Baseline Sample Data',
    current: false
  },
  {
    mode: 'sample',
    title: 'Current Sample Data',
    description: 'Initialize with the current sample library.',
    button: 'Use Current Sample Data',
    current: true
  },
  {
    mode: 'official',
    title: 'Current Official Data',
    description: 'Initialize with the current complete library.',
    button: 'Use Current Official Data',
    current: true
  }
];

const DATASET_FAILURES = {
  dataset_unavailable: {
    type: 'Current Dataset Unavailable',
    message: 'FreeTV could not reach the current dataset service. You can try again or choose another setup option.',
    retryable: true
  },
  dataset_metadata_invalid: {
    type: 'Dataset Information Error',
    message: 'FreeTV could not safely verify the current dataset information. Please return to Data Setup and choose another option or try again later.',
    retryable: false
  },
  dataset_integrity_failed: {
    type: 'Dataset Verification Failed',
    message: 'The downloaded dataset could not be safely verified. It was not installed.',
    retryable: false
  }
};

const CURRENT_DATASET_FAILURE_MESSAGE = 'FreeTV was unable to retrieve or safely verify the data being downloaded. '
  + 'The Administrator account was not created, and FreeTV initialization was not completed. '
  + 'Check your Internet connection, then return to Data Setup to try again. If the problem continues, '
  + 'choose another setup option or try again later. See the FreeTV documentation for additional troubleshooting help.';

export function DataInitializationPage({ onInitialized }) {
  const [selectedMode, setSelectedMode] = useState(null);
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [datasetFailure, setDatasetFailure] = useState(null);

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
          password_confirmation: passwordConfirmation,
          mode: selectedMode
        })
      });
      const data = await response.json().catch(() => null);

      if (!response.ok || data?.success !== true) {
        const recognizedFailure = DATASET_FAILURES[data?.error_code];
        if (recognizedFailure) {
          setDatasetFailure(recognizedFailure);
          return;
        }
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

  if (datasetFailure) {
    const currentDatasetTitle = selectedMode === 'sample'
      ? 'Error Getting Current Sample Data'
      : selectedMode === 'official' ? 'Error Getting Current Official Data' : null;
    return (
      <ErrorPage
        type={currentDatasetTitle || datasetFailure.type}
        message={currentDatasetTitle ? CURRENT_DATASET_FAILURE_MESSAGE : datasetFailure.message}
        showReload={datasetFailure.retryable}
        onReload={() => setDatasetFailure(null)}
        homeLabel="Back to Data Setup"
        onHome={() => {
          setDatasetFailure(null);
          setSelectedMode(null);
          setError('');
        }}
      />
    );
  }

  if (selectedMode) {
    const modeLabel = INITIALIZATION_MODES.find(option => option.mode === selectedMode)?.title;
    return (
      <div className="container py-5" style={{ maxWidth: 680 }}>
        <div className="card shadow">
          <div className="card-body p-4 p-md-5">
            <h1 className="h2 mb-3">Set Up Your FreeTV Library</h1>
            <p className="text-muted">
              {selectedMode === 'fresh'
                ? 'Start Fresh creates the first Administrator account and one empty default playlist named Playlist One. It does not add any shows.'
                : selectedMode === 'baseline'
                  ? 'Baseline Sample Data verifies and installs the bundled sample library and creates your Administrator account.'
                  : `${modeLabel} downloads and verifies the selected FreeTV dataset, installs its matching Viewer files, and creates your Administrator account.`}
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
                  {submitting ? (
                    <>
                      {selectedMode !== 'fresh' && (
                        <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                      )}
                      {selectedMode === 'fresh' ? `Installing ${modeLabel}...` : `Initializing ${modeLabel}...`}
                    </>
                  ) : `Initialize with ${modeLabel}`}
                </button>
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  onClick={() => { setSelectedMode(null); setError(''); }}
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
        {INITIALIZATION_MODES.map(option => (
          <div className="col-12 col-md-6" key={option.mode}>
            <div className={`card h-100 shadow-sm ${option.current ? 'border-warning bg-light' : 'border-primary'}`}>
              <div className="card-body d-flex flex-column">
                <h2 className="h4 card-title d-flex align-items-center gap-2">
                  <span>{option.title}</span>
                  {option.current && (
                    <img
                      src="/assets/internet.svg"
                      title="Internet required"
                      alt="Internet required"
                      width="20"
                      height="20"
                    />
                  )}
                </h2>
                <p className="card-text">{option.description}</p>
                <button
                  className={`btn ${option.current ? 'btn-warning' : 'btn-primary'} mt-auto`}
                  type="button"
                  onClick={() => setSelectedMode(option.mode)}
                >
                  {option.button}
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
