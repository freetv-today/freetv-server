// Environment configuration utilities

/** @type {boolean} Production environment flag */
export const isProduction = import.meta.env.PROD;

/** @type {boolean} Development environment flag */
export const isDevelopment = import.meta.env.DEV;

/** @type {string} Base path from environment or fallback */
export const basePath = import.meta.env.VITE_BASE_PATH || (isProduction ? '/admin/' : '/');

/** @type {string} Base path with trailing slash removed for consistent usage */
export const basePathClean = basePath.replace(/\/$/, '');

/** @type {string} API base URL */
export const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || (isProduction ? 'https://freetv.today' : 'http://localhost:8000');

/**
 * Helper to create environment-aware paths
 * @param {string} path - The path to process
 * @returns {string} Environment-aware path
 */
export function createPath(path) {
  if (path.startsWith('/')) {
    // Absolute path - prefix with base path if in production
    return isProduction ? basePathClean + path : path;
  }
  // Relative path - return as-is
  return path;
}

/**
 * Helper to create API paths
 * @param {string} path - The path to process
 * @returns {string} Properly formatted API path
 */
export function createApiPath(path) {
  return path.startsWith('/') ? path : '/' + path;
}

// Environment info for debugging
export const env = {
  mode: import.meta.env.MODE,
  isDev: isDevelopment,
  isProd: isProduction,
  basePath,
  basePathClean,
  apiBaseUrl
};

export default env;