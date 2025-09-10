import { useEffect } from 'preact/hooks';
import { generateNewCode } from '@/utils';

/**
 * useVisitBeacon - Custom hook for beacon analytics and activity tracking
 * @param {Object} config - App config (must include appdata, collector, version)
 */


export function useVisitBeacon(config) {
  useEffect(() => {
    if (!config?.appdata) return;
    // Initialize or update visitData in localStorage (once per app load)
    let visitData = null;
    try {
      visitData = JSON.parse(localStorage.getItem('visitData') || '{}');
    } catch {
      visitData = null;
    }
    const now = new Date().toISOString();
    if (!visitData || !visitData.token) {
      // No visitData or missing token: create new session and token
      const code = generateNewCode();
      visitData = { lastVisit: now, start: now, end: '', token: code };
      localStorage.setItem('visitData', JSON.stringify(visitData));
      if (typeof window !== 'undefined' && window.sessionStorage) {
        window.sessionStorage.setItem('token', code);
      }
    } else {
      // Update lastVisit only
      visitData.lastVisit = now;
      localStorage.setItem('visitData', JSON.stringify(visitData));
      // Restore token to sessionStorage if missing
      if (typeof window !== 'undefined' && window.sessionStorage) {
        if (!window.sessionStorage.getItem('token') && visitData.token) {
          window.sessionStorage.setItem('token', visitData.token);
        }
      }
    }
  }, [config?.appdata]);

  useEffect(() => {
    if (!config?.appdata || !config?.collector) return;
    // Beacon: send on exit
    const handleVisibilityChange = () => {
      if (document.visibilityState !== 'hidden') return;
      let visitData = null;
      try { visitData = JSON.parse(localStorage.getItem('visitData') || '{}'); } catch { /* empty */ }
      if (!visitData) return;
      visitData.end = new Date().toISOString();
      visitData.version = config.version;
      // If recentShows exists and is non-empty, include it in the beacon payload
      let beaconPayload = { ...visitData };
      if (Array.isArray(visitData.recentShows) && visitData.recentShows.length > 0) {
        beaconPayload.recentShows = [...visitData.recentShows];
        // Clear recentShows after sending for privacy and to avoid duplicate reporting
        visitData.recentShows = [];
        localStorage.setItem('visitData', JSON.stringify(visitData));
      } else {
        localStorage.setItem('visitData', JSON.stringify(visitData));
      }
      navigator.sendBeacon(config.collector, JSON.stringify(beaconPayload));
    };
    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => {
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [config?.appdata, config?.collector, config?.version]);
}
