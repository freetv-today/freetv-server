import { useEffect, useState } from 'preact/hooks';

export function SelectLarge() {
  const [playlists, setPlaylists] = useState([]);

  useEffect(() => {
    fetch('/playlists/index.json')
      .then(res => res.json())
      .then(data => setPlaylists(data.playlists || []));
  }, []);

  return (
    <select 
      id="selectPlaylistLarge" 
      class="form-select form-select-sm selectlist-nav" 
      data-bs-theme="dark"
    >
      {playlists.map(pl => (
        <option value={pl.filename} key={pl.filename}>
          {pl.dbtitle}
        </option>
      ))}
    </select>
  );
}