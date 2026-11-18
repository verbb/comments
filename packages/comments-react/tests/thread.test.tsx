import { act, cleanup, render } from '@testing-library/react';
import { StrictMode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { CommentsProvider, CommentsThread, type CommentsThreadProps } from '../src/index';
import { ENDPOINT, fakeApi, until } from './support/api';

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
});

function setup(props: Partial<CommentsThreadProps> = {}, api = fakeApi()) {
    const ui = (extra: Partial<CommentsThreadProps> = {}) => (
        <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
            <CommentsThread ownerId={109} siteId={1} loadMode="eager" {...props} {...extra} />
        </CommentsProvider>
    );
    const result = render(ui());

    return { ...result, api, rerenderWith: (extra: Partial<CommentsThreadProps>) => result.rerender(ui(extra)) };
}

const ready = (container: HTMLElement) => until(() => container.querySelector('[data-comments-root][data-comments-phase="ready"]'));
// The three root comments and the reply previewed under the second.
const comments = (container: HTMLElement) => container.querySelectorAll('[data-comments-root] article[id^="comment-"]');

describe('<CommentsThread>', () => {
    it('renders the mount point, then the renderer draws the thread inside it', async () => {
        const { container, api } = setup({ count: 3 });
        const root = container.querySelector<HTMLElement>('section[data-comments-root]')!;

        expect(root.id).toBe('comments-109-1');
        expect(root.dataset.commentsAutoMount).toBe('false');
        expect(root.className).toContain('comments--reserve-2');

        await ready(container);

        expect(comments(container)).toHaveLength(4);
        expect(api.of('thread')).toHaveLength(1);
        expect(api.of('thread')[0]!.url.searchParams.get('ownerId')).toBe('109');
        expect(root.dataset.commentsJs).toBe('1');
    });

    it('calls an event prop for every browser event, with the event itself', async () => {
        const onMounted = vi.fn();
        const onRootsLoaded = vi.fn();
        const onVoteChanged = vi.fn();
        const onDestroyed = vi.fn();
        const { container, unmount } = setup({ onMounted, onRootsLoaded, onVoteChanged, onDestroyed });

        await ready(container);

        expect(onMounted).toHaveBeenCalledTimes(1);
        expect(onMounted.mock.calls[0]![0]).toBeInstanceOf(CustomEvent);
        expect(onMounted.mock.calls[0]![0].detail.instanceId).toBe('comments-109-1');
        await until(() => onRootsLoaded.mock.calls.length === 1);

        const like = container.querySelector<HTMLButtonElement>('[data-comments-action="vote"]')!;
        await act(async () => like.click());
        await until(() => onVoteChanged.mock.calls.length === 1);
        expect(onVoteChanged.mock.calls[0]![0].detail.vote).toBe('upvote');

        unmount();
        expect(onDestroyed).toHaveBeenCalledTimes(1);
    });

    it('uses the latest event prop without starting the thread again', async () => {
        const first = vi.fn();
        const second = vi.fn();
        const { container, rerenderWith, api } = setup({ onVoteChanged: first });

        await ready(container);
        rerenderWith({ onVoteChanged: second });
        await act(async () => container.querySelector<HTMLButtonElement>('[data-comments-action="vote"]')!.click());
        await until(() => second.mock.calls.length === 1);

        expect(first).not.toHaveBeenCalled();
        expect(api.of('thread')).toHaveLength(1);
    });

    it('starts one thread under StrictMode, and stops it on unmount', async () => {
        const api = fakeApi();
        const onMounted = vi.fn();
        const onDestroyed = vi.fn();
        const { container, unmount } = render(
            <StrictMode>
                <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch}>
                    <CommentsThread ownerId={109} siteId={1} loadMode="eager" onMounted={onMounted} onDestroyed={onDestroyed} />
                </CommentsProvider>
            </StrictMode>,
        );

        await ready(container);
        await new Promise((resolve) => setTimeout(resolve, 50));

        expect(onMounted).toHaveBeenCalledTimes(1);
        expect(api.of('thread')).toHaveLength(1);
        expect(comments(container)).toHaveLength(4);

        unmount();
        expect(onDestroyed).toHaveBeenCalledTimes(1);
    });

    it('starts a new thread when ownerUri changes, and stops the old one', async () => {
        const onDestroyed = vi.fn();
        const { container, rerenderWith, api } = setup({ ownerId: undefined, ownerUri: 'blog/first', onDestroyed });

        await ready(container);
        const first = container.querySelector('section[data-comments-root]');

        rerenderWith({ ownerUri: 'blog/second' });
        await until(() => api.of('thread').length === 2);
        await ready(container);

        const second = container.querySelector('section[data-comments-root]');
        expect(second).not.toBe(first);
        expect(onDestroyed).toHaveBeenCalledTimes(1);
        expect(api.of('thread').map((request) => request.url.searchParams.get('ownerUri'))).toEqual(['blog/first', 'blog/second']);
        expect(container.querySelectorAll('section[data-comments-root]')).toHaveLength(1);
        expect(comments(container)).toHaveLength(4);
    });

    it('switches the theme in place, and redraws with new controls and labels', async () => {
        const { container, rerenderWith, api } = setup({ theme: 'light' });

        await ready(container);
        const root = container.querySelector<HTMLElement>('section[data-comments-root]')!;

        rerenderWith({ theme: 'dark', themeConfig: { quietButton: { attributes: { class: 'btn-ghost' } } }, labels: { reply: 'Answer' } });
        expect(root.dataset.commentsTheme).toBe('dark');
        await until(() => container.querySelector('[data-comments-action="reply"].btn-ghost'));
        expect(container.querySelector('[data-comments-action="reply"]')!.textContent).toContain('Answer');

        rerenderWith({ theme: 'dark' });
        await until(() => !container.querySelector('.btn-ghost'));
        expect(api.of('thread')).toHaveLength(1);
        expect(container.querySelector('section[data-comments-root]')).toBe(root);
    });

    it('merges the provider’s themeConfig per control under the thread’s own', async () => {
        const api = fakeApi();
        const { container } = render(
            <CommentsProvider endpoint={ENDPOINT} fetch={api.fetch} themeConfig={{ quietButton: { attributes: { class: 'from-provider', title: 'Provider' } } }}>
                <CommentsThread ownerId={109} loadMode="eager" themeConfig={{ quietButton: { attributes: { title: 'Thread' } } }} />
            </CommentsProvider>,
        );

        await ready(container);
        const reply = container.querySelector('[data-comments-action="reply"]')!;

        expect(reply.classList.contains('from-provider')).toBe(true);
        expect(reply.getAttribute('title')).toBe('Thread');
    });

    it('re-sorts a loaded thread in place for a new sort, keeping its mount point and controller', async () => {
        const onDestroyed = vi.fn();
        const { container, rerenderWith, api } = setup({ sort: 'newest', onDestroyed });

        await ready(container);
        const root = container.querySelector<HTMLElement>('section[data-comments-root]')!;

        rerenderWith({ sort: 'oldest' });
        await until(() => api.of('thread').length === 2);
        await ready(container);

        expect(api.of('thread').map((request) => request.url.searchParams.get('sort'))).toEqual(['newest', 'oldest']);
        expect(container.querySelector('section[data-comments-root]')).toBe(root);
        expect(onDestroyed).not.toHaveBeenCalled();
    });

    it('starts a loaded thread again, with the default sort, when its sort is taken away', async () => {
        const onDestroyed = vi.fn();
        const { container, rerenderWith, api } = setup({ sort: 'oldest', onDestroyed });

        await ready(container);
        rerenderWith({ sort: undefined });
        await until(() => api.of('thread').length === 2);
        await ready(container);

        expect(onDestroyed).toHaveBeenCalledTimes(1);
        expect(api.of('thread').map((request) => request.url.searchParams.get('sort'))).toEqual(['oldest', 'newest']);
    });

    it('starts a waiting thread again, sorted, for a new sort', async () => {
        const onDestroyed = vi.fn();
        const { container, rerenderWith, api } = setup({ loadMode: 'button', sort: 'newest', onDestroyed });

        await until(() => container.querySelector('[data-comments-action="show-comments"]'));
        rerenderWith({ sort: 'oldest' });
        await until(() => onDestroyed.mock.calls.length === 1);
        const button = await until(() => container.querySelector<HTMLButtonElement>('[data-comments-action="show-comments"]'));

        expect(api.requests).toHaveLength(0);
        await act(async () => button.click());
        await ready(container);
        expect(api.of('thread').map((request) => request.url.searchParams.get('sort'))).toEqual(['oldest']);
    });

    it('starts a new thread for a new limit, and reads loadMode only when the thread starts', async () => {
        const onDestroyed = vi.fn();
        const { container, rerenderWith, api } = setup({ limit: 10, onDestroyed });

        await ready(container);
        const root = container.querySelector<HTMLElement>('section[data-comments-root]')!;

        rerenderWith({ limit: 10, loadMode: 'button' });
        await new Promise((resolve) => setTimeout(resolve, 50));
        expect(container.querySelector('section[data-comments-root]')).toBe(root);
        expect(root.dataset.commentsLoadMode).toBe('eager');
        expect(onDestroyed).not.toHaveBeenCalled();

        rerenderWith({ limit: 20 });
        await until(() => api.of('thread').length === 2);
        await ready(container);
        expect(onDestroyed).toHaveBeenCalledTimes(1);
        expect(api.of('thread').map((request) => request.url.searchParams.get('limit'))).toEqual(['10', '20']);
    });

    it('waits for Show comments with loadMode="button"', async () => {
        const { container, api } = setup({ loadMode: 'button', count: 3 });

        const button = await until(() => container.querySelector<HTMLButtonElement>('[data-comments-action="show-comments"]'));
        expect(api.requests).toHaveLength(0);

        await act(async () => button.click());
        await ready(container);
        expect(api.of('thread')).toHaveLength(1);
    });
});

describe('beside the plugin’s own script', () => {
    it('isn’t mounted twice when the page also runs the bundled loader', async () => {
        const browser = await import('@verbb/comments-browser');
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        const app = browser.startComments({ observe: true });
        const { container, api } = setup();

        await ready(container);
        await new Promise((resolve) => setTimeout(resolve, 50));

        // Started once, by the component with its own settings (the loader would have used the page's
        // endpoint and the global fetch), and the guard says to turn the bundled script off.
        expect(app).toBe((globalThis as { Comments?: unknown }).Comments);
        expect(api.of('thread')).toHaveLength(1);
        expect(comments(container)).toHaveLength(4);
        expect(warn.mock.calls.some(([message]) => String(message).includes('Load Bundled JavaScript'))).toBe(true);
    });
});
