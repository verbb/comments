import { mount } from '@vue/test-utils';
import { cookieSession, guestSession, memberToken, type SignInProvider } from '@verbb/comments-core';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { computed, defineAsyncComponent, defineComponent, h, nextTick, reactive, ref, type Component, type PropType, type VNode } from 'vue';
import { CommentsPlugin, CommentsThread, provideCommentsSettings, type CommentsController, useCommentForm, useCommentsThread, type CommentsProviderSettings } from '../src/index';
import { ENDPOINT, fakeApi, until, validationFailed } from './support/api';

const mounted: Array<{ unmount(): void }> = [];

afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.replaceChildren();
    document.documentElement.className = '';
    vi.restoreAllMocks();
});

const ready = () => until(() => document.querySelector('[data-comments-root][data-comments-phase="ready"]'));
const token = () => ({ token: 'cmt_x', expiresAt: Math.floor(Date.now() / 1000) + 900 });

function setup(component: Component, settings: CommentsProviderSettings | (() => CommentsProviderSettings), options: Record<string, unknown> = {}) {
    const wrapper = mount(component, { attachTo: document.body, global: { plugins: [[CommentsPlugin, settings]] }, ...options });
    mounted.push(wrapper);
    return wrapper;
}

describe('component props', () => {
    it('come from mixins, extends and async components, and nothing else lands as attributes', async () => {
        const api = fakeApi();
        const shared = { props: { comment: Object } };
        const WithMixin = defineComponent({ mixins: [shared], props: { labels: Object }, render() { return h('b', { class: 'mixin-author' }, (this as unknown as { comment: { uid: string } }).comment.uid); } });
        const Base = defineComponent({ props: { comment: Object } });
        const WithExtends = defineComponent({ extends: Base, render() { return h('i', { class: 'extends-meta' }, (this as unknown as { comment: { uid: string } }).comment.uid); } });
        const Async = defineAsyncComponent(async () => defineComponent({ props: { comment: Object }, render() { return h('u', { class: 'async-content' }, (this as unknown as { comment: { uid: string } }).comment.uid); } }));

        setup(CommentsThread, { endpoint: ENDPOINT, fetch: api.fetch }, { props: { ownerId: 109, siteId: 1, loadMode: 'eager', parts: { authorName: WithMixin, meta: WithExtends, content: Async } } });
        await ready();
        await until(() => document.querySelectorAll('.async-content').length === 4);

        for (const selector of ['.mixin-author', '.extends-meta', '.async-content']) {
            const element = document.querySelector(selector)!;
            expect(element.textContent).toMatch(/^c\d/);
            expect(element.getAttributeNames()).toEqual(['class']);
        }
    });

    it('come from the app’s global mixins', async () => {
        const api = fakeApi();
        const Author = defineComponent({ render() { return h('b', { class: 'global-author' }, (this as unknown as { comment?: { uid: string } }).comment?.uid ?? 'none'); } });

        setup(CommentsThread, { endpoint: ENDPOINT, fetch: api.fetch }, { props: { ownerId: 109, siteId: 1, loadMode: 'eager', parts: { authorName: Author } }, global: { plugins: [[CommentsPlugin, { endpoint: ENDPOINT, fetch: api.fetch }]], mixins: [{ props: { comment: Object } }] } });
        await ready();
        await until(() => document.querySelectorAll('.global-author').length === 4);

        expect(document.querySelector('.global-author')!.textContent).toBe('c1');
    });
});

describe('an override that throws', () => {
    it('stays in its own part, with Comments’ markup in its place', async () => {
        const api = fakeApi();
        const errors: unknown[] = [];
        const Bad = defineComponent({ props: { comment: { type: Object as PropType<{ uid: string }>, required: true } }, setup(props) { return () => { if (props.comment.uid === 'c2') throw new Error('bad'); return h('b', { class: 'ok-author' }, 'ok'); }; } });
        const App = defineComponent({ render: () => h('div', [h('nav', { id: 'nav' }, 'Nav'), h(CommentsThread, { ownerId: 109, siteId: 1, loadMode: 'eager', parts: { authorName: Bad } })]) });

        vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        setup(App, { endpoint: ENDPOINT, fetch: api.fetch }, { global: { plugins: [[CommentsPlugin, { endpoint: ENDPOINT, fetch: api.fetch }]], config: { errorHandler: (error: unknown) => { errors.push(error); } } } });
        await ready();
        await until(() => document.querySelectorAll('.ok-author').length === 3);
        await until(() => document.querySelector('[data-comments-host="comment:c2:authorName"]')?.textContent?.includes('Author c2'));

        expect(document.querySelector('#nav')).not.toBeNull();
        expect(document.querySelectorAll('article[id^="comment-"]')).toHaveLength(4);
        expect(errors).toHaveLength(1);
    });
});

