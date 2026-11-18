import { renderToString } from '@vue/server-renderer';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createSSRApp, h } from 'vue';
import { CommentsPlugin, CommentsThread } from '../src/index';
import { ENDPOINT, fakeApi, until } from './support/api';

afterEach(() => {
    document.body.replaceChildren();
    vi.restoreAllMocks();
});

describe('hydrating a server render', () => {
    it('matches without a warning, then draws the thread', async () => {
        const api = fakeApi();
        const app = () => {
            const created = createSSRApp(() => h('main', [
                h('h1', 'Post'),
                h(CommentsThread, { ownerUri: 'blog/hello', count: 3, loadMode: 'eager' }),
                h(CommentsThread, { ownerId: 109, siteId: 1, count: 3, loadMode: 'eager' }),
            ]));
            created.use(CommentsPlugin, { endpoint: ENDPOINT, fetch: api.fetch });
            return created;
        };
        const container = document.createElement('div');
        container.innerHTML = await renderToString(app());
        document.body.append(container);
        const serverIds = Array.from(container.querySelectorAll('section'), (section) => section.id);
        const warn = vi.spyOn(console, 'warn');
        const error = vi.spyOn(console, 'error');

        const client = app();
        client.mount(container);

        expect(warn).not.toHaveBeenCalled();
        expect(error).not.toHaveBeenCalled();
        expect(Array.from(container.querySelectorAll('section'), (section) => section.id)).toEqual(serverIds);

        await until(() => container.querySelectorAll('[data-comments-phase="ready"]').length === 2);
        expect(api.of('thread')).toHaveLength(2);

        client.unmount();
    });
});
