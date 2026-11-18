import { mount } from '@vue/test-utils';
import type { CommentsController } from '@verbb/comments-browser';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, inject, onMounted, onUnmounted, onUpdated, ref, type PropType } from 'vue';
import { CommentsPlugin, CommentsThread, templatePart, type CommentsFieldOverrides, type CommentsPartOverrides } from '../src/index';
import { ENDPOINT, fakeApi, until, validationFailed } from './support/api';

const mounted: Array<{ unmount(): void }> = [];

afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.replaceChildren();
    vi.restoreAllMocks();
});

const lifecycle = { mounted: [] as string[], unmounted: [] as string[] };

type CommentShape = { uid: string; author: { name: string }; interaction: { upvotes: number } };

const Author = defineComponent({
    props: {
        comment: { type: Object as PropType<CommentShape>, required: true },
        actions: { type: Object as PropType<{ vote(value: string): Promise<void> }>, required: true },
    },
    setup(props) {
        const tone = inject('tone', 'plain');
        const uid = props.comment.uid;

        onMounted(() => lifecycle.mounted.push(uid));
        onUnmounted(() => lifecycle.unmounted.push(uid));

        return () => h('b', { class: 'custom-author', 'data-uid': props.comment.uid, 'data-tone': tone }, [
            `${props.comment.author.name} (${props.comment.interaction.upvotes})`,
            h('button', { type: 'button', class: 'custom-like', onClick: () => void props.actions.vote('upvote') }, 'Like'),
        ]);
    },
});

const Meta = defineComponent({
    props: { renderDefaultInto: { type: Function as PropType<(into: Element) => void>, required: true } },
    setup(props) {
        const element = ref<HTMLElement | null>(null);
        const draw = () => props.renderDefaultInto(element.value!);

        onMounted(draw);
        onUpdated(draw);

        return () => h('span', { class: 'custom-meta', ref: element });
    },
});

async function setup(props: { parts?: CommentsPartOverrides; fields?: CommentsFieldOverrides } = {}, api = fakeApi()) {
    let controller: CommentsController | undefined;
    const Page = defineComponent({
        props: { parts: Object as PropType<CommentsPartOverrides>, fields: Object as PropType<CommentsFieldOverrides> },
        setup(pageProps) {
            return () => h(CommentsThread, { ownerId: 109, siteId: 1, loadMode: 'eager', parts: pageProps.parts, fields: pageProps.fields, onMounted: (event: CustomEvent) => { controller = event.detail.controller; } });
        },
    });
    const wrapper = mount(Page, {
        props,
        attachTo: document.body,
        global: { plugins: [[CommentsPlugin, { endpoint: ENDPOINT, fetch: api.fetch }]], provide: { tone: 'warm' } },
    });
    mounted.push(wrapper);
    const container = wrapper.element.parentElement as HTMLElement;

    await until(() => container.querySelector('[data-comments-phase="ready"]'));
    return { wrapper, container, api, controller: () => controller! };
}

