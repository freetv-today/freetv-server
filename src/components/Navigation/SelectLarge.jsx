import { playlistSignal, switchPlaylist } from '@signals/playlistSignal';

export function SelectLarge() {
  
  const { playlists, currentPlaylist } = playlistSignal.value;
  function handleChange(e) {
    switchPlaylist(e.target.value);
  }
  return (
    <select
      id="selectPlaylistLarge"
      className="form-select form-select-sm selectlist-nav"
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