describe('settings that change', () => {
    it('start the thread again when someone signs in, through provideCommentsSettings()', async () => {
        const api = fakeApi();
        const signedIn = ref(false);
        const Inner = defineComponent({
            setup() {
                provideCommentsSettings(() => ({ auth: signedIn.value ? memberToken({ getToken: async () => token() }) : undefined }));
                return () => h(CommentsThread, { ownerId: 109, siteId: 1, loadMode: 'eager' });
            },
        });

        setup(Inner, { endpoint: ENDPOINT, fetch: api.fetch });
        await ready();
        signedIn.value = true;
        await until(() => api.of('viewer').length === 2);

        expect(api.of('viewer').at(-1)!.headers.get('authorization')).toBe('Bearer cmt_x');
    });

    it('take a new provider of the same kind over without starting again', async () => {
        const api = fakeApi();
        const settings = reactive<CommentsProviderSettings>({ endpoint: ENDPOINT, fetch: api.fetch, auth: guestSession() });
        const destroyed = vi.fn();

        setup(defineComponent({ render: () => h(CommentsThread, { ownerId: 109, siteId: 1, loadMode: 'eager', onDestroyed: destroyed }) }), settings);
        await ready();
        settings.auth = guestSession();
        settings.nonce = 'n-2';
        await nextTick();
        await new Promise((resolve) => setTimeout(resolve, 50));

        expect(destroyed).not.toHaveBeenCalled();
        expect(api.of('thread')).toHaveLength(1);
    });

    it('don’t start useCommentsThread() again for a provider a getter makes afresh', async () => {
        const api = fakeApi();
        const tick = ref(0);
        let tokens = 0;
        let status = '';
        const Headless = defineComponent({
            setup() {
                const thread = useCommentsThread(() => ({ ownerId: 109, endpoint: ENDPOINT, fetch: api.fetch, auth: memberToken({ getToken: async () => { tokens++; void tick.value; return token(); } }) }));
                return () => {
                    status = thread.state.value.status;
                    return h('p', `${tick.value}`);
                };
            },
        });

        setup(Headless, {});
        await until(() => status === 'ready');
        tick.value++;
        await nextTick();
        await new Promise((resolve) => setTimeout(resolve, 50));

        expect(api.of('thread')).toHaveLength(1);
        expect(tokens).toBeLessThanOrEqual(2);
    });
});

describe('props that change after the thread starts', () => {
    it('falls back to light when theme goes, and follows darkSelector', async () => {
        const api = fakeApi();
        const wrapper = setup(CommentsThread, { endpoint: ENDPOINT, fetch: api.fetch }, { props: { ownerId: 109, siteId: 1, loadMode: 'eager', theme: 'dark' } });

        await ready();
        const root = document.querySelector<HTMLElement>('section')!;
        expect(root.dataset.commentsTheme).toBe('dark');

        await wrapper.setProps({ theme: undefined });
        expect(root.dataset.commentsTheme).toBe('light');

        await wrapper.setProps({ darkSelector: 'html.night' });
        document.documentElement.classList.add('night');
        await until(() => root.dataset.commentsTheme === 'dark');
        document.documentElement.classList.remove('night');
        await until(() => root.dataset.commentsTheme === 'light');
    });
});

describe('useCommentForm()', () => {
    it('tries the same comment again with the same operation ID, and keeps a guest’s name', async () => {
        let refusals = 1;
        const api = fakeApi({ override: { create: () => (refusals-- > 0 ? validationFailed({ content: ['Try again.'] }) : undefined) } });
        let posted = '';
        const Form = defineComponent({
            setup() {
                const thread = useCommentsThread({ ownerId: 109 });
                const form = useCommentForm({ thread });
                return () => h('form', { ref: form.ref, 'data-ready': thread.state.value.status, onSubmit: (event: Event) => form.handleSubmit(event).then((response) => { posted = response ? 'yes' : 'no'; }) }, [
                    h('textarea', { name: 'content', onVnodeMounted: (node: VNode) => { (node.el as HTMLTextAreaElement).value = 'Hello'; } }),
                    h('input', { name: 'guest[name]', onVnodeMounted: (node: VNode) => { (node.el as HTMLInputElement).value = 'Aroha'; } }),
                    h('button', { type: 'submit' }, 'Post'),
                ]);
            },
        });

        setup(Form, { endpoint: ENDPOINT, fetch: api.fetch });
        await until(() => document.querySelector('form[data-ready="ready"]'));
        document.querySelector('button')!.click();
        await until(() => posted === 'no');
        document.querySelector('button')!.click();
        await until(() => posted === 'yes');

        const [first, second] = api.of('create');
        expect(second!.body.operationId).toBe(first!.body.operationId);
        expect(document.querySelector('textarea')!.value).toBe('');
        expect(document.querySelector<HTMLInputElement>('[name="guest[name]"]')!.value).toBe('Aroha');
    });
});

