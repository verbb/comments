import { describe, expect, it, vi } from 'vitest';

const register = vi.fn();
vi.mock('@verbb/editor', () => ({ registerVerbbEditorElement: register }));

// LICENSE.md is left out: it's the Verbb licence text, word for word.
const sources = import.meta.glob<string>(['../src/**/*.ts', '../scripts/*.mjs', '../*.json', '../README.md', '../CHANGELOG.md', './**/*.ts', './browser/*.{js,mjs}'], { eager: true, query: '?raw', import: 'default' });
const code = (path: string) => sources[path]!.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');

describe('the package', () => {
    it('starts nothing when imported', async () => {
        const module = await import('../src/index');

        expect(module.CommentsThread.name).toBe('CommentsThread');
        expect((globalThis as Record<symbol, unknown>)[Symbol.for('verbb.comments')]).toBeUndefined();
        expect(document.querySelector('[data-comments-root]')).toBeNull();
    });

    it('loads Editor and the renderer only with import(), so a bundler splits them off', () => {
        for (const path of Object.keys(sources).filter((name) => name.startsWith('../src/'))) {
            expect(code(path), path).not.toMatch(/from\s+['"]@verbb\/editor|from\s+['"]@verbb\/comments-browser\/renderer/);
        }

        expect(code('../src/plugin.ts')).toMatch(/import\(\s*'@verbb\/editor'\s*\)/);
    });
});

describe('Editor from npm', () => {
    it('is registered by the default loader, since importing it registers nothing', async () => {
        const { loadEditorFromPackage } = await import('../src/plugin');

        await loadEditorFromPackage();
        expect(register).toHaveBeenCalledTimes(1);
    });
});

describe('terminology', () => {
    // Front-end code, docs and names never call the script "a runtime" or its data "a contract".
    it.each(Object.keys(sources))('%s avoids both words', (path) => {
        const offences = sources[path]!.match(/runtime|Runtime|RUNTIME|contract|Contract|CONTRACT/g) ?? [];

        expect(path.endsWith('package.test.ts') ? [] : offences).toEqual([]);
    });
});
