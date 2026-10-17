import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { after, before, test } from 'node:test';
import {
	createAdminSession,
	requestJson,
	startPhpTestServer,
} from '../support/php-test-server.js';
import { databaseTestBridge } from '../support/test-database.js';

let context;
let fixture;
let server;
let viewerSession;
let editorSession;

const token = randomBytes(6).toString('hex');
const sharedIdentifier = `regression-${token}-shared`;
const editedIdentifier = `regression-${token}-edited`;
const editSourceIdentifier = `regression-${token}-edit-source`;
const editTargetIdentifier = `regression-${token}-edit-target`;

function fixtureScope() {
	return {
		test_database: fixture.test_database,
		token: fixture.token,
		playlist_a_id: fixture.playlist_a_id,
		playlist_b_id: fixture.playlist_b_id,
	};
}

function fixtureState() {
	return databaseTestBridge('show-fixture-state', fixtureScope());
}

function show(overrides = {}) {
	return {
		category: 'Comedy',
		status: 'active',
		identifier: sharedIdentifier,
		title: 'Shared Regression Show',
		desc: 'Added through the HTTP regression suite',
		start: '1970',
		end: '1971',
		imdb: 'tt9100001',
		group: '  Morning Regression  ',
		...overrides,
	};
}

async function updateShow(playlist, showData, { add = false, originalIdentifier } = {}) {
	return requestJson(server.baseUrl, '/api/admin/update-show.php', {
		method: 'POST',
		body: JSON.stringify({
			playlist,
			show: showData,
			add,
			...(originalIdentifier === undefined ? {} : { originalIdentifier }),
		}),
		sessionId: editorSession,
	});
}

async function playlistProxy(filename) {
	return requestJson(
		server.baseUrl,
		`/api/admin/playlist_proxy.php?file=${encodeURIComponent(filename)}`,
		{ sessionId: viewerSession },
	);
}

function findShow(state, playlist, identifier) {
	return state.playlists[playlist].shows.find((candidate) => candidate.identifier === identifier);
}

function assertTimestampWithinOperation(timestamp, before, after) {
	assert.equal(typeof timestamp, 'string');
	assert.ok(timestamp >= before, `${timestamp} is earlier than operation boundary ${before}`);
	assert.ok(timestamp <= after, `${timestamp} is later than operation boundary ${after}`);
}

before(async () => {
	context = databaseTestBridge('context');
	assert.notEqual(context.test_database, context.source_database);
	fixture = databaseTestBridge('show-fixture-create', {
		test_database: context.test_database,
		token,
	});

	try {
		server = await startPhpTestServer({ testDatabaseName: context.test_database });
		viewerSession = createAdminSession(server.sessionPath, 'viewer');
		editorSession = createAdminSession(server.sessionPath, 'editor');
	} catch (error) {
		databaseTestBridge('show-fixture-cleanup', fixtureScope());
		throw error;
	}
});

after(async () => {
	const failures = [];
	if (fixture) {
		try {
			const cleanup = databaseTestBridge('show-fixture-cleanup', fixtureScope());
			assert.equal(cleanup.test_database, context.test_database);
			assert.equal(cleanup.deleted_playlists, 2);
			assert.equal(cleanup.remaining_playlists, 0);
			assert.equal(cleanup.remaining_shows, 0);
			assert.deepEqual(cleanup.counts_after, fixture.counts_before);
		} catch (error) {
			failures.push(error);
		}
	}
	try {
		await server?.stop();
	} catch (error) {
		failures.push(error);
	}
	if (failures.length > 0) {
		throw new AggregateError(failures, 'Show-management fixture cleanup failed');
	}
});

