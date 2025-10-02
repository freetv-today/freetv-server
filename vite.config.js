import { defineConfig, loadEnv } from 'vite';
import preact from '@preact/preset-vite';
import { resolve } from 'path';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig(({ mode }) => {
  // Load environment variables
  const env = loadEnv(mode, '.', '');
  
  // Use environment-based base path
  const base = env.VITE_BASE_PATH || (mode === 'production' ? '/admin/' : '/');
  
  return {
    plugins: [preact()],
    base: base,
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
        '/api': 'http://localhost:8000'
      }
    },
    // Tell Vite to not process these asset references
    define: {
      __SUPPRESS_ASSET_WARNINGS__: true
    }
  };
});