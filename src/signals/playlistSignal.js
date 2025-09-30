import { signal } from '@preact/signals';
import { enforceMinLoadingTime } from '@/utils';

/**
 * playlistSignal - Global signal for admin playlist state.
 * Holds playlists list, current playlist, show data, loading, and error.
 * @type {import('@preact/signals').Signal<{playlists: Array, currentPlaylist: string|null, showData: Array, loading: boolean, error: string|null}>}
 */

export const playlistSignal = signal({
  playlists: [],
  currentPlaylist: null,
  showData: [],
  loading: true,
  error: null,
});

/**
 * switchPlaylist - Helper to switch playlists and update the signal.
 * @param {string} filename - Playlist filename to load.
 * @param {number} [minTime=1200] - Minimum loading time in milliseconds.
 * @returns {Promise<void>}
 */

export async function switchPlaylist(filename, minTime = 1200) {
  const startTime = Date.now();
  playlistSignal.value = { ...playlistSignal.value, loading: true, error: null, currentPlaylist: filename };
  localStorage.setItem('adminCurrentPlaylist', filename);
  let showData = [];
  let error = null;
  try {
    const playlistRes = await fetch(`/playlists/${filename}`);
    if (!playlistRes.ok) throw new Error('Failed to load playlist data');
    const playlistData = await playlistRes.json();
    showData = playlistData.shows || [];
  } catch (err) {
    error = err.message || 'Error loading playlist';
  }
  await enforceMinLoadingTime(startTime, minTime);
  playlistSignal.value = {
    ...playlistSignal.value,
    showData,
    loading: false,
    error,
  };
}

/**
 * loadPlaylists - Helper to load playlists index and default playlist on app start.
 * @param {number} [minTime=1200] - Minimum loading time in milliseconds.
 * @returns {Promise<void>}
 */

export async function loadPlaylists(minTime = 1200) {
  const startTime = Date.now();
  playlistSignal.value = { ...playlistSignal.value, loading: true, error: null };
  let playlists = [];
  let playlistName = null;
  let error = null;
  try {
    const indexRes = await fetch('/playlists/index.json');
    if (!indexRes.ok) throw new Error('Failed to load playlist index');
    const indexData = await indexRes.json();
    playlists = indexData.playlists || [];
    playlistName = localStorage.getItem('adminCurrentPlaylist') || indexData.default;
    playlistSignal.value = {
      ...playlistSignal.value,
      playlists,
      currentPlaylist: playlistName,
      loading: true,
      error: null,
    };
    await switchPlaylist(playlistName, minTime);
  } catch (err) {
    error = err.message || 'Error loading playlists';
    playlistSignal.value = {
      ...playlistSignal.value,
      playlists: [],
      showData: [],
      loading: false,
      error,
    };
    return;
  }
  await enforceMinLoadingTime(startTime, minTime);
  // Only set loading: false if not already set by switchPlaylist
  if (playlistSignal.value.loading) {
    playlistSignal.value = { ...playlistSignal.value, loading: false };
  }
}