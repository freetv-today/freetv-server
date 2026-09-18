import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const page = fs.readFileSync(path.join(root, 'src/pages/DataInitializationPage.jsx'), 'utf8');
const adminAssets = fs.readFileSync(path.join(root, 'src/adminAssets.js'), 'utf8');
const endpoint = fs.readFileSync(path.join(root, 'public/api/admin/initialize.php'), 'utf8');
const provider = fs.readFileSync(path.join(root, 'public/api/admin/DatasetPackageProvider.php'), 'utf8');
const baselineProvider = fs.readFileSync(
  path.join(root, 'public/api/admin/BaselineDatasetPackageProvider.php'),
  'utf8',
);

test('First Run UI exposes exactly four peer modes in the approved order', () => {
  const modes = ['fresh', 'baseline', 'sample', 'official'];
  const titles = [
    'Start Fresh',
    'Baseline Sample Data',
    'Current Sample Data',
    'Current Official Data',
  ];
  const descriptions = [
    'Create an empty FreeTV library.',
    'Initialize with the bundled sample library.',
    'Initialize with the current sample library.',
    'Initialize with the current complete library.',
  ];
  let previous = -1;
  for (let index = 0; index < modes.length; index += 1) {
    const position = page.indexOf(`mode: '${modes[index]}'`);
    assert.ok(position > previous, `${modes[index]} is missing or out of order`);
    previous = position;
    assert.ok(page.indexOf(`title: '${titles[index]}'`, position) > position);
    assert.ok(page.indexOf(`description: '${descriptions[index]}'`, position) > position);
  }
  assert.equal((page.match(/^\s+mode: '(?:fresh|baseline|sample|official)',$/gm) || []).length, 4);
  assert.match(page, /mode: selectedMode/);
  assert.doesNotMatch(page, /Coming Soon/);
  assert.match(endpoint, /\['fresh', 'baseline', 'sample', 'official'\]/);
  assert.match(endpoint, /\$bootstrapper->baseline/);
  assert.match(endpoint, /\$bootstrapper->sample/);
  assert.match(endpoint, /\$bootstrapper->official/);
  assert.match(endpoint, /\$bootstrapper->fresh/);
});

test('local and Current choices have distinct visual and progress treatment', () => {
  assert.match(page, /option\.current \? 'btn-warning' : 'btn-primary'/);
  assert.match(adminAssets, /internetIcon \} from '\.\.\/public\/assets\/internet\.svg'/);
  assert.match(page, /import \{ internetIcon \} from '@\/adminAssets'/);
  assert.match(page, /src=\{internetIcon\}/);
  assert.doesNotMatch(page, /\/assets\/internet\.svg/);
  assert.equal((page.match(/title="Internet required"/g) || []).length, 1);
  assert.equal((page.match(/alt="Internet required"/g) || []).length, 1);
  assert.match(page, /selectedMode === 'baseline'/);
  assert.match(page, /spinner-border spinner-border-sm me-2/);
  assert.match(page, /Initializing \$\{modeLabel\}\.\.\./);
  assert.doesNotMatch(page, /alert alert-info/);
  assert.doesNotMatch(page, /This may take several minutes/);
  assert.doesNotMatch(page, /Baseline Sample Data downloads/);
});

test('Baseline is wired to its local package provider without remote metadata or downloads', () => {
  assert.match(endpoint, /new \\FreeTV\\Admin\\BaselineDatasetPackageProvider/);
  assert.match(baselineProvider, /resources\/freetv-baseline-sample-data\.zip/);
  assert.doesNotMatch(baselineProvider, /METADATA_URL|curl|https?:\/\//);
});

test('recognized remote failures use stable codes without changing the four setup choices', () => {
  for (const code of ['dataset_unavailable', 'dataset_metadata_invalid', 'dataset_integrity_failed']) {
    assert.match(page, new RegExp(`${code}:`));
  }
  assert.match(page, /DATASET_FAILURES\[data\?\.error_code\]/);
  assert.equal((page.match(/^\s+mode: '(?:fresh|baseline|sample|official)',$/gm) || []).length, 4);
  assert.doesNotMatch(page, /setSelectedMode\('baseline'\)/);
});

test('current package metadata and downloads retain the required trust ordering and TLS controls', () => {
  assert.match(provider, /https:\/\/freetv\.today\/api\/admin\/dataset-package-metadata\.php/);
  assert.match(provider, /MAX_METADATA_BYTES\s*=\s*65536/);
  assert.match(provider, /\$this->metadataFetcher/);
  assert.match(provider, /\$this->downloader/);
  assert.doesNotMatch(provider, /v3\.0\.0-data-preview/);
  assert.match(provider, /hash_file\('sha256', \$zipPath\)/);
  assert.match(provider, /hash_equals\(\$definition\['sha256'\], \$archiveHash\)/);
  assert.ok(
    provider.indexOf('hash_equals($definition[\'sha256\'], $archiveHash)')
      < provider.indexOf('($this->packageValidator)($zipPath, $root, $dataset)'),
    'Outer archive SHA-256 must be verified before package extraction/validation',
  );
  assert.match(provider, /CURLOPT_PROTOCOLS\s*=>\s*CURLPROTO_HTTPS/);
  assert.match(provider, /CURLOPT_REDIR_PROTOCOLS\s*=>\s*CURLPROTO_HTTPS/);
  assert.match(provider, /CURLOPT_SSL_VERIFYPEER\s*=>\s*true/);
  assert.match(provider, /CURLOPT_SSL_VERIFYHOST\s*=>\s*2/);
  assert.doesNotMatch(provider, /CURLOPT_SSL_VERIFYPEER\s*=>\s*false/);
});
