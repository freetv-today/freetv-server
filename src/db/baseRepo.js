// src/db/baseRepo.js
import { getPool } from './connection.js';

export function createRepo(tableName) {
  return {
    async query(sql, params = []) {
      const pool = getPool();
      const [rows] = await pool.execute(sql, params);
      return rows;
    },

    async getAll(where = '', params = []) {
      const sql = `SELECT * FROM ${tableName} ${where ? 'WHERE ' + where : ''} ORDER BY sort_order, title`;
      return this.query(sql, params);
    },

    async getById(id) {
      const pool = getPool();
      const [rows] = await pool.execute(`SELECT * FROM ${tableName} WHERE id = ?`, [id]);
      return rows[0];
    },

    async create(data) {
      const pool = getPool();
      const keys = Object.keys(data);
      const placeholders = keys.map(() => '?').join(', ');
      const sql = `INSERT INTO ${tableName} (${keys.join(', ')}) VALUES (${placeholders})`;
      const [result] = await pool.execute(sql, Object.values(data));
      return result.insertId;
    },

    async update(id, data) {
      const pool = getPool();
      const sets = Object.keys(data).map(key => `${key} = ?`).join(', ');
      const sql = `UPDATE ${tableName} SET ${sets} WHERE id = ?`;
      const [result] = await pool.execute(sql, [...Object.values(data), id]);
      return result.affectedRows > 0;
    },

    async delete(id) {
      const pool = getPool();
      const [result] = await pool.execute(`DELETE FROM ${tableName} WHERE id = ?`, [id]);
      return result.affectedRows > 0;
    }
  };
}