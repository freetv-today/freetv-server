import { useEffect } from 'preact/hooks';

/**
 * AdminFilenameInfoModal - Modal for explaining playlist file name rules (admin area pattern)
 * @param {Object} props
 * @param {boolean} props.show - Whether the modal is visible
 * @param {function} props.onClose - Function to close the modal
 */
export function AdminFilenameInfoModal({ show, onClose }) {
  useEffect(() => {
    if (show) {
      document.body.classList.add('modal-open');
    } else {
      document.body.classList.remove('modal-open');
    }
    return () => document.body.classList.remove('modal-open');
  }, [show]);

  if (!show) return null;

  return (
    <div className="modal fade show d-block" tabIndex={-1} style={{ background: 'rgba(0,0,0,0.3)' }}>
      <div className="modal-dialog">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Playlist File Name Info</h5>
            <button type="button" className="btn-close" aria-label="Close" onClick={e => { e.preventDefault(); onClose(); }}></button>
          </div>
          <div className="modal-body">
            <p>File names should only use letters, numbers, dashes, and underscores. Do not use spaces or special characters. The file will be saved as <code>yourname.json</code> in the playlists folder. If you type an extension (like .txt or .doc), it will be saved as <code>yourname.txt.json</code>. Avoid using extensions unless you intend this.</p>
            <p><b>Do not use "index" as a file name.</b></p>
          </div>
          <div className="modal-footer">
            <button type="button" className="btn btn-secondary" onClick={e => { e.preventDefault(); onClose(); }}>Close</button>
          </div>
        </div>
      </div>
    </div>
  );
}
