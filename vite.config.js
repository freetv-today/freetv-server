import { defineConfig, loadEnv } from 'vite';
import preact from '@preact/preset-vite';
import { resolve } from 'path';
import { fileURLToPath, URL } from 'node:url';

function developmentAdminAssets(base) {
  const assetBase = `${base.endsWith('/') ? base : `${base}/`}assets/`;

  return {
    name: 'development-admin-assets',
    apply: 'serve',
    enforce: 'pre',
    transform(code, id) {
      if (id.endsWith('/src/adminAssets.js')) {
        return code.replace(
          /from '\.\.\/public\/assets\/([^']+)'/g,
          "from '/assets/$1?url'"
        );
      }
      return null;
    },
    transformIndexHtml: {
      order: 'pre',
      handler(html) {
        return html
          .replace('./public/assets/freetv.png', `${assetBase}freetv.png`)
          .replace('./public/assets/error-reload.svg', `${assetBase}error-reload.svg`);
      }
    }
  };
}

export default defineConfig(({ command, mode }) => {
  // Load environment variables
  const env = loadEnv(mode, '.', '');

  const apiProxyTarget =
  env.VITE_API_PROXY_TARGET || 'http://localhost:8081';
  
  // Use environment-based base path
  const base = env.VITE_BASE_PATH || (mode === 'production' ? '/admin/' : '/');
  
  return {
    plugins: [preact(), developmentAdminAssets(base)],
    base: base,
    // Server public/ is the PHP/publication web root, not the Admin asset source.
    // Keep it available to the standalone dev server, but never copy it into dist.
    publicDir: command === 'build' ? false : 'public',
    resolve: {
      alias: {
        '@': resolve(fileURLToPath(new URL('.', import.meta.url)), 'src'),
        '@components': resolve(fileURLToPath(new URL('.', import.meta.url)), 'src/components'),
        '@pages': resolve(fileURLToPath(new URL('.', import.meta.url)), 'src/pages'),
        '@context': resolve(fileURLToPath(new URL('.', import.meta.url)), 'src/context'),
        '@signals': resolve(fileURLToPath(new URL('.', import.meta.url)), 'src/signals'),
        '@hooks': resolve(fileURLToPath(new URL('.', import.meta.url)), 'src/hooks'),
      },
    },
    build: {
      rollupOptions: {
        external: [], // Add any external dependencies if needed
      },
      // Suppress asset resolution warnings for files in /public
      emptyOutDir: true,
      assetsInlineLimit: 0 // Prevents inlining assets, keeping references as-is
    },
    server: {
      host: '0.0.0.0', 
      port: 5173, 
      proxy: {
         '/api': apiProxyTarget
      }
    }
  };
});
