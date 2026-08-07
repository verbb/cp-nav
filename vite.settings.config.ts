import path from 'node:path';
import { defineConfig } from 'vite';

const settingsRoot = path.resolve(__dirname, 'src/web/assets/settings');

export default defineConfig({
  root: settingsRoot,
  base: '',
  build: {
    outDir: path.resolve(settingsRoot, 'dist'),
    emptyOutDir: true,
    sourcemap: true,
    target: 'es2022',
    rollupOptions: {
      input: {
        settings: path.resolve(settingsRoot, 'src/main.js'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name ?? '';

          if (name.endsWith('.css')) {
            return 'css/settings.css';
          }

          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
