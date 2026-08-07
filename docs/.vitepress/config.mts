import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vitepress';

type SidebarEntry = string | SidebarGroup;

type SidebarGroup = {
    title: string;
    path?: string;
    collapsable?: boolean;
    children: SidebarEntry[];
};

const docsRoot = fileURLToPath(new URL('..', import.meta.url));
const sidebarConfig = JSON.parse(fs.readFileSync(path.join(docsRoot, '.sidebar.json'), 'utf8')) as SidebarGroup[];

const h1Regex = /^#\s+(.+)$/m;
const zeroWidthRegex = /[\u200B-\u200D\uFEFF]/g;

const getDocPath = (entryPath: string): string => {
    return path.join(docsRoot, `${entryPath}.md`);
};

const getDocLink = (entryPath: string): string => {
    const normalizedPath = entryPath.replace(/\\/g, '/').replace(/\.md$/, '');

    if (normalizedPath.endsWith('/index')) {
        return `/${normalizedPath.slice(0, -'/index'.length)}/`;
    }

    return `/${normalizedPath}`;
};

const getFallbackTitle = (entryPath: string): string => {
    return entryPath
        .split('/')
        .pop()!
        .replace(/-/g, ' ')
        .replace(/\b\w/g, char => char.toUpperCase());
};

const getDocTitle = (entryPath: string): string => {
    const filePath = getDocPath(entryPath);

    if (!fs.existsSync(filePath)) {
        return getFallbackTitle(entryPath);
    }

    const content = fs.readFileSync(filePath, 'utf8');
    const match = content.match(h1Regex);

    if (!match) {
        return getFallbackTitle(entryPath);
    }

    return match[1].replace(zeroWidthRegex, '').trim();
};

const transformSidebarEntry = (entry: SidebarEntry) => {
    if (typeof entry === 'string') {
        return {
            text: getDocTitle(entry),
            link: getDocLink(entry),
        };
    }

    const item = {
        text: entry.title,
        collapsed: entry.collapsable ?? false,
        items: entry.children.map(transformSidebarEntry),
    };

    if (entry.path) {
        return {
            ...item,
            link: getDocLink(entry.path),
        };
    }

    return item;
};

export default defineConfig({
    title: 'Control Panel Nav',
    description: 'Local preview for the main Control Panel Nav plugin docs.',
    cleanUrls: true,
    appearance: false,
    lastUpdated: true,
    vite: {
        ssr: {
            noExternal: ['@verbb/vitepress-theme'],
        },
        plugins: [
            tailwindcss(),
        ],
        server: {
            port: 5490,
            strictPort: true,
        },
    },
    themeConfig: {
        siteTitle: 'Control Panel Nav',
        sidebar: sidebarConfig.map(transformSidebarEntry),
        search: {
            provider: 'local',
        },
        outline: [2, 3],
        socialLinks: [
            { icon: 'github', link: 'https://github.com/verbb/cp-nav' },
        ],
    },
});
