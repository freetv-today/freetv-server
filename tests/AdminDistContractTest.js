import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
// Node ESM requires this extension; the project lint rule targets browser imports.
// eslint-disable-next-line import/extensions
import { validateAdminDist } from '../scripts/validate-admin-dist.js';

function fixture(t) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'freetv-admin-dist-'));
  t.after(() => fs.rmSync(root, { recursive: true, force: true }));
  fs.mkdirSync(path.join(root, 'assets'));
  fs.writeFileSync(path.join(root, 'index.html'), '<!doctype html>');
  fs.writeFileSync(path.join(root, 'assets/admin.js'), 'export {};');
  fs.writeFileSync(path.join(root, 'assets/admin.css'), 'body {}');
  return root;
}

test('accepts the Admin frontend allowlist', (t) => {
  const root = fixture(t);
  assert.equal(validateAdminDist(root).files, 3);
});

test('rejects Server runtime directories', (t) => {
  const root = fixture(t);
  fs.mkdirSync(path.join(root, 'api'));
  assert.throws(() => validateAdminDist(root), /forbidden runtime path|unexpected root entry/);
});

test('rejects credential key files inside assets', (t) => {
  const root = fixture(t);
  fs.writeFileSync(path.join(root, 'assets/credentials.key'), 'not-a-real-key');
  assert.throws(() => validateAdminDist(root), /credential\/key files are not allowed/);
});

test('rejects files outside the root allowlist', (t) => {
  const root = fixture(t);
  fs.writeFileSync(path.join(root, 'manifest.json'), '{}');
  assert.throws(() => validateAdminDist(root), /unexpected root entry/);
});
