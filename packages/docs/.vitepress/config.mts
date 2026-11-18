import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vitepress';

const pluginDocs = 'https://verbb.io/craft-plugins/comments/docs/v4';

export default defineConfig({
    title: 'Comments Packages',
    description: 'Documentation for the Comments front-end packages: the browser renderer, core, web components, React and Vue.',
    base: '/comments/',
    cleanUrls: true,
    appearance: false,
    lastUpdated: true,
    // The generated reference tables are included into pages, and the ownership list is for maintainers.
    srcExclude: ['_generated/**', '_data/**', 'OWNERSHIP.md', 'README.md'],
    markdown: {
        config(md) {
            // Inline code can hold Twig such as `{{ craft.comments.render(entry) }}`, which Vue would otherwise evaluate.
            const renderInlineCode = md.renderer.rules.code_inline!;

            md.renderer.rules.code_inline = (tokens, index, options, env, self) => {
                return renderInlineCode(tokens, index, options, env, self).replace('<code', '<code v-pre');
            };
        },
    },
    vite: {
        server: {
            port: 5421,
            strictPort: true,
        },
        preview: {
            port: 4421,
            strictPort: true,
        },
        ssr: {
            // The theme imports mark.js source with extensionless imports; bundle it for SSR.
            noExternal: ['@verbb/vitepress-theme', 'mark.js'],
        },
        plugins: [
            tailwindcss(),
        ],
    },
    head: [
        ['link', { rel: 'icon', type: 'image/svg+xml', href: '/comments/icon.svg' }],
    ],
    themeConfig: {
        siteTitle: 'Comments Packages',
        logo: '/icon.svg',
        docsTheme: {
            homeLink: '/comments/',
            primary: '#d98a00',
        },
        nav: [
            { text: 'Browser', link: '/browser/' },
            { text: 'Core', link: '/core/' },
            { text: 'Web Components', link: '/web-components/' },
            { text: 'React', link: '/react/' },
            { text: 'Vue', link: '/vue/' },
            { text: 'Starters', link: '/starters/' },
            { text: 'Plugin Docs', link: `${pluginDocs}/get-started/installation-setup` },
        ],
        sidebar: {
            '/browser/': [
                {
                    text: 'Getting Started',
                    items: [
                        { text: 'Overview', link: '/browser/' },
                        { text: 'Starting and Mounting', link: '/browser/starting-and-mounting' },
                        { text: 'Configuring Threads', link: '/browser/configuring-threads' },
                        { text: 'Lazy Loading', link: '/browser/lazy-loading' },
                        { text: 'Dark Mode', link: '/browser/dark-mode' },
                    ],
                },
                {
                    text: 'Styling and Markup',
                    items: [
                        { text: 'Class Names and CSS Layers', link: '/browser/class-names-css-layers' },
                        { text: 'themeConfig for Controls', link: '/browser/theme-config' },
                        { text: 'Part Overrides', link: '/browser/part-overrides' },
                        { text: 'Field Overrides', link: '/browser/field-overrides' },
                        { text: 'Your Own Interface', link: '/browser/your-own-interface' },
                    ],
                },
                {
                    text: 'Events',
                    items: [
                        { text: 'Browser Events', link: '/browser/browser-events' },
                        { text: 'Validating Before Posting', link: '/browser/validating-before-posting' },
                        { text: 'Changing Data Before It’s Drawn', link: '/browser/changing-data-before-its-drawn' },
                    ],
                },
                {
                    text: 'Your Own Build',
                    items: [
                        { text: 'Bundling in Your Own Build', link: '/browser/bundling-in-your-own-build' },
                        { text: 'Turbo, Sprig and Datastar', link: '/browser/turbo-sprig-datastar' },
                        { text: 'TypeScript', link: '/browser/typescript' },
                    ],
                },
                {
                    text: 'Reference',
                    items: [
                        { text: 'Markup Reference', link: '/browser/markup-reference' },
                    ],
                },
            ],
            '/core/': [
                {
                    text: 'Core',
                    items: [
                        { text: 'Overview', link: '/core/' },
                        { text: 'Client and Transports', link: '/core/client-transports' },
                        { text: 'Signing In', link: '/core/signing-in' },
                        { text: 'The Thread Store', link: '/core/thread-store' },
                        { text: 'Protection', link: '/core/protection' },
                        { text: 'Errors', link: '/core/errors' },
                    ],
                },
            ],
            '/web-components/': [
                {
                    text: 'Getting Started',
                    items: [
                        { text: 'Installation', link: '/web-components/' },
                    ],
                },
                {
                    text: 'The Thread',
                    items: [
                        { text: 'The verbb-comments Element', link: '/web-components/verbb-comments' },
                        { text: 'Events and Validation', link: '/web-components/events' },
                    ],
                },
                {
                    text: 'Look and Feel',
                    items: [
                        { text: 'Styling', link: '/web-components/styling' },
                        { text: 'Part Overrides', link: '/web-components/part-overrides' },
                        { text: 'Field Overrides', link: '/web-components/field-overrides' },
                    ],
                },
                {
                    text: 'Reference',
                    items: [
                        { text: 'TypeScript', link: '/web-components/typescript' },
                    ],
                },
            ],
            '/react/': [
                {
                    text: 'Getting Started',
                    items: [
                        { text: 'Installation', link: '/react/' },
                        { text: 'Next.js', link: '/react/nextjs' },
                    ],
                },
                {
                    text: 'The Thread',
                    items: [
                        { text: 'The CommentsThread Component', link: '/react/comments-thread' },
                        { text: 'Events and Validation', link: '/react/events' },
                    ],
                },
                {
                    text: 'Look and Feel',
                    items: [
                        { text: 'Styling', link: '/react/styling' },
                        { text: 'Part Overrides', link: '/react/part-overrides' },
                        { text: 'Field Overrides', link: '/react/field-overrides' },
                    ],
                },
                {
                    text: 'Your Own Interface',
                    items: [
                        { text: 'Hooks for Your Own UI', link: '/react/hooks' },
                    ],
                },
                {
                    text: 'Reference',
                    items: [
                        { text: 'TypeScript', link: '/react/typescript' },
                    ],
                },
            ],
            '/vue/': [
                {
                    text: 'Getting Started',
                    items: [
                        { text: 'Installation', link: '/vue/' },
                        { text: 'Nuxt', link: '/vue/nuxt' },
                    ],
                },
                {
                    text: 'The Thread',
                    items: [
                        { text: 'The CommentsThread Component', link: '/vue/comments-thread' },
                        { text: 'Events and Validation', link: '/vue/events' },
                    ],
                },
                {
                    text: 'Look and Feel',
                    items: [
                        { text: 'Styling', link: '/vue/styling' },
                        { text: 'Part Overrides', link: '/vue/part-overrides' },
                        { text: 'Field Overrides', link: '/vue/field-overrides' },
                    ],
                },
                {
                    text: 'Your Own Interface',
                    items: [
                        { text: 'Composables for Your Own UI', link: '/vue/composables' },
                    ],
                },
                {
                    text: 'Reference',
                    items: [
                        { text: 'TypeScript', link: '/vue/typescript' },
                    ],
                },
            ],
            '/starters/': [
                {
                    text: 'Starters',
                    items: [
                        { text: 'Overview', link: '/starters/' },
                        { text: 'Craft Starter', link: '/starters/craft' },
                        { text: 'React Starter', link: '/starters/react' },
                        { text: 'Vue Starter', link: '/starters/vue' },
                        { text: 'Web Components Starter', link: '/starters/web' },
                        { text: 'Next.js Starter', link: '/starters/next' },
                        { text: 'Nuxt Starter', link: '/starters/nuxt' },
                    ],
                },
            ],
            '/': [
                {
                    text: 'Packages',
                    items: [
                        { text: 'Start Here', link: '/' },
                        { text: 'Browser', link: '/browser/' },
                        { text: 'Core', link: '/core/' },
                        { text: 'Web Components', link: '/web-components/' },
                        { text: 'React', link: '/react/' },
                        { text: 'Vue', link: '/vue/' },
                        { text: 'Starters', link: '/starters/' },
                    ],
                },
            ],
        },
        socialLinks: [
            { icon: 'github', link: 'https://github.com/verbb/comments' },
        ],
        outline: [2, 3],
        lastUpdatedText: 'Last updated',
        search: {
            provider: 'local',
        },
    },
});
