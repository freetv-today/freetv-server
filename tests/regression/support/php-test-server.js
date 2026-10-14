import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
import net from 'node:net';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const adminRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const host = '127.0.0.1';
const php = process.env.PHP_BINARY || 'php';

function reservePort() {
	return new Promise((resolve, reject) => {
		const server = net.createServer();
		server.unref();
		server.once('error', reject);
		server.listen(0, host, () => {
			const address = server.address();
			const port = typeof address === 'object' && address ? address.port : null;
			server.close((error) => {
				if (error) reject(error);
				else if (!port) reject(new Error('Could not allocate a PHP test-server port'));
				else resolve(port);
			});
		});
	});
}

function hasExited(child) {
	return child.exitCode !== null || child.signalCode !== null;
}

function waitForServer(child, port, output, spawnError, timeoutMs = 5000) {
	const startedAt = Date.now();

	return new Promise((resolve, reject) => {
		function attempt() {
			if (spawnError()) {
				reject(new Error(`Could not start the PHP test server: ${spawnError().message}`));
				return;
			}
			if (hasExited(child)) {
				reject(new Error(`PHP test server exited before becoming ready.\n${output()}`));
				return;
			}

			const request = http.get({
				host,
				port,
				path: '/api/admin/playlist_proxy.php?file=index.json',
			}, (response) => {
				response.resume();
				if (response.statusCode === 401) {
					resolve();
					return;
				}
				reject(new Error(
					`PHP test server readiness endpoint returned HTTP ${response.statusCode}.\n${output()}`
				));
			});
			request.once('error', () => {
				request.destroy();
				if (Date.now() - startedAt >= timeoutMs) {
					reject(new Error(`PHP test server did not become ready within ${timeoutMs} ms.\n${output()}`));
					return;
				}
				setTimeout(attempt, 50);
			});
		}

		attempt();
	});
}

function waitForExit(child, timeoutMs) {
	if (hasExited(child)) return Promise.resolve(true);

	return new Promise((resolve) => {
		const timer = setTimeout(() => {
			child.off('exit', onExit);
			resolve(false);
		}, timeoutMs);
		function onExit() {
			clearTimeout(timer);
			resolve(true);
		}
		child.once('exit', onExit);
	});
}

export async function startPhpTestServer() {
	const port = await reservePort();
	const temporaryRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'freetv-admin-api-'));
	const sessionPath = path.join(temporaryRoot, 'sessions');
	fs.mkdirSync(sessionPath, { mode: 0o700 });
	const outputChunks = [];
	let childSpawnError = null;

	const child = spawn(php, [
		'-d', `session.save_path=${sessionPath}`,
		'-d', 'session.use_strict_mode=1',
		'-d', 'display_errors=0',
		'-d', 'log_errors=1',
		'-S', `${host}:${port}`,
		'-t', path.join(adminRoot, 'public'),
	], {
		cwd: adminRoot,
		env: {
			...process.env,
			DB_HOST: host,
			DB_PORT: '0',
			DB_NAME: 'freetv_regression_tests_must_not_connect',
			DB_USER: 'freetv_regression_tests',
			DB_PASS: '',
			VITE_DB_HOST: host,
			VITE_DB_NAME: 'freetv_regression_tests_must_not_connect',
			VITE_DB_USER: 'freetv_regression_tests',
			VITE_DB_PASS: '',
		},
		stdio: ['ignore', 'pipe', 'pipe'],
	});

	const collect = (chunk) => outputChunks.push(chunk.toString());
	child.once('error', (error) => { childSpawnError = error; });
	child.stdout.on('data', collect);
	child.stderr.on('data', collect);

	try {
		await waitForServer(child, port, () => outputChunks.join(''), () => childSpawnError);
	} catch (error) {
		if (child.pid) {
			child.kill('SIGTERM');
			await waitForExit(child, 1000);
		}
		fs.rmSync(temporaryRoot, { recursive: true, force: true });
		throw error;
	}

	async function stop() {
		if (!hasExited(child)) {
			child.kill('SIGTERM');
			if (!await waitForExit(child, 2000)) {
				child.kill('SIGKILL');
				await waitForExit(child, 1000);
			}
		}
		fs.rmSync(temporaryRoot, { recursive: true, force: true });
	}

	return {
		baseUrl: `http://${host}:${port}`,
		output: () => outputChunks.join(''),
		sessionPath,
		stop,
	};
}

export function createAdminSession(sessionPath, role) {
	if (!['viewer', 'editor', 'admin'].includes(role)) {
		throw new Error(`Unsupported test-session role: ${role}`);
	}

	const sessionId = `freetv-regression-${role}-${process.pid}`;
	const code = [
		'session_id($argv[1]);',
		'session_start();',
		'$_SESSION["admin"] = ["id" => 1, "username" => "regression-test", "role" => $argv[2]];',
		'session_write_close();',
	].join(' ');
	const result = spawnSync(php, [
		'-d', `session.save_path=${sessionPath}`,
		'-d', 'session.use_strict_mode=0',
		'-r', code,
		sessionId,
		role,
	], { cwd: adminRoot, encoding: 'utf8' });

	if (result.status !== 0) {
		throw new Error(`Could not create the ${role} test session: ${result.stderr || result.stdout}`);
	}

	return sessionId;
}

export async function requestJson(baseUrl, pathname, { sessionId, ...options } = {}) {
	const headers = new Headers(options.headers);
	if (sessionId) headers.set('Cookie', `PHPSESSID=${sessionId}`);
	if (options.body !== undefined && !headers.has('Content-Type')) {
		headers.set('Content-Type', 'application/json');
	}

	const response = await fetch(new URL(pathname, baseUrl), { ...options, headers });
	const text = await response.text();
	let json = null;
	try {
		json = JSON.parse(text);
	} catch {
		// Some endpoint failures may not guarantee JSON; callers can assert text instead.
	}

	return { response, status: response.status, text, json };
}
