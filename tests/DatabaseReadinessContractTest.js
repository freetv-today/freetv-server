import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const readinessEndpoint = fs.readFileSync(path.join(root, 'public/api/admin/readiness.php'), 'utf8');
const readinessHook = fs.readFileSync(path.join(root, 'src/hooks/useDatabaseReadiness.js'), 'utf8');
const readinessPage = fs.readFileSync(path.join(root, 'src/pages/DatabaseReadinessPage.jsx'), 'utf8');

test('readiness preserves existing statuses and adds the permissions status', () => {
  for (const status of [
    'dependencies_missing',
    'database_config_missing',
    'database_unavailable',
    'schema_missing',
    'initialization_required',
    'ready',
    'database_permissions_insufficient'
  ]) {
    assert.match(readinessHook, new RegExp(`'${status}'`));
  }
});

test('frontend preserves only the two valid database capability modes', () => {
  assert.match(readinessHook, /data\.database_mode === 'create_database'/);
  assert.match(readinessHook, /data\.database_mode === 'existing_database'/);
  assert.match(readinessHook, /databaseMode: null/);
});

test('schema behavior remains ahead of capability probing and ready remains unchanged', () => {
  const schemaResponse = readinessEndpoint.indexOf("respond('schema_missing'");
  const capabilityProbe = readinessEndpoint.indexOf('new DatabaseCapabilityProbe');
  assert.ok(schemaResponse >= 0 && capabilityProbe > schemaResponse);
  assert.match(readinessEndpoint, /respond\('initialization_required', 200, \['database_mode' => \$databaseMode\]\)/);
  assert.match(readinessEndpoint, /respond\('ready', 200\)/);
});

test('permissions page gives provider-neutral independent MariaDB guidance', () => {
  assert.match(readinessPage, /basic MariaDB “hello world” workflow/);
  assert.match(readinessPage, /use your assigned database/);
  assert.match(readinessPage, /Create a table/);
  assert.match(readinessPage, /Insert a row/);
  assert.match(readinessPage, /Read that row back/);
  assert.doesNotMatch(readinessPage, /Hostinger|cPanel/);
});
