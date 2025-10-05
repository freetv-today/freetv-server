import { useState, useEffect } from 'preact/hooks';
import { playlistSignal, switchPlaylist } from '@signals/playlistSignal';
import { useLocation } from 'preact-iso';
import { AdminShowForm } from '@/components/UI/AdminShowForm';
import { useDebugLog } from '@/hooks/useDebugLog';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { AdminMessage } from '@/components/UI/AdminMessage';
import { createPath } from '@/utils/env';

export function AddShow() {

  const log = useDebugLog();
  const { route } = useLocation();
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    document.title = "Free TV: Admin Dashboard - Add Show";
    log('Rendered Add Show page (pages/AddShow.jsx)');
  }, []);

  // Get unique categories for select from current playlist's showData
  const { showData, currentPlaylist } = playlistSignal.value;
  const categories = Array.from(new Set((showData || []).map(s => s.category).filter(Boolean)));

  function handleCancel() {
    route(createPath('/dashboard'));
  }

  // Save handler: if stayOnPage is true, show signal-based alert and reset form; 
  // else, route to dashboard and show admin message
  async function handleSave(newShow, stayOnPage = false, resetFormCallback) {
    setSaving(true);
    setError(null);
    try {
      const res = await fetch('/api/admin/update-show.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          playlist: currentPlaylist,
          show: newShow,
          add: true
        })
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        console.error('[AddShow] Add failed:', data && data.message ? data.message : 'Add failed.');
        setError(data && data.message ? data.message : 'Add failed.');
      } else {
        if (stayOnPage) {
          setAdminMsg({ type: 'success', text: 'The show has been added successfully.' });
          await switchPlaylist(currentPlaylist);
          window.scrollTo({ top: 0, behavior: 'auto' });
          if (typeof resetFormCallback === 'function') resetFormCallback();
        } else {
          await switchPlaylist(currentPlaylist);
          setAdminMsg({ type: 'success', text: 'The new show has been added successfully.' });
          route(createPath('/dashboard'));
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
        mode="add"
      />
    </div>
  );
}
