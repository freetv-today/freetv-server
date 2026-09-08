import assert from 'node:assert/strict';
import test from 'node:test';
// Node ESM requires this extension; the project lint rule targets browser imports.
// eslint-disable-next-line import/extensions
import { shouldShowDataSnapshotNavigation } from '../src/utils/dataSnapshotNavigation.js';

test('hides Data Snapshot navigation when the Vite flag is absent or not exactly true', () => {
  assert.equal(shouldShowDataSnapshotNavigation(true, undefined), false);
  assert.equal(shouldShowDataSnapshotNavigation(true, 'false'), false);
  assert.equal(shouldShowDataSnapshotNavigation(true, 'TRUE'), false);
  assert.equal(shouldShowDataSnapshotNavigation(true, true), false);
});

test('shows Data Snapshot navigation when an Admin explicitly enables it', () => {
  assert.equal(shouldShowDataSnapshotNavigation(true, 'true'), true);
});

test('keeps Data Snapshot navigation hidden from non-Admins when enabled', () => {
  assert.equal(shouldShowDataSnapshotNavigation(false, 'true'), false);
});
