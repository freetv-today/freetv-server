import { useState } from 'preact/hooks';
import { playlistSignal, switchPlaylist } from '@signals/playlistSignal';
import { useLocation } from 'preact-iso';
import { AdminShowForm } from '@/components/UI/AdminShowForm';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { AdminMessage } from '@/components/UI/AdminMessage';

export function EditShow() {

  const { url, route } = useLocation();
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  // Extract imdb param from url
  const imdb = (() => {
    const match = url.match(/\/dashboard\/edit\/([^/?#]+)/);
    return match ? decodeURIComponent(match[1]) : '';
  })();

  // Find show by imdb from current playlist's showData
  const { showData, currentPlaylist } = playlistSignal.value;
  const show = (showData || []).find(s => s.imdb === imdb);

  // Get unique categories for select from current playlist's showData
  const categories = Array.from(new Set((showData || []).map(s => s.category).filter(Boolean)));

  function handleCancel() {
    route('/dashboard');
  }

  async function handleSave(updatedShow) {
    setSaving(true);
    setError(null);
    try {
      const res = await fetch('/api/admin/update-show.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          playlist: currentPlaylist,
          show: updatedShow
        })
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        setError(data && data.message ? data.message : 'Save failed.');
      } else {
        await switchPlaylist(currentPlaylist);
        setAdminMsg({ type: 'success', text: 'The show you edited has been updated successfully.' });
        route('/dashboard');
      }
    } catch (err) {
      setError('Save failed.');
    } finally {
      setSaving(false);
    }
  }

  if (!show) {
    return <div className="container mt-5"><div className="alert alert-danger">Show not found.</div></div>;
  }
  
  return (
    <div className="container mt-4" style={{ maxWidth: 700 }}>
      <AdminMessage />
      <h2 className="mb-3">Edit Show</h2>
      {error && <div className="alert alert-danger mb-3">{error}</div>}
      <AdminShowForm
        initialData={show}
        onSave={handleSave}
        onCancel={handleCancel}
        saving={saving}
        error={null}
        categories={categories}
        mode="edit"
      />
    </div>
  );
}
