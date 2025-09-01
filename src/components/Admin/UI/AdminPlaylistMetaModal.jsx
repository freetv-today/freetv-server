import { useState, useEffect, useContext } from 'preact/hooks';
import { useLocalStorage } from '@/hooks/useLocalStorage';
import { PlaylistContext } from '@/context/PlaylistContext';
import { useDebugLog } from '@/hooks/useDebugLog';

/**
 * AdminPlaylistMetaModal - Modal for editing playlist meta data
 * @param {Object} props
 * @param {boolean} props.show - Whether the modal is visible
 * @param {(reason: 'cancel' | 'save') => void} props.onClose - Function to close the modal
 * @param {Object} props.meta - The meta data object
 * @param {boolean} props.saving - Whether the save is in progress
 * @param {string|null} props.error - Error message, if any
 * @param {function(Object):void} props.onSave - Called with updated meta on save
 */

export function AdminPlaylistMetaModal({ show, onClose, meta, saving, error, onSave }) {
  
  const log = useDebugLog();
  const [adminMsg, setAdminMsg] = useLocalStorage('adminMsg', null);
  const { currentPlaylist, changePlaylist } = useContext(PlaylistContext);
  const [form, setForm] = useState({
    lastupdated: meta?.lastupdated || '',
    dbtitle: meta?.dbtitle || '',
    dbversion: meta?.dbversion || '',
    author: meta?.author || '',
    email: meta?.email || '',
    link: meta?.link || ''
  });
  const [touched, setTouched] = useState(false);

  useEffect(() => {
    setForm({
      lastupdated: meta?.lastupdated || '',
      dbtitle: meta?.dbtitle || '',
      dbversion: meta?.dbversion || '',
      author: meta?.author || '',
      email: meta?.email || '',
      link: meta?.link || ''
    });
    setTouched(false);
  }, [meta, show]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm(f => ({ ...f, [name]: value }));
    setTouched(true);
  }

  async function handleSubmit(e) {
    e.preventDefault();
    // 1. Save meta (calls update-meta.php via onSave)
    await onSave(form);
    // 2. Rebuild index.json (wait for it to finish)
    await fetch('/api/admin/playlist_utils.php', { method: 'POST' });
    // 3. Refresh playlist data in context (wait for it to finish)
    if (typeof changePlaylist === 'function' && currentPlaylist) {
      await changePlaylist(currentPlaylist, true, false);
    }
    // 4. Set adminMsg and close modal (only if no error)
    if (!error) {
      setAdminMsg({ type: 'success', text: 'Playlist meta data updated successfully.' });
      log('Playlist meta data saved');
      onClose('save');
    }
  }

  if (!show || !meta) return null;

  return (
    <div class={`modal fade${show ? ' show d-block' : ''}`} tabIndex={-1} style={show ? { backgroundColor: 'rgba(0,0,0,0.5)' } : {}}>
      <div class="modal-dialog">
        <div class="modal-content">
          <form onSubmit={handleSubmit}>
            <div class="modal-header">
              <h5 class="modal-title">Edit Playlist Meta Data</h5>
          <button type="button" class="btn-close" aria-label="Close" onClick={() => onClose('cancel')}></button>
            </div>
            <div class="modal-body">
              <div class="mb-3" title="Timestamp will be updated automatically when changes are saved">
                <label class="form-label">Last Updated</label>
                <input type="text" class="form-control form-control-sm" name="lastupdated" value={form.lastupdated || ''} disabled />
              </div>
              <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" class="form-control form-control-sm" name="dbtitle" value={form.dbtitle || ''} onInput={handleChange} />
              </div>
              <div class="mb-3">
                <label class="form-label">Version</label>
                <input type="text" class="form-control form-control-sm" name="dbversion" value={form.dbversion || ''} onInput={handleChange} />
              </div>
              <div class="mb-3">
                <label class="form-label">Author</label>
                <input type="text" class="form-control form-control-sm" name="author" value={form.author || ''} onInput={handleChange} />
              </div>
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control form-control-sm" name="email" value={form.email || ''} onInput={handleChange} />
              </div>
              <div class="mb-3">
                <label class="form-label">Link</label>
                <input type="text" class="form-control form-control-sm" name="link" value={form.link || ''} onInput={handleChange} />
              </div>
              {error && <div class="alert alert-danger">{error}</div>}
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary btn-sm" onClick={() => onClose('cancel')} disabled={saving}>Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm" disabled={saving || !touched}>
                {saving ? 'Saving...' : 'Save'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
