import path from 'node:path';
import { defineConfig } from 'vite';

const sidebarRoot = path.resolve(__dirname, 'src/web/assets/sidebar');

export default defineConfig({
  root: sidebarRoot,
  base: '',
  build: {
    outDir: path.resolve(sidebarRoot, 'dist'),
    emptyOutDir: true,
    sourcemap: true,
    target: 'es2022',
    rollupOptions: {
      input: {
        sidebar: path.resolve(sidebarRoot, 'src/main.js'),
      },
      output: {
        entryFileNames: 'js/[name].js',
        chunkFileNames: 'js/chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name ?? '';

          if (name.endsWith('.css')) {
            return 'css/sidebar.css';
          }

          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
