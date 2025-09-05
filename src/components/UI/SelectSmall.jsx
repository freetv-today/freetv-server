import { useContext } from 'preact/hooks';
import { PlaylistContext } from '@/context/PlaylistContext';

export function SelectSmall() {
  const { playlists, currentPlaylist, changePlaylist } = useContext(PlaylistContext);

  function handleChange(e) {
    changePlaylist(e.target.value);
  }

  return (
    <select
      id="selectPlaylistSmall"
      className="form-select form-select-sm mt-1"
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