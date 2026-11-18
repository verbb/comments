import { act } from '@testing-library/react';
import { hydrateRoot } from 'react-dom/client';
import { renderToString } from 'react-dom/server';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CommentsProvider, CommentsThread } from '../src/index';
import { ENDPOINT, fakeApi, until } from './support/api';

afterEach(() => {
    document.body.replaceChildren();
    vi.restoreAllMocks();
});

describe('hydrating a server render', () => {
    it('matches without a warning, then draws the thread', async () => {
        const api = fakeApi();
        const app = (
            <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                <main>
                    <h1>Post</h1>
                    <CommentsThread ownerUri="blog/hello" count={3} loadMode="eager" />
                    <CommentsThread ownerId={109} siteId={1} count={3} loadMode="eager" />
                </main>
            </CommentsProvider>
        );
        const container = document.createElement('div');
        container.innerHTML = renderToString(app);
        document.body.append(container);
        const serverIds = Array.from(container.querySelectorAll('section'), (section) => section.id);
        const errors = vi.spyOn(console, 'error');
        const recoverable = vi.fn();

        const root = await act(async () => hydrateRoot(container, app, { onRecoverableError: recoverable }));

        expect(errors).not.toHaveBeenCalled();
        expect(recoverable).not.toHaveBeenCalled();
        expect(Array.from(container.querySelectorAll('section'), (section) => section.id)).toEqual(serverIds);

        await until(() => container.querySelectorAll('[data-comments-phase="ready"]').length === 2);
        expect(api.of('thread')).toHaveLength(2);

        await act(async () => root.unmount());
    });
});
