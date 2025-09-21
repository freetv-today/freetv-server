import { useState, useEffect } from 'preact/hooks';
import { capitalizeFirstLetter } from '@/utils';

/**
 * Modal for reporting a problem with a show.
 * @param {Object} props
 * @param {boolean} props.show - Whether the modal is visible
 * @param {() => void} props.onClose - Function to close the modal
 * @param {string} props.title - Show title
 * @param {string} props.category - Show category
 * @param {string} props.identifier - Show identifier
 * @param {string} props.desc - Show description
 * @param {string} props.start - Start year
 * @param {string} props.end - End year
 * @param {string} props.imdb - IMDB id
 * @param {string} props.playlist - Playlist filename
 * @returns {import('preact').JSX.Element|null}
 */

export function ReportProblemModal({ show, onClose, title, category, identifier, desc, start, end, imdb, playlist }) {
  const [submitting, setSubmitting] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState(null);
  const [rateLimited, setRateLimited] = useState(false);
  const [thumbnailSrc, setThumbnailSrc] = useState('/assets/vintage-tv.png');

  useEffect(() => {
    if (imdb) {
      const img = new window.Image();
      img.src = `/thumbs/${imdb}.jpg`;
      img.onload = () => setThumbnailSrc(img.src);
      img.onerror = () => setThumbnailSrc('/assets/vintage-tv.png');
    } else {
      setThumbnailSrc('/assets/vintage-tv.png');
    }
  }, [imdb]);

  // Reset state when modal is closed or opened for a new report
  useEffect(() => {
    if (show) {
      setSubmitting(false);
      setSubmitted(false);
      setError(null);
      setRateLimited(false);
    }
  }, [show, title, identifier]);

  // Handles the report submission
  const handleSubmit = async (e) => {
    if (e && typeof e.preventDefault === 'function') e.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      const response = await fetch('/api/report-problem.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, category, identifier, desc, start, end, imdb, playlist })
      });
      let data = null;
      try {
        data = await response.json();
      } catch (err) {
        // fallback: generic error
      }
      if (response.status === 429) {
        setError(data && data.message ? data.message : 'You are submitting problem reports too quickly. Please wait awhile before attempting to report another show title.');
        setRateLimited(true);
        return;
      }
      if (response.status === 400) {
        setError(data && data.message ? data.message : 'You have already reported this title.');
        setRateLimited(true);
        return;
      }
      if (!response.ok) {
        setError(data && data.message ? data.message : 'There was a problem submitting your report. Please try again later.');
        return;
      }
      setSubmitted(true);
    } catch (err) {
      setError('There was a problem submitting your report. Please try again later.');
    } finally {
      setSubmitting(false);
    }
  };

  if (!show) return null;

  // Modal implementation matches DescriptionModal.jsx, but only shows Title, Category, IMDB, and thumbnail
  return (
    <div className={`modal fade${show ? ' show d-block' : ''}`} tabIndex={-1} style={show ? { backgroundColor: 'rgba(0,0,0,0.5)' } : {}}>
      <div className="modal-dialog">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Report a Problem</h5>
            <button type="button" className="btn-close" aria-label="Close" onClick={onClose} disabled={submitting}></button>
          </div>
          <div className="modal-body">
            {submitted ? (
              <div className="alert alert-success">
                Thank you! Your problem report has been received.
              </div>
            ) : (
              <>
                <p>Are you sure you want to report a problem with the following show?</p>
                <div className="d-flex flex-row gap-2 mb-4 justify-content-center align-middle">
                  <div className="m-0">
                    <img src={thumbnailSrc} width="100" alt={`${title} Thumbnail`} />
                  </div>
                  <div className="flex-grow-1 p-2">
                    <ul>
                      <li><strong>Title:</strong> {title}</li>
                      <li><strong>Category:</strong> {capitalizeFirstLetter(category)}</li>
                      <li><strong>IMDB:</strong> {imdb}</li>
                    </ul>
                  </div>
                </div>
                {error && <div className="alert alert-danger">{error}</div>}
              </>
            )}
          </div>
          <div className="modal-footer">
            {submitted ? (
              <button type="button" className="btn btn-secondary" onClick={onClose}>Close</button>
            ) : (
              <>
                <button type="button" className="btn btn-secondary" onClick={onClose} disabled={submitting}>Cancel</button>
                <button type="button" className="btn btn-danger" onClick={handleSubmit} disabled={submitting || rateLimited}>
                  {submitting ? (
                    <>
                      <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                      Reporting...
                    </>
                  ) : 'Report Problem'}
                </button>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
