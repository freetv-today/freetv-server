import { useState, useEffect } from 'preact/hooks';

/**
 * AdminPlaylistMetaModal - Modal for editing playlist meta data
 * @param {Object} props
 * @param {boolean} props.show - Whether the modal is visible
 * @param {(reason: 'cancel' | 'save') => void} props.onClose - Function to close the modal
 * @param {boolean} props.saving - Whether the save is in progress
 * @param {string|null} props.error - Error message, if any
 * @param {function(Object):void} props.onSave - Called with updated meta on save
 * @param {Object} props.meta - The current playlist metadata, including is_default
 */

export function AdminPlaylistMetaModal({ show, onClose, saving, error, onSave, meta }) {
  const [form, setForm] = useState({
    lastupdated: meta?.lastupdated || '',
    dbtitle: meta?.dbtitle || '',
    dbversion: meta?.dbversion || '',
    author: meta?.author || '',
    email: meta?.email || '',
    link: meta?.link || '',
    is_default: meta?.is_default === true
  });
  const [touched, setTouched] = useState(false);

  // Reset form when meta or show changes
  useEffect(() => {
    setForm({
      lastupdated: meta?.lastupdated || '',
      dbtitle: meta?.dbtitle || '',
      dbversion: meta?.dbversion || '',
      author: meta?.author || '',
      email: meta?.email || '',
      link: meta?.link || '',
      is_default: meta?.is_default === true
    });
    setTouched(false);
  }, [meta, show]);

  function handleChange(e) {
    const { name, value } = e.target;
    setForm(f => ({ ...f, [name]: value }));
    setTouched(true);
  }

  function handleDefaultChange(e) {
    if (meta?.is_default === true || e.currentTarget.checked !== true) return;
    setForm(current => ({ ...current, is_default: true }));
    setTouched(true);
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const updatedMeta = {
      dbtitle: form.dbtitle,
      dbversion: form.dbversion,
      author: form.author,
      email: form.email,
      link: form.link
    };
    if (meta?.is_default !== true && form.is_default === true) {
      updatedMeta.is_default = true;
    }
    await onSave(updatedMeta);
  }

  if (!show) return null;

  return (
    <div className={`modal fade${show ? ' show d-block' : ''}`} tabIndex={-1} style={show ? { backgroundColor: 'rgba(0,0,0,0.5)' } : {}}>
      <div className="modal-dialog">
        <div className="modal-content">
          <form onSubmit={handleSubmit}>
            <div className="modal-header">
              <h5 className="modal-title">Edit Playlist Meta Data</h5>
              <button type="button" className="btn-close" aria-label="Close" onClick={() => onClose('cancel')}></button>
            </div>
            <div className="modal-body">
              <div className="mb-3" title="Timestamp will be updated automatically when changes are saved">
                <label className="form-label">Last Updated</label>
                <input type="text" className="form-control form-control-sm" name="lastupdated" value={form.lastupdated} disabled />
              </div>
              <div className="mb-3">
                <label className="form-label">Title</label>
                <input type="text" className="form-control form-control-sm" name="dbtitle" value={form.dbtitle} onInput={handleChange} />
              </div>
              <div className="mb-3">
                <label className="form-label">Version</label>
                <input type="text" className="form-control form-control-sm" name="dbversion" value={form.dbversion} onInput={handleChange} />
              </div>
              <div className="mb-3">
                <label className="form-label">Author</label>
                <input type="text" className="form-control form-control-sm" name="author" value={form.author} onInput={handleChange} />
              </div>
              <div className="mb-3">
                <label className="form-label">Email</label>
                <input type="email" className="form-control form-control-sm" name="email" value={form.email} onInput={handleChange} />
              </div>
              <div className="mb-3">
                <label className="form-label">Link</label>
                <input type="text" className="form-control form-control-sm" name="link" value={form.link} onInput={handleChange} />
              </div>
              <div className="form-check mb-3">
                <input
                  type="checkbox"
                  className="form-check-input"
                  id="playlist-default"
                  checked={form.is_default}
                  onChange={handleDefaultChange}
                  disabled={saving || form.is_default}
                />
                <label className="form-check-label" htmlFor="playlist-default">
                  Default Playlist
                </label>
              </div>
              {error && <div className="alert alert-danger">{error}</div>}
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary btn-sm" onClick={() => onClose('cancel')} disabled={saving}>Cancel</button>
              <button type="submit" className="btn btn-primary btn-sm" disabled={saving || !touched}>
                {saving ? 'Saving...' : 'Save'}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
