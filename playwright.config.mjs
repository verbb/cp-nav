import { defineConfig } from '@playwright/test';
export default defineConfig({
  testDir: './tests/Browser',
  workers: 1,
  globalTimeout: 300000,
  timeout: 60000,
  use: { baseURL: 'https://cp-nav-react-tests.ddev.site', ignoreHTTPSErrors: true, trace: 'retain-on-failure', screenshot: 'only-on-failure' },
  reporter: [['list'], ['html', { open: 'never' }]],
});
