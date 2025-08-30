import { useContext, useMemo, useState } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { PlaylistContext } from '@/context/PlaylistContext';
import { AdminShowForm } from '@/components/Admin/AdminShowForm';
import { useLocalStorage } from '@/hooks/useLocalStorage';

export function EditShow() {
  const { url, route } = useLocation();
  const { currentPlaylist, currentPlaylistData, changePlaylist } = useContext(PlaylistContext);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [adminMsg, setAdminMsg] = useLocalStorage('adminMsg', null);

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
        // Call the PHP utility to rebuild the index
        await fetch('/api/admin/playlist_utils.php', { method: 'POST' });
        await changePlaylist(currentPlaylist, true, false);
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
    return <div class="container mt-5"><div class="alert alert-danger">Show not found.</div></div>;
  }

  return (
    <div class="container mt-4" style={{ maxWidth: 700 }}>
      <h2 class="mb-3">Edit Show</h2>
      {error && <div class="alert alert-danger mb-3">{error}</div>}
      <AdminShowForm
        initialData={show}
        onSave={handleSave}
        onCancel={handleCancel}
        saving={saving}
        error={null}
        categories={categories}
      />
    </div>
  );
}
