import { describe, expect, it } from 'vitest';
import * as core from '../src/index';

const sources = import.meta.glob<string>('../src/**/*.ts', { eager: true, query: '?raw', import: 'default' });
const ownFiles = {
    ...sources,
    // LICENSE.md is left out: it's the Verbb licence text, word for word.
    ...import.meta.glob<string>(['./**/*.ts', './fixtures/*.json', '../scripts/*.{mjs,mts}', '../*.json', '../CHANGELOG.md', '../README.md', '../tsconfig*.json', '../vitest.config.ts', '../schema/*.json'], { eager: true, query: '?raw', import: 'default' }),
};

describe('core has no DOM', () => {
    // Core runs in Node, workers and server components, so it never reaches for the page.
    // A global, not a property such as Editor's `content.document` in the JSON.
    const pagePattern = /(?<![.\w'"])\b(document|window|HTMLElement|Element|localStorage|sessionStorage|navigator|location|requestAnimationFrame|IntersectionObserver|MutationObserver|CustomEvent)\b(?!\s*\??:)/;

    it.each(Object.keys(sources))('%s uses no page globals', (path) => {
        const code = sources[path]!.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');

        expect(code.match(pagePattern)?.[0] ?? null).toBeNull();
    });

    it('tells page globals from JSON keys and properties', () => {
        expect(['document.title', 'window.x', 'localStorage.getItem()', 'new CustomEvent()'].map((code) => pagePattern.test(code))).toEqual([true, true, true, true]);
        expect(['content.document', 'document: Record<string, unknown>', 'document?: string', "'document'"].map((code) => pagePattern.test(code))).toEqual([false, false, false, false]);
    });

    it('imports and creates a client in Node', () => {
        const client = core.createCommentsClient({ endpoint: 'https://example.com/actions/comments/api' });

        expect(client.auth.profile).toBe('same-origin-browser');
        expect(typeof document).toBe('undefined');
    });
});

describe('terminology', () => {
    // Front-end code, docs and names never call the script "a runtime" or its data "a contract",
    // matching the plugin's own guard for src, docs, README.md and marketing.
    it.each(Object.keys(ownFiles))('%s avoids both words', (path) => {
        const offences = ownFiles[path]!.match(/runtime|Runtime|RUNTIME|contract|Contract|CONTRACT/g) ?? [];

        // This file has to name them to look for them.
        expect(path.endsWith('guards.test.ts') ? [] : offences).toEqual([]);
    });
});
