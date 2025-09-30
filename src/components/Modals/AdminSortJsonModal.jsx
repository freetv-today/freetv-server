import { useState, useContext } from 'preact/hooks';
import { setAdminMsg } from '@/signals/adminMessageSignal';
// import { PlaylistContext } from '@/context/PlaylistContext';

export function AdminSortJsonModal({ show, onClose, playlistFilename }) {
  const [saving, setSaving] = useState(false);
  // const { currentPlaylist } = useContext(PlaylistContext);

  // Get playlist filename from prop, context, or localStorage
  function getPlaylistFilename() {
    if (playlistFilename) return playlistFilename;
    // if (currentPlaylist) return currentPlaylist;
    try {
      const ls = window.localStorage.getItem('playlist');
      if (ls) return JSON.parse(ls);
    } catch {}
    return null;
  }

  const handleSort = async () => {
    setSaving(true);
    try {
      const filename = getPlaylistFilename();
      if (!filename) {
        setAdminMsg({ type: 'danger', text: 'No playlist selected.' });
        onClose('error');
        setSaving(false);
        return;
      }
      const cleanFilename = filename.replace(/^"|"$/g, '');
      const res = await fetch(`/api/admin/sort-playlist.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ filename: cleanFilename })
      });
      const data = await res.json();
      if (res.ok && data.success) {
        setAdminMsg({
          type: 'success',
          text: data.message || 'Playlist sorted by category.'
        });
        onClose('save');
      } else {
        setAdminMsg({
          type: 'danger',
          text: data.message || 'Sort failed.'
        });
        onClose('error');
      }
    } catch (e) {
      setAdminMsg({
        type: 'danger',
        text: 'Network or server error.'
      });
      onClose('error');
    } finally {
      setSaving(false);
    }
  };

  if (!show) return null;

  return (
    <div className={`modal fade${show ? ' show d-block' : ''}`} tabIndex={-1} style={show ? { backgroundColor: 'rgba(0,0,0,0.5)' } : {}}>
      <div className="modal-dialog modal-dialog-centered">
        <div className="modal-content">
          <div className="modal-header">
            <h5 className="modal-title">Sort Playlist JSON Data by Category</h5>
            <button type="button" className="btn-close" aria-label="Close" onClick={() => onClose('cancel')} disabled={saving}></button>
          </div>
          <div className="modal-body">
            <p><b>Use this tool to sort the current playlist data alphabetically by category.</b> This will reorganize all the JSON data in the current playlist, update the timestamp, and save it. Please note: this won't change the Admin Dashboard view which is sorted alphabetically by Show Title. <span className="text-danger opacity-75">This operation cannot be undone!</span></p> 
            
            

          </div>
          <div className="modal-footer mb-3">
            <button type="button" className="btn btn-secondary btn-sm" onClick={() => onClose('cancel')} disabled={saving}>Cancel</button>
            <button type="button" className="btn btn-danger btn-sm" onClick={handleSort} disabled={saving}>
              {saving && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
              {saving ? 'Sorting Data...' : 'Sort JSON Data By Category'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
