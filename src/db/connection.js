// src/db/connection.js
import mysql from 'mysql2/promise';
import { env } from '@/utils/env';

// Configuration object
const dbConfig = {
  host: env.DB_HOST || 'localhost',
  port: env.DB_PORT || 3306,
  user: env.DB_USER,
  password: env.DB_PASS,
  database: env.DB_NAME || 'freetv',
  
  // Pool options - tune these as needed
  waitForConnections: true,
  connectionLimit: 10,        // Max concurrent connections
  queueLimit: 0,              // Unlimited queue (or set a limit)
  enableKeepAlive: true,
  
  // Optional: timezone handling
  timezone: 'Z',              // UTC
  
  // Security / charset
  charset: 'utf8mb4',
};

// Create the pool (singleton)
let pool;

export function getPool() {
  if (!pool) {
    pool = mysql.createPool(dbConfig);
    
    // Optional: Test connection on startup
    pool.getConnection()
      .then(conn => {
        console.log('✅ Database connection established');
        conn.release();
      })
      .catch(err => {
        console.error('❌ Database connection failed:', err.message);
        process.exit(1); // Fail fast in production
      });
  }
  return pool;
}

// Optional: Graceful shutdown helper
export async function closePool() {
  if (pool) {
    await pool.end();
    console.log('Database pool closed');
  }
}