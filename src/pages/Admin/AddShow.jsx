import { useContext, useMemo, useState, useRef } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { PlaylistContext } from '@/context/PlaylistContext.jsx';
import { AdminShowForm } from '@/components/Admin/AdminShowForm.jsx';
import { useLocalStorage } from '@/hooks/useLocalStorage.jsx';

export function AddShow() {
  const { route } = useLocation();
  const { currentPlaylist, currentPlaylistData, changePlaylist } = useContext(PlaylistContext);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [adminMsg, setAdminMsg] = useLocalStorage('adminMsg', null);

  // Get unique categories for select
  const categories = useMemo(() => {
    if (!currentPlaylistData || !currentPlaylistData.shows) return [];
    const cats = currentPlaylistData.shows.map(s => s.category).filter(Boolean);
    return Array.from(new Set(cats));
  }, [currentPlaylistData]);

  function handleCancel() {
    route('/dashboard');
  }
  
  async function handleSave(newShow) {
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
        console.log('[AddShow] Setting admin message...');
        setAdminMsg({ type: 'success', text: 'The new show has been added successfully.' });
        console.log('[AddShow] Routing to /dashboard...');
        route('/dashboard');
        console.log('[AddShow] Refreshing playlist context...');
        await changePlaylist(currentPlaylist, true, false);
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

  return (
    <div class="container mt-4" style={{ maxWidth: 700 }}>
      <h2 class="mb-3">Add New Video</h2>
      {error && <div class="alert alert-danger mb-3">{error}</div>}
      <AdminShowForm
        initialData={initialData}
        onSave={handleSave}
        onCancel={handleCancel}
        saving={saving}
        error={null}
        categories={categories}
      />
    </div>
  );
}
