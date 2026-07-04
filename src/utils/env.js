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

// ==================== Database Configuration ====================

/** Database settings */
export const db = {
  host: import.meta.env.VITE_DB_HOST || 'localhost',
  port: parseInt(import.meta.env.VITE_DB_PORT || '3306', 10),
  user: import.meta.env.VITE_DB_USER,
  password: import.meta.env.VITE_DB_PASS,
  database: import.meta.env.VITE_DB_NAME || 'freetv',
  
  // Connection pool settings
  connectionLimit: parseInt(import.meta.env.VITE_DB_POOL_LIMIT || '10', 10),
};

// Optional: Quick validation helper
export function validateEnv() {
  const missing = [];
  
  if (!db.user) missing.push('VITE_DB_USER');
  if (!db.password) missing.push('VITE_DB_PASS');
  
  if (missing.length > 0) {
    console.warn('⚠️ Missing required DB environment variables:', missing);
  }
  
  return missing.length === 0;
}

// Run validation in development
if (isDevelopment) {
  validateEnv();
}

/**
 * Helper to create environment-aware paths
 * @param {string} path - The path to process
 * @returns {string} Environment-aware path
 */
export function createPath(path) {
  if (path.startsWith('/')) {
    return isProduction ? basePathClean + path : path;
  }
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
  apiBaseUrl,
  db   // ← Add this
};

export default env;