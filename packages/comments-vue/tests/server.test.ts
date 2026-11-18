// @vitest-environment node
import { execFileSync } from 'node:child_process';
import { describe, expect, it } from 'vitest';
import { fetchCommentsCount, fetchStructuredData } from '../src/server';
import serverSource from '../src/server.ts?raw';
import { ENDPOINT, fakeApi } from './support/api';

describe('the server helpers', () => {
    it('read the count and the structured data from the public reads', async () => {
        const api = fakeApi();

        expect(await fetchCommentsCount({ endpoint: ENDPOINT, ownerUri: 'blog/hello', fetch: api.fetch })).toBe(3);
        expect(await fetchStructuredData({ endpoint: ENDPOINT, ownerId: 109, siteId: 1, limit: 5, fetch: api.fetch })).toMatchObject({ '@type': 'DiscussionForumPosting' });

        const [thread] = api.of('thread');
        const [structured] = api.of('structured-data');
        expect(thread!.url.searchParams.get('ownerUri')).toBe('blog/hello');
        // The default page size, which Craft and a CDN cache.
        expect(thread!.url.searchParams.has('limit')).toBe(false);
        expect(structured!.url.searchParams.get('limit')).toBe('5');
        expect(structured!.url.searchParams.get('siteId')).toBe('1');
    });

    it('need an absolute endpoint and an owner', async () => {
        await expect(fetchCommentsCount({ endpoint: '/actions/comments/api', ownerId: 1 })).rejects.toThrow(/absolute URL/);
        await expect(fetchStructuredData({ endpoint: ENDPOINT })).rejects.toThrow(/ownerId/);
    });

    it('import only @verbb/comments-core', () => {
        const imports = [...serverSource.matchAll(/^import[^'"]*['"]([^'"]+)['"]/gm)].map((match) => match[1]);

        expect(imports).toEqual(['@verbb/comments-core']);
    });

    it('run in plain Node, where touching the page would throw', () => {
        const file = new URL('../src/server.ts', import.meta.url).href;
        const script = `
            for (const name of ['window', 'document', 'navigator', 'HTMLElement', 'customElements', 'localStorage', 'sessionStorage', 'requestAnimationFrame', 'IntersectionObserver', 'MutationObserver']) {
                Object.defineProperty(globalThis, name, { configurable: true, get() { throw new Error('touched ' + name); } });
            }
            const helpers = await import(${JSON.stringify(file)});
            const answer = (body) => new Response(JSON.stringify({ apiVersion: 1, status: 'ok', ...body }), { headers: { 'Content-Type': 'application/json', 'X-Comments-Api-Version': '1' } });
            const fetch = async (url) => String(url).includes('/thread') ? answer({ conversation: { commentCount: 42 } }) : answer({ structuredData: { '@type': 'DiscussionForumPosting' } });
            const count = await helpers.fetchCommentsCount({ endpoint: 'https://cms.example.com/actions/comments/api', ownerId: 7, fetch });
            const data = await helpers.fetchStructuredData({ endpoint: 'https://cms.example.com/actions/comments/api', ownerId: 7, fetch });
            console.log(JSON.stringify({ exports: Object.keys(helpers).sort(), count, type: data['@type'] }));
        `;
        const output = execFileSync(process.execPath, ['--experimental-strip-types', '--no-warnings', '--input-type=module', '-e', script], { encoding: 'utf8' });

        expect(JSON.parse(output)).toEqual({ exports: ['fetchCommentsCount', 'fetchStructuredData', 'jsonLdScript'], count: 42, type: 'DiscussionForumPosting' });
    });
});
