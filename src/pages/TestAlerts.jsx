import { useState } from 'preact/hooks';
import { ErrorPage } from '../components/UI/ErrorPage';
import { useAlert } from '../context/AlertContext';

const ALERT_TYPES = [
  { value: 'danger', label: 'Danger (Red)' },
  { value: 'warning', label: 'Warning (Yellow)' },
  { value: 'info', label: 'Info (Blue)' },
  { value: 'success', label: 'Success (Green)' },
  { value: 'blue', label: 'Blue (Custom)' },
];

export function TestAlerts() {
  const [mode, setMode] = useState('showalert');
  const [alertType, setAlertType] = useState('danger');
  const [heading, setHeading] = useState('Test Heading');
  const [message, setMessage] = useState('This is a test error message.');
  const [showErrorPage, setShowErrorPage] = useState(false);
  const alertContext = useAlert();

  function handleGenerate(e) {
    e.preventDefault();
    if (mode === 'showalert') {
      alertContext.addAlert({
        type: alertType,
        heading: heading.trim(),
        body: message.trim(),
      });
      setShowErrorPage(false);
    } else {
      setShowErrorPage(true);
    }
  }

  return (
    <div className="container py-4">
      <h2>Test Alerts</h2>
      <form onSubmit={handleGenerate} className="mb-4">
        <div className="mb-3">
          <label className="form-label">Select Alert Mode:</label>
          <div>
            <div className="form-check form-check-inline">
              <input className="form-check-input" type="radio" name="mode" id="showalert" value="showalert" checked={mode === 'showalert'} onChange={() => setMode('showalert')} />
              <label className="form-check-label" htmlFor="showalert">ShowAlert</label>
            </div>
            <div className="form-check form-check-inline">
              <input className="form-check-input" type="radio" name="mode" id="errorpage" value="errorpage" checked={mode === 'errorpage'} onChange={() => setMode('errorpage')} />
              <label className="form-check-label" htmlFor="errorpage">ErrorPage</label>
            </div>
          </div>
        </div>
        {mode === 'showalert' && (
          <div className="mb-3">
            <label className="form-label">Alert Type:</label>
            <select className="form-select" value={alertType} onChange={e => setAlertType(e.currentTarget.value)}>
              {ALERT_TYPES.map(opt => (
                <option key={opt.value} value={opt.value}>{opt.label}</option>
              ))}
            </select>
          </div>
        )}
        <div className="mb-3">
          <label className="form-label">Heading:</label>
          <input className="form-control" type="text" maxLength={64} value={heading} onChange={e => setHeading(e.currentTarget.value)} required />
        </div>
        <div className="mb-3">
          <label className="form-label">Message:</label>
          <textarea className="form-control" maxLength={255} value={message} onChange={e => setMessage(e.currentTarget.value)} required />
        </div>
        <button className="btn btn-primary" type="submit">Generate</button>
      </form>
      {showErrorPage && mode === 'errorpage' && (
        <ErrorPage type={heading} message={message} />
      )}
    </div>
  );
}
