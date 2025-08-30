import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';
import { SpinnerLoadingAppData } from '@components/Loaders/SpinnerLoadingAppData';

export function SelectLarge() {
  const { playlists, currentPlaylist, changePlaylist, loading } = useContext(PlaylistContext);

  function handleChange(e) {
    changePlaylist(e.target.value);
  }

  return (
    <select
      id="selectPlaylistLarge"
      class="form-select form-select-sm selectlist-nav"
      data-bs-theme="dark"
      value={currentPlaylist || ''}
      onChange={handleChange}
    >
      {playlists.map(pl => (
        <option value={pl.filename} key={pl.filename}>
          {pl.dbtitle}
        </option>
      ))}
    </select>
  );
}