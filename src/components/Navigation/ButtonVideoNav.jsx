import { useLocalStorage } from '@/hooks/useLocalStorage';
import { confirmPlaylistReload } from '@/utils.js';
import { useRef } from 'preact/hooks';

export function ButtonVideoNav() {
  const [embedPlaylist, setEmbedPlaylist] = useLocalStorage('embedPlaylist', true);
  const prevValue = useRef(embedPlaylist);

  const handlePlaylistToggle = () => {
    const newValue = !embedPlaylist;
    if (confirmPlaylistReload()) {
      setEmbedPlaylist(newValue);
      window.location.reload();
    } else {
      // revert toggle
      setEmbedPlaylist(prevValue.current);
    }
  };

  // Keep prevValue in sync
  prevValue.current = embedPlaylist;

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
        onChange={handlePlaylistToggle}
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