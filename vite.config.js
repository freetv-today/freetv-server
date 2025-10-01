import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';
import { resolve } from 'path';

export default defineConfig({
  plugins: [preact()],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
      '@components': resolve(__dirname, 'src/components'),
      '@pages': resolve(__dirname, 'src/pages'),
      '@context': resolve(__dirname, 'src/context'),
      '@signals': resolve(__dirname, 'src/signals'),
      '@hooks': resolve(__dirname, 'src/hooks'),
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
});