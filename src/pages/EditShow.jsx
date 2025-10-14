import { useState } from 'preact/hooks';
import { playlistSignal, switchPlaylist } from '@signals/playlistSignal';
import { useLocation } from 'preact-iso';
import { AdminShowForm } from '@/components/UI/AdminShowForm';
import { setAdminMsg } from '@/signals/adminMessageSignal';
import { AdminMessage } from '@/components/UI/AdminMessage';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';
import { createPath } from '@/utils/env';

export function EditShow() {

  const { url, route } = useLocation();
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  // Extract identifier param from url
  const identifier = (() => {
    const match = url.match(/\/dashboard\/edit\/([^/?#]+)/);
    return match ? decodeURIComponent(match[1]) : '';
  })();

  // Find show by identifier from current playlist's showData
  const { showData, currentPlaylist, loading: playlistLoading, error: playlistError } = playlistSignal.value;
  if (playlistLoading) return <SpinnerLoadingAppData />;
  if (playlistError) return <div className="alert alert-danger mt-4">{playlistError}</div>;
  const show = (showData || []).find(s => s.identifier === identifier);

  // Get unique categories for select from current playlist's showData
  const categories = Array.from(new Set((showData || []).map(s => s.category).filter(Boolean))).sort();

  function handleCancel() {
    route(createPath('/dashboard'));
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
          originalIdentifier: identifier, // <-- Add this: the identifier from the URL
          show: updatedShow
        })
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        setError(data && data.message ? data.message : 'Save failed.');
      } else {
        await switchPlaylist(currentPlaylist);
        // Check if identifier changed
        if (updatedShow.identifier !== identifier) {
          setAdminMsg({ type: 'success', text: 'The show has been updated successfully. The identifier was changed.' });
        } else {
          setAdminMsg({ type: 'success', text: 'The show you edited has been updated successfully.' });
        }
        route(createPath('/dashboard')); // <-- Always route back to dashboard
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
