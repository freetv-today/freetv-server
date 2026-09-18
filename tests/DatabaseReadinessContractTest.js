import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const readinessEndpoint = fs.readFileSync(path.join(root, 'public/api/admin/readiness.php'), 'utf8');
const readinessService = fs.readFileSync(path.join(root, 'public/api/admin/DatabaseReadiness.php'), 'utf8');
const readinessHook = fs.readFileSync(path.join(root, 'src/hooks/useDatabaseReadiness.js'), 'utf8');
const readinessPage = fs.readFileSync(path.join(root, 'src/pages/DatabaseReadinessPage.jsx'), 'utf8');
const loginPage = fs.readFileSync(path.join(root, 'src/pages/index.jsx'), 'utf8');

test('readiness preserves existing statuses and adds the permissions status', () => {
  for (const status of [
    'dependencies_missing',
    'database_config_missing',
    'database_unavailable',
    'database_missing',
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

test('target inspection precedes deferred capability probing and ready remains unchanged', () => {
  const targetConnection = readinessService.indexOf("$connection = ($this->configuredConnectionFactory)()");
  const readyResponse = readinessService.indexOf("'status' => 'ready'");
  const capabilityProbe = readinessService.indexOf("$mode = ($this->capabilityProbe)()");
  assert.ok(targetConnection >= 0 && readyResponse > targetConnection && capabilityProbe > readyResponse);
  assert.match(readinessService, /MariaDbError::isUnknownDatabase/);
  assert.match(readinessService, /'status' => 'database_missing'/);
  assert.match(readinessEndpoint, /Database::createBootstrapConnection/);
  assert.match(readinessEndpoint, /Database::createConfiguredConnection/);
  assert.match(readinessService, /'status' => 'initialization_required'/);
  assert.match(readinessService, /'status' => 'ready'/);
});

test('recoverable missing database and schema states expose the existing initialization page', () => {
  assert.match(loginPage, /readiness\.status === 'database_missing'/);
  assert.match(loginPage, /readiness\.status === 'schema_missing'/);
  assert.match(loginPage, /readiness\.databaseMode !== null/);
});

test('permissions page gives provider-neutral independent MariaDB guidance', () => {
  assert.match(readinessPage, /basic MariaDB “hello world” workflow/);
  assert.match(readinessPage, /use your assigned database/);
  assert.match(readinessPage, /Create a table/);
  assert.match(readinessPage, /Insert a row/);
  assert.match(readinessPage, /Read that row back/);
  assert.doesNotMatch(readinessPage, /Hostinger|cPanel/);
});
