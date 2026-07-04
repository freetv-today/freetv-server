// src/db/repositories/showRepo.js
import { getPool } from '../connection.js';

export const showRepo = {
  async getAll(filters = {}) {
    let query = 'SELECT * FROM playlist_shows WHERE 1=1';
    const params = [];

    if (filters.playlist_id) {
      query += ' AND playlist_id = ?';
      params.push(filters.playlist_id);
    }
    if (filters.category) {
      query += ' AND category = ?';
      params.push(filters.category);
    }
    if (filters.status) {
      query += ' AND status = ?';
      params.push(filters.status);
    }
    // Add search, etc. later
    query += ' ORDER BY sort_order, title';

    const pool = getPool();
    const [rows] = await pool.execute(query, params);
    return rows;
  },

  async getById(id) {
    const pool = getPool();
    const [rows] = await pool.execute(
      'SELECT * FROM playlist_shows WHERE id = ?',
      [id]
    );
    return rows[0];
  },

  async create(showData) {
    const pool = getPool();
    const keys = Object.keys(showData);
    const placeholders = keys.map(() => '?').join(', ');
    const sql = `INSERT INTO playlist_shows (${keys.join(', ')}) VALUES (${placeholders})`;
    const [result] = await pool.execute(sql, Object.values(showData));
    return result.insertId;
  },

  async update(id, updates) {
    const pool = getPool();
    const sets = Object.keys(updates).map(key => `${key} = ?`).join(', ');
    const sql = `UPDATE playlist_shows SET ${sets} WHERE id = ?`;
    const values = [...Object.values(updates), id];
    const [result] = await pool.execute(sql, values);
    return result.affectedRows > 0;
  },

  async delete(id) {
    const pool = getPool();
    const [result] = await pool.execute(
      'DELETE FROM playlist_shows WHERE id = ?',
      [id]
    );
    return result.affectedRows > 0;
  },

  // For publishing JSON files
  async exportToJson(playlistId) {
    const shows = await this.getAll({ playlist_id: playlistId });
    return {
      dbtitle: 'FreeTV Playlist',
      lastupdated: new Date().toISOString(),
      shows: shows // map fields if column names differ from JSON expectation
    };
  }
};