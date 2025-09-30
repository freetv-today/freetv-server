import { useState, useEffect } from 'preact/hooks';

/**
 * useDataValidation - Validates that required application data exists
 * @returns {Object} - Validation state and helper functions
 */

export function useDataValidation() {
  const [dataState, setDataState] = useState({
    loading: true,
    hasData: false,
    issues: [],
    canProceed: false,
    details: {
      hasConfig: false,
      hasPlaylistData: false,
      hasThumbnails: false
    }
  });

  useEffect(() => {
    validateAppData();
  }, []);

  async function validateAppData() {
    setDataState(prev => ({ ...prev, loading: true }));
    const issues = [];
    let hasPlaylistData = false;
    let hasConfig = false;
    let hasThumbnails = false;

    try {
      // Check for config.json
      const configRes = await fetch('/config.json');
      if (configRes.ok) {
        hasConfig = true;
      } else {
        issues.push({
          type: 'config',
          message: 'config.json not found',
          severity: 'critical'
        });
      }
    } catch {
      issues.push({
        type: 'config',
        message: 'Failed to load config.json',
        severity: 'critical'
      });
    }

    try {
      // Check for playlist index
      const indexRes = await fetch('/playlists/index.json');
      if (indexRes.ok) {
        const indexData = await indexRes.json();
        if (indexData.playlists && indexData.playlists.length > 0) {
          hasPlaylistData = true;
          // Try to load the default playlist to verify it exists
          const defaultPlaylist = indexData.default;
          if (defaultPlaylist) {
            const playlistRes = await fetch(`/playlists/${defaultPlaylist}`);
            if (!playlistRes.ok) {
              issues.push({
                type: 'playlist',
                message: `Default playlist "${defaultPlaylist}" not found`,
                severity: 'critical'
              });
              hasPlaylistData = false;
            }
          }
        } else {
          issues.push({
            type: 'playlist',
            message: 'No playlists found in index.json',
            severity: 'critical'
          });
        }
      } else {
        issues.push({
          type: 'playlist',
          message: 'playlists/index.json not found',
          severity: 'critical'
        });
      }
    } catch {
      issues.push({
        type: 'playlist',
        message: 'Failed to load playlist data',
        severity: 'critical'
      });
    }

    try {
      // Check for thumbnails directory (try loading a common file)
      const thumbRes = await fetch('/thumbs/index.html');
      if (thumbRes.ok) {
        hasThumbnails = true;
      } else {
        issues.push({
          type: 'thumbnails',
          message: 'Thumbnails directory not accessible',
          severity: 'warning'
        });
      }
    } catch {
      issues.push({
        type: 'thumbnails',
        message: 'Thumbnails directory not found',
        severity: 'warning'
      });
    }

    const hasData = hasConfig && hasPlaylistData;
    const canProceed = hasData; // Can proceed if we have config and playlist data

    setDataState({
      loading: false,
      hasData,
      issues,
      canProceed,
      details: {
        hasConfig,
        hasPlaylistData,
        hasThumbnails
      }
    });
  }

  return {
    ...dataState,
    revalidate: validateAppData
  };
}