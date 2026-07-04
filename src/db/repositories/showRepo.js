// src/db/repositories/showRepo.js
import pool from '../connection.js';

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
    // ... status, search, etc.

    const [rows] = await pool.execute(query + ' ORDER BY sort_order', params);
    return rows;
  },

  async getById(id) {
    const [rows] = await pool.execute(
      'SELECT * FROM playlist_shows WHERE id = ?',
      [id]
    );
    return rows[0];
  },

  async create(showData) {
    const { playlist_id, title, ...rest } = showData;
    const [result] = await pool.execute(
      `INSERT INTO playlist_shows 
       (playlist_id, title, description, identifier, ...) 
       VALUES (?, ?, ?, ?, ...)`,
      [playlist_id, title, ...]
    );
    return result.insertId;
  },

  async update(id, updates) {
    // Dynamic update or specific
    const [result] = await pool.execute(
      'UPDATE playlist_shows SET ? WHERE id = ?',
      [updates, id] // mysql2 handles object
    );
    return result.affectedRows > 0;
  },

  async delete(id) {
    const [result] = await pool.execute(
      'DELETE FROM playlist_shows WHERE id = ?',
      [id]
    );
    return result.affectedRows > 0;
  },

  // Bonus: export to JSON for publishing
  async exportToJson(playlistId) { ... }
};