import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const page = fs.readFileSync(path.join(root, 'src/pages/DataInitializationPage.jsx'), 'utf8');
const endpoint = fs.readFileSync(path.join(root, 'public/api/admin/initialize.php'), 'utf8');
const provider = fs.readFileSync(path.join(root, 'public/api/admin/DatasetPackageProvider.php'), 'utf8');

test('First Run UI exposes three peer modes through initialize.php', () => {
  for (const mode of ['fresh', 'sample', 'official']) {
    assert.match(page, new RegExp(`setSelectedMode\\('${mode}'\\)`));
  }
  assert.match(page, /mode: selectedMode/);
  assert.match(page, /This may take several minutes/);
  assert.doesNotMatch(page, /Coming Soon/);
  assert.match(endpoint, /\['fresh', 'sample', 'official'\]/);
  assert.match(endpoint, /\$bootstrapper->sample/);
  assert.match(endpoint, /\$bootstrapper->official/);
  assert.match(endpoint, /\$bootstrapper->fresh/);
});

test('fixed release downloads retain TLS verification', () => {
  assert.match(provider, /v3\.0\.0-data-preview\/freetv-sample-data\.zip/);
  assert.match(provider, /v3\.0\.0-data-preview\/freetv-official-data\.zip/);
  assert.match(provider, /CURLOPT_PROTOCOLS\s*=>\s*CURLPROTO_HTTPS/);
  assert.match(provider, /CURLOPT_REDIR_PROTOCOLS\s*=>\s*CURLPROTO_HTTPS/);
  assert.match(provider, /CURLOPT_SSL_VERIFYPEER\s*=>\s*true/);
  assert.match(provider, /CURLOPT_SSL_VERIFYHOST\s*=>\s*2/);
  assert.doesNotMatch(provider, /CURLOPT_SSL_VERIFYPEER\s*=>\s*false/);
});
