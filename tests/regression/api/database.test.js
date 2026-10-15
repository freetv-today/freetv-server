import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import {
	createAdminSession,
	requestJson,
	startPhpTestServer,
} from '../support/php-test-server.js';
import { databaseTestBridge } from '../support/test-database.js';

let context;
let server;
let viewerSession;
let editorSession;

before(async () => {
	context = databaseTestBridge('context');
	assert.notEqual(context.test_database, context.source_database);
	server = await startPhpTestServer({ testDatabaseName: context.test_database });
	viewerSession = createAdminSession(server.sessionPath, 'viewer');
	editorSession = createAdminSession(server.sessionPath, 'editor');
});

after(async () => {
	await server?.stop();
});

function expectedIndex() {
	return {
		default: context.index.default,
		playlists: context.index.playlists.map((playlist) => ({
			filename: playlist.filename,
			dbtitle: playlist.dbtitle,
			lastupdated: playlist.lastupdated,
			author: playlist.author,
			email: playlist.email,
			link: playlist.link,
		})),
	};
}

function expectedPlaylist() {
	const metadata = context.playlist.metadata;
	return {
		filename: metadata.filename,
		dbtitle: metadata.dbtitle,
		dbversion: metadata.dbversion,
		author: metadata.author,
		email: metadata.email,
		link: metadata.link,
		lastupdated: metadata.lastupdated,
		is_default: metadata.is_default,
		shows: context.playlist.shows.map((show) => {
			const expected = {
				category: show.category,
				status: show.status,
				identifier: show.identifier,
				title: show.title,
				desc: show.description,
				start: show.start_year,
				end: show.end_year,
				imdb: show.imdb,
			};
			if (typeof show.group_name === 'string' && show.group_name.trim() !== '') {
				expected.group = show.group_name.trim();
			}
			return expected;
		}),
	};
}

function recordScope() {
	return {
		test_database: context.test_database,
		playlist_id: context.toggle.playlist_id,
		show_id: context.toggle.show_id,
		identifier: context.toggle.identifier,
	};
}

test('playlist proxy returns database-backed index and ordered playlist data', async () => {
	const index = await requestJson(
		server.baseUrl,
		'/api/admin/playlist_proxy.php?file=index.json',
		{ sessionId: viewerSession },
	);
	assert.equal(index.status, 200);
	assert.ok(Array.isArray(index.json?.playlists));
	assert.deepEqual(index.json, expectedIndex());

	const playlist = await requestJson(
		server.baseUrl,
		`/api/admin/playlist_proxy.php?file=${encodeURIComponent(context.playlist.metadata.filename)}`,
		{ sessionId: viewerSession },
	);
	assert.equal(playlist.status, 200);
	assert.ok(Array.isArray(playlist.json?.shows));
	assert.ok(playlist.json.shows.length > 0, 'Selected database fixture playlist has no shows');
	assert.deepEqual(playlist.json, expectedPlaylist());
	for (const show of playlist.json.shows) {
		assert.ok(Object.hasOwn(show, 'desc'));
		assert.ok(Object.hasOwn(show, 'start'));
		assert.ok(Object.hasOwn(show, 'end'));
	}
});

test('toggle status updates the scoped cloned row and restores it afterward', async () => {
	const original = {
		...recordScope(),
		status: context.toggle.status,
		show_updated_at: context.toggle.show_updated_at,
		lastupdated: context.toggle.lastupdated,
		playlist_updated_at: context.toggle.playlist_updated_at,
	};
	const expectedStatus = original.status === 'active' ? 'disabled' : 'active';
	let testFailure;

	try {
		const result = await requestJson(server.baseUrl, '/api/admin/toggle-status.php', {
			method: 'POST',
			body: JSON.stringify({
				playlist: context.toggle.filename,
				identifier: context.toggle.identifier,
			}),
			sessionId: editorSession,
		});
		assert.equal(result.status, 200);
		assert.deepEqual(result.json, {
			success: true,
			message: 'Status updated',
			status: expectedStatus,
		});

		const changed = databaseTestBridge('state', recordScope());
		assert.equal(changed.test_database, context.test_database);
		assert.equal(changed.playlist_id, original.playlist_id);
		assert.equal(changed.show_id, original.show_id);
		assert.equal(changed.identifier, original.identifier);
		assert.equal(changed.status, expectedStatus);
		assert.ok(changed.lastupdated >= context.server_time);
		assert.ok(changed.lastupdated <= changed.server_time);

		const playlist = await requestJson(
			server.baseUrl,
			`/api/admin/playlist_proxy.php?file=${encodeURIComponent(context.toggle.filename)}`,
			{ sessionId: viewerSession },
		);
		assert.equal(playlist.status, 200);
		assert.equal(playlist.json?.lastupdated, changed.lastupdated);
		const changedShow = playlist.json?.shows?.find(
			(show) => show.identifier === original.identifier,
		);
		assert.ok(changedShow, 'Toggled show is missing from playlist proxy response');
		assert.equal(changedShow.status, expectedStatus);
	} catch (error) {
		testFailure = error;
	}

	let restorationFailure;
	try {
		const restored = databaseTestBridge('restore', original);
		assert.equal(restored.test_database, context.test_database);
		assert.equal(restored.status, original.status);
		assert.equal(restored.show_updated_at, original.show_updated_at);
		assert.equal(restored.lastupdated, original.lastupdated);
		assert.equal(restored.playlist_updated_at, original.playlist_updated_at);
	} catch (error) {
		restorationFailure = error;
	}

	if (restorationFailure) {
		throw new AggregateError(
			[testFailure, restorationFailure].filter(Boolean),
			`Failed to restore cloned playlist_show ${original.show_id}`,
		);
	}
	if (testFailure) throw testFailure;
});
