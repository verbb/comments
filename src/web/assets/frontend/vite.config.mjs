import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const here = dirname(fileURLToPath(import.meta.url));

/**
 * The plugin's own copy of the comments script and stylesheets, built from the workspace's
 * @verbb/comments-browser at the exact version in package.json. The built `dist` is committed so
 * Composer ships it without npm.
 *
 * The script is split three ways: the small startup loader (`js/comments.js`), the renderer it
 * imports when a thread nears view, and the comment forms, imported when someone heads for one.
 * Chunks load from the script's own origin. It's built in library mode, which leaves out Vite's
 * preload helper, so the loader stays small. Editor never enters this graph: the script loads
 * Editor's own module when someone starts writing in a rich editor.
 *
 * `--mode css` builds the stylesheets, in a pass of their own.
 */
export default defineConfig(({ mode }) => (mode === 'css' ? {
    base: './',
    build: {
        outDir: resolve(here, 'dist'),
        emptyOutDir: false,
        cssTarget: ['safari15'],
        rolldownOptions: {
            input: {
                'comments-styles': resolve(here, 'src/css/comments.css'),
                'comments-base': resolve(here, 'src/css/comments-base.css'),
            },
            output: {
                entryFileNames: 'css/[name].js',
                assetFileNames: (asset) => {
                    const name = asset.names?.[0] ?? asset.name ?? '';

                    if (name === 'comments-styles.css') return 'css/comments.css';
                    if (name === 'comments-base.css') return 'css/comments-base.css';

                    return 'assets/[name]-[hash][extname]';
                },
            },
        },
    },
} : {
    base: './',
    build: {
        outDir: resolve(here, 'dist'),
        emptyOutDir: true,
        sourcemap: false,
        minify: true,
        // Safari 15.4 is the oldest supported browser.
        target: ['es2020', 'safari15'],
        // Which chunk is which, for the size check's budgets per piece.
        manifest: 'manifest.json',
        lib: {
            entry: { comments: resolve(here, 'src/js/comments.ts') },
            formats: ['es'],
        },
        rolldownOptions: {
            output: {
                minify: true,
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/chunks/[name]-[hash].js',
            },
        },
    },
}));