test('adds a show at the end of its playlist and exposes it through the proxy', async () => {
	const before = fixtureState();
	const previousMaximum = Math.max(...before.playlists.a.shows.map((entry) => entry.sort_order));
	const showData = show();
	const result = await updateShow(fixture.filenames.a, showData, { add: true });
	const changed = fixtureState();

	assert.equal(result.status, 200);
	assert.deepEqual(result.json, { success: true, message: 'Show added' });
	const stored = findShow(changed, 'a', sharedIdentifier);
	assert.ok(stored);
	assert.deepEqual(
		{
			category: stored.category,
			status: stored.status,
			identifier: stored.identifier,
			title: stored.title,
			description: stored.description,
			start_year: stored.start_year,
			end_year: stored.end_year,
			imdb: stored.imdb,
			group_name: stored.group_name,
		},
		{
			category: showData.category,
			status: showData.status,
			identifier: showData.identifier,
			title: showData.title,
			description: showData.desc,
			start_year: showData.start,
			end_year: showData.end,
			imdb: showData.imdb,
			group_name: 'Morning Regression',
		},
	);
	assert.equal(stored.sort_order, previousMaximum + 1);
	assertTimestampWithinOperation(
		changed.playlists.a.lastupdated,
		before.server_time,
		changed.server_time,
	);
	assert.equal(changed.playlists.b.lastupdated, before.playlists.b.lastupdated);

	const proxy = await playlistProxy(fixture.filenames.a);
	assert.equal(proxy.status, 200);
	assert.equal(proxy.json.lastupdated, changed.playlists.a.lastupdated);
	const proxied = proxy.json.shows.find((entry) => entry.identifier === sharedIdentifier);
	assert.deepEqual(proxied, {
		category: showData.category,
		status: showData.status,
		identifier: showData.identifier,
		title: showData.title,
		desc: showData.desc,
		start: showData.start,
		end: showData.end,
		imdb: showData.imdb,
		group: 'Morning Regression',
	});
});

test('allows the same Internet Archive identifier in another playlist', async () => {
	const before = fixtureState();
	const previousMaximum = Math.max(...before.playlists.b.shows.map((entry) => entry.sort_order));
	const showData = show({
		title: 'Playlist B Shared Regression Show',
		desc: 'The same archive identifier in a different playlist',
		imdb: 'tt9100002',
		group: 'Evening Regression',
	});
	const result = await updateShow(fixture.filenames.b, showData, { add: true });
	const changed = fixtureState();

	assert.equal(result.status, 200);
	assert.deepEqual(result.json, { success: true, message: 'Show added' });
	const showA = findShow(changed, 'a', sharedIdentifier);
	const showB = findShow(changed, 'b', sharedIdentifier);
	assert.ok(showA);
	assert.ok(showB);
	assert.notEqual(showA.id, showB.id);
	assert.equal(showA.playlist_id, fixture.playlist_a_id);
	assert.equal(showB.playlist_id, fixture.playlist_b_id);
	assert.equal(showB.sort_order, previousMaximum + 1);
	assertTimestampWithinOperation(
		changed.playlists.b.lastupdated,
		before.server_time,
		changed.server_time,
	);
	assert.equal(changed.playlists.a.lastupdated, before.playlists.a.lastupdated);

	const [proxyA, proxyB] = await Promise.all([
		playlistProxy(fixture.filenames.a),
		playlistProxy(fixture.filenames.b),
	]);
	assert.equal(proxyA.status, 200);
	assert.equal(proxyB.status, 200);
	assert.equal(proxyA.json.shows.filter((entry) => entry.identifier === sharedIdentifier).length, 1);
	assert.equal(proxyB.json.shows.filter((entry) => entry.identifier === sharedIdentifier).length, 1);
});

test('rejects a duplicate identifier in the same playlist without changing data', async () => {
	const before = fixtureState();
	const original = findShow(before, 'a', sharedIdentifier);
	const result = await updateShow(
		fixture.filenames.a,
		show({ title: 'Should Not Replace Existing Show', imdb: 'tt9100003' }),
		{ add: true },
	);
	const afterAttempt = fixtureState();

	assert.equal(result.status, 409);
	assert.deepEqual(result.json, {
		success: false,
		message: 'A show with this identifier already exists in the selected playlist',
	});
	assert.equal(
		afterAttempt.playlists.a.shows.filter((entry) => entry.identifier === sharedIdentifier).length,
		1,
	);
	assert.deepEqual(findShow(afterAttempt, 'a', sharedIdentifier), original);
	assert.equal(afterAttempt.playlists.a.lastupdated, before.playlists.a.lastupdated);
	assert.equal(afterAttempt.playlists.a.updated_at, before.playlists.a.updated_at);
});

