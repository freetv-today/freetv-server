import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { after, before, test } from 'node:test';
import { requestJson, startPhpTestServer } from '../support/php-test-server.js';
import { databaseTestBridge } from '../support/test-database.js';

const token = randomBytes(6).toString('hex');
const successResponse = {
	success: true,
	message: 'Thank you! Your problem report has been received.',
};
const rateLimitResponse = {
	success: false,
	message: 'You are submitting problem reports too quickly. Please wait awhile before attempting to report another show title.',
};

let context;
let fixture;
let server;

function fixtureScope() {
	return {
		test_database: fixture.test_database,
		token: fixture.token,
		playlist_a_id: fixture.playlist_a_id,
		playlist_b_id: fixture.playlist_b_id,
		baseline_attempt_id: fixture.baseline_attempt_id,
	};
}

function fixtureState() {
	return databaseTestBridge('report-fixture-state', fixtureScope());
}

function findShow(state, playlistId, identifier) {
	return state.shows.find(
		(show) => show.playlist_id === playlistId && show.identifier === identifier,
	);
}

function findReport(state, playlistId, identifier) {
	return state.reports.find(
		(report) => report.playlist_id === playlistId && report.identifier === identifier,
	);
}

async function submitReport(playlist, identifier) {
	return requestJson(server.baseUrl, '/api/report-problem.php', {
		method: 'POST',
		body: JSON.stringify({ playlist, identifier }),
	});
}

async function withIsolatedRateLimit(run) {
	const beforeState = fixtureState();
	assert.deepEqual(beforeState.attempts, [], 'A reporting phase began with rate-limit attempts');
	let testFailure;
	try {
		await run(beforeState);
	} catch (error) {
		testFailure = error;
	}

	let cleanupFailure;
	try {
		const finalState = fixtureState();
		const cleanup = databaseTestBridge('report-fixture-reset-attempts', {
			...fixtureScope(),
			attempt_ids: finalState.attempts.map((attempt) => attempt.id),
		});
		assert.equal(cleanup.test_database, context.test_database);
		assert.equal(cleanup.deleted_attempts, finalState.attempts.length);
		assert.equal(cleanup.remaining_client_attempts, 0);
	} catch (error) {
		cleanupFailure = error;
	}

	if (cleanupFailure) {
		throw new AggregateError(
			[testFailure, cleanupFailure].filter(Boolean),
			'Problem-report rate-limit phase cleanup failed',
		);
	}
	if (testFailure) throw testFailure;
}

before(async () => {
	context = databaseTestBridge('context');
	assert.notEqual(context.test_database, context.source_database);
	fixture = databaseTestBridge('report-fixture-create', {
		test_database: context.test_database,
		token,
	});

	try {
		server = await startPhpTestServer({ testDatabaseName: context.test_database });
	} catch (error) {
		const state = fixtureState();
		databaseTestBridge('report-fixture-cleanup', {
			...fixtureScope(),
			attempt_ids: state.attempts.map((attempt) => attempt.id),
		});
		throw error;
	}
});

