import { useState, useEffect } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { useDebugLog } from '@/hooks/useDebugLog';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';

/**
 * DataSetupPage - Helps users set up application data when none exists
 */

export function DataSetupPage({ dataState, onRetry }) {
  const log = useDebugLog();
  const { route } = useLocation();
  const [setupLoading, setSetupLoading] = useState(false);
  const [setupError, setSetupError] = useState(null);
  const [setupProgress, setSetupProgress] = useState('');

  useEffect(() => {
    document.title = "Admin Dashboard - Data Setup Required";
    log('Rendered Data Setup page - no application data found');
  }, []);

  const handleLoadOfficialData = async () => {
    setSetupLoading(true);
    setSetupError(null);
    setSetupProgress('Connecting to GitHub...');
    try {
      const response = await fetch('/api/admin/setup-data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'official' })
      });
      
      setSetupProgress('Downloading files...');
      const result = await response.json();
      if (result.success) {
        setSetupProgress('Setup complete!');
        log('Official data loaded successfully');
        setTimeout(() => onRetry(), 1000);
      } else {
        setSetupError(result.message || 'Failed to load official data');
      }
    } catch (err) {
      setSetupError('Network error loading official data: ' + err.message);
    }
    setSetupLoading(false);
    setSetupProgress('');
  };

  const handleLoadSampleData = async () => {
    setSetupLoading(true);
    setSetupError(null);
    setSetupProgress('Creating sample data...');
    try {
      const response = await fetch('/api/admin/setup-data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'sample' })
      });
      
      const result = await response.json();
      if (result.success) {
        setSetupProgress('Sample data created!');
        log('Sample data loaded successfully');
        setTimeout(() => onRetry(), 1000);
      } else {
        setSetupError(result.message || 'Failed to load sample data');
      }
    } catch (err) {
      setSetupError('Network error loading sample data: ' + err.message);
    }
    setSetupLoading(false);
    setSetupProgress('');
  };

  const handleStartFresh = async () => {
    setSetupLoading(true);
    setSetupError(null);
    try {
      const response = await fetch('/api/admin/setup-data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'fresh' })
      });
      
      const result = await response.json();
      if (result.success) {
        log('Fresh setup completed');
        onRetry();
      } else {
        setSetupError(result.message || 'Failed to create fresh setup');
      }
    } catch (err) {
      setSetupError('Network error setting up fresh data');
    }
    setSetupLoading(false);
  };

  if (setupLoading) {
    return (
      <div className="d-flex flex-column align-items-center justify-content-center" style={{ minHeight: '60vh' }}>
        <div className="spinner-border text-primary mb-3" role="status" style={{ width: '3rem', height: '3rem' }}>
          <span className="visually-hidden">Loading...</span>
        </div>
        <h4 className="text-primary mb-2">Setting Up Your Data</h4>
        {setupProgress && <p className="text-muted">{setupProgress}</p>}
      </div>
    );
  }

  return (
    <div className="container py-5" style={{ maxWidth: 800 }}>
      <div className="text-center mb-5">
        <img src="/assets/sadface.svg" alt="😢" width="80" className="mb-3" />
        <h2 className="text-danger mb-3">Application Data Not Found</h2>
        <p className="lead text-muted">
          Your Admin Dashboard needs data to function. Choose one of the options below to get started.
        </p>
      </div>

      {setupError && (
        <div className="alert alert-danger" role="alert">
          <strong>Setup Error:</strong> {setupError}
        </div>
      )}

      <div className="row g-4">
        {/* Option 1: Official Data */}
        <div className="col-md-4">
          <div className="card h-100 border-primary">
            <div className="card-header bg-primary text-white">
              <h5 className="card-title mb-0">
                <i className="bi bi-download me-2"></i>
                Official Data
              </h5>
            </div>
            <div className="card-body">
              <p className="card-text">
                Load the complete FreeTv dataset from GitHub including all playlists, 
                thumbnails, and configuration.
              </p>
              <ul className="small text-muted">
                <li>Full production dataset</li>
                <li>All movie categories</li>
                <li>Complete thumbnail library</li>
                <li>Production configuration</li>
              </ul>
            </div>
            <div className="card-footer">
              <button 
                className="btn btn-primary w-100" 
                onClick={handleLoadOfficialData}
                disabled={setupLoading}
              >
                Load Official Data
              </button>
            </div>
          </div>
        </div>

        {/* Option 2: Sample Data */}
        <div className="col-md-4">
          <div className="card h-100 border-success">
            <div className="card-header bg-success text-white">
              <h5 className="card-title mb-0">
                <i className="bi bi-play-circle me-2"></i>
                Sample Data
              </h5>
            </div>
            <div className="card-body">
              <p className="card-text">
                Load a smaller dataset perfect for development and testing 
                the Admin Dashboard features.
              </p>
              <ul className="small text-muted">
                <li>2-3 sample playlists</li>
                <li>4-5 categories each</li>
                <li>20-30 shows total</li>
                <li>Sample thumbnails included</li>
              </ul>
            </div>
            <div className="card-footer">
              <button 
                className="btn btn-success w-100" 
                onClick={handleLoadSampleData}
                disabled={setupLoading}
              >
                Load Sample Data
              </button>
            </div>
          </div>
        </div>

        {/* Option 3: Start Fresh */}
        <div className="col-md-4">
          <div className="card h-100 border-warning">
            <div className="card-header bg-warning text-dark">
              <h5 className="card-title mb-0">
                <i className="bi bi-plus-circle me-2"></i>
                Start Fresh
              </h5>
            </div>
            <div className="card-body">
              <p className="card-text">
                Create minimal configuration files only. You'll start with 
                an empty admin dashboard and add your own content.
              </p>
              <ul className="small text-muted">
                <li>Basic configuration only</li>
                <li>Empty playlist structure</li>
                <li>No initial content</li>
                <li>Full customization freedom</li>
              </ul>
            </div>
            <div className="card-footer">
              <button 
                className="btn btn-warning w-100" 
                onClick={handleStartFresh}
                disabled={setupLoading}
              >
                Start Fresh
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Issues Details */}
      {dataState.issues && dataState.issues.length > 0 && (
        <div className="mt-5">
          <h4 className="mb-3">Missing Components:</h4>
          <div className="list-group">
            {dataState.issues.map((issue, index) => (
              <div 
                key={index} 
                className={`list-group-item ${issue.severity === 'critical' ? 'list-group-item-danger' : 'list-group-item-warning'}`}
              >
                <div className="d-flex w-100 justify-content-between">
                  <h6 className="mb-1 text-capitalize">{issue.type} Issue</h6>
                  <small className={`badge ${issue.severity === 'critical' ? 'bg-danger' : 'bg-warning'}`}>
                    {issue.severity}
                  </small>
                </div>
                <p className="mb-1">{issue.message}</p>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="text-center mt-5">
        <button 
          className="btn btn-outline-secondary me-2" 
          onClick={onRetry}
          disabled={setupLoading}
        >
          Check Again
        </button>
        <button 
          className="btn btn-outline-primary" 
          onClick={() => route('/')}
          disabled={setupLoading}
        >
          Back to Login
        </button>
      </div>
    </div>
  );
}