import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref, type VNode } from 'vue';
import { CommentsPlugin, CommentsThread, useCommentForm, useCommentsEvent, useCommentsThread, type UseCommentsThreadResult } from '../src/index';
import { ENDPOINT, fakeApi, until, validationFailed } from './support/api';

const mounted: Array<{ unmount(): void }> = [];

afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.replaceChildren();
    vi.restoreAllMocks();
});

function setup(component: ReturnType<typeof defineComponent>, api = fakeApi(), props: Record<string, unknown> = {}) {
    const wrapper = mount(component, { props, attachTo: document.body, global: { plugins: [[CommentsPlugin, { endpoint: ENDPOINT, fetch: api.fetch }]] } });
    mounted.push(wrapper);
    return { wrapper, api, container: wrapper.element as HTMLElement };
}

let thread: UseCommentsThreadResult;

const List = defineComponent({
    props: { ownerUri: { type: String, default: 'blog/hello' } },
    setup(props) {
        thread = useCommentsThread(() => ({ ownerUri: props.ownerUri }));

        return () => h('ul', { 'data-status': thread.state.value.status }, thread.roots.value.map((comment) => h('li', { key: comment.uid, 'data-uid': comment.uid }, [
            `${comment.content?.text} (${comment.interaction?.upvotes})`,
            ...thread.replies(comment.uid).map((reply) => h('span', { class: 'reply' }, reply.uid)),
        ])));
    },
});

describe('useCommentsThread()', () => {
    it('loads the thread on mount and gives its state and roots as refs', async () => {
        const { container, api } = setup(List);

        await until(() => container.dataset.status === 'ready');

        expect(Array.from(container.querySelectorAll('li'), (item) => item.dataset.uid)).toEqual(['c1', 'c2', 'c3']);
        expect(container.querySelector('.reply')!.textContent).toBe('c2-1');
        expect(api.of('thread')[0]!.url.searchParams.get('ownerUri')).toBe('blog/hello');
        expect(api.of('viewer')).toHaveLength(1);
    });

    it('runs actions through the store, and the refs follow', async () => {
        const { container, api } = setup(List);

        await until(() => container.dataset.status === 'ready');
        await thread.vote('c1', 'upvote');
        await until(() => container.querySelector('[data-uid="c1"]')!.textContent!.includes('(3)'));

        expect(api.of('vote')[0]!.body).toMatchObject({ uid: 'c1', vote: 'upvote' });
        expect(thread.state.value.permissions.c1!.vote).toBe('upvote');
    });

    it('makes a new store for a new owner, and destroys it with the component', async () => {
        const { container, wrapper, api } = setup(List);

        await until(() => container.dataset.status === 'ready');
        const first = thread.store.value!;
        const destroy = vi.spyOn(first, 'destroy');

        await wrapper.setProps({ ownerUri: 'blog/other' });
        await until(() => thread.store.value !== first && container.dataset.status === 'ready');

        expect(destroy).toHaveBeenCalled();
        expect(api.of('thread').at(-1)!.url.searchParams.get('ownerUri')).toBe('blog/other');

        const second = thread.store.value!;
        const destroySecond = vi.spyOn(second, 'destroy');
        wrapper.unmount();
        mounted.splice(0);
        expect(destroySecond).toHaveBeenCalled();
    });
});

const Form = defineComponent({
    props: { mode: { type: String as () => 'create' | 'reply', default: 'create' }, parentUid: { type: String, default: undefined } },
    setup(props) {
        const local = useCommentsThread({ ownerId: 109 });
        const form = useCommentForm({ thread: local, mode: () => props.mode, parentUid: () => props.parentUid });
        const result = ref('');

        return () => h('form', {
            ref: form.ref,
            'data-ready': local.state.value.status,
            onSubmit: (event: Event) => form.handleSubmit(event).then((response) => { result.value = response ? response.comment.uid : 'refused'; }),
        }, [
            // Uncontrolled, as a form whose fields the browser keeps.
            h('textarea', { name: 'content', onVnodeMounted: (node: VNode) => { (node.el as HTMLTextAreaElement).value = 'Hello there'; } }),
            h('input', { name: 'fields[rating]', onVnodeMounted: (node: VNode) => { (node.el as HTMLInputElement).value = '4'; } }),
            form.fieldErrors.value.content ? h('p', { class: 'error' }, form.fieldErrors.value.content[0]) : null,
            form.error.value ? h('p', { class: 'message' }, form.error.value) : null,
            h('button', { type: 'submit', disabled: form.posting.value }, 'Post'),
            h('output', result.value),
        ]);
    },
});