after(async () => {
	const failures = [];
	if (fixture) {
		try {
			const state = fixtureState();
			const cleanup = databaseTestBridge('report-fixture-cleanup', {
				...fixtureScope(),
				attempt_ids: state.attempts.map((attempt) => attempt.id),
			});
			assert.equal(cleanup.test_database, context.test_database);
			assert.equal(cleanup.deleted_playlists, 2);
			assert.equal(cleanup.remaining_playlists, 0);
			assert.equal(cleanup.remaining_shows, 0);
			assert.equal(cleanup.remaining_reports, 0);
			assert.equal(cleanup.remaining_client_attempts, 0);
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
		throw new AggregateError(failures, 'Problem-report fixture cleanup failed');
	}
});

test('handles allowed and disallowed CORS preflight requests', async () => {
	const allowed = await requestJson(server.baseUrl, '/api/report-problem.php', {
		method: 'OPTIONS',
		headers: { Origin: 'https://test.freetv.today' },
	});
	assert.equal(allowed.status, 204);
	assert.equal(allowed.text, '');
	assert.equal(allowed.response.headers.get('access-control-allow-origin'), 'https://test.freetv.today');
	assert.equal(allowed.response.headers.get('access-control-allow-methods'), 'POST, OPTIONS');
	assert.equal(allowed.response.headers.get('access-control-allow-headers'), 'Content-Type');
	assert.equal(allowed.response.headers.get('access-control-max-age'), '600');
	assert.equal(allowed.response.headers.get('vary'), 'Origin');

	const disallowed = await requestJson(server.baseUrl, '/api/report-problem.php', {
		method: 'OPTIONS',
		headers: { Origin: 'https://example.test' },
	});
	assert.equal(disallowed.status, 403);
	assert.equal(disallowed.response.headers.get('access-control-allow-origin'), null);
	assert.equal(disallowed.response.headers.get('access-control-allow-methods'), null);
});

test('rejects malformed and invalid requests without database side effects', async () => {
	const beforeState = fixtureState();
	const cases = [
		{
			options: { method: 'POST', body: '{' },
			status: 400,
			json: { success: false, message: 'Invalid JSON request body' },
		},
		{
			options: { method: 'POST', body: JSON.stringify({ identifier: fixture.identifiers.primary }) },
			status: 400,
			json: { success: false, message: 'Missing or invalid playlist or identifier' },
		},
		{
			options: { method: 'POST', body: JSON.stringify({ playlist: fixture.filenames.a }) },
			status: 400,
			json: { success: false, message: 'Missing or invalid playlist or identifier' },
		},
		{
			options: {
				method: 'POST',
				body: JSON.stringify({ playlist: '../invalid.json', identifier: fixture.identifiers.primary }),
			},
			status: 400,
			json: { success: false, message: 'Invalid playlist filename' },
		},
		{
			options: { method: 'GET' },
			status: 405,
			json: { success: false, message: 'Method not allowed' },
		},
	];

	for (const candidate of cases) {
		const result = await requestJson(server.baseUrl, '/api/report-problem.php', candidate.options);
		assert.equal(result.status, candidate.status);
		assert.deepEqual(result.json, candidate.json);
	}

	const afterState = fixtureState();
	assert.deepEqual(afterState.reports, beforeState.reports);
	assert.deepEqual(afterState.attempts, beforeState.attempts);
});

test('rejects unknown playlists and shows without consuming rate-limit attempts', async () => {
	const beforeState = fixtureState();
	const unknownPlaylist = await submitReport(
		`regression-report-${token}-missing.json`,
		fixture.identifiers.primary,
	);
	assert.equal(unknownPlaylist.status, 404);
	assert.deepEqual(unknownPlaylist.json, { success: false, message: 'Playlist not found' });

	const unknownShow = await submitReport(fixture.filenames.a, `regression-report-${token}-missing`);
	assert.equal(unknownShow.status, 404);
	assert.deepEqual(unknownShow.json, { success: false, message: 'Show not found' });

	const afterState = fixtureState();
	assert.deepEqual(afterState.reports, beforeState.reports);
	assert.deepEqual(afterState.attempts, beforeState.attempts);
});

test('creates, increments, and rate-limits an Active-show report', async () => {
	await withIsolatedRateLimit(async () => {
		const show = findShow(fixtureState(), fixture.playlist_a_id, fixture.identifiers.primary);
		assert.ok(show);

		const first = await submitReport(fixture.filenames.a, fixture.identifiers.primary);
		assert.equal(first.status, 200);
		assert.deepEqual(first.json, successResponse);
		const afterFirst = fixtureState();
		const firstReport = findReport(afterFirst, fixture.playlist_a_id, fixture.identifiers.primary);
		assert.ok(firstReport);
		assert.equal(afterFirst.reports.length, 1);
		assert.equal(firstReport.playlist_show_id, show.id);
		assert.equal(firstReport.identifier, show.identifier);
		assert.equal(firstReport.title, show.title);
		assert.equal(firstReport.category, show.category);
		assert.equal(firstReport.imdb, show.imdb);
		assert.equal(firstReport.report_count, 1);
		assert.equal(firstReport.status, 'reported');
		assert.equal(afterFirst.attempts.length, 1);

		const second = await submitReport(fixture.filenames.a, fixture.identifiers.primary);
		assert.equal(second.status, 200);
		assert.deepEqual(second.json, successResponse);
		const afterSecond = fixtureState();
		const secondReport = findReport(afterSecond, fixture.playlist_a_id, fixture.identifiers.primary);
		assert.equal(afterSecond.reports.length, 1);
		assert.equal(secondReport.id, firstReport.id);
		assert.equal(secondReport.report_count, 2);
		assert.equal(secondReport.status, 'reported');
		assert.equal(afterSecond.attempts.length, 2);

		const third = await submitReport(fixture.filenames.a, fixture.identifiers.primary);
		assert.equal(third.status, 429);
		assert.deepEqual(third.json, rateLimitResponse);
		const afterThird = fixtureState();
		const thirdReport = findReport(afterThird, fixture.playlist_a_id, fixture.identifiers.primary);
		assert.equal(afterThird.reports.length, 1);
		assert.equal(thirdReport.id, firstReport.id);
		assert.equal(thirdReport.report_count, 2);
		assert.equal(afterThird.attempts.length, 3);
	});
});

test('keeps reports with the same identifier isolated by playlist', async () => {
	await withIsolatedRateLimit(async () => {
		const first = await submitReport(fixture.filenames.a, fixture.identifiers.shared);
		const second = await submitReport(fixture.filenames.b, fixture.identifiers.shared);
		assert.equal(first.status, 200);
		assert.equal(second.status, 200);

		const state = fixtureState();
		const reportA = findReport(state, fixture.playlist_a_id, fixture.identifiers.shared);
		const reportB = findReport(state, fixture.playlist_b_id, fixture.identifiers.shared);
		assert.ok(reportA);
		assert.ok(reportB);
		assert.notEqual(reportA.id, reportB.id);
		assert.equal(reportA.report_count, 1);
		assert.equal(reportB.report_count, 1);
		assert.equal(reportA.playlist_id, fixture.playlist_a_id);
		assert.equal(reportB.playlist_id, fixture.playlist_b_id);
		assert.equal(state.attempts.length, 2);
	});
});

test('reopens an addressed report and preserves its row and metadata', async () => {
	const prepared = databaseTestBridge('report-fixture-prepare-addressed', fixtureScope());
	await withIsolatedRateLimit(async () => {
		const beforeState = fixtureState();
		const beforeReport = findReport(
			beforeState,
			fixture.playlist_a_id,
			fixture.identifiers.addressed,
		);
		const show = findShow(beforeState, fixture.playlist_a_id, fixture.identifiers.addressed);
		assert.ok(beforeReport);
		assert.ok(show);
		assert.equal(beforeReport.id, prepared.report_id);
		assert.equal(beforeReport.status, 'addressed');
		assert.equal(beforeReport.report_count, 4);

		const result = await submitReport(fixture.filenames.a, fixture.identifiers.addressed);
		assert.equal(result.status, 200);
		assert.deepEqual(result.json, successResponse);
		const afterState = fixtureState();
		const reopened = findReport(afterState, fixture.playlist_a_id, fixture.identifiers.addressed);
		assert.equal(reopened.id, beforeReport.id);
		assert.equal(reopened.status, 'reported');
		assert.equal(reopened.report_count, 5);
		assert.equal(reopened.first_reported_at, beforeReport.first_reported_at);
		assert.equal(reopened.playlist_show_id, show.id);
		assert.equal(reopened.title, show.title);
		assert.equal(reopened.category, show.category);
		assert.equal(reopened.imdb, show.imdb);
		assert.equal(afterState.attempts.length, 1);
	});
});

test('rejects a Disabled-show report without report or rate-limit side effects', async () => {
	await withIsolatedRateLimit(async (beforeState) => {
		assert.equal(
			findReport(beforeState, fixture.playlist_a_id, fixture.identifiers.disabled),
			undefined,
		);
		const result = await submitReport(fixture.filenames.a, fixture.identifiers.disabled);
		const afterState = fixtureState();

		assert.equal(
			result.status,
			404,
			`Disabled-show report returned HTTP ${result.status}: ${result.text}`,
		);
		assert.deepEqual(result.json, { success: false, message: 'Show not found' });
		assert.deepEqual(afterState.reports, beforeState.reports);
		assert.equal(
			findReport(afterState, fixture.playlist_a_id, fixture.identifiers.disabled),
			undefined,
		);
		assert.deepEqual(afterState.attempts, beforeState.attempts);
	});
});

test('does not reopen an addressed report for a Disabled show', async () => {
	const prepared = databaseTestBridge('report-fixture-prepare-addressed', {
		...fixtureScope(),
		target: 'disabled',
	});
	await withIsolatedRateLimit(async (beforeState) => {
		const beforeReport = findReport(
			beforeState,
			fixture.playlist_a_id,
			fixture.identifiers.disabled,
		);
		assert.ok(beforeReport);
		assert.equal(beforeReport.id, prepared.report_id);
		assert.equal(beforeReport.status, 'addressed');
		assert.equal(beforeReport.report_count, 4);

		const result = await submitReport(fixture.filenames.a, fixture.identifiers.disabled);
		const afterState = fixtureState();
		const afterReport = findReport(
			afterState,
			fixture.playlist_a_id,
			fixture.identifiers.disabled,
		);

		assert.equal(result.status, 404);
		assert.deepEqual(result.json, { success: false, message: 'Show not found' });
		assert.deepEqual(afterReport, beforeReport);
		assert.deepEqual(afterState.reports, beforeState.reports);
		assert.deepEqual(afterState.attempts, beforeState.attempts);
	});
});
