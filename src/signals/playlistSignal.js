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
 * switchPlaylist - Switch to different playlist
 */
export async function switchPlaylist(filename, minTime = 1200) {
  const startTime = Date.now();
  playlistSignal.value = {
    ...playlistSignal.value,
    currentPlaylist: filename,
    loading: true,
    error: null,
  };

  let showData = [];
  let error = null;
  try {
    if (typeof filename !== 'string' || filename === '') {
      throw new Error('No playlist selected');
    }

    const encodedFilename = encodeURIComponent(filename);
    const res = await fetch(`/api/admin/playlist_proxy.php?file=${encodedFilename}&t=${Date.now()}`);
    if (!res.ok) throw new Error('Failed to load playlist data');

    const playlistData = await res.json();
    showData = Array.isArray(playlistData.shows) ? playlistData.shows : [];
    localStorage.setItem('adminCurrentPlaylist', filename);
  } catch (err) {
    error = err instanceof Error ? err.message : 'Error loading playlist';
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
 * loadPlaylists - Load the playlist index and selected playlist from the database-backed proxy
 */
export async function loadPlaylists(minTime = 1200) {
  const startTime = Date.now();
  playlistSignal.value = { ...playlistSignal.value, loading: true, error: null };

  try {
    const res = await fetch(`/api/admin/playlist_proxy.php?file=index.json&t=${Date.now()}`);
    if (!res.ok) throw new Error('Failed to load playlist index');

    const indexData = await res.json();
    const playlists = Array.isArray(indexData.playlists) ? indexData.playlists : [];
    const availableFilenames = new Set(
      playlists
        .map(playlist => playlist && playlist.filename)
        .filter(filename => typeof filename === 'string' && filename !== '')
    );

    const storedFilename = localStorage.getItem('adminCurrentPlaylist');
    const defaultFilename = indexData.default;
    const firstFilename = playlists.find(
      playlist => playlist && availableFilenames.has(playlist.filename)
    )?.filename;

    const selectedFilename = availableFilenames.has(storedFilename)
      ? storedFilename
      : availableFilenames.has(defaultFilename)
        ? defaultFilename
        : firstFilename || null;

    playlistSignal.value = {
      ...playlistSignal.value,
      playlists,
      currentPlaylist: selectedFilename,
      showData: [],
      loading: true,
      error: null,
    };

    if (selectedFilename === null) {
      await enforceMinLoadingTime(startTime, minTime);
      playlistSignal.value = {
        ...playlistSignal.value,
        loading: false,
      };
      return;
    }

    await switchPlaylist(selectedFilename, minTime);
  } catch (err) {
    await enforceMinLoadingTime(startTime, minTime);
    playlistSignal.value = {
      ...playlistSignal.value,
      playlists: [],
      currentPlaylist: null,
      showData: [],
      loading: false,
      error: err instanceof Error ? err.message : 'Error loading playlists',
    };
  }
}