describe('signing out through the latest provider', () => {
    it('reaches the composable and the thread when a getter makes the provider afresh', async () => {
        const api = fakeApi();
        const tick = ref(0);
        let status = '';
        // A new provider whenever the app's own state changes, shared by the thread and the composable.
        const provider = computed<SignInProvider>(() => {
            void tick.value;
            return cookieSession();
        });
        const make = () => provider.value;
        const Page = defineComponent({
            setup() {
                provideCommentsSettings(() => ({ endpoint: ENDPOINT, fetch: api.fetch, auth: make() }));
                const thread = useCommentsThread(() => ({ ownerId: 109, endpoint: ENDPOINT, fetch: api.fetch, auth: make() }));
                return () => {
                    status = thread.state.value.status;
                    return h(CommentsThread, { ownerId: 109, siteId: 1, loadMode: 'eager' });
                };
            },
        });

        setup(Page, { endpoint: ENDPOINT, fetch: api.fetch });
        await until(() => status === 'ready');
        await ready();
        const before = api.of('viewer').length;

        tick.value++;
        await nextTick();
        provider.value.reset!();

        await until(() => api.of('viewer').length >= before + 2);
        expect(api.of('thread').length).toBe(2);
    });
});

describe('an async override that fails to load', () => {
    it('draws Comments’ own markup, and tells the app', async () => {
        const api = fakeApi();
        const errors: unknown[] = [];
        const Broken = defineAsyncComponent(() => Promise.reject(new Error('chunk failed')));

        vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        setup(CommentsThread, { endpoint: ENDPOINT, fetch: api.fetch }, {
            props: { ownerId: 109, siteId: 1, loadMode: 'eager', parts: { authorName: Broken } },
            global: { plugins: [[CommentsPlugin, { endpoint: ENDPOINT, fetch: api.fetch }]], config: { errorHandler: (error: unknown) => { errors.push(error); } } },
        });
        await ready();

        await until(() => document.querySelector('[data-comments-host="comment:c1:authorName"]')?.textContent?.includes('Author c1'));
        expect(document.querySelectorAll('article[id^="comment-"]')).toHaveLength(4);
        expect(errors.length).toBeGreaterThan(0);
        expect(String(errors[0])).toContain('chunk failed');
    });
});

describe('a failed override whose comment goes', () => {
    it('has its host dropped with the comment', async () => {
        const api = fakeApi();
        let controller: CommentsController | undefined;
        const Bad = defineComponent({ props: { comment: { type: Object as PropType<{ uid: string }>, required: true } }, setup(props) { return () => { if (props.comment.uid === 'c3') throw new Error('bad'); return h('b', { class: 'ok-author' }, 'ok'); }; } });

        vi.spyOn(console, 'warn').mockImplementation(() => undefined);
        setup(CommentsThread, { endpoint: ENDPOINT, fetch: api.fetch }, {
            props: { ownerId: 109, siteId: 1, loadMode: 'eager', parts: { authorName: Bad }, onMounted: (event: CustomEvent) => { controller = event.detail.controller; } },
            global: { plugins: [[CommentsPlugin, { endpoint: ENDPOINT, fetch: api.fetch }]], config: { errorHandler: () => undefined } },
        });

        await until(() => document.querySelector('[data-comments-host="comment:c3:authorName"]')?.textContent?.includes('Author c3'));
        await controller!.store.delete('c3');

        await until(() => !controller!.getHosts().some((host) => host.key.startsWith('comment:c3:')));
        expect(document.querySelector('[data-comments-host="comment:c3:authorName"]')).toBeNull();
        expect(document.querySelectorAll('.ok-author')).toHaveLength(3);
    });
});