describe('component part overrides', () => {
    it('render each part through a teleport, inside the app’s own tree', async () => {
        lifecycle.mounted = [];
        const { container } = await setup({ parts: { authorName: Author } });

        await until(() => container.querySelectorAll('.custom-author').length === 4);
        const author = container.querySelector<HTMLElement>('.custom-author[data-uid="c1"]')!;

        expect(author.dataset.tone).toBe('warm');
        expect(author.parentElement!.localName).toBe('verbb-comments-host');
        expect(author.textContent).toContain('Author c1 (2)');
        // Only the declared props, so the rest of the context doesn't land on the element as attributes.
        expect(author.getAttribute('labels')).toBeNull();
        expect(author.getAttributeNames().sort()).toEqual(['class', 'data-tone', 'data-uid']);
        expect(lifecycle.mounted.sort()).toEqual(['c1', 'c2', 'c2-1', 'c3']);
    });

    it('keep the teleport when the renderer redraws the comment, with new props', async () => {
        lifecycle.mounted = [];
        lifecycle.unmounted = [];
        const { container, api } = await setup({ parts: { authorName: Author } });

        await until(() => container.querySelectorAll('.custom-author').length === 4);
        const before = container.querySelector('.custom-author[data-uid="c1"]')!;
        const host = before.parentElement;

        container.querySelector<HTMLButtonElement>('.custom-author[data-uid="c1"] .custom-like')!.click();
        await until(() => container.querySelector('.custom-author[data-uid="c1"]')!.textContent!.includes('(3)'));

        expect(api.of('vote')[0]!.body).toMatchObject({ uid: 'c1', vote: 'upvote' });
        expect(container.querySelector('.custom-author[data-uid="c1"]')).toBe(before);
        expect(before.parentElement).toBe(host);
        expect(lifecycle.mounted.filter((uid) => uid === 'c1')).toHaveLength(1);
        expect(lifecycle.unmounted).toEqual([]);
    });

    it('drop the teleport when the renderer removes the comment', async () => {
        lifecycle.unmounted = [];
        const { container, controller } = await setup({ parts: { authorName: Author } });

        await until(() => container.querySelectorAll('.custom-author').length === 4);
        await controller().store.delete('c3');

        await until(() => !container.querySelector('.custom-author[data-uid="c3"]'));
        expect(lifecycle.unmounted).toEqual(['c3']);
        expect(container.querySelectorAll('.custom-author')).toHaveLength(3);
    });

    it('unmount every teleport with the thread', async () => {
        lifecycle.unmounted = [];
        const { container, wrapper } = await setup({ parts: { authorName: Author } });

        await until(() => container.querySelectorAll('.custom-author').length === 4);
        wrapper.unmount();
        mounted.splice(0);

        expect(lifecycle.unmounted.sort()).toEqual(['c1', 'c2', 'c2-1', 'c3']);
    });

    it('can draw Comments’ own markup inside the component with renderDefaultInto()', async () => {
        const { container } = await setup({ parts: { meta: Meta } });

        await until(() => container.querySelectorAll('.custom-meta').length === 4);
        await until(() => container.querySelector('.custom-meta')!.textContent!.includes('2 hours ago'));
    });

    it('take a templatePart() function or a custom element’s name as the renderer would', async () => {
        const { container } = await setup({
            parts: {
                authorName: templatePart(({ comment, html }) => html`<i class="cheap-author">${(comment as { uid: string }).uid}</i>`),
                avatar: 'acme-avatar',
            },
        });

        await until(() => container.querySelectorAll('.cheap-author').length === 4);
        expect(container.querySelectorAll('acme-avatar')).toHaveLength(4);
        expect(container.querySelector('verbb-comments-host')).toBeNull();
    });

    it('swap in place when the parts change, without starting the thread again', async () => {
        const { container, wrapper, api } = await setup({ parts: { authorName: Author } });

        await until(() => container.querySelectorAll('.custom-author').length === 4);
        await wrapper.setProps({ parts: { authorName: templatePart(({ html }) => html`<i class="cheap-author">x</i>`) } });

        await until(() => container.querySelectorAll('.cheap-author').length === 4);
        expect(container.querySelectorAll('.custom-author')).toHaveLength(0);
        expect(api.of('thread')).toHaveLength(1);
    });
});

const Stars = defineComponent({
    props: {
        value: { type: null as unknown as PropType<unknown>, default: null },
        setValue: { type: Function as PropType<(value: unknown) => void>, required: true },
        error: { type: String as PropType<string | null>, default: null },
        id: { type: String, required: true },
        labelId: { type: String, required: true },
    },
    setup(props) {
        return () => h('div', { id: props.id, role: 'radiogroup', 'aria-labelledby': props.labelId, 'aria-invalid': props.error ? 'true' : undefined, class: 'stars' },
            [1, 2, 3, 4, 5].map((n) => h('button', { type: 'button', 'data-star': n, 'aria-pressed': String(Number(props.value) >= n), onClick: () => props.setValue(n) }, '★')));
    },
});

const RATING = { handle: 'rating', uid: 'f-rating', label: 'Rating', instructions: null, required: false, input: 'number', multiple: false, options: [], attributes: {}, show: true, display: 'inline' };

async function post(container: HTMLElement, star: number, text: string) {
    const button = await until(() => container.querySelector<HTMLButtonElement>(`form .stars [data-star="${star}"]`));
    button.click();
    await until(() => container.querySelector(`form .stars [data-star="${star}"][aria-pressed="true"]`));

    const form = button.closest('form')!;
    const textarea = form.querySelector<HTMLTextAreaElement>('textarea[name="content"]')!;
    textarea.value = text;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
    form.requestSubmit();

    return form;
}

describe('a field input component', () => {
    it('posts the value it sets', async () => {
        const api = fakeApi({ fields: { comment: [RATING] } });
        const { container } = await setup({ fields: { rating: { input: Stars } } }, api);

        await post(container, 4, 'Four stars');
        await until(() => api.of('create').length === 1);

        expect(api.of('create')[0]!.body).toMatchObject({ content: { format: 'plain', text: 'Four stars' }, fields: { rating: 4 } });
    });

    it('gets the server’s error for its field', async () => {
        const api = fakeApi({ fields: { comment: [RATING] }, override: { create: () => validationFailed({ 'fields.rating': ['Pick at least two stars.'] }) } });
        const { container } = await setup({ fields: { rating: { input: Stars } } }, api);

        const form = await post(container, 1, 'One star');

        await until(() => form.querySelector('.stars[aria-invalid="true"]'));
        expect(form.textContent).toContain('Pick at least two stars.');
    });
});
