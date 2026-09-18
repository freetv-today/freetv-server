// Vite environment detection
const isVite = typeof import.meta !== 'undefined' && import.meta.env;

// Core flags
export const isProduction = isVite ? import.meta.env.PROD : false;

// Paths
export const basePath = isVite 
  ? (import.meta.env.VITE_BASE_PATH || (isProduction ? '/admin/' : '/'))
  : '/';

export const basePathClean = basePath.replace(/\/$/, '');

/**
 * Helper to create environment-aware paths
 */
export function createPath(path) {
  if (path.startsWith('/')) {
    return isProduction ? basePathClean + path : path;
  }
  return path;
}
