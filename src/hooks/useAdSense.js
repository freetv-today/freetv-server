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
        (win.adsbygoogle = win.adsbygoogle || []).push({});
      } catch {
        // No action needed if adsbygoogle push fails
      }
    }
  }, [enabled]);
}