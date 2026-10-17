import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const adminRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const bridgePath = path.join(adminRoot, 'tests/regression/support/database-api-test-bridge.php');
const php = process.env.PHP_BINARY || 'php';

export function databaseTestBridge(action, payload = {}) {
	const result = spawnSync(php, [bridgePath, action], {
		cwd: adminRoot,
		encoding: 'utf8',
		input: JSON.stringify(payload),
		maxBuffer: 10 * 1024 * 1024,
	});

	if (result.error) {
		throw new Error(`Could not run database test support: ${result.error.message}`);
	}
	if (result.status !== 0) {
		const detail = (result.stderr || result.stdout).trim();
		throw new Error(detail || `Database test support failed for action ${action}`);
	}

	try {
		return JSON.parse(result.stdout);
	} catch {
		throw new Error(`Database test support returned invalid JSON for action ${action}`);
	}
}
