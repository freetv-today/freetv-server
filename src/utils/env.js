// src/utils/env.js
import dotenv from 'dotenv';
import { fileURLToPath } from 'node:url';
import { dirname } from 'node:path';

// Load .env early for Node.js scripts
if (typeof process !== 'undefined' && !process.env.VITE_DB_USER) {
  dotenv.config({ override: true });
  console.log('✅ dotenv auto-loaded in env.js');
}

// Simple detection for Vite vs Node
const isVite = typeof import.meta !== 'undefined' && import.meta.env;

export const isProduction = isVite ? import.meta.env.PROD : (process.env.NODE_ENV === 'production');
export const isDevelopment = isVite ? import.meta.env.DEV : !isProduction;

export const basePath = isVite 
  ? (import.meta.env.VITE_BASE_PATH || (isProduction ? '/admin/' : '/'))
  : '/';

export const apiBaseUrl = isVite 
  ? (import.meta.env.VITE_API_BASE_URL || (isProduction ? 'https://freetv.today' : 'http://localhost:8000'))
  : 'http://localhost:8000';

// ==================== Database Configuration ====================
export const db = {
  host: process.env.VITE_DB_HOST || process.env.DB_HOST || 'localhost',
  port: parseInt(process.env.VITE_DB_PORT || process.env.DB_PORT || '3306', 10),
  user: process.env.VITE_DB_USER || process.env.DB_USER || 'root',
  password: process.env.VITE_DB_PASS || process.env.DB_PASS || '',
  database: process.env.VITE_DB_NAME || process.env.DB_NAME || 'freetv',
};

export function validateEnv() {
  const missing = [];
  if (!db.user) missing.push('VITE_DB_USER / DB_USER');
  if (!db.password) missing.push('VITE_DB_PASS / DB_PASS');
  if (missing.length > 0) {
    console.warn('⚠️ Missing DB env vars:', missing);
  } else {
    console.log('✅ DB env vars loaded successfully');
  }
  return missing.length === 0;
}

if (isDevelopment) {
  validateEnv();
}

export const env = {
  mode: isVite ? import.meta.env.MODE : process.env.NODE_ENV || 'development',
  isDev: isDevelopment,
  isProd: isProduction,
  basePath,
  apiBaseUrl,
  db
};

export default env;