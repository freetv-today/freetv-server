import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const errorPage = fs.readFileSync(path.join(root, 'src/pages/ErrorPage.jsx'), 'utf8');
const firstRun = fs.readFileSync(path.join(root, 'src/pages/DataInitializationPage.jsx'), 'utf8');

test('ErrorPage keeps its default route and label while allowing a custom secondary action', () => {
  assert.match(errorPage, /homePath = '\/'/);
  assert.match(errorPage, /homeLabel = 'Back to Login'/);
  assert.match(errorPage, /onHome \? onHome\(\) : route\(createPath\(homePath\)\)/);
  assert.match(errorPage, /\{homeLabel\}/);
  assert.match(errorPage, />\s*Try Again\s*</);
});

test('First Run renders recognized dataset failures through ErrorPage', () => {
  assert.match(firstRun, /import \{ ErrorPage \} from '@\/pages\/ErrorPage'/);
  assert.match(firstRun, /if \(datasetFailure\) \{[\s\S]*?<ErrorPage/);
  assert.match(firstRun, /type=\{datasetFailure\.type\}/);
  assert.match(firstRun, /message=\{datasetFailure\.message\}/);
  assert.doesNotMatch(firstRun, /message=\{data\?\.message\}/);
});

test('availability failure offers explicit retry and Data Setup actions without losing form state', () => {
  assert.match(firstRun, /dataset_unavailable:[\s\S]*?type: 'Current Dataset Unavailable'/);
  assert.match(firstRun, /dataset_unavailable:[\s\S]*?retryable: true/);
  assert.match(firstRun, /showReload=\{datasetFailure\.retryable\}/);
  assert.match(firstRun, /onReload=\{\(\) => setDatasetFailure\(null\)\}/);
  assert.match(firstRun, /homeLabel="Back to Data Setup"/);
  assert.match(firstRun, /onHome=\{\(\) => \{[\s\S]*?setSelectedMode\(null\)/);
  assert.doesNotMatch(firstRun, /localStorage|sessionStorage/);
});

test('metadata and package integrity failures are hard failures without automatic fallback', () => {
  assert.match(firstRun, /dataset_metadata_invalid:[\s\S]*?type: 'Dataset Information Error'/);
  assert.match(firstRun, /dataset_metadata_invalid:[\s\S]*?retryable: false/);
  assert.match(firstRun, /dataset_integrity_failed:[\s\S]*?type: 'Dataset Verification Failed'/);
  assert.match(firstRun, /The downloaded dataset could not be safely verified\. It was not installed\./);
  assert.doesNotMatch(firstRun, /setSelectedMode\('baseline'\)|automatic(?:ally)? fallback/i);
});
