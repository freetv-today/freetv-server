import { useEffect, useState } from 'preact/hooks';

export function SelectSmall() {

  const [playlists, setPlaylists] = useState([]);

  useEffect(() => {
    fetch('/playlists/index.json')
      .then(res => res.json())
      .then(data => setPlaylists(data.playlists || []));
  }, []);

  return (
    <select id="selectPlaylistSmall" class="form-select form-select-sm mt-1">
      {playlists.map(pl => (
        <option value={pl.filename} key={pl.filename}>
          {pl.dbtitle}
        </option>
      ))}
    </select>
  );
}