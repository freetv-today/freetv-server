// src/db/connection.js
import mysql from 'mysql2/promise';
import { env } from '../utils/env.js';

const dbConfig = {
  host: env.db.host,
  port: env.db.port,
  user: env.db.user,
  password: env.db.password,
  database: env.db.database,
  
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0,
  enableKeepAlive: true,
  timezone: 'Z',
  charset: 'utf8mb4',
};

let pool;

export function getPool() {
  if (!pool) {
    pool = mysql.createPool(dbConfig);
    pool.getConnection()
      .then(conn => {
        console.log('✅ Database connection established with user:', dbConfig.user);
        conn.release();
      })
      .catch(err => {
        console.error('❌ Database connection failed:', err.message);
      });
  }
  return pool;
}

export async function closePool() {
  if (pool) await pool.end();
}