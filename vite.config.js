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
  server: {
    proxy: {
      '/api': 'http://localhost:8000'
    }
  }
});