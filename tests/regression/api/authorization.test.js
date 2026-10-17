import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import {
	createAdminSession,
	requestJson,
	startPhpTestServer,
} from '../support/php-test-server.js';

let server;
let viewerSession;
let editorSession;

before(async () => {
	server = await startPhpTestServer();
	viewerSession = createAdminSession(server.sessionPath, 'viewer');
	editorSession = createAdminSession(server.sessionPath, 'editor');
});

after(async () => {
	await server?.stop();
});

test('playlist proxy rejects an unauthenticated request', async () => {
	const result = await requestJson(server.baseUrl, '/api/admin/playlist_proxy.php?file=index.json');

	assert.equal(result.status, 401);
	assert.deepEqual(result.json, { success: false, message: 'Unauthorized' });
});

test('show update rejects an unauthenticated request', async () => {
	const result = await requestJson(server.baseUrl, '/api/admin/update-show.php', {
		method: 'POST',
		body: '{}',
	});

	assert.equal(result.status, 401);
	assert.deepEqual(result.json, { success: false, message: 'Unauthorized' });
});

test('show update rejects a Viewer-role session', async () => {
	const result = await requestJson(server.baseUrl, '/api/admin/update-show.php', {
		method: 'POST',
		body: '{}',
		sessionId: viewerSession,
	});

	assert.equal(result.status, 403);
	assert.deepEqual(result.json, { success: false, message: 'Forbidden' });
});

test('show update rejects an unsupported HTTP method', async () => {
	const result = await requestJson(server.baseUrl, '/api/admin/update-show.php', {
		method: 'GET',
		sessionId: editorSession,
	});

	assert.equal(result.status, 405);
	assert.deepEqual(result.json, { success: false, message: 'Method not allowed' });
});

test('show update rejects malformed JSON', async () => {
	const result = await requestJson(server.baseUrl, '/api/admin/update-show.php', {
		method: 'POST',
		body: '{',
		sessionId: editorSession,
	});

	assert.equal(result.status, 400);
	assert.deepEqual(result.json, { success: false, message: 'Invalid JSON request body' });
});

test('show update rejects missing playlist and show data', async () => {
	const result = await requestJson(server.baseUrl, '/api/admin/update-show.php', {
		method: 'POST',
		body: '{}',
		sessionId: editorSession,
	});

	assert.equal(result.status, 400);
	assert.deepEqual(result.json, {
		success: false,
		message: 'Missing or invalid playlist or show data',
	});
});
