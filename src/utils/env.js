// src/utils/env.js
import dotenv from 'dotenv';

// Load .env ONLY in Node.js (server-side)
const isNode = typeof process !== 'undefined' && process.versions?.node;

if (isNode && !process.env.VITE_DB_USER) {
  dotenv.config({ override: true });
  console.log('✅ dotenv auto-loaded in env.js');
}

// Vite environment detection
const isVite = typeof import.meta !== 'undefined' && import.meta.env;

// Safe env getter
const getEnv = (key, fallback = '') => {
  if (isVite) return import.meta.env[key] || fallback;
  return isNode ? (process.env[key] || fallback) : fallback;
};

// Core flags
export const isProduction = isVite ? import.meta.env.PROD : (isNode ? (process.env.NODE_ENV === 'production') : false);
export const isDevelopment = isVite ? import.meta.env.DEV : !isProduction;

// Paths
export const basePath = isVite 
  ? (getEnv('VITE_BASE_PATH', isProduction ? '/admin/' : '/'))
  : '/';

export const basePathClean = basePath.replace(/\/$/, '');

export const apiBaseUrl = isVite 
  ? (getEnv('VITE_API_BASE_URL', isProduction ? 'https://freetv.today' : 'http://localhost:8000'))
  : 'http://localhost:8000';

// ==================== Database Configuration (Node only) ====================
export const db = {
  host: getEnv('VITE_DB_HOST', getEnv('DB_HOST', 'localhost')),
  port: parseInt(getEnv('VITE_DB_PORT', getEnv('DB_PORT', '3306')), 10),
  user: getEnv('VITE_DB_USER', getEnv('DB_USER', 'root')),
  password: getEnv('VITE_DB_PASS', getEnv('DB_PASS', '')),
  database: getEnv('VITE_DB_NAME', getEnv('DB_NAME', 'freetv')),
};

export function validateEnv() {
  const missing = [];
  if (!db.user) missing.push('VITE_DB_USER / DB_USER');
  if (!db.password) missing.push('VITE_DB_PASS / DB_PASS');
  if (missing.length > 0 && isDevelopment) {
    console.warn('⚠️ Missing DB env vars:', missing);
  } else if (isDevelopment) {
    console.log('✅ DB env vars loaded successfully');
  }
  return missing.length === 0;
}

if (isDevelopment && isNode) {
  validateEnv();
}

/**
 * Helper to create environment-aware paths
 */
export function createPath(path) {
  if (path.startsWith('/')) {
    return isProduction ? basePathClean + path : path;
  }
  return path;
}

/**
 * Helper to create API paths
 */
export function createApiPath(path) {
  return path.startsWith('/') ? path : '/' + path;
}

export const env = {
  mode: isVite ? import.meta.env.MODE : (isNode ? process.env.NODE_ENV : 'development'),
  isDev: isDevelopment,
  isProd: isProduction,
  basePath,
  basePathClean,
  apiBaseUrl,
  db
};

export default env;