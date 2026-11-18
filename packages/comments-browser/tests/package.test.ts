import { describe, expect, it } from 'vitest';
import { escapeHtml, format, renderToString, resolveLabels } from '../src/render';
import { html, nothing } from 'lit-html';
import { repeat } from 'lit-html/directives/repeat.js';
import { attributes, richHtml } from '../src/directives';

// LICENSE.md is left out: it's the Verbb licence text, word for word.
const sources = import.meta.glob<string>(['../src/**/*.{ts,css}', '../scripts/*.mjs', '../*.json', '../README.md', '../CHANGELOG.md', './**/*.ts'], { eager: true, query: '?raw', import: 'default' });

describe('importing the package', () => {
    it('starts nothing and touches no page', async () => {
        const module = await import('../src/index');

        expect(typeof module.startComments).toBe('function');
        expect((globalThis as Record<symbol, unknown>)[Symbol.for('verbb.comments')]).toBeUndefined();
        expect((globalThis as { Comments?: unknown }).Comments).toBeUndefined();
        expect(typeof document).toBe('undefined');
    });

    // Editor's GIF search, `@verbb/editor/gifs`, stands alone without Tiptap, so the plain form's
    // GIF picker may use it.
    it('never imports Editor, which loads only when someone starts writing', () => {
        for (const [path, code] of Object.entries(sources).filter(([path]) => path.startsWith('../src/'))) {
            const withoutComments = code.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, '');
            expect(withoutComments, path).not.toMatch(/(?:from\s+|import\(\s*)['"]@verbb\/editor(?!\/gifs['"])/);
        }
    });
});

describe('renderToString', () => {
    it('escapes text and attributes, and leaves out property and event bindings', () => {
        const value = '"<b>&\'';
        const out = renderToString(html`<p title=${value} class="a ${value}" .value=${'x'} @click=${() => 0} ?hidden=${true} ?open=${false} data-x=${nothing}>${value}</p>`);

        expect(out).toBe('<p title="&quot;&lt;b&gt;&amp;&#39;" class="a &quot;&lt;b&gt;&amp;&#39;" hidden>&quot;&lt;b&gt;&amp;&#39;</p>');
    });

    it('draws nested templates, lists, repeat() and Comments’ directives', () => {
        const out = renderToString(html`<ul ${attributes({ class: 'list', hidden: true })}>${repeat(['a', 'b'], (item) => item, (item) => html`<li>${item}</li>`)}${[html`<li>c</li>`, nothing, null]}</ul><div class="rich" ${richHtml('<p>Safe</p>')}></div>`);

        expect(out).toBe('<ul class="list" hidden><li>a</li><li>b</li><li>c</li></ul><div class="rich"><p>Safe</p></div>');
    });

    it('escapes the five characters that matter', () => {
        expect(escapeHtml('<a href="x">\'&')).toBe('&lt;a href=&quot;x&quot;&gt;&#39;&amp;');
    });
});

describe('labels', () => {
    it('take the site’s words over the fallbacks, and fill placeholders', () => {
        const labels = resolveLabels({ loading: 'Chargement…' }, { reply: 'Répondre', empty: '' });

        expect(labels.loading).toBe('Chargement…');
        expect(labels.reply).toBe('Répondre');
        expect(labels.comments).toBe('Comments');
        expect(format('{count} of {limit} and {other}', { count: 3, limit: 5 })).toBe('3 of 5 and {other}');
    });
});

describe('terminology', () => {
    // Front-end code, docs and names never call the script "a runtime" or its data "a contract".
    it.each(Object.keys(sources))('%s avoids both words', (path) => {
        const offences = sources[path]!.match(/runtime|Runtime|RUNTIME|contract|Contract|CONTRACT/g) ?? [];

        expect(path.endsWith('package.test.ts') ? [] : offences).toEqual([]);
    });
});
