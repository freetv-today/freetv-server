import { playlistSignal, switchPlaylist } from '@signals/playlistSignal';

export function SelectSmall() {
  const { playlists, currentPlaylist } = playlistSignal.value;
  function handleChange(e) {
    switchPlaylist(e.target.value);
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