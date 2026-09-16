import type { Theme } from 'vitepress';
import { createVerbbDocsTheme } from '@verbb/vitepress-theme';

const theme: Theme = {
    extends: createVerbbDocsTheme({}),
    enhanceApp({ router }) {
        // The shared theme's home links use client routing, which bypasses the server redirect.
        router.onBeforeRouteChange = async (to) => {
            if (new URL(to, 'http://localhost').pathname === '/') {
                await router.go('/feature-tour/overview');
                return false;
            }
        };
    },
};

export default theme;
