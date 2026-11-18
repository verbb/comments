import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { CommentsPlugin, CommentsThread, type CommentsProviderSettings } from '../src/index';
import { ENDPOINT, fakeApi, until } from './support/api';

const mounted: Array<{ unmount(): void }> = [];

afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.replaceChildren();
    vi.restoreAllMocks();
});

function setup(props: Record<string, unknown> = {}, api = fakeApi(), settings: CommentsProviderSettings = {}) {
    const wrapper = mount(CommentsThread, {
        props: { ownerId: 109, siteId: 1, loadMode: 'eager', ...props },
        attachTo: document.body,
        global: { plugins: [[CommentsPlugin, { endpoint: ENDPOINT, fetch: api.fetch, ...settings }]] },
    });
    mounted.push(wrapper);

    return { wrapper, api, container: wrapper.element.parentElement as HTMLElement };
}

const ready = (container: HTMLElement) => until(() => container.querySelector('[data-comments-root][data-comments-phase="ready"]'));
// The three root comments and the reply previewed under the second.
const comments = (container: HTMLElement) => container.querySelectorAll('[data-comments-root] article[id^="comment-"]');

describe('<CommentsThread>', () => {
    it('renders the mount point, then the renderer draws the thread inside it', async () => {
        const { container, api } = setup({ count: 3, class: 'my-thread' });
        const root = container.querySelector<HTMLElement>('section[data-comments-root]')!;

        expect(root.id).toBe('comments-109-1');
        expect(root.dataset.commentsAutoMount).toBe('false');
        expect(root.className).toContain('comments--reserve-2');
        expect(root.classList.contains('my-thread')).toBe(true);

        await ready(container);

        expect(comments(container)).toHaveLength(4);
        expect(api.of('thread')).toHaveLength(1);
        expect(api.of('thread')[0]!.url.searchParams.get('ownerId')).toBe('109');
        expect(root.dataset.commentsJs).toBe('1');
    });

    it('emits every browser event, with the event itself', async () => {
        const { container, wrapper } = setup();

        await ready(container);
        await until(() => wrapper.emitted('roots-loaded'));

        const [[event]] = wrapper.emitted('mounted') as [[CustomEvent]];
        expect(event).toBeInstanceOf(CustomEvent);
        expect(event.detail.instanceId).toBe('comments-109-1');

        container.querySelector<HTMLButtonElement>('[data-comments-action="vote"]')!.click();
        await until(() => wrapper.emitted('vote-changed'));
        expect((wrapper.emitted('vote-changed')![0]![0] as CustomEvent).detail.vote).toBe('upvote');

        const destroyed = vi.fn();
        container.querySelector('section')!.addEventListener('comments:destroyed', destroyed);
        wrapper.unmount();
        mounted.splice(0);
        expect(destroyed).toHaveBeenCalledTimes(1);
    });

    it('starts a new thread when ownerUri changes, and stops the old one', async () => {
        const { container, wrapper, api } = setup({ ownerId: undefined, ownerUri: 'blog/first' });

        await ready(container);
        const first = container.querySelector('section[data-comments-root]');

        await wrapper.setProps({ ownerUri: 'blog/second' });
        await until(() => api.of('thread').length === 2);
        await ready(container);

        expect(container.querySelector('section[data-comments-root]')).not.toBe(first);
        expect(wrapper.emitted('destroyed')).toHaveLength(1);
        expect(api.of('thread').map((request) => request.url.searchParams.get('ownerUri'))).toEqual(['blog/first', 'blog/second']);
        expect(container.querySelectorAll('section[data-comments-root]')).toHaveLength(1);
        expect(comments(container)).toHaveLength(4);
    });

    it('switches the theme in place, and redraws with new controls and labels', async () => {
        const { container, wrapper, api } = setup({ theme: 'light' });

        await ready(container);
        const root = container.querySelector<HTMLElement>('section[data-comments-root]')!;

        await wrapper.setProps({ theme: 'dark', themeConfig: { quietButton: { attributes: { class: 'btn-ghost' } } }, labels: { reply: 'Answer' } });
        expect(root.dataset.commentsTheme).toBe('dark');
        await until(() => container.querySelector('[data-comments-action="reply"].btn-ghost'));
        expect(container.querySelector('[data-comments-action="reply"]')!.textContent).toContain('Answer');

        await wrapper.setProps({ themeConfig: undefined, labels: undefined });
        await until(() => !container.querySelector('.btn-ghost'));
        expect(api.of('thread')).toHaveLength(1);
        expect(container.querySelector('section[data-comments-root]')).toBe(root);
    });

    it('merges the plugin’s themeConfig per control under the thread’s own', async () => {
        const { container } = setup({ themeConfig: { quietButton: { attributes: { title: 'Thread' } } } }, fakeApi(), { themeConfig: { quietButton: { attributes: { class: 'from-plugin', title: 'Plugin' } } } });

        await ready(container);
        const reply = container.querySelector('[data-comments-action="reply"]')!;

        expect(reply.classList.contains('from-plugin')).toBe(true);
        expect(reply.getAttribute('title')).toBe('Thread');
    });

    it('re-sorts a loaded thread in place for a new sort, keeping its mount point and controller', async () => {
        const { wrapper, container, api } = setup({ sort: 'newest' });

        await ready(container);
        const root = container.querySelector<HTMLElement>('section[data-comments-root]')!;

        await wrapper.setProps({ sort: 'oldest' });
        await until(() => api.of('thread').length === 2);
        await ready(container);

        expect(api.of('thread').map((request) => request.url.searchParams.get('sort'))).toEqual(['newest', 'oldest']);
        expect(container.querySelector('section[data-comments-root]')).toBe(root);
        expect(wrapper.emitted('destroyed')).toBeUndefined();
    });

    it('starts a loaded thread again, with the default sort, when its sort is taken away', async () => {
        const { wrapper, container, api } = setup({ sort: 'oldest' });

        await ready(container);
        await wrapper.setProps({ sort: undefined });
        await until(() => api.of('thread').length === 2);
        await ready(container);

        expect(wrapper.emitted('destroyed')).toHaveLength(1);
        expect(api.of('thread').map((request) => request.url.searchParams.get('sort'))).toEqual(['oldest', 'newest']);
    });

    it('starts a waiting thread again, sorted, for a new sort', async () => {
        const { wrapper, container, api } = setup({ loadMode: 'button', sort: 'newest' });

        await until(() => container.querySelector('[data-comments-action="show-comments"]'));
        await wrapper.setProps({ sort: 'oldest' });
        await until(() => wrapper.emitted('destroyed')?.length === 1);
        const button = await until(() => container.querySelector<HTMLButtonElement>('[data-comments-action="show-comments"]'));

        expect(api.requests).toHaveLength(0);
        button.click();
        await ready(container);
        expect(api.of('thread').map((request) => request.url.searchParams.get('sort'))).toEqual(['oldest']);
    });

    it('starts a new thread for a new limit, and reads loadMode only when the thread starts', async () => {
        const { wrapper, container, api } = setup({ limit: 10 });

        await ready(container);
        const root = container.querySelector<HTMLElement>('section[data-comments-root]')!;

        await wrapper.setProps({ loadMode: 'button' });
        await new Promise((resolve) => setTimeout(resolve, 50));
        expect(container.querySelector('section[data-comments-root]')).toBe(root);
        expect(root.dataset.commentsLoadMode).toBe('eager');
        expect(wrapper.emitted('destroyed')).toBeUndefined();

        await wrapper.setProps({ limit: 20, loadMode: 'eager' });
        await until(() => api.of('thread').length === 2);
        await ready(container);
        expect(wrapper.emitted('destroyed')).toHaveLength(1);
        expect(api.of('thread').map((request) => request.url.searchParams.get('limit'))).toEqual(['10', '20']);
    });

    it('waits for Show comments with loadMode="button"', async () => {
        const { container, api } = setup({ loadMode: 'button', count: 3 });

        const button = await until(() => container.querySelector<HTMLButtonElement>('[data-comments-action="show-comments"]'));
        expect(api.requests).toHaveLength(0);

        button.click();
        await ready(container);
        expect(api.of('thread')).toHaveLength(1);
    });

    it('starts once when the parent renders again, and stops on unmount', async () => {
        const api = fakeApi();
        const Parent = defineComponent({ props: { tick: Number }, setup: (props) => () => h('div', [h('span', String(props.tick)), h(CommentsThread, { ownerId: 109, siteId: 1, loadMode: 'eager' })]) });
        const wrapper = mount(Parent, {
            props: { tick: 0 },
            attachTo: document.body,
            global: { plugins: [[CommentsPlugin, { endpoint: ENDPOINT, fetch: api.fetch }]] },
        });

        await ready(wrapper.element as HTMLElement);
        await wrapper.setProps({ tick: 1 });
        await nextTick();
        await flushPromises();

        expect(api.of('thread')).toHaveLength(1);
        const root = (wrapper.element as HTMLElement).querySelector('section')!;
        wrapper.unmount();
        expect(root.dataset.commentsJs).toBeUndefined();
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
