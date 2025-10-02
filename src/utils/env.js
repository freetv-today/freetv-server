// Environment configuration utilities
export const isProduction = import.meta.env.PROD;
export const isDevelopment = import.meta.env.DEV;

// Get base path from environment or fallback
export const basePath = import.meta.env.VITE_BASE_PATH || (isProduction ? '/admin/' : '/');

// Remove trailing slash for consistent usage
export const basePathClean = basePath.replace(/\/$/, '');

// API base URL
export const apiBaseUrl = import.meta.env.VITE_API_BASE_URL || (isProduction ? 'https://freetv.today' : 'http://localhost:8000');

// Helper to create environment-aware paths
export function createPath(path) {
  if (path.startsWith('/')) {
    // Absolute path - prefix with base path if in production
    return isProduction ? basePathClean + path : path;
  }
  // Relative path - return as-is
  return path;
}

// Helper to create API paths
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