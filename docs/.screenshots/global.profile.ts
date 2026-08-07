import { registerGlobalProfile } from '@verbb/docs-screenshots/api';

export default registerGlobalProfile({
    id: 'default',
    siteName: 'Verbb Docs Screenshots',
    adminPath: 'admin',
    language: 'en-US',
    locale: 'en-AU',
    timezone: 'Australia/Melbourne',
    colorScheme: 'light',
    viewport: {
        width: 1600,
        height: 1200,
        deviceScaleFactor: 2,
    },
    admin: {
        username: 'admin',
        password: 'password123',
        email: 'admin@example.test',
    },
});
