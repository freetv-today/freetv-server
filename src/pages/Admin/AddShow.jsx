import { useContext, useMemo, useState, useEffect } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { PlaylistContext } from '@/context/PlaylistContext';
import { AdminShowForm } from '@/components/Admin/UI/AdminShowForm';
import { useDebugLog } from '@/hooks/useDebugLog';
import { useAdminSession } from '@hooks/Admin/useAdminSession';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { AdminMessage } from '@/components/Admin/UI/AdminMessage';

export function AddShow() {

  const user = useAdminSession();
  const log = useDebugLog();
  const { route } = useLocation();
  const { currentPlaylist, currentPlaylistData, changePlaylist } = useContext(PlaylistContext);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    document.title = "Free TV: Admin Dashboard - Add Show";
    log('Rendered Add Show page (pages/Admin/AddShow.jsx)');
  }, []);

  // Get unique categories for select
  const categories = useMemo(() => {
    if (!currentPlaylistData || !currentPlaylistData.shows) return [];
    const cats = currentPlaylistData.shows.map(s => s.category).filter(Boolean);
    return Array.from(new Set(cats));
  }, [currentPlaylistData]);

  function handleCancel() {
    route('/dashboard');
  }

  // Save handler: if stayOnPage is true, show signal-based alert and reset form; else, route to dashboard
  async function handleSave(newShow, stayOnPage = false, resetFormCallback) {
    setSaving(true);
    setError(null);
    try {
      console.log('[AddShow] Posting new show...', { playlist: currentPlaylist, show: newShow });
      const res = await fetch('/api/admin/update-show.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          playlist: currentPlaylist,
          show: newShow,
          add: true
        })
      });
      console.log('[AddShow] Received response from update-show.php:', res);
      const data = await res.json();
      console.log('[AddShow] Parsed JSON:', data);
      if (!res.ok || !data.success) {
        console.error('[AddShow] Add failed:', data && data.message ? data.message : 'Add failed.');
        setError(data && data.message ? data.message : 'Add failed.');
      } else {
        console.log('[AddShow] Calling playlist_utils.php to rebuild index...');
        const rebuildRes = await fetch('/api/admin/playlist_utils.php', { method: 'POST' });
        console.log('[AddShow] Rebuild index response:', rebuildRes);
        if (stayOnPage) {
          setAdminMsg({ type: 'success', text: 'The show has been added successfully.' });
          if (typeof resetFormCallback === 'function') resetFormCallback();
          await changePlaylist(currentPlaylist, true, false); // normal for add more
        } else {
          setAdminMsg({ type: 'success', text: 'The new show has been added successfully.' });
          route('/dashboard');
          await changePlaylist(currentPlaylist, true, false, true); // suppressRoute for dashboard
        }
      }
    } catch (err) {
        console.error('[AddShow] Exception during add:', err);
        setError('Add failed.');
    } finally {
        setSaving(false);
        console.log('[AddShow] Done.');
    }
  }

  // Blank initial data for the form
  const initialData = {
    category: '',
    status: 'active',
    identifier: '',
    title: '',
    desc: '',
    start: '',
    end: '',
    imdb: ''
  };
  const [formKey, setFormKey] = useState(0); // for resetting AdminShowForm

  if (!user) return null;

  // Handler for Save and Add More Shows
  function handleSaveAndAddMore(newShow, resetFormCallback) {
    handleSave(newShow, true, () => {
      setFormKey(k => k + 1); // force AdminShowForm to reset
      if (typeof resetFormCallback === 'function') resetFormCallback();
    });
  }

  return (
    <div className="container mt-4" style={{ maxWidth: 700 }}>
      <AdminMessage />
      <h2 className="mb-3">Add New Video</h2>
      {error && <div className="alert alert-danger mb-3">{error}</div>}
      <AdminShowForm
        key={formKey}
        initialData={initialData}
        onSave={handleSave}
        onSaveAndAddMore={handleSaveAndAddMore}
        onCancel={handleCancel}
        saving={saving}
        error={null}
        categories={categories}
      />
    </div>
  );
}
