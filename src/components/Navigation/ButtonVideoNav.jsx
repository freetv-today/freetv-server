import { useLocalStorage } from '@/hooks/useLocalStorage';
import { confirmPlaylistReload } from '@/utils.js';

export function ButtonVideoNav() {
  const [embedPlaylist, setEmbedPlaylist] = useLocalStorage('embedPlaylist', true);

  const handlePlaylistToggle = (e) => {
    // Prevent the checkbox from toggling automatically
    e.preventDefault();

    const newValue = !embedPlaylist;
    if (confirmPlaylistReload()) {
      setEmbedPlaylist(newValue);
      window.location.reload();
    }
    // If cancelled, do nothing: the checked prop will keep the button in sync
  };

  return (
    <span>
      <button
        id="backBtn"
        class="btn btn-sm btn-outline-warning fw-bold ms-2 me-2"
        title="Go back to the previous page"
        onClick={() => window.history.back()}
      >
        &larr; Back
      </button>
      <input
        id="playlistBtn"
        type="checkbox"
        class="btn-check"
        autoComplete="off"
        checked={embedPlaylist}
        onClick={handlePlaylistToggle}
        readOnly
      />
      <label
        class="btn btn-sm btn-outline-warning fw-bold"
        htmlFor="playlistBtn"
        title="Episode Playlist"
      >
        Episode Playlist
      </label>
    </span>
  );
}