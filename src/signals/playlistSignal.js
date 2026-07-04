// src/signals/playlistSignal.js
import { signal } from '@preact/signals';
import { enforceMinLoadingTime } from '@/utils/utils';

/**
 * playlistSignal - Global signal for admin playlist state.
 */
export const playlistSignal = signal({
  playlists: [],
  currentPlaylist: null,
  showData: [],
  loading: true,
  error: null,
});

/**
 * loadPlaylists - Load playlists and default show data from DB
 */
export async function loadPlaylists(minTime = 1200) {
  const startTime = Date.now();
  playlistSignal.value = { ...playlistSignal.value, loading: true, error: null };

  try {
    // For now, get all shows from default playlist (we can expand later)
    const res = await fetch(`/api/admin/shows.php?playlist_id=1&t=${Date.now()}`);
    if (!res.ok) throw new Error('Failed to load shows');

    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load data');

    playlistSignal.value = {
      ...playlistSignal.value,
      showData: data.shows || [],
      loading: false,
    };

    console.log(`Loaded ${data.shows.length} shows from DB`);
  } catch (err) {
    console.error(err);
    playlistSignal.value = {
      ...playlistSignal.value,
      showData: [],
      loading: false,
      error: err.message,
    };
  }

  await enforceMinLoadingTime(startTime, minTime);
}

/**
 * switchPlaylist - Switch to different playlist
 */
export async function switchPlaylist(playlistId, minTime = 1200) {
  const startTime = Date.now();
  playlistSignal.value = { ...playlistSignal.value, loading: true, error: null, currentPlaylist: playlistId };

  try {
    const res = await fetch(`/api/admin/shows.php?playlist_id=${playlistId}&t=${Date.now()}`);
    if (!res.ok) throw new Error('Failed to load playlist');

    const data = await res.json();
    if (!data.success) throw new Error(data.message || 'Failed to load data');

    playlistSignal.value = {
      ...playlistSignal.value,
      showData: data.shows || [],
      loading: false,
    };
  } catch (err) {
    playlistSignal.value = {
      ...playlistSignal.value,
      showData: [],
      loading: false,
      error: err.message,
    };
  }

  await enforceMinLoadingTime(startTime, minTime);
}