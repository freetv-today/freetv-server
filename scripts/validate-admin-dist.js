import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/* global process */

const ALLOWED_ROOT_ENTRIES = new Set(['assets', 'index.html']);
const ALLOWED_ASSET_EXTENSIONS = new Set(['.css', '.gif', '.js', '.png', '.svg', '.ttf']);
const FORBIDDEN_SEGMENTS = new Set(['api', 'logs', 'playlists', 'temp', 'thumbs', 'tools']);

function walk(directory, relativeDirectory = '') {
  const entries = [];
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const relativePath = path.join(relativeDirectory, entry.name);
    const absolutePath = path.join(directory, entry.name);
    entries.push({ entry, relativePath, absolutePath });
    if (entry.isDirectory()) entries.push(...walk(absolutePath, relativePath));
  }
  return entries;
}

export function validateAdminDist(distRoot) {
  const resolvedRoot = path.resolve(distRoot);
  const errors = [];

  if (!fs.existsSync(resolvedRoot) || !fs.statSync(resolvedRoot).isDirectory()) {
    throw new Error(`Admin dist directory is missing: ${resolvedRoot}`);
  }

  const rootEntries = fs.readdirSync(resolvedRoot, { withFileTypes: true });
  for (const entry of rootEntries) {
    if (!ALLOWED_ROOT_ENTRIES.has(entry.name)) {
      errors.push(`unexpected root entry: ${entry.name}`);
    }
  }

  const indexPath = path.join(resolvedRoot, 'index.html');
  const assetsPath = path.join(resolvedRoot, 'assets');
  if (!fs.existsSync(indexPath) || !fs.statSync(indexPath).isFile()) {
    errors.push('index.html is missing or is not a file');
  }
  if (!fs.existsSync(assetsPath) || !fs.statSync(assetsPath).isDirectory()) {
    errors.push('assets/ is missing or is not a directory');
  }

  let hasJavaScript = false;
  let hasCss = false;
  for (const { entry, relativePath } of walk(resolvedRoot)) {
    const segments = relativePath.split(path.sep);
    const basename = segments.at(-1).toLowerCase();

    if (entry.isSymbolicLink()) {
      errors.push(`symbolic links are not allowed: ${relativePath}`);
      continue;
    }
    if (segments.some((segment) => FORBIDDEN_SEGMENTS.has(segment.toLowerCase()))) {
      errors.push(`forbidden runtime path: ${relativePath}`);
    }
    if (basename === 'config.json' || basename === '.env' || basename.startsWith('.env.')) {
      errors.push(`forbidden runtime/config file: ${relativePath}`);
    }
    if (basename.endsWith('.key')) {
      errors.push(`credential/key files are not allowed: ${relativePath}`);
    }
    if (entry.isFile() && relativePath !== 'index.html') {
      if (!relativePath.startsWith(`assets${path.sep}`)) {
        errors.push(`frontend asset is outside assets/: ${relativePath}`);
        continue;
      }
      const extension = path.extname(basename);
      if (!ALLOWED_ASSET_EXTENSIONS.has(extension)) {
        errors.push(`unsupported Admin asset type: ${relativePath}`);
      }
      if (extension === '.js') hasJavaScript = true;
      if (extension === '.css') hasCss = true;
    }
  }

  if (!hasJavaScript) errors.push('assets/ does not contain an Admin JavaScript bundle');
  if (!hasCss) errors.push('assets/ does not contain an Admin CSS bundle');

  if (errors.length > 0) {
    throw new Error(`Admin dist contract validation failed:\n - ${errors.join('\n - ')}`);
  }

  return { root: resolvedRoot, files: walk(resolvedRoot).filter(({ entry }) => entry.isFile()).length };
}

const scriptPath = process.argv[1] ? path.resolve(process.argv[1]) : null;
if (scriptPath === fileURLToPath(import.meta.url)) {
  const serverRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
  try {
    const result = validateAdminDist(path.join(serverRoot, 'dist'));
    console.log(`Admin dist contract validation passed (${result.files} files).`);
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
