// @vitest-environment node
import { renderToString } from '@vue/server-renderer';
import { describe, expect, it } from 'vitest';
import { createSSRApp, defineComponent, h } from 'vue';
import { CommentsPlugin, CommentsThread, templatePart, useCommentsThread } from '../src/index';

const Custom = defineComponent({
    setup() {
        const thread = useCommentsThread({ ownerId: 109 });
        return () => h('p', { 'data-status': thread.state.value.status }, String(thread.roots.value.length));
    },
});

describe('a server render', () => {
    it('outputs only the mount point: the reserved space, the heading and the count', async () => {
        const app = createSSRApp(() => h(CommentsThread, { ownerId: 109, siteId: 1, count: 8, loadMode: 'button', parts: { badge: () => null, authorName: templatePart(() => 'x') } }));
        app.use(CommentsPlugin, { endpoint: 'https://cms.example.com/actions/comments/api', labels: { comments: 'Kōrero' } });
        const html = await renderToString(app);

        expect(typeof document).toBe('undefined');
        expect(html).toBe(
            '<!--[--><section id="comments-109-1" class="comments comments--reserve-4" aria-labelledby="comments-109-1-heading" data-comments-root data-comments-api-version="1" data-comments-auto-mount="false" data-comments-adapter="vue"'
            + ' data-comments-endpoint="https://cms.example.com/actions/comments/api" data-comments-owner-id="109" data-comments-site-id="1" data-comments-load-mode="button" data-comments-theme="light" data-comments-count="8"'
            + ' data-comments-labels="{&quot;comments&quot;:&quot;Kōrero&quot;}">'
            + '<h2 id="comments-109-1-heading" class="comments__heading" tabindex="-1">Kōrero <span class="comments__count"><span class="comments__visually-hidden">: </span>8</span></h2>'
            + '<noscript><p class="comments__noscript">Turn on JavaScript to read and post comments.</p></noscript></section><!--]-->',
        );
    });

    it('escapes labels and gives a thread by URI an ID of its own', async () => {
        const html = await renderToString(createSSRApp(() => h(CommentsThread, { ownerUri: 'blog/hello', labels: { comments: '<b>Talk</b>' } })));

        expect(html).toMatch(/<section id="comments-[\w-]+" /);
        expect(html).toContain('&#60;b&#62;Talk&#60;/b&#62;');
        expect(html).not.toContain('<b>');
        expect(html).not.toContain('data-comments-owner-id');
    });

    it('gives useCommentsThread() the empty state, and fetches nothing', async () => {
        expect(await renderToString(createSSRApp(Custom))).toBe('<p data-status="idle">0</p>');
    });
});

describe('structured data on the server', () => {
    it('is written with jsonLdScript(), so a comment can’t end the script', async () => {
        const { fetchStructuredData, jsonLdScript } = await import('../src/server');
        const fetch = async () => new Response(JSON.stringify({ apiVersion: 1, status: 'ok', structuredData: { '@type': 'DiscussionForumPosting', comment: [{ '@type': 'Comment', text: 'nice </script><script>alert(1)</script> & \u2028' }] } }), { headers: { 'Content-Type': 'application/json', 'X-Comments-Api-Version': '1' } });
        const jsonLd = await fetchStructuredData({ endpoint: 'https://cms.example.com/actions/comments/api', ownerUri: 'blog/x', fetch: fetch as typeof globalThis.fetch });
        const html = await renderToString(createSSRApp(() => h('script', { type: 'application/ld+json', innerHTML: jsonLdScript(jsonLd) })));

        expect(html.match(/<\/script>/g)).toHaveLength(1);
        expect(html).not.toContain('<script>alert');
        expect(html).toContain('\\u003c/script\\u003e');
        expect(JSON.parse(html.replace(/^<script[^>]*>|<\/script>$/g, ''))).toEqual(jsonLd);
    });
});