test('edits only the selected playlist show and preserves its ordering', async () => {
	const before = fixtureState();
	const originalA = findShow(before, 'a', sharedIdentifier);
	const originalB = findShow(before, 'b', sharedIdentifier);
	const edited = show({
		category: 'Drama',
		status: 'disabled',
		identifier: editedIdentifier,
		title: 'Edited Playlist A Show',
		desc: 'Edited through the HTTP regression suite',
		start: '1980',
		end: '1985',
		imdb: 'tt9100004',
		group: '  Edited Group  ',
	});
	const result = await updateShow(fixture.filenames.a, edited, {
		originalIdentifier: sharedIdentifier,
	});
	const changed = fixtureState();

	assert.equal(result.status, 200);
	assert.deepEqual(result.json, { success: true, message: 'Show updated' });
	assert.equal(findShow(changed, 'a', sharedIdentifier), undefined);
	const stored = findShow(changed, 'a', editedIdentifier);
	assert.ok(stored);
	assert.equal(stored.id, originalA.id);
	assert.equal(stored.sort_order, originalA.sort_order);
	assert.deepEqual(
		{
			category: stored.category,
			status: stored.status,
			title: stored.title,
			description: stored.description,
			start_year: stored.start_year,
			end_year: stored.end_year,
			imdb: stored.imdb,
			group_name: stored.group_name,
		},
		{
			category: edited.category,
			status: edited.status,
			title: edited.title,
			description: edited.desc,
			start_year: edited.start,
			end_year: edited.end,
			imdb: edited.imdb,
			group_name: 'Edited Group',
		},
	);
	assert.deepEqual(findShow(changed, 'b', sharedIdentifier), originalB);
	assert.equal(changed.playlists.b.lastupdated, before.playlists.b.lastupdated);
	assertTimestampWithinOperation(
		changed.playlists.a.lastupdated,
		before.server_time,
		changed.server_time,
	);

	const [proxyA, proxyB] = await Promise.all([
		playlistProxy(fixture.filenames.a),
		playlistProxy(fixture.filenames.b),
	]);
	assert.equal(proxyA.status, 200);
	assert.equal(proxyB.status, 200);
	assert.deepEqual(
		proxyA.json.shows.find((entry) => entry.identifier === editedIdentifier),
		{
			category: edited.category,
			status: edited.status,
			identifier: edited.identifier,
			title: edited.title,
			desc: edited.desc,
			start: edited.start,
			end: edited.end,
			imdb: edited.imdb,
			group: 'Edited Group',
		},
	);
	assert.equal(
		proxyB.json.shows.find((entry) => entry.identifier === sharedIdentifier).title,
		originalB.title,
	);
});

test('rejects a duplicate identifier during edit without partial transaction effects', async () => {
	const before = fixtureState();
	const sourceBefore = findShow(before, 'a', editSourceIdentifier);
	const targetBefore = findShow(before, 'a', editTargetIdentifier);
	const result = await updateShow(
		fixture.filenames.a,
		show({
			identifier: editTargetIdentifier,
			title: 'Duplicate Edit Must Not Persist',
			imdb: 'tt9100005',
		}),
		{ originalIdentifier: editSourceIdentifier },
	);
	const afterAttempt = fixtureState();

	assert.deepEqual(findShow(afterAttempt, 'a', editSourceIdentifier), sourceBefore);
	assert.deepEqual(findShow(afterAttempt, 'a', editTargetIdentifier), targetBefore);
	assert.equal(
		afterAttempt.playlists.a.shows.filter((entry) => entry.identifier === editTargetIdentifier).length,
		1,
	);
	assert.equal(afterAttempt.playlists.a.lastupdated, before.playlists.a.lastupdated);
	assert.equal(afterAttempt.playlists.a.updated_at, before.playlists.a.updated_at);
	assert.equal(
		result.status,
		409,
		`duplicate edit returned HTTP ${result.status}: ${result.text}`,
	);
	assert.deepEqual(result.json, {
		success: false,
		message: 'A show with this identifier already exists in the selected playlist',
	});
});