describe('useCommentForm()', () => {
    it('posts what the form holds, with Protect’s evidence, and empties the form', async () => {
        const { container, api } = setup(Form, fakeApi({ protect: true }));

        await until(() => container.dataset.ready === 'ready');
        const textarea = container.querySelector('textarea')!;

        textarea.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
        await until(() => api.of('protection').length >= 1 && container.querySelector('[name="a1b2c3"], .protect-honeypot'));

        container.querySelector('button')!.click();
        await until(() => container.querySelector('output')!.textContent);

        const [create] = api.of('create');
        expect(create!.body).toMatchObject({ ownerId: 109, parentUid: null, content: { format: 'plain', text: 'Hello there' }, fields: { rating: '4' } });
        expect(create!.body.protection).toBeTruthy();
        expect(container.querySelector('output')!.textContent).toMatch(/^new-/);
        expect(textarea.value).toBe('');
    });

    it('gives the server’s field errors and message, and says when it’s posting', async () => {
        const { container } = setup(Form, fakeApi({ override: { create: () => validationFailed({ content: ['Say a little more.'] }) } }));

        await until(() => container.dataset.ready === 'ready');
        container.querySelector('button')!.click();

        await until(() => container.querySelector('.error'));
        expect(container.querySelector('.error')!.textContent).toBe('Say a little more.');
        expect(container.querySelector('.message')!.textContent).toBeTruthy();
        await until(() => container.querySelector('output')!.textContent === 'refused');
        expect(container.querySelector('button')!.disabled).toBe(false);
    });

    it('posts a reply under its parent', async () => {
        const { container, api } = setup(Form, fakeApi(), { mode: 'reply', parentUid: 'c2' });

        await until(() => container.dataset.ready === 'ready');
        container.querySelector('button')!.click();
        await until(() => api.of('create').length === 1);

        expect(api.of('create')[0]!.body.parentUid).toBe('c2');
    });
});

describe('useCommentsEvent()', () => {
    it('hears the browser events of every thread on the page, or only one', async () => {
        const all = vi.fn();
        const one = vi.fn();
        const other = vi.fn();
        const Page = defineComponent({
            setup() {
                useCommentsEvent('comments:roots-loaded', all);
                useCommentsEvent('comments:roots-loaded', one, { instanceId: 'comments-109-1' });
                useCommentsEvent('comments:roots-loaded', other, { instanceId: 'comments-elsewhere' });
                return () => h(CommentsThread, { ownerId: 109, siteId: 1, loadMode: 'eager' });
            },
        });

        setup(Page);

        await until(() => all.mock.calls.length === 1);
        expect(all.mock.calls[0]![0]).toBeInstanceOf(CustomEvent);
        expect(one).toHaveBeenCalledTimes(1);
        expect(other).not.toHaveBeenCalled();
    });

    it('hears a headless thread’s store', async () => {
        const created = vi.fn();
        const Headless = defineComponent({
            setup() {
                thread = useCommentsThread({ ownerId: 109 });
                useCommentsEvent('comments:comment-created', created, { thread });
                return () => h('div');
            },
        });

        setup(Headless);
        await until(() => thread.state.value.status === 'ready');
        await thread.create({ content: { format: 'plain', text: 'Hi' } });

        expect(created).toHaveBeenCalledTimes(1);
        expect(created.mock.calls[0]![0]).toMatchObject({ apiVersion: 1, parentUid: null });
    });
});
