import path from 'node:path';
import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import tailwindShadowDom from 'vite-plugin-tailwind-shadowdom';

const builderRoot = path.resolve(__dirname, 'src/web/assets/builder');

const parseServerPort = (value: string | undefined, fallback: number): number => {
  const port = Number.parseInt(value || '', 10);

  return Number.isInteger(port) ? port : fallback;
};

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, __dirname, '');
  const devServerPublicUrl = (env.CPNAV_CP_DEV_SERVER_PUBLIC || 'http://localhost:4021/').replace(/\/$/, '');
  const devServerHost = env.CPNAV_CP_DEV_SERVER_HOST || 'localhost';
  const devServerPort = parseServerPort(env.CPNAV_CP_DEV_SERVER_PORT, 4021);
  const hmrProtocol = env.CPNAV_CP_DEV_SERVER_HMR_PROTOCOL || 'ws';

  return {
    root: builderRoot,
    // CP bundles publish under Craft cpresources; relative base keeps lazy chunks beside entries.
    base: '',
    plugins: [react(), tailwindcss(), tailwindShadowDom()],
    resolve: {
      // Dedupe React/Lit/kit so icon registry + CE classes stay single-instance.
      dedupe: [
        'react',
        'react-dom',
        '@lit/react',
        '@lit/reactive-element',
        'lit',
        'lit-element',
        'lit-html',
        // One registry module so registerIcon() is visible to <pk-icon>/getIcon().
        '@verbb/plugin-kit-icons',
        '@verbb/plugin-kit-web',
        '@verbb/plugin-kit-react',
      ],
    },
    // Optional plugin-local HMR — Craft must set CPNAV_USE_VITE_DEV_SERVER=true.
    server: {
      origin: devServerPublicUrl,
      host: devServerHost,
      port: devServerPort,
      strictPort: true,
      cors: true,
      hmr: {
        protocol: hmrProtocol,
      },
    },
    build: {
      outDir: path.resolve(builderRoot, 'dist'),
      emptyOutDir: true,
      manifest: 'manifest.json',
      sourcemap: true,
      target: 'es2022',
      rollupOptions: {
        input: {
          builder: path.resolve(builderRoot, 'src/main.tsx'),
        },
      },
    },
  };
});
