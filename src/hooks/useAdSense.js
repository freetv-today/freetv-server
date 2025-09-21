import { useEffect } from 'preact/hooks';

/**
 * @typedef {Object} WindowWithAds
 * @property {Array} [adsbygoogle]
 */

/** @type {Window & WindowWithAds} */
const win = window;

export function useAdsense(enabled) {
  useEffect(() => {
    if (enabled && win.adsbygoogle) {
      try {
        win.adsbygoogle.push({});
      } catch {
        // Ignore errors
      }
    }
  }, [enabled]);
}