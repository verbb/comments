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
    title: 'Comments',
    description: 'Local preview for the Comments plugin docs.',
    cleanUrls: true,
    appearance: false,
    lastUpdated: true,
    markdown: {
        config(md) {
            // Inline code often holds Twig such as `{{ siteName }}`, which Vue would otherwise try to evaluate.
            const renderInlineCode = md.renderer.rules.code_inline!;

            md.renderer.rules.code_inline = (tokens, index, options, env, self) => {
                return renderInlineCode(tokens, index, options, env, self).replace('<code', '<code v-pre');
            };
        },
    },
    vite: {
        ssr: {
            // The theme imports mark.js source with extensionless imports; bundle it for SSR.
            noExternal: ['@verbb/vitepress-theme', 'mark.js'],
        },
        plugins: [
            tailwindcss(),
        ],
        server: {
            port: 5420,
            strictPort: true,
        },
    },
    themeConfig: {
        siteTitle: 'Comments',
        sidebar: sidebarConfig.map(transformSidebarEntry),
        search: {
            provider: 'local',
        },
        outline: [2, 3],
        socialLinks: [
            { icon: 'github', link: 'https://github.com/verbb/comments' },
        ],
    },
});
