
import { useEffect } from 'preact/hooks';

// Modal for displaying application info/stats, using Bootstrap modal structure for consistency
export function AdminInfoModal({ show, onClose, stats }) {
  useEffect(() => {
    if (!show) return;
    function onKey(e) {
  if (e.key === 'Escape') onClose('cancel');
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [show, onClose]);

  if (!show) return null;

  // Use the same structure and classes as AdminPlaylistMetaModal
  return (
    <div className={`modal fade${show ? ' show d-block' : ''}`} tabIndex={-1} style={show ? { backgroundColor: 'rgba(0,0,0,0.5)' } : {}}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Current Playlist Information</h5>
            <button type="button" className="btn-close" aria-label="Close" onClick={() => onClose('cancel')}></button>
          </div>
          <div className="modal-body">
            <ul className="list-group list-group-flush">
              <li className="list-group-item">
                <strong>Total Shows:</strong> {stats.totalShows}
              </li>
              <li className="list-group-item">
                <strong>Active Shows:</strong> {stats.activeShows}
              </li>
              <li className="list-group-item">
                <strong>Disabled Shows:</strong> {stats.disabledShows}
              </li>
              <li className="list-group-item">
                <strong>Total Playlists:</strong> {stats.totalPlaylists}
              </li>
            </ul>
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={() => onClose('cancel')}>Close</button>
          </div>
        </div>
      </div>
    </div>
  );
}
