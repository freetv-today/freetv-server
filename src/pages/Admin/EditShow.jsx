import { useContext, useMemo, useState } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { AdminShowForm } from '@/components/Admin/AdminShowForm.jsx';

export default function EditShow() {
  const { url, route } = useLocation();
  const { currentPlaylist, currentPlaylistData, changePlaylist } = useContext(PlaylistContext);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);

  // Extract imdb param from url
  const imdb = (() => {
    const match = url.match(/\/dashboard\/edit\/([^/?#]+)/);
    return match ? decodeURIComponent(match[1]) : '';
  })();

  // Find show by imdb
  const show = useMemo(() => {
    if (!currentPlaylistData || !currentPlaylistData.shows) return null;
    return currentPlaylistData.shows.find(s => s.imdb === imdb);
  }, [currentPlaylistData, imdb]);

  // Get unique categories for select
  const categories = useMemo(() => {
    if (!currentPlaylistData || !currentPlaylistData.shows) return [];
    const cats = currentPlaylistData.shows.map(s => s.category).filter(Boolean);
    return Array.from(new Set(cats));
  }, [currentPlaylistData]);

  function handleCancel() {
    // Go back to dashboard
    route('/dashboard');
  }

  async function handleSave(updatedShow) {
    setSaving(true);
    setError(null);
    setSuccess(null);
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
        setSuccess('Show updated successfully. Page will be reloaded to refresh playlist data.');
        // Reload playlist data after dismiss
        setTimeout(() => {
          changePlaylist(currentPlaylist, true, false);
          route('/dashboard');
        }, 1500);
      }
    } catch (err) {
      setError('Save failed.');
    } finally {
      setSaving(false);
    }
  }

  if (!show) {
    return <div class="container mt-5"><div class="alert alert-danger">Show not found.</div></div>;
  }

  return (
    <div class="container mt-4" style={{ maxWidth: 700 }}>
      <h2 class="mb-3">Edit Show</h2>
      <AdminShowForm
        initialData={show}
        onSave={handleSave}
        onCancel={handleCancel}
        saving={saving}
        error={error}
        categories={categories}
      />
      {success && <div class="alert alert-success mt-3">{success}</div>}
    </div>
  );
}